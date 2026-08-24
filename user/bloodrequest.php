<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
  if (isset($_POST['action']) && $_POST['action'] === 'blood_received' && isset($_POST['request_id'])) {
    $req_id = (int)$_POST['request_id'];
    // Verify it belongs to the user and is 'Assigned' or 'Accepted'
    $stmt = $conn->prepare("
        SELECT id, assigned_donor_id, blood_groups_id
        FROM blood_request 
        WHERE id = ? AND users_id = ? AND status IN ('Assigned', 'Accepted')
    ");
    $stmt->bind_param("ii", $req_id, $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
      $row = $res->fetch_assoc();
      $updReq = $conn->prepare("UPDATE blood_request SET status = 'Completed', received_at = NOW() WHERE id = ?");
      $updReq->bind_param("i", $req_id);
      $updReq->execute();

      $updAssign = $conn->prepare("UPDATE donor_assignments SET status = 'Completed', completed_at = NOW() WHERE request_id = ? AND status IN ('Assigned', 'Accepted')");
      $updAssign->bind_param("i", $req_id);
      $updAssign->execute();

      // Insert into donation history
      if (!empty($row['assigned_donor_id'])) {
        $dhStmt = $conn->prepare("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, units, donation_date, status) VALUES (?, ?, ?, ?, 1, NOW(), 'Completed')");
        $dhStmt->bind_param("iiii", $row['assigned_donor_id'], $_SESSION['user_id'], $req_id, $row['blood_groups_id']);
        $dhStmt->execute();
        $dhStmt->close();

        // Mark donor as available again
        $updDonor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
        $updDonor->bind_param("i", $row['assigned_donor_id']);
        $updDonor->execute();
        $updDonor->close();
      }

      $notifTitle = 'Request Completed';
      $notifMsg = 'Requester has confirmed that the blood was received. This request is now completed. (Request #' . $req_id . ')';
      $admin_id = 0; // Admin is hardcoded as user_id 0
      $notif = $conn->prepare("INSERT INTO notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'System', ?, ?)");
      $notif->bind_param("iiss", $admin_id, $req_id, $notifTitle, $notifMsg);
      $notif->execute();
      $success_msg = "Blood received successfully. Request is now completed.";
    } else {
      $error_msg = "Invalid request or it is not in an active assignment status.";
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_request' && isset($_POST['request_id'])) {
    $req_id = (int)$_POST['request_id'];
    $stmt = $conn->prepare("SELECT id, status, assigned_donor_id FROM blood_request WHERE id = ? AND users_id = ? AND status IN ('Pending', 'Approved', 'Assigned', 'Accepted')");
    $stmt->bind_param("ii", $req_id, $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
      $row = $res->fetch_assoc();
      $updReq = $conn->prepare("UPDATE blood_request SET status = 'Rejected' WHERE id = ?");
      $updReq->bind_param("i", $req_id);
      $updReq->execute();

      if (in_array($row['status'], ['Assigned', 'Accepted']) && !empty($row['assigned_donor_id'])) {
        $donor_id = $row['assigned_donor_id'];
        // Update donor assignment
        $updAssign = $conn->prepare("UPDATE donor_assignments SET status = 'Cancelled' WHERE request_id = ? AND status IN ('Assigned', 'Accepted')");
        $updAssign->bind_param("i", $req_id);
        $updAssign->execute();

        // Update donor availability
        $updDonor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
        $updDonor->bind_param("i", $donor_id);
        $updDonor->execute();

        // Get donor user details to send email
        $donorStmt = $conn->prepare("SELECT u.id as user_id, u.email, u.username FROM donor d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
        $donorStmt->bind_param("i", $donor_id);
        $donorStmt->execute();
        $donorRes = $donorStmt->get_result();
        if ($donorRes->num_rows > 0) {
          $donorUser = $donorRes->fetch_assoc();

          // In-app Notification
          $notifTitle = 'Assignment Cancelled';
          $notifMsg = 'Your assigned blood donation request has been cancelled by the requester. You are now marked as Available.';
          $notif = $conn->prepare("INSERT INTO notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'Assignment_Cancelled', ?, ?)");
          $notif->bind_param("iiss", $donorUser['user_id'], $req_id, $notifTitle, $notifMsg);
          $notif->execute();
          $notification_id = $conn->insert_id;
        }
      }
      $success_msg = "Request cancelled successfully.";
    } else {
      $error_msg = "Cannot cancel this request.";
    }
  }
}

$totalRequests = 0;
$urgentToday = 0;

try {
  $totalRequests = $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0;
  $urgentToday = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Pending','Approved')")->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {
  // silent
}

$userId = $_SESSION['user_id'] ?? 0;
$myRequests = [];
if ($isLoggedIn && $userId > 0) {
  $stmt_myreq = $conn->prepare("SELECT r.id, r.hospital, r.required_date, r.status, r.urgency,
                                         bg.blood_gp_name
                                  FROM blood_request r
                                  LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                                  WHERE r.users_id = ?
                                  ORDER BY r.required_date DESC");
  $stmt_myreq->bind_param("i", $userId);
  $stmt_myreq->execute();
  $res_myreq = $stmt_myreq->get_result();
  if ($res_myreq) {
    $myRequests = $res_myreq->fetch_all(MYSQLI_ASSOC);
  }
  $stmt_myreq->close();

  // Fetch assignment details for 'Assigned' or 'Accepted' requests
  $assignments = [];
  $assigned_reqs = array_filter($myRequests, function ($r) {
    return in_array($r['status'], ['Assigned', 'Accepted']);
  });
  if (count($assigned_reqs) > 0) {
    $req_ids = implode(',', array_column($assigned_reqs, 'id'));
    $assignRes = $conn->query("SELECT da.request_id, u.username as donor_name, d.phone, d.blood_groups, d.address FROM donor_assignments da JOIN donor d ON da.donor_id = d.id JOIN users u ON d.user_id = u.id WHERE da.request_id IN ($req_ids) AND da.status IN ('Assigned', 'Accepted')");
    if ($assignRes) {
      while ($row = $assignRes->fetch_assoc()) {
        $assignments[$row['request_id']] = $row;
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Blood Requests – BloodLife</title>
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

    .animate-fade-down {
      animation: fadeInDown 0.6s ease-out;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out;
    }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body {
      background-color: #fdf2f8 !important;
      background-image: none !important;
    }

    html:not(.dark) .bg-gray-50 {
      background-color: #fdf2f8 !important;
    }

    html:not(.dark) .bg-gray-100 {
      background-color: #ffffff !important;
    }

    html.dark body {
      background-color: #111827 !important;
      background-image: none !important;
      color: #e5e7eb;
    }

    html.dark nav.bg-white,
    html.dark nav.bg-white.shadow-lg {
      background-color: #1f2937 !important;
    }

    html.dark .bg-white {
      background-color: #1f2937 !important;
    }

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

    html.dark .bg-gray-50,
    html.dark .bg-gray-100 {
      background-color: #374151 !important;
    }

    html.dark .border-gray-200,
    html.dark .border-2.border-gray-200 {
      border-color: #4b5563 !important;
    }

    html.dark .border-t {
      border-color: #374151 !important;
    }

    html.dark .bg-red-50 {
      background-color: rgba(220, 38, 38, 0.15) !important;
    }

    html.dark tbody tr {
      border-color: #374151 !important;
    }

    html.dark tbody tr:hover {
      background-color: #374151 !important;
    }
  </style>
</head>

<body class="bg-gradient-to-b from-pink-50 to-pink-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Hero Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center animate-fade-up">

      <h1 class="text-5xl font-bold mb-4" data-i18n="blood_requests_title"> Requests Blood</h1>
      <p class="text-xl opacity-90 max-w-2xl mx-auto">Patients urgently need your help. Review open requests and respond — your one donation can save a life.</p>


    </div>
  </section>

  <!-- New Request CTA Strip -->
  <section class="bg-red-50 border-b border-red-100 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-gray-700 font-medium text-lg">Need blood urgently? Submit a request and reach hundreds of donors instantly.</p>
      <a href="requestblood.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105 whitespace-nowrap">
        + New Request Blood
      </a>
    </div>
  </section>

  <!-- My Blood Requests Section -->
  <?php if ($isLoggedIn): ?>
    <section class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
        <div class="flex items-center justify-between mb-5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📄</div>
            <h2 class="text-xl font-bold text-gray-900">My Blood Requests</h2>
          </div>
        </div>
        <div class="space-y-4">
          <?php if (!empty($success_msg)): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg mb-4">
              <p class="text-green-700 font-medium"><?= htmlspecialchars($success_msg) ?></p>
            </div>
          <?php endif; ?>
          <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg mb-4">
              <p class="text-red-700 font-medium"><?= htmlspecialchars($error_msg) ?></p>
            </div>
          <?php endif; ?>

          <?php if (count($myRequests) > 0): ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-100">
                    <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="date">Date</th>
                    <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="blood_type">Blood Type</th>
                    <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="units">Units</th>
                    <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="hospital_col">Hospital</th>
                    <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="status">Status</th>
                    <th class="text-left text-gray-500 font-semibold pb-3">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <?php foreach ($myRequests as $br): ?>
                    <tr id="req-<?= $br['id'] ?>" class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium"><?= date('M j, Y', strtotime($br['required_date'])) ?></td>
                      <td class="py-3"><span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($br['blood_gp_name'] ?? '-') ?></span></td>
                      <td class="py-3 text-gray-600">1 Unit</td>
                      <td class="py-3 text-gray-600"><?= htmlspecialchars($br['hospital'] ?? '-') ?></td>
                      <td class="py-3">
                        <?php
                        $status = htmlspecialchars($br['status'] ?? 'Pending');
                        $statusColors = [
                          'Pending'   => 'bg-yellow-100 text-yellow-700',
                          'Approved'  => 'bg-blue-100 text-blue-700',
                          'Assigned'  => 'bg-blue-100 text-blue-700',
                          'Accepted'  => 'bg-blue-100 text-blue-700',
                          'Completed' => 'bg-green-100 text-green-700',
                          'Rejected'  => 'bg-red-100 text-red-700',
                        ];
                        $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <span class="<?= $color ?> text-xs font-bold px-2 py-1 rounded-full" data-i18n="<?= strtolower($status) ?>"><?= $status ?></span>
                      </td>
                      <td class="py-3">
                        <?php if ($status === 'Completed'): ?>
                          <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                              <button type="button" disabled class="bg-gray-300 text-gray-500 cursor-not-allowed px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition" title="Already Completed">
                                <i class="fas fa-check-circle mr-1"></i> Blood Received
                              </button>
                            </div>
                          </div>
                        <?php elseif ($status === 'Assigned' || $status === 'Accepted'): ?>
                          <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                              <button type="button" onclick="viewAssignment(<?= $br['id'] ?>)" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                <i class="fas fa-eye mr-1"></i> View Assignment
                              </button>

                              <form method="POST" class="inline" onsubmit="return confirm('Are you sure you have received the blood? This will officially complete the request.');">
                                <input type="hidden" name="action" value="blood_received">
                                <input type="hidden" name="request_id" value="<?= $br['id'] ?>">
                                <button type="submit"
                                  class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition">
                                  <i class="fas fa-check-circle mr-1"></i> Blood Received
                                </button>
                              </form>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                              <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                <input type="hidden" name="action" value="cancel_request">
                                <input type="hidden" name="request_id" value="<?= $br['id'] ?>">
                                <button type="submit" class="bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                  <i class="fas fa-times mr-1"></i> Cancel Request
                                </button>
                              </form>
                            </div>
                          </div>
                        <?php elseif (in_array($status, ['Pending', 'Approved'])): ?>
                          <div class="flex items-center gap-2">
                            <a href="requestblood.php?edit=<?= $br['id'] ?>" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg text-xs font-bold transition inline-block">
                              <i class="fas fa-edit mr-1"></i> Update
                            </a>
                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                              <input type="hidden" name="action" value="cancel_request">
                              <input type="hidden" name="request_id" value="<?= $br['id'] ?>">
                              <button type="submit" class="bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                <i class="fas fa-times mr-1"></i> Cancel Request
                              </button>
                            </form>
                          </div>
                        <?php else: ?>
                          <span class="text-gray-400 text-xs font-bold">No Action</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="border-2 border-gray-100 rounded-xl p-8 text-center">
              <p class="text-gray-500">No blood requests submitted yet.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- View Assignment Modal -->
    <div id="assignmentModal" class="fixed inset-0 bg-black/60 z-[70] hidden items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 animate-fade-up">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-xl font-bold text-gray-900">Assignment Details</h3>
          <button type="button" onclick="closeAssignmentModal()" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="space-y-3" id="assignmentModalContent">
          <!-- Content injected by JS -->
        </div>
        <div class="mt-6">
          <button type="button" onclick="closeAssignmentModal()" class="w-full bg-gray-100 text-gray-800 font-bold py-2 rounded-xl hover:bg-gray-200 transition">Close</button>
        </div>
      </div>
    </div>

  <?php endif; ?>

  <!-- Footer -->
  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    const assignmentsData = <?= isset($assignments) ? json_encode($assignments) : '{}' ?>;

    function viewAssignment(requestId) {
      const data = assignmentsData[requestId];
      if (data) {
        let html = `
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xl font-bold shadow-sm">
                        ${data.donor_name.substring(0,2).toUpperCase()}
                    </div>
                    <div>
                        <p class="font-bold text-gray-900">${data.donor_name}</p>
                        <p class="text-sm font-bold text-red-500">Blood: ${data.blood_groups}</p>
                    </div>
                </div>
                <div class="text-sm text-gray-700 space-y-2 border-t pt-3">
                    <p><i class="fas fa-phone mr-2 text-gray-400"></i> ${data.phone}</p>
                    <p><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i> ${data.address}</p>
                </div>
            `;
        document.getElementById('assignmentModalContent').innerHTML = html;
        document.getElementById('assignmentModal').classList.remove('hidden');
        document.getElementById('assignmentModal').classList.add('flex');
      } else {
        alert("No assignment details found.");
      }
    }

    function closeAssignmentModal() {
      document.getElementById('assignmentModal').classList.add('hidden');
      document.getElementById('assignmentModal').classList.remove('flex');
    }

    function toggleUserDropdown() {
      document.getElementById('userDropdown').classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
      const menu = document.getElementById('userMenu');
      const dropdown = document.getElementById('userDropdown');
      if (menu && dropdown && !menu.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
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

</body>

</html>