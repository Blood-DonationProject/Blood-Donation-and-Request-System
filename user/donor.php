<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

// Check if logged-in user already has a donor record
$isAlreadyDonor = false;
$donorId = 0;
$donorIds = [];
if ($isLoggedIn) {
  $userId = $_SESSION['user_id'] ?? 0;
  if ($userId > 0) {
    $stmt = $conn->prepare("SELECT id FROM donor WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
      $isAlreadyDonor = true;
      while ($row = $result->fetch_assoc()) {
        $donorIds[] = (int)$row['id'];
      }
      $donorId = $donorIds[0] ?? 0;
    }
    $stmt->close();
  }
}

// Handle Accept/Decline Assignment
if (isset($_GET['action']) && isset($_GET['req_id']) && $isLoggedIn) {
  $r_id = (int)$_GET['req_id'];
  $userId = $_SESSION['user_id'] ?? 0;

  // Find the specific donor record for this user assigned to this request
  $d_id = 0;
  if ($userId > 0) {
    $findDonor = $conn->prepare("SELECT d.id FROM donor d WHERE d.user_id = ? AND (d.id = (SELECT assigned_donor_id FROM blood_request WHERE id = ?) OR d.id IN (SELECT donor_id FROM donor_assignments WHERE request_id = ?)) LIMIT 1");
    $findDonor->bind_param("iii", $userId, $r_id, $r_id);
    $findDonor->execute();
    $donorMatch = $findDonor->get_result()->fetch_assoc();
    if ($donorMatch) {
      $d_id = (int)$donorMatch['id'];
    } elseif (!empty($donorId)) {
      $d_id = $donorId;
    }
    $findDonor->close();
  }

  require_once __DIR__ . '/../includes/notification_helper.php';

  // Verify request is not expired before accepting/rejecting
  $chkReq = $conn->prepare("SELECT id, status, hospital, required_date FROM blood_request WHERE id = ?");
  $chkReq->bind_param("i", $r_id);
  $chkReq->execute();
  $reqData = $chkReq->get_result()->fetch_assoc();
  $chkReq->close();

  if ($reqData && ($reqData['status'] === 'Expired' || strtotime($reqData['required_date']) < strtotime('today'))) {
    // Request has expired - cannot perform action
  } else if ($_GET['action'] === 'accept' && $d_id > 0) {
    // Update blood_request
    $stmt_a = $conn->prepare("UPDATE blood_request SET status = 'Accepted' WHERE id = ? AND (assigned_donor_id = ? OR assigned_donor_id IS NULL) AND status NOT IN ('Expired', 'Completed', 'Rejected', 'Cancelled')");
    $stmt_a->bind_param("ii", $r_id, $d_id);
    $stmt_a->execute();
    $stmt_a->close();

    // Check if donor_assignments record exists, if so update, else insert
    $chk_assign = $conn->prepare("SELECT id FROM donor_assignments WHERE request_id = ? AND donor_id = ?");
    $chk_assign->bind_param("ii", $r_id, $d_id);
    $chk_assign->execute();
    $res_assign = $chk_assign->get_result();
    if ($res_assign && $res_assign->num_rows > 0) {
      $stmt_assign = $conn->prepare("UPDATE donor_assignments SET status = 'Accepted', responded_at = NOW() WHERE request_id = ? AND donor_id = ?");
      $stmt_assign->bind_param("ii", $r_id, $d_id);
      $stmt_assign->execute();
      $stmt_assign->close();
    } else {
      $stmt_assign = $conn->prepare("INSERT INTO donor_assignments (request_id, donor_id, assigned_by, status, responded_at) VALUES (?, ?, 1, 'Accepted', NOW())");
      $stmt_assign->bind_param("ii", $r_id, $d_id);
      $stmt_assign->execute();
      $stmt_assign->close();
    }
    $chk_assign->close();

    // Fetch assignment_id
    $assignment_id = null;
    $get_assign = $conn->prepare("SELECT id FROM donor_assignments WHERE request_id = ? AND donor_id = ?");
    $get_assign->bind_param("ii", $r_id, $d_id);
    $get_assign->execute();
    if ($row_assign = $get_assign->get_result()->fetch_assoc()) $assignment_id = (int)$row_assign['id'];
    $get_assign->close();

    // Notify Admins
    $hosp = $reqData['hospital'] ?? '';
    $msg = "Donor \"" . htmlspecialchars($username) . "\" has accepted the blood request #" . $r_id . ($hosp ? " (" . $hosp . ")" : "") . ".";
    notify_admins($conn, 'Assignment_Accepted', 'Donor accepted the blood request', $msg, $r_id, $assignment_id, $d_id);

    // Notify Donor
    create_notification($conn, $userId, 'Assignment_Accepted', 'Assignment Accepted', "You have accepted the blood request #{$r_id} ({$hosp}). Please proceed to the hospital.", $r_id, $assignment_id, $d_id, $userId);

    // Notify Requester
    $get_req = $conn->prepare("SELECT u.id as user_id, u.email, u.username FROM blood_request br JOIN users u ON br.users_id = u.id WHERE br.id = ?");
    $get_req->bind_param("i", $r_id);
    $get_req->execute();
    $req_user = $get_req->get_result()->fetch_assoc();
    $get_req->close();

    $emailRes = null;
    if ($req_user) {
      $reqMsg = "The assigned donor " . htmlspecialchars($username) . " has accepted your blood request #" . $r_id . ".";
      $reqNotifId = create_notification($conn, $req_user['user_id'], 'Assignment_Accepted', 'Donor Accepted', $reqMsg, $r_id, $assignment_id, $d_id, $userId);

      // Fail-safe decoupled email to requester
      require_once __DIR__ . '/../includes/mailer.php';
      $emailRes = send_donor_accepted_email($req_user['user_id'], [
        'id'          => $r_id,
        'hospital'    => $hosp,
        'blood_group' => $reqData['blood_group'] ?? ''
      ], ['username' => $username], $reqNotifId);
    }

    $_SESSION['flash_success'] = format_action_feedback('Assignment accepted', $emailRes);
  } elseif ($_GET['action'] === 'reject' && $d_id > 0) {
    // Update blood_request: unassign donor and set to Pending
    $stmt_a = $conn->prepare("UPDATE blood_request SET status = 'Pending', assigned_donor_id = NULL WHERE id = ? AND (assigned_donor_id = ? OR assigned_donor_id IS NULL)");
    $stmt_a->bind_param("ii", $r_id, $d_id);
    $stmt_a->execute();
    $stmt_a->close();

    // Update donor_assignments
    $stmt_assign = $conn->prepare("UPDATE donor_assignments SET status = 'Rejected', responded_at = NOW() WHERE request_id = ? AND donor_id = ?");
    $stmt_assign->bind_param("ii", $r_id, $d_id);
    $stmt_assign->execute();
    $stmt_assign->close();

    // Make donor available again
    $stmt_donor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
    $stmt_donor->bind_param("i", $d_id);
    $stmt_donor->execute();
    $stmt_donor->close();

    // Fetch assignment_id
    $assignment_id = null;
    $get_assign = $conn->prepare("SELECT id FROM donor_assignments WHERE request_id = ? AND donor_id = ?");
    $get_assign->bind_param("ii", $r_id, $d_id);
    $get_assign->execute();
    if ($row_assign = $get_assign->get_result()->fetch_assoc()) $assignment_id = (int)$row_assign['id'];
    $get_assign->close();

    // Notify Admins
    $hosp = $reqData['hospital'] ?? '';
    $msg = "Donor \"" . htmlspecialchars($username) . "\" has rejected the blood request #" . $r_id . ($hosp ? " (" . $hosp . ")" : "") . ". Please assign another donor.";
    notify_admins($conn, 'Assignment_Rejected', 'Donor rejected the blood request', $msg, $r_id, $assignment_id, $d_id);

    // Notify Donor
    create_notification($conn, $userId, 'Assignment_Rejected', 'Assignment Declined', "You declined blood request #{$r_id}. Your profile has been set back to Available.", $r_id, $assignment_id, $d_id, $userId);

    // Fail-safe decoupled email alert to Admin
    require_once __DIR__ . '/../includes/mailer.php';
    $adminEmailRes = send_admin_donor_rejected_email([
      'id'          => $r_id,
      'hospital'    => $hosp,
      'blood_group' => $reqData['blood_group'] ?? ''
    ], ['username' => $username]);

    // Notify Requester
    $get_req = $conn->prepare("SELECT u.id as user_id, u.email, u.username FROM blood_request br JOIN users u ON br.users_id = u.id WHERE br.id = ?");
    $get_req->bind_param("i", $r_id);
    $get_req->execute();
    $req_user = $get_req->get_result()->fetch_assoc();
    $get_req->close();

    $reqEmailRes = null;
    if ($req_user) {
      $reqMsg = "The assigned donor was unable to fulfill your blood request. We are finding another donor for you.";
      $reqNotifId = create_notification($conn, $req_user['user_id'], 'Assignment_Rejected', 'Donor Rejected', $reqMsg, $r_id, $assignment_id, $d_id, $userId);

      // Fail-safe decoupled email to requester
      $reqEmailRes = send_requester_donor_rejected_email($req_user['user_id'], [
        'id'       => $r_id,
        'hospital' => $hosp
      ], $reqNotifId);
    }

    $allSuccess = (!empty($adminEmailRes['success']) && ($reqEmailRes === null || !empty($reqEmailRes['success'])));
    $_SESSION['flash_success'] = format_action_feedback('Assignment declined', $allSuccess);
  }
  header("Location: donor.php");
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'register_donor') {
  $_SESSION['donor_registration_flow'] = true;
  header("Location: donateform.php");
  exit;
}

// Assigned Requests
$assignedRequests = [];
if ($isLoggedIn && !empty($userId)) {
  $stmt_assigned = $conn->prepare("SELECT r.id, r.units, r.hospital, r.required_date, r.status as req_status, r.urgency,
                                                bg.blood_gp_name, 
                                                COALESCE(r.requester_name, ru.username, 'Requester') as requester_name, 
                                                COALESCE(ru.email, r.requester_name, 'N/A') as requester_email, 
                                                COALESCE(rd.phone, 'N/A') as requester_phone,
                                                COALESCE(NULLIF(da.status, ''), NULLIF(r.status, ''), 'Assigned') as assignment_status, 
                                                COALESCE(da.created_at, r.created_at, NOW()) as assigned_date,
                                                r.assigned_donor_id
                                         FROM blood_request r
                                         JOIN donor d ON r.assigned_donor_id = d.id
                                         LEFT JOIN (
                                             SELECT request_id, donor_id, MAX(id) as max_id 
                                             FROM donor_assignments 
                                             GROUP BY request_id, donor_id
                                         ) da_max ON da_max.request_id = r.id AND da_max.donor_id = d.id
                                         LEFT JOIN donor_assignments da ON da.id = da_max.max_id
                                         LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                                         LEFT JOIN users ru ON r.users_id = ru.id
                                         LEFT JOIN donor rd ON ru.id = rd.user_id
                                         WHERE d.user_id = ?
                                           AND r.status NOT IN ('Cancelled')
                                         ORDER BY r.required_date DESC");
  $stmt_assigned->bind_param("i", $userId);
  $stmt_assigned->execute();
  $res_assigned = $stmt_assigned->get_result();
  if ($res_assigned) {
    $assignedRequests = $res_assigned->fetch_all(MYSQLI_ASSOC);
  }
  $stmt_assigned->close();
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Become a Blood Donor – BloodLife</title>
  <script>
    (function() {
      var t = localStorage.getItem('bloodlife-theme');
      if (t === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class'
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }

      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-12px);
      }
    }

    @keyframes pulse-ring {
      0% {
        transform: scale(.9);
        opacity: 1;
      }

      100% {
        transform: scale(1.4);
        opacity: 0;
      }
    }



    .animate-fade-down {
      animation: fadeInDown 0.6s ease-out both;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out both;
    }

    .animate-fade-left {
      animation: fadeInLeft 0.6s ease-out both;
    }

    .animate-fade-right {
      animation: fadeInRight 0.6s ease-out both;
    }

    .float-anim {
      animation: float 3s ease-in-out infinite;
    }

    .pulse-ring {
      animation: pulse-ring 2s ease-out infinite;
    }



    .hero-bg {
      background: linear-gradient(135deg, #dc2626 0%, #991b1b 40%, #be123c 70%, #e11d48 100%);
      position: relative;
      overflow: hidden;
    }

    .hero-bg::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 30% 50%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 40%);
      animation: float 8s ease-in-out infinite;
    }

    .section-pink {
      background: linear-gradient(180deg, #fff1f2 0%, #ffffff 100%);
    }

    .section-white {
      background: #ffffff;
    }

    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px rgba(220, 38, 38, 0.15);
    }

    .step-arrow {
      position: relative;
    }

    .step-arrow::after {
      content: '';
      position: absolute;
      top: 50%;
      right: -1.25rem;
      width: 2rem;
      height: 2px;
      background: linear-gradient(90deg, #fca5a5, #dc2626);
    }

    .step-arrow:last-child::after {
      display: none;
    }
  </style>
  <style id="dark-mode-styles">
    /* Light mode resets */
    html:not(.dark) body {
      background-color: #ffffff !important;
      background-image: none !important;
    }

    html:not(.dark) .bg-gray-50 {
      background-color: #ffffff !important;
    }

    html:not(.dark) .bg-gray-100 {
      background-color: #ffffff !important;
    }

    /* Dark mode body & nav */
    html.dark body {
      background-color: #111827 !important;
      background-image: none !important;
      color: #e5e7eb;
    }

    html.dark nav.bg-slate-100,
    html.dark nav.bg-slate-100.shadow-lg {
      background-color: #1f2937 !important;
    }

    /* Dark mode backgrounds */
    html.dark .bg-white {
      background-color: #1f2937 !important;
    }

    html.dark .bg-gray-50,
    html.dark .bg-gray-100 {
      background-color: #374151 !important;
    }

    html.dark .bg-red-50 {
      background-color: rgba(220, 38, 38, 0.15) !important;
    }

    html.dark .bg-red-100 {
      background-color: rgba(220, 38, 38, 0.2) !important;
    }

    html.dark .bg-pink-50 {
      background-color: rgba(236, 72, 153, 0.1) !important;
    }

    html.dark .bg-green-50 {
      background-color: rgba(34, 197, 94, 0.15) !important;
    }

    html.dark footer {
      background-color: #1f2937 !important;
    }

    /* Dark mode sections */
    html.dark .section-pink {
      background: linear-gradient(180deg, rgba(236, 72, 153, 0.08) 0%, #1f2937 100%) !important;
    }

    html.dark .section-white {
      background-color: #111827 !important;
    }

    /* Dark mode text */
    html.dark .text-gray-900,
    html.dark .text-gray-800 {
      color: #f3f4f6 !important;
    }

    html.dark .text-gray-700 {
      color: #d1d5db !important;
    }

    html.dark .text-gray-600 {
      color: #9ca3af !important;
    }

    html.dark .text-gray-500 {
      color: #9ca3af !important;
    }

    html.dark .text-gray-400 {
      color: #6b7280 !important;
    }

    /* Dark mode forms */
    html.dark input,
    html.dark select,
    html.dark textarea {
      background-color: #374151 !important;
      border-color: #4b5563 !important;
      color: #e5e7eb !important;
    }

    html.dark label {
      color: #d1d5db !important;
    }

    /* Dark mode borders */
    html.dark .border-gray-200,
    html.dark .border-2.border-gray-200 {
      border-color: #4b5563 !important;
    }

    html.dark .border-gray-300 {
      border-color: #4b5563 !important;
    }

    html.dark .border-t {
      border-color: #374151 !important;
    }

    html.dark .border-pink-100 {
      border-color: rgba(236, 72, 153, 0.2) !important;
    }

    html.dark .border-white\/50 {
      border-color: rgba(255, 255, 255, 0.25) !important;
    }

    html.dark .divide-y.divide-gray-50>*+* {
      border-color: #374151 !important;
    }

    /* Dark mode table */
    html.dark tbody tr {
      border-color: #374151 !important;
    }

    html.dark tbody tr:hover {
      background-color: #374151 !important;
    }

    /* Dark mode FAQ */
    html.dark .faq-item {
      background-color: #1f2937 !important;
      border-color: #4b5563 !important;
    }
  </style>
</head>

<body class="bg-white min-h-screen">


  <!-- Mobile Menu Toggle -->
  <div id="mobileMenuToggle" class="fixed top-4 right-4 z-50 md:hidden bg-red-600 text-white p-2 rounded-lg cursor-pointer">
    ☰
  </div>

  <!-- Navbar -->
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
      <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm">
        <div class="flex items-center space-x-2">
          <i class="fas fa-check-circle text-green-600 text-lg"></i>
          <span class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['flash_success']); ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 text-sm font-bold">&times;</button>
      </div>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm">
        <div class="flex items-center space-x-2">
          <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
          <span class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['flash_error']); ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 text-sm font-bold">&times;</button>
      </div>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 1. HERO BANNER -->
  <!-- ══════════════════════════════════════════════════ -->
  <section class="section-pink text-white py-20 sm:py-28 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      <div class="grid md:grid-cols-2 gap-12 items-center">
        <!-- Right: Illustration -->
        <div class="hidden md:flex justify-center animate-fade-right" style="animation-delay: 0.2s;">
          <div class="relative">
            <!-- Decorative rings -->
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="w-72 h-72 rounded-full border-2 border-pink-500/10 pulse-ring"></div>
              <div class="absolute w-56 h-56 rounded-full border-2 border-pink-500/10 pulse-ring" style="animation-delay: 0.5s;"></div>
            </div>
            <!-- Blood drop icon -->
            <div class="relative w-72 h-72 bg-pink-500/20 backdrop-blur-sm rounded-full flex items-center justify-center float-anim">
              <div class="text-center">
                <i class="fas fa-droplet text-8xl text-red-500 mb-4 drop-shadow-lg heartbeat"></i>
                <p class="text-2xl font-bold text-red-600">Give Blood</p>
                <p class="text-pink-400 text-sm mt-1">Save a Life</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Left: Text -->
        <div class="animate-fade-up">

          <h1 class="text-4xl text-pink-500 sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
            Become a<br>
            <span class="text-red-600">Blood Donor</span>
          </h1>
          <p class="text-lg sm:text-xl text-gray-500 mb-8 leading-relaxed max-w-lg" data-i18n="hero_desc">
            Every drop of blood is a gift of life. Your donation can save someone's life today.
          </p>

          <?php if ($isAlreadyDonor): ?>
            <div class="bg-gray-700/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 mb-6">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                  <i class="fas fa-check text-white"></i>
                </div>
                <p class="font-bold text-red-600 text-lg">You are already registered as a donor.</p>
              </div>
              <a href="donateform.php?edit=<?= $donorId ?>" class="inline-flex items-center bg-pink-300 gap-2 text-red-700 px-8 py-3 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105">
                <i class="fas fa-user-pen"></i> View Donor Profile
              </a>
            </div>
          <?php else: ?>
            <div class="flex flex-col sm:flex-row gap-4">
              <a href="donor.php?action=register_donor" class="bg-white text-red-700 px-8 py-4 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl border-2 border-red-500/30 transition transform hover:scale-105 text-center">
                <i class="fas fa-hand-holding-heart mr-2"></i> <span data-i18n="register_as_donor">Register as Donor</span
                  </a>
                <a href="#process" class="text-red-500 px-8 py-4 rounded-xl font-bold  hover:bg-red-50/10 border-2 border-red-500/30 transition text-center">
                  <i class="fas fa-circle-info mr-2"></i> <span data-i18n="learn_more">Learn More</span>
                </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats -->


    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- MY DONATION / ASSIGNMENT -->
  <!-- ═══════════════════════════════════════════════════ -->
  <?php if ($isAlreadyDonor): ?>
    <section class="section-white py-10 pb-0">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl shadow p-6 border border-pink-100 animate-fade-up">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🤝</div>
              <h2 class="text-xl font-bold text-gray-900">My Donation / Assignment</h2>
            </div>
          </div>
          <div class="space-y-4">
            <?php if (count($assignedRequests) > 0): ?>
              <?php foreach ($assignedRequests as $ar): ?>
                <div id="req-<?= $ar['id'] ?>" class="border-2 border-blue-100 rounded-2xl p-5 sm:p-6 bg-white shadow-sm hover:shadow-md transition">
                  <div class="flex flex-col md:flex-row gap-6">
                    <!-- Left: Avatar & Badges -->
                    <div class="flex flex-col items-center sm:items-start gap-4 md:w-48 flex-shrink-0 border-b md:border-b-0 md:border-r border-gray-100 pb-4 md:pb-0 md:pr-6">
                      <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center font-bold text-blue-700 text-3xl shadow-inner mx-auto sm:mx-0">
                        <?= htmlspecialchars($ar['blood_gp_name'] ?? 'N/A') ?>
                      </div>
                      <?php
                      $isExpired = (($ar['req_status'] ?? '') === 'Expired' || ($ar['assignment_status'] ?? '') === 'Expired' || strtotime($ar['required_date']) < strtotime('today'));
                      $displayStatus = $isExpired ? 'Expired' : htmlspecialchars($ar['assignment_status']);
                      $badgeBg = $isExpired ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-blue-50 text-blue-700 border-blue-200';
                      ?>
                      <div class="flex flex-col w-full gap-2 text-center sm:text-left">
                        <span class="<?= $badgeBg ?> border text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">
                          <?= $isExpired ? '⌛' : '🔵' ?> Status: <?= $displayStatus ?>
                        </span>
                        <?php if ($ar['urgency'] === 'Urgent'): ?>
                          <span class="bg-red-50 text-red-600 border border-red-200 text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">
                            🚨 Urgent Request
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <!-- Middle: Details Grid -->
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                      <!-- Request Details -->
                      <div>
                        <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2"><i class="fas fa-file-medical text-blue-400"></i> Request Info</h4>
                        <div class="space-y-2 text-sm text-gray-700">
                          <p><span class="font-semibold text-gray-900">Hospital:</span> <?= htmlspecialchars($ar['hospital']) ?></p>
                          <p><span class="font-semibold text-gray-900">Required By:</span> <?= date('M j, Y', strtotime($ar['required_date'])) ?></p>
                          <p><span class="font-semibold text-gray-900">Units Needed:</span> <?= htmlspecialchars($ar['units']) ?> Unit(s)</p>
                        </div>
                      </div>

                      <!-- Requester Details -->
                      <div>
                        <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2"><i class="fas fa-user text-rose-400"></i> Requester Info</h4>
                        <div class="space-y-2 text-sm text-gray-700">
                          <p><span class="font-semibold text-gray-900">Name:</span> <?= htmlspecialchars($ar['requester_name'] ?? 'N/A') ?></p>
                          <p><span class="font-semibold text-gray-900">Phone:</span> <?= htmlspecialchars($ar['requester_phone'] ?? 'N/A') ?></p>
                          <p><span class="font-semibold text-gray-900">Email:</span> <?= htmlspecialchars($ar['requester_email'] ?? 'N/A') ?></p>
                        </div>
                      </div>

                      <!-- Assignment Details -->
                      <div class="sm:col-span-2 bg-gray-50 p-3 rounded-xl border border-gray-100 flex items-center justify-between">
                        <p class="text-sm text-gray-600"><i class="fas fa-calendar-check text-gray-400 mr-2"></i> <span class="font-semibold">Assigned On:</span> <?= date('M j, Y g:i A', strtotime($ar['assigned_date'])) ?></p>
                      </div>
                    </div>

                    <!-- Right: Actions -->
                    <div class="flex flex-col justify-center items-center md:items-end gap-3 md:w-40 flex-shrink-0 pt-4 md:pt-0 border-t md:border-t-0 md:border-l border-gray-100 md:pl-6">
                      <?php
                      $currentAssignStatus = !empty($ar['assignment_status']) ? $ar['assignment_status'] : $ar['req_status'];
                      if (!$isExpired && in_array($currentAssignStatus, ['Assigned', 'Pending'])):
                      ?>
                        <p class="text-xs text-gray-500 font-semibold mb-1 text-center md:text-right">Awaiting your response</p>
                        <a href="?action=accept&req_id=<?= $ar['id'] ?>" class="w-full bg-green-600 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-green-700 shadow-sm hover:shadow-md transition text-center text-sm flex justify-center items-center gap-2" onclick="return confirm('Are you sure you want to ACCEPT this assignment?')">
                          <i class="fas fa-check"></i> Accept
                        </a>
                        <a href="?action=reject&req_id=<?= $ar['id'] ?>" class="w-full bg-white text-red-600 border-2 border-red-200 px-5 py-2 rounded-xl font-bold hover:bg-red-50 transition text-center text-sm flex justify-center items-center gap-2" onclick="return confirm('Are you sure you want to REJECT this assignment?')">
                          <i class="fas fa-times"></i> Reject
                        </a>
                      <?php elseif ($isExpired || $currentAssignStatus === 'Expired'): ?>
                        <div class="flex flex-col items-center justify-center bg-gray-100 p-4 rounded-xl border border-gray-200 w-full h-full min-h-[100px]">
                          <i class="fas fa-clock text-gray-400 text-2xl mb-2"></i>
                          <span class="text-sm font-bold text-gray-600 text-center">Expired</span>
                        </div>
                      <?php else: ?>
                        <div class="flex flex-col items-center justify-center bg-gray-50 p-4 rounded-xl border border-gray-200 w-full h-full min-h-[100px]">
                          <i class="fas fa-clipboard-check text-green-500 text-2xl mb-2"></i>
                          <span class="text-sm font-bold text-gray-700 text-center"><?= htmlspecialchars($currentAssignStatus ?: 'Action Taken') ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="border-2 border-gray-100 rounded-xl p-8 text-center">
                <p class="text-gray-500">No assignments currently.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>




  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 3. DONOR ELIGIBILITY -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">

        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Donor Eligibility</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Before you donate, make sure you meet these basic requirements to ensure your safety and the safety of recipients.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
        <!-- Age -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-red-200">
            <i class="fas fa-cake-candles"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Age 18–65 years</h4>
          <p class="text-gray-500 text-sm">You must be between 18 and 65 years old to donate blood.</p>
        </div>

        <!-- Weight -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-pink-200">
            <i class="fas fa-weight-scale"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Weight at least 100 lb</h4>
          <p class="text-gray-500 text-sm">You must weigh at least 100 lbs to safely donate blood.</p>
        </div>

        <!-- Health -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-rose-200">
            <i class="fas fa-heart"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Good Health Condition</h4>
          <p class="text-gray-500 text-sm">You should be in good health, free from fever or infectious diseases.</p>
        </div>

        <!-- Interval -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-red-100">
            <i class="fas fa-clock"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Last Donation 3 Months Ago</h4>
          <p class="text-gray-500 text-sm">You must wait at least 3 months between whole blood donations.</p>
        </div>
      </div>


    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 4. BLOOD DONATION PROCESS -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section id="process" class="section-pink py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">

        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Blood Donation Process</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Donating blood is quick, easy, and safe. Here's what to expect during your visit.
        </p>
      </div>

      <!-- Horizontal Steps (Desktop) -->
      <div class="hidden lg:flex items-start justify-between max-w-5xl mx-auto">
        <!-- Step 1 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-lg shadow-red-200 hover:scale-110 transition-transform">
            <i class="fas fa-user-plus"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Register as Donor</h4>
          <p class="text-gray-500 text-sm px-2">Fill out the registration form with your basic details.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-red-300 to-pink-300 hidden lg:block"></div>
        </div>

        <!-- Step 2 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-pink-500 to-rose-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-pink-200 hover:scale-110 transition-transform">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Admin Reviews</h4>
          <p class="text-gray-500 text-sm px-2">Our team reviews your registration and verifies your eligibility.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-pink-300 to-rose-300 hidden lg:block"></div>
        </div>

        <!-- Step 3 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-rose-500 to-red-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-rose-200 hover:scale-110 transition-transform">
            <i class="fas fa-link"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Assigned to a Blood Request</h4>
          <p class="text-gray-500 text-sm px-2">You are matched with a patient request based on your blood type.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-rose-300 to-red-300 hidden lg:block"></div>
        </div>

        <!-- Step 4 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-red-400 to-pink-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-100 hover:scale-110 transition-transform">
            <i class="fas fa-droplet"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Donate Blood</h4>
          <p class="text-gray-500 text-sm px-2">Relax while about 450ml of blood is collected. Takes 8–10 minutes.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-red-300 to-pink-300 hidden lg:block"></div>
        </div>



        <!-- Step 6 -->
        <div class="flex flex-col items-center text-center flex-1">
          <div class="w-20 h-20 bg-gradient-to-br from-red-600 to-rose-600 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-200 hover:scale-110 transition-transform">
            <i class="fas fa-check"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2"> Mark as Completed</h4>
          <p class="text-gray-500 text-sm px-2">Admin confirms the donation is completed.</p>
        </div>
      </div>

      <!-- Vertical Steps (Mobile) -->
      <div class="lg:hidden space-y-6 max-w-md mx-auto">
        <!-- Step 1 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-red-200">
            <i class="fas fa-user-plus"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Register as Donor</h4>
            <p class="text-gray-500 text-sm mt-1">Fill out the registration form with your basic details.</p>
          </div>
        </div>
        <!-- Step 2 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-rose-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-pink-200">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Admin Reviews</h4>
            <p class="text-gray-500 text-sm mt-1">Our team reviews your registration and verifies your eligibility.</p>
          </div>
        </div>
        <!-- Step 3 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-red-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-rose-200">
            <i class="fas fa-link"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Assigned to a Blood Request</h4>
            <p class="text-gray-500 text-sm mt-1">You are matched with a patient request based on your blood type.</p>
          </div>
        </div>
        <!-- Step 4 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-pink-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-red-100">
            <i class="fas fa-droplet"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Donate Blood</h4>
            <p class="text-gray-500 text-sm mt-1">Relax while about 450ml of blood is collected. Takes 8–10 minutes.</p>
          </div>
        </div>
        <!-- Step 5 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-rose-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-red-200">
            <i class="fas fa-certificate"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900" data-i18n="step_cert">Receive Certificate</h4>
            <p class="text-gray-500 text-sm mt-1" data-i18n="step_cert_desc">Get your donation certificate and know you've saved lives.</p>
          </div>
        </div>
      </div>

      <!-- Before You Donate -->
      <div class="mt-16 bg-white rounded-2xl p-8 sm:p-10 border border-pink-100 shadow-sm">
        <h3 class="text-xl font-bold text-gray-900 mb-6 text-center">
          <i class="fas fa-clipboard-list text-red-500 mr-2"></i> Before You Donate
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-bed text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Sleep Well</p>
            <p class="text-gray-500 text-xs mt-1">Get 7–8 hours</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-utensils text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Eat Healthy</p>
            <p class="text-gray-500 text-xs mt-1">Iron-rich food</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-glass-water text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Stay Hydrated</p>
            <p class="text-gray-500 text-xs mt-1">Drink plenty of water</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-id-card text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Bring Your ID</p>
            <p class="text-gray-500 text-xs mt-1">Valid photo ID</p>
          </div>
        </div>
      </div>
    </div>
  </section>





  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 8. CONTACT INFORMATION -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">

        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Contact Information</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Have questions? Reach out to us anytime. We're here to help you become a lifesaver.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 max-w-4xl mx-auto">
        <!-- Email -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-200">
            <i class="fas fa-envelope"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Email</h4>
          <p class="text-gray-500 text-sm">bloodcommunication12@gmail.com</p>
        </div>

        <!-- Phone -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200">
            <i class="fas fa-phone"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Phone</h4>
          <p class="text-gray-500 text-sm">09-258111622</p>
        </div>

        <!-- Address -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center sm:col-span-2 lg:col-span-1">
          <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-rose-200">
            <i class="fas fa-location-dot"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Address</h4>
          <p class="text-gray-500 text-sm">123 Health Street, City</p>
        </div>
      </div>
    </div>
  </section>



  <!-- ═══════════════════════════════════════════════════ -->
  <!-- FOOTER -->
  <!-- ═══════════════════════════════════════════════════ -->
  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- SCRIPTS -->
  <!-- ═══════════════════════════════════════════════════ -->
  <script>
    // Notification Dropdown Toggle
    function toggleNotifDropdown() {
      document.getElementById('notifDropdown').classList.toggle('hidden');
    }

    // User Dropdown Toggle
    function toggleUserDropdown() {
      document.getElementById('userDropdown').classList.toggle('hidden');
    }

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
      var notifMenu = document.getElementById('notifMenu');
      var notifDropdown = document.getElementById('notifDropdown');
      var userMenu = document.getElementById('userMenu');
      var userDropdown = document.getElementById('userDropdown');

      if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
        notifDropdown.classList.add('hidden');
      }
      if (userMenu && userDropdown && !userMenu.contains(e.target)) {
        userDropdown.classList.add('hidden');
      }
    });





    // Scroll reveal animations
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, {
      threshold: 0.1
    });

    document.querySelectorAll('.card-hover').forEach(function(el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'all 0.6s ease-out';
      observer.observe(el);
    });
  </script>

  <script>
    (function() {
      var KEY = 'bloodlife-theme';

      function getTheme() {
        return localStorage.getItem(KEY) || 'light';
      }

      function apply(t) {
        if (t === 'dark') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
          var sun = btn.querySelector('.theme-icon-sun');
          var moon = btn.querySelector('.theme-icon-moon');
          if (sun) sun.style.display = t === 'dark' ? 'none' : 'inline';
          if (moon) moon.style.display = t === 'dark' ? 'inline' : 'none';
        });
      }
      apply(getTheme());
      window.toggleTheme = function() {
        var current = localStorage.getItem(KEY) || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        apply(next);
      };
    })();
  </script>

  <!-- Accept Confirmation Modal -->
  <div id="acceptConfirmModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
      <div class="p-8 text-center space-y-6">
        <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
          🤝
        </div>
        <div>
          <h2 class="font-bold text-2xl text-gray-900 mb-2">Accept Assignment</h2>
          <p class="text-gray-500">Are you sure you want to accept this blood donation assignment?</p>
        </div>
      </div>
      <div class="px-8 pb-8 flex gap-3">
        <button onclick="closeAcceptModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
        <a href="#" id="confirmAcceptBtn" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 transition text-center shadow-md flex items-center justify-center">Yes, Accept</a>
      </div>
    </div>
  </div>

  <!-- Decline Confirmation Modal -->
  <div id="declineConfirmModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
      <div class="p-8 text-center space-y-6">
        <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
          ⚠️
        </div>
        <div>
          <h2 class="font-bold text-2xl text-gray-900 mb-2">Decline Assignment</h2>
          <p class="text-gray-500">Are you sure you want to decline this assignment?</p>
        </div>
      </div>
      <div class="px-8 pb-8 flex gap-3">
        <button onclick="closeDeclineModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
        <a href="#" id="confirmDeclineBtn" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition text-center shadow-md flex items-center justify-center">Yes, Decline</a>
      </div>
    </div>
  </div>

  <script>
    function openAcceptModal(id) {
      document.getElementById('confirmAcceptBtn').href = 'donor.php?action=accept&req_id=' + id;
      document.getElementById('acceptConfirmModal').classList.remove('hidden');
      document.getElementById('acceptConfirmModal').classList.add('flex');
    }

    function closeAcceptModal() {
      document.getElementById('acceptConfirmModal').classList.remove('flex');
      document.getElementById('acceptConfirmModal').classList.add('hidden');
    }

    function openDeclineModal(id) {
      document.getElementById('confirmDeclineBtn').href = 'donor.php?action=decline&req_id=' + id;
      document.getElementById('declineConfirmModal').classList.remove('hidden');
      document.getElementById('declineConfirmModal').classList.add('flex');
    }

    function closeDeclineModal() {
      document.getElementById('declineConfirmModal').classList.remove('flex');
      document.getElementById('declineConfirmModal').classList.add('hidden');
    }
  </script>

</body>

</html>