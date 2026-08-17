<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

// Check if logged-in user already has a donor record
$isAlreadyDonor = false;
$donorId = 0;
if ($isLoggedIn) {
  $userId = $_SESSION['user_id'] ?? 0;
  if ($userId > 0) {
    $stmt = $conn->prepare("SELECT id FROM donor WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $isAlreadyDonor = true;
      $donorId = $row['id'];
    }
    $stmt->close();
  }
}

// Handle Accept/Decline Assignment
if (isset($_GET['action']) && isset($_GET['req_id'])) {
  $r_id = (int)$_GET['req_id'];
  $d_id = $donorId ?? 0;

  // Fetch all admins for notification
  $admins = [0]; // Admin is hardcoded as user_id 0 in this system
  if ($_GET['action'] === 'accept' && $d_id > 0) {
    // Update blood_request
    $stmt_a = $conn->prepare("UPDATE blood_request SET status = 'Accepted' WHERE id = ? AND assigned_donor_id = ?");
    $stmt_a->bind_param("ii", $r_id, $d_id);
    $stmt_a->execute();

    // Update donor_assignments
    $stmt_assign = $conn->prepare("UPDATE donor_assignments SET status = 'Accepted', responded_at = NOW() WHERE request_id = ? AND donor_id = ?");
    $stmt_assign->bind_param("ii", $r_id, $d_id);
    $stmt_assign->execute();

    // Notify Admin
    $assignment_id = null;
    $get_assign = $conn->prepare("SELECT id FROM donor_assignments WHERE request_id = ? AND donor_id = ?");
    $get_assign->bind_param("ii", $r_id, $d_id);
    $get_assign->execute();
    if ($row_assign = $get_assign->get_result()->fetch_assoc()) $assignment_id = $row_assign['id'];
    $get_assign->close();

    $msg = "Donor " . htmlspecialchars($username) . " has accepted the assignment for Request #" . $r_id . ".";
    $notifType = 'StatusUpdate';
    $notifTitle = 'Assignment Accepted';
    $notif = $conn->prepare("INSERT INTO notifications (user_id, request_id, assignment_id, type, title, message) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($admins as $admin_id) {
      $notif->bind_param("iiisss", $admin_id, $r_id, $assignment_id, $notifType, $notifTitle, $msg);
      $notif->execute();
    }
  } elseif ($_GET['action'] === 'decline' && $d_id > 0) {
    // Update blood_request: unassign donor and set to Pending
    $stmt_a = $conn->prepare("UPDATE blood_request SET status = 'Pending', assigned_donor_id = NULL WHERE id = ? AND assigned_donor_id = ?");
    $stmt_a->bind_param("ii", $r_id, $d_id);
    $stmt_a->execute();

    // Update donor_assignments
    $stmt_assign = $conn->prepare("UPDATE donor_assignments SET status = 'Rejected', responded_at = NOW() WHERE request_id = ? AND donor_id = ?");
    $stmt_assign->bind_param("ii", $r_id, $d_id);
    $stmt_assign->execute();

    // Make donor available again
    $stmt_donor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
    $stmt_donor->bind_param("i", $d_id);
    $stmt_donor->execute();

    // Notify Admin
    $assignment_id = null;
    $get_assign = $conn->prepare("SELECT id FROM donor_assignments WHERE request_id = ? AND donor_id = ?");
    $get_assign->bind_param("ii", $r_id, $d_id);
    $get_assign->execute();
    if ($row_assign = $get_assign->get_result()->fetch_assoc()) $assignment_id = $row_assign['id'];
    $get_assign->close();

    $msg = "Donor " . htmlspecialchars($username) . " has declined the assignment for Request #" . $r_id . ".";
    $notifType = 'StatusUpdate';
    $notifTitle = 'Assignment Declined';
    $notif = $conn->prepare("INSERT INTO notifications (user_id, request_id, assignment_id, type, title, message) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($admins as $admin_id) {
      $notif->bind_param("iiisss", $admin_id, $r_id, $assignment_id, $notifType, $notifTitle, $msg);
      $notif->execute();
    }
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
if ($donorId > 0) {
  $stmt_assigned = $conn->prepare("SELECT r.id, r.units, r.hospital, r.required_date, r.status as req_status, r.urgency,
                                                bg.blood_gp_name, ru.username as requester_name, ru.email as requester_email, rd.phone as requester_phone,
                                                da.status as assignment_status, da.created_at as assigned_date
                                         FROM blood_request r
                                         JOIN (SELECT request_id, MAX(id) as max_id FROM donor_assignments WHERE donor_id = ? GROUP BY request_id) da_max ON da_max.request_id = r.id
                                         JOIN donor_assignments da ON da.id = da_max.max_id
                                         LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                                         LEFT JOIN users ru ON r.users_id = ru.id
                                         LEFT JOIN donor rd ON ru.id = rd.user_id
                                         WHERE r.assigned_donor_id = ?
                                         ORDER BY r.required_date DESC");
  $stmt_assigned->bind_param("ii", $donorId, $donorId);
  $stmt_assigned->execute();
  $res_assigned = $stmt_assigned->get_result();
  if ($res_assigned) {
    $assignedRequests = $res_assigned->fetch_all(MYSQLI_ASSOC);
  }
  $stmt_assigned->close();
}

// Fetch all donors with username
$donors = [];
$donorResult = $conn->query("
    SELECT d.id, d.user_id, d.gender, d.age, d.blood_groups, d.address, d.last_donation_date, d.available_status, u.username
    FROM donor d
    JOIN users u ON d.user_id = u.id
    WHERE u.status = 'Active'
    ORDER BY d.id DESC
");
if ($donorResult && $donorResult->num_rows > 0) {
  $donors = $donorResult->fetch_all(MYSQLI_ASSOC);
}

// Count total donations per donor
$donationCounts = [];
if (count($donors) > 0) {
  $donorIds = array_column($donors, 'id');
  $placeholders = implode(',', array_fill(0, count($donorIds), '?'));
  $types = str_repeat('i', count($donorIds));
  $countStmt = $conn->prepare("SELECT donor_id, COUNT(*) AS total FROM donor_assignments WHERE status='Completed' AND donor_id IN ($placeholders) GROUP BY donor_id");
  $countStmt->bind_param($types, ...$donorIds);
  $countStmt->execute();
  $countResult = $countStmt->get_result();
  while ($row = $countResult->fetch_assoc()) {
    $donationCounts[$row['donor_id']] = $row['total'];
  }
  $countStmt->close();
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

    html.dark nav.bg-white,
    html.dark nav.bg-white.shadow-lg {
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

    /* Dark mode donor cards */
    html.dark .donor-card {
      background-color: #1f2937 !important;
      border-color: #4b5563 !important;
    }

    html.dark .donor-card:hover {
      box-shadow: 0 20px 40px rgba(220, 38, 38, 0.2) !important;
    }

    /* Dark mode donor modal */
    html.dark #donorDetailModal .bg-white {
      background-color: #1f2937 !important;
    }

    html.dark #donorDetailModal .bg-gray-100 {
      background-color: #374151 !important;
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
              <a href="donor.php?action=register_donor" class="bg-white text-red-700 px-8 py-4 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105 text-center">
                <i class="fas fa-hand-holding-heart mr-2"></i> <span data-i18n="register_as_donor">Register as Donor</span>
              </a>
              <a href="#process" class="border-2 border-white/50 text-red-500 px-8 py-4 rounded-xl font-bold hover:bg-red-50/10 transition text-center">
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
                      <div class="flex flex-col w-full gap-2 text-center sm:text-left">
                        <span class="bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">
                          🔵 Status: <?= htmlspecialchars($ar['assignment_status']) ?>
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
                      <?php if ($ar['assignment_status'] === 'Assigned'): ?>
                        <p class="text-xs text-gray-500 font-semibold mb-1 text-center md:text-right">Awaiting your response</p>
                        <a href="?action=accept&req_id=<?= $ar['id'] ?>" class="w-full bg-green-600 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-green-700 shadow-sm hover:shadow-md transition text-center text-sm flex justify-center items-center gap-2" onclick="return confirm('Are you sure you want to ACCEPT this assignment?')">
                          <i class="fas fa-check"></i> Accept
                        </a>
                        <a href="?action=decline&req_id=<?= $ar['id'] ?>" class="w-full bg-white text-red-600 border-2 border-red-200 px-5 py-2 rounded-xl font-bold hover:bg-red-50 transition text-center text-sm flex justify-center items-center gap-2" onclick="return confirm('Are you sure you want to DECLINE this assignment?')">
                          <i class="fas fa-times"></i> Decline
                        </a>
                      <?php else: ?>
                        <div class="flex flex-col items-center justify-center bg-gray-50 p-4 rounded-xl border border-gray-200 w-full h-full min-h-[100px]">
                          <i class="fas fa-clipboard-check text-green-500 text-2xl mb-2"></i>
                          <span class="text-sm font-bold text-gray-700 text-center">Action Taken</span>
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
  <!-- 6. OUR DONORS -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12">

        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Find Blood Donors</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Browse our registered donors and find the blood type you need.
        </p>
      </div>



      <!-- Donor Carousel Wrapper -->
      <div class="relative w-full max-w-7xl mx-auto group" id="donorCarouselWrapper">
        <!-- Prev Button -->
        <button id="donorPrevBtn" class="absolute left-0 top-1/2 -translate-y-1/2 -ml-4 md:-ml-6 z-10 bg-white shadow-lg text-red-600 rounded-full w-12 h-12 flex items-center justify-center hover:bg-red-50 hover:scale-110 transition opacity-0 group-hover:opacity-100 hidden sm:flex">
          <i class="fas fa-chevron-left"></i>
        </button>

        <div id="donorCarouselViewport" class="overflow-hidden w-full px-2 py-4">
          <!-- Donor Cards Track -->
          <div id="donorCardsGrid" class="flex overflow-x-auto snap-x snap-mandatory gap-6 scroll-smooth" style="scrollbar-width: none; -ms-overflow-style: none;">
            <style>
              #donorCardsGrid::-webkit-scrollbar {
                display: none;
              }
            </style>
            <?php if (count($donors) > 0): ?>
              <?php foreach ($donors as $d):
                $isAvailable = ($d['available_status'] ?? 'Available') === 'Available';
                $statusColor = $isAvailable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                $statusIcon = $isAvailable ? 'fa-circle-check' : 'fa-circle-xmark';
                $avatarBg = $isAvailable ? 'from-red-500 to-rose-500' : 'from-gray-400 to-gray-500';
                $initials = strtoupper(substr($d['username'], 0, 1));
              ?>
                <div class="donor-slide donor-card flex-none w-[85%] sm:w-[calc(50%-12px)] lg:w-[calc(33.333%-16px)] xl:w-[calc(25%-18px)] snap-start bg-white rounded-2xl border border-pink-100 shadow-sm overflow-hidden animate-fade-up hover:-translate-y-1.5 hover:scale-[1.02] hover:shadow-xl transition-all duration-300"
                  data-name="<?= htmlspecialchars(strtolower($d['username'])) ?>"
                  data-bloodgroup="<?= htmlspecialchars($d['blood_groups']) ?>"
                  data-status="<?= htmlspecialchars($d['available_status'] ?? 'Available') ?>"
                  data-address="<?= htmlspecialchars(strtolower($d['address'])) ?>">
                  <!-- Avatar Header -->
                  <div class="relative bg-gradient-to-br <?= $avatarBg ?> p-6 text-center">
                    <div class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-3 border-2 border-white/30">
                      <span class="text-3xl font-bold text-white"><?= $initials ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($d['username']) ?></h3>
                  </div>
                  <!-- Card Body -->
                  <div class="p-5 space-y-3">
                    <!-- Blood Group -->
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-droplet text-red-500 text-sm"></i>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Blood Group</p>
                        <p class="font-bold text-gray-900"><?= htmlspecialchars($d['blood_groups']) ?></p>
                      </div>
                    </div>
                    <!-- Township -->
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 bg-pink-50 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-location-dot text-pink-500 text-sm"></i>
                      </div>
                      <div class="min-w-0">
                        <p class="text-xs text-gray-500">Township</p>
                        <p class="font-semibold text-gray-900 truncate" title="<?= htmlspecialchars($d['address']) ?>"><?= htmlspecialchars($d['address']) ?></p>
                      </div>
                    </div>
                    <!-- Status Badge -->
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 <?= $isAvailable ? 'bg-green-50' : 'bg-red-50' ?> rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas <?= $statusIcon ?> <?= $isAvailable ? 'text-green-500' : 'text-red-500' ?> text-sm"></i>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Status</p>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusColor ?>"><?= htmlspecialchars($d['available_status']) ?></span>
                      </div>
                    </div>
                    <!-- Last Donation Date -->
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-calendar-check text-rose-500 text-sm"></i>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Last Donation</p>
                        <p class="font-semibold text-gray-900"><?= $d['last_donation_date'] ? htmlspecialchars(date('M d, Y', strtotime($d['last_donation_date']))) : 'Never donated' ?></p>
                      </div>
                    </div>
                  </div>

                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Next Button -->
        <button id="donorNextBtn" class="absolute right-0 top-1/2 -translate-y-1/2 -mr-4 md:-mr-6 z-10 bg-white shadow-lg text-red-600 rounded-full w-12 h-12 flex items-center justify-center hover:bg-red-50 hover:scale-110 transition opacity-0 group-hover:opacity-100 hidden sm:flex">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>

      <!-- Pagination Dots -->
      <div class="flex justify-center items-center gap-2 mt-4 mb-8" id="donorCarouselDots"></div>

      <!-- Empty State -->
      <div id="donorEmptyState" class="hidden text-center py-16">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-users-slash text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">No Donors Found</h3>
        <p class="text-gray-500">Try adjusting your search filters.</p>
      </div>
      <?php if (count($donors) === 0): ?>
        <div class="text-center py-16">
          <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-users-slash text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">No Donors Yet</h3>
          <p class="text-gray-500">Be the first to register as a blood donor!</p>
          <a href="donor.php?action=register_donor" class="inline-flex items-center gap-2 bg-red-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-red-700 transition mt-4">
            <i class="fas fa-hand-holding-heart"></i> Register as Donor
          </a>
        </div>
      <?php endif; ?>
    </div>
  </section>


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
          <p class="text-gray-500 text-sm">info@bloodlife.com</p>
        </div>

        <!-- Phone -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200">
            <i class="fas fa-phone"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Phone</h4>
          <p class="text-gray-500 text-sm">1-800-BLOOD-999</p>
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



    // Donor card filtering
    var donorSearchInput = document.getElementById('donorSearchInput');
    var donorFilterBloodGroup = document.getElementById('donorFilterBloodGroup');
    var donorFilterStatus = document.getElementById('donorFilterStatus');
    var donorCards = document.querySelectorAll('.donor-card');
    var donorEmptyState = document.getElementById('donorEmptyState');
    var donorCardsGrid = document.getElementById('donorCardsGrid');

    function applyDonorFilters() {
      var q = donorSearchInput.value.toLowerCase();
      var bg = donorFilterBloodGroup.value;
      var status = donorFilterStatus.value;
      var visible = 0;
      donorCards.forEach(function(card) {
        var matchSearch = !q || card.dataset.name.includes(q) || card.dataset.address.includes(q);
        var matchBg = !bg || card.dataset.bloodgroup === bg;
        var matchStatus = !status || card.dataset.status === status;
        var show = matchSearch && matchBg && matchStatus;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (donorEmptyState) {
        donorEmptyState.classList.toggle('hidden', visible > 0);
      }
    }

    function clearDonorFilters() {
      donorSearchInput.value = '';
      donorFilterBloodGroup.value = '';
      donorFilterStatus.value = '';
      applyDonorFilters();
    }

    if (donorSearchInput) donorSearchInput.addEventListener('keyup', applyDonorFilters);
    if (donorFilterBloodGroup) donorFilterBloodGroup.addEventListener('change', applyDonorFilters);
    if (donorFilterStatus) donorFilterStatus.addEventListener('change', applyDonorFilters);

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

  <!-- Donor Detail Modal -->
  <div id="donorDetailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
      <div id="modalAvatar" class="p-8 text-center">
        <div class="w-24 h-24 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-4 border-2 border-white/30">
          <span id="modalInitial" class="text-4xl font-bold text-white"></span>
        </div>
        <h3 id="modalName" class="text-2xl font-bold text-white"></h3>
      </div>
      <div class="p-6 space-y-4">
        <!-- Blood Group -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-droplet text-red-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Blood Group</p>
            <p id="modalBloodGroup" class="font-bold text-gray-900"></p>
          </div>
        </div>
        <!-- Gender -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-venus-mars text-pink-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Gender</p>
            <p id="modalGender" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Age -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-cake-candles text-rose-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Age</p>
            <p id="modalAge" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Township -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-location-dot text-pink-500"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-500">Township</p>
            <p id="modalTownship" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Status -->
        <div class="flex items-center gap-3">
          <div id="modalStatusIcon" class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Available Status</p>
            <span id="modalStatus" class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"></span>
          </div>
        </div>
        <!-- Last Donation Date -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-calendar-check text-rose-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Last Donation</p>
            <p id="modalLastDonation" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Total Donations -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-hand-holding-heart text-red-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Total Donations</p>
            <p id="modalDonations" class="font-bold text-gray-900 text-lg"></p>
          </div>
        </div>
      </div>
      <div class="px-6 pb-6">
        <button onclick="closeDonorModal()" class="w-full bg-gray-100 text-gray-700 font-semibold py-3 rounded-xl hover:bg-gray-200 transition">Close</button>
      </div>
    </div>
  </div>

  <script>
    function openDonorModal(btn) {
      var modal = document.getElementById('donorDetailModal');
      var avatarBg = btn.dataset.avatarbg;

      document.getElementById('modalInitial').textContent = btn.dataset.initial;
      document.getElementById('modalName').textContent = btn.dataset.name;
      document.getElementById('modalBloodGroup').textContent = btn.dataset.bloodgroup;
      document.getElementById('modalGender').textContent = btn.dataset.gender;
      document.getElementById('modalAge').textContent = btn.dataset.age + ' years';
      document.getElementById('modalTownship').textContent = btn.dataset.address;
      document.getElementById('modalLastDonation').textContent = btn.dataset.lastdonation;
      document.getElementById('modalDonations').textContent = btn.dataset.donations;

      var avatar = document.getElementById('modalAvatar');
      avatar.className = 'p-8 text-center bg-gradient-to-br ' + avatarBg;

      var isAvailable = btn.dataset.status === 'Available';
      var statusEl = document.getElementById('modalStatus');
      var statusIconEl = document.getElementById('modalStatusIcon');
      statusEl.textContent = btn.dataset.status;
      statusEl.className = 'inline-flex rounded-full px-3 py-1 text-xs font-semibold ' + (isAvailable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
      statusIconEl.className = 'w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ' + (isAvailable ? 'bg-green-50' : 'bg-red-50');
      statusIconEl.querySelector('i').className = 'fas ' + (isAvailable ? 'fa-circle-check text-green-500' : 'fa-circle-xmark text-red-500');

      modal.classList.remove('hidden');
    }

    function closeDonorModal() {
      document.getElementById('donorDetailModal').classList.add('hidden');
    }

    document.getElementById('donorDetailModal').addEventListener('click', function(e) {
      if (e.target === this) closeDonorModal();
    });
  </script>


  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const track = document.getElementById('donorCardsGrid');
      const prevBtn = document.getElementById('donorPrevBtn');
      const nextBtn = document.getElementById('donorNextBtn');
      const dotsContainer = document.getElementById('donorCarouselDots');
      const wrapper = document.getElementById('donorCarouselWrapper');
      let autoSlideInterval;

      function getVisibleSlides() {
        return Array.from(track.children).filter(el => el.classList.contains('donor-slide') && el.style.display !== 'none');
      }

      function updateCarousel() {
        const slides = getVisibleSlides();
        if (dotsContainer) dotsContainer.innerHTML = '';

        const maxScroll = track.scrollWidth - track.clientWidth;
        const shouldShowControls = slides.length > 1 && maxScroll > 10;

        if (!shouldShowControls) {
          if (prevBtn) prevBtn.style.display = 'none';
          if (nextBtn) nextBtn.style.display = 'none';
          return;
        } else {
          if (prevBtn) prevBtn.style.display = '';
          if (nextBtn) nextBtn.style.display = '';
        }

        if (maxScroll > 0) {
          const numDots = Math.ceil(track.scrollWidth / track.clientWidth);
          for (let i = 0; i < numDots; i++) {
            const dot = document.createElement('button');
            dot.className = 'w-3 h-3 rounded-full transition-all bg-gray-300 hover:bg-red-400';
            dot.onclick = () => {
              track.scrollTo({
                left: i * track.clientWidth,
                behavior: 'smooth'
              });
            };
            if (dotsContainer) dotsContainer.appendChild(dot);
          }
          updateDots();
        }
      }

      function updateDots() {
        if (!dotsContainer || !dotsContainer.children.length) return;
        const index = Math.round(track.scrollLeft / track.clientWidth);
        Array.from(dotsContainer.children).forEach((dot, i) => {
          if (i === index) {
            dot.classList.remove('bg-gray-300');
            dot.classList.add('bg-red-600', 'w-6');
          } else {
            dot.classList.add('bg-gray-300');
            dot.classList.remove('bg-red-600', 'w-6');
          }
        });
      }

      if (track) track.addEventListener('scroll', updateDots);

      function scrollNext() {
        if (!track) return;
        const maxScroll = track.scrollWidth - track.clientWidth;
        if (maxScroll <= 10) return; // Nothing to scroll

        const slides = getVisibleSlides();
        const slideWidth = slides.length > 0 ? slides[0].offsetWidth + 24 : 0;
        if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 10) {
          track.scrollTo({
            left: 0,
            behavior: 'smooth'
          });
        } else {
          track.scrollBy({
            left: slideWidth,
            behavior: 'smooth'
          });
        }
      }

      function scrollPrev() {
        if (!track) return;
        const maxScroll = track.scrollWidth - track.clientWidth;
        if (maxScroll <= 10) return; // Nothing to scroll

        const slides = getVisibleSlides();
        const slideWidth = slides.length > 0 ? slides[0].offsetWidth + 24 : 0;
        if (track.scrollLeft <= 0) {
          track.scrollTo({
            left: track.scrollWidth,
            behavior: 'smooth'
          });
        } else {
          track.scrollBy({
            left: -slideWidth,
            behavior: 'smooth'
          });
        }
      }

      if (nextBtn) nextBtn.addEventListener('click', scrollNext);
      if (prevBtn) prevBtn.addEventListener('click', scrollPrev);

      function startAutoSlide() {
        stopAutoSlide();
        autoSlideInterval = setInterval(scrollNext, 3500);
      }

      function stopAutoSlide() {
        clearInterval(autoSlideInterval);
      }

      if (wrapper) {
        wrapper.addEventListener('mouseenter', stopAutoSlide);
        wrapper.addEventListener('mouseleave', startAutoSlide);
        // Touch events for mobile to pause
        wrapper.addEventListener('touchstart', stopAutoSlide, {
          passive: true
        });
        wrapper.addEventListener('touchend', startAutoSlide, {
          passive: true
        });
      }

      updateCarousel();
      startAutoSlide();
      window.addEventListener('resize', updateCarousel);

      // Hook into existing filters if defined
      if (typeof window.applyDonorFilters === 'function') {
        const originalApply = window.applyDonorFilters;
        window.applyDonorFilters = function() {
          originalApply();
          setTimeout(() => {
            track.scrollTo({
              left: 0,
              behavior: 'smooth'
            });
            updateCarousel();
          }, 100);
        };
      }
    });
  </script>
</body>

</html>