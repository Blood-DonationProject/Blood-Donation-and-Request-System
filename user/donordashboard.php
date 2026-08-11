<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$userId = $_SESSION['user_id'] ?? 0;

// Role-based access control
$userRole = $_SESSION['user_role'] ?? '';
if (!$isLoggedIn) {
  header('Location: login.php');
  exit;
}

// Redirect Admin to admin dashboard
if ($userRole === 'Admin') {
  header('Location: ../admin/dashboard.php');
  exit;
}

$greeting = 'Good morning';

$donorData = [];
$donationCount = 0;
$donations = [];
$urgentRequests = [];
if ($isLoggedIn) {
  // Donor info
  $stmt = $conn->prepare("SELECT d.id AS donor_id, d.user_id, u.username, u.email, d.address, d.blood_groups AS blood_group_name
                            FROM donor d
                            JOIN users u ON u.id = d.user_id
                            WHERE d.user_id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $donorData = $result->fetch_assoc();
  $stmt->close();

  // Donation count & history
  if (!empty($donorData['donor_id'])) {
    $did = $donorData['donor_id'];
    $stmt2 = $conn->prepare("SELECT dh.*, bg.blood_gp_name
                                 FROM donation_history dh
                                 LEFT JOIN blood_groups bg ON bg.id = dh.blood_groups_id
                                 WHERE dh.donor_id = ?
                                 ORDER BY dh.donation_date DESC");
    $stmt2->bind_param("i", $did);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $donations = $result2->fetch_all(MYSQLI_ASSOC);
    $donationCount = count($donations);
    $stmt2->close();
  }

  // User blood request count
  $myRequestCount = 0;
  $stmt_req = $conn->prepare("SELECT COUNT(*) AS req_count FROM blood_request WHERE users_id = ?");
  $stmt_req->bind_param("i", $userId);
  $stmt_req->execute();
  $res_req = $stmt_req->get_result();
  if ($res_req) {
    $myRequestCount = $res_req->fetch_assoc()['req_count'] ?? 0;
  }
  $stmt_req->close();

  // Notification count (currently hardcoded or derived if table exists)
  $notificationCount = 0;
  // For now, setting it to 0 as we don't have a specific notifications table defined.

    // Handle Accept/Decline Assignment
    if (isset($_GET['action']) && isset($_GET['req_id'])) {
        $r_id = (int)$_GET['req_id'];
        $d_id = $donorData['donor_id'] ?? 0;
        
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
        header("Location: donordashboard.php");
        exit;
    }

    // My Blood Requests
    $myRequests = [];
    $stmt_myreq = $conn->prepare("SELECT r.id, r.units, r.hospital, r.required_date, r.status, r.urgency,
                                         bg.blood_gp_name
                                  FROM blood_request r
                                  LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                                  WHERE r.users_id = ?
                                  ORDER BY r.required_date DESC LIMIT 5");
    $stmt_myreq->bind_param("i", $userId);
    $stmt_myreq->execute();
    $res_myreq = $stmt_myreq->get_result();
    if ($res_myreq) {
        $myRequests = $res_myreq->fetch_all(MYSQLI_ASSOC);
    }
    $stmt_myreq->close();

    // Assigned Requests
    $assignedRequests = [];
    if (!empty($donorData['donor_id'])) {
        $did = $donorData['donor_id'];
        $stmt_assigned = $conn->prepare("SELECT r.id, r.units, r.hospital, r.required_date, r.status, r.urgency,
                                                bg.blood_gp_name
                                         FROM blood_request r
                                         LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                                         WHERE r.assigned_donor_id = ?
                                         ORDER BY r.required_date DESC");
        $stmt_assigned->bind_param("i", $did);
        $stmt_assigned->execute();
        $res_assigned = $stmt_assigned->get_result();
        if ($res_assigned) {
            $assignedRequests = $res_assigned->fetch_all(MYSQLI_ASSOC);
        }
        $stmt_assigned->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard – BloodLife</title>
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
      background-color: #ffffff !important;
      background-image: none !important;
    }

    html:not(.dark) .bg-gray-50 {
      background-color: #ffffff !important;
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

    html.dark .bg-green-50 {
      background-color: rgba(34, 197, 94, 0.15) !important;
    }

    html.dark tbody tr {
      border-color: #374151 !important;
    }

    html.dark tbody tr:hover {
      background-color: #374151 !important;
    }
  </style>
</head>

<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Welcome Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 animate-fade-up">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <p class="text-red-200 text-sm font-semibold mb-1"><?= date('l, j F Y') ?></p>
          <h1 class="text-4xl font-bold mb-1"><?= $greeting ?>, <?= $username ?>! 👋</h1>
          <p class="text-lg opacity-90"><span data-i18n="you_have">You have</span> <span class="font-bold text-yellow-300">2 urgent requests</span> <span data-i18n="urgent_requests_matching">matching your blood type nearby.</span></p>
        </div>
        <a href="requestblood.php" class="bg-white text-red-600 px-6 py-3 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105 whitespace-nowrap">
          <span data-i18n="submit_request_btn">+ Submit Request</span>
        </a>
      </div>
    </div>
  </section>

  <!-- Eligibility Banner -->
  <section class="bg-green-50 border-b border-green-200 py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <span class="text-2xl">✅</span>
        <p class="text-green-800 font-semibold" data-i18n="eligible_donate_today">You are eligible to donate blood today! It's been 60+ days since your last donation.</p>
      </div>
      <a href="bloodrequest.php" class="bg-green-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-green-700 transition whitespace-nowrap text-sm" data-i18n="find_a_request">Find a Request</a>
    </div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 animate-fade-up">
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">🩸</div>
        <h3 class="text-3xl font-bold text-red-600"><?= htmlspecialchars($donorData['blood_group_name'] ?? 'N/A') ?></h3>
        <p class="text-gray-500 text-sm mt-1">My Blood Group</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">❤️</div>
        <h3 class="text-3xl font-bold text-red-600"><?= $donationCount ?></h3>
        <p class="text-gray-500 text-sm mt-1">Total Donations</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">📄</div>
        <h3 class="text-3xl font-bold text-red-600"><?= $myRequestCount ?></h3>
        <p class="text-gray-500 text-sm mt-1">My Blood Requests</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">🔔</div>
        <h3 class="text-3xl font-bold text-red-600"><?= $notificationCount ?></h3>
        <p class="text-gray-500 text-sm mt-1">Unread Notifications</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8">

      <!-- Left Column -->
      <div class="lg:col-span-2 space-y-8">

        <!-- My Blood Requests -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📄</div>
              <h2 class="text-xl font-bold text-gray-900">My Blood Requests</h2>
            </div>
            <a href="bloodrequest.php" class="text-red-600 text-sm font-semibold hover:underline">View all →</a>
          </div>
          <div class="space-y-4">
            <?php if (count($myRequests) > 0): ?>
              <?php foreach ($myRequests as $mr): ?>
                <div class="border-2 border-red-100 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:border-red-400 transition">
                  <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-red-100 to-red-200 flex items-center justify-center font-bold text-red-700 text-xl"><?= htmlspecialchars($mr['blood_gp_name'] ?? 'N/A') ?></div>
                  <div class="flex-1">
                    <div class="flex flex-wrap gap-2 mb-1">
                      <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">🔴 <?= htmlspecialchars($mr['status']) ?></span>
                      <span class="bg-yellow-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">⚠️ <?= htmlspecialchars($mr['urgency'] ?? 'Normal') ?></span>
                      <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?= htmlspecialchars($mr['hospital']) ?></span>
                    </div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($mr['units']) ?> unit(s) needed</p>
                    <p class="text-xs text-gray-400 mt-0.5">Required by <?= date('M j, Y', strtotime($mr['required_date'])) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="border-2 border-gray-100 rounded-xl p-8 text-center">
                <p class="text-gray-500">No blood requests submitted yet.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- My Donation / Assignment -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🤝</div>
              <h2 class="text-xl font-bold text-gray-900">My Donation / Assignment</h2>
            </div>
          </div>
          <div class="space-y-4">
            <?php if (count($assignedRequests) > 0): ?>
              <?php foreach ($assignedRequests as $ar): ?>
                <div class="border-2 border-blue-100 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:border-blue-400 transition">
                  <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center font-bold text-blue-700 text-xl"><?= htmlspecialchars($ar['blood_gp_name'] ?? 'N/A') ?></div>
                  <div class="flex-1">
                    <div class="flex flex-wrap gap-2 mb-1">
                      <span class="bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">🔵 <?= htmlspecialchars($ar['status']) ?></span>
                      <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?= htmlspecialchars($ar['hospital']) ?></span>
                    </div>
                    <p class="font-semibold text-gray-800">You have been assigned to donate.</p>
                    <p class="text-xs text-gray-400 mt-0.5">Required by <?= date('M j, Y', strtotime($ar['required_date'])) ?></p>
                  </div>
                  <?php if ($ar['status'] !== 'Completed' && $ar['status'] !== 'Accepted' && $ar['status'] !== 'Rejected'): ?>
                  <div class="flex flex-col gap-2">
                      <button type="button" onclick="openAcceptModal(<?= $ar['id'] ?>)" class="bg-green-600 text-white px-5 py-2 rounded-xl font-bold hover:shadow-lg transition text-sm text-center">Accept</button>
                      <button type="button" onclick="openDeclineModal(<?= $ar['id'] ?>)" class="bg-red-600 text-white px-5 py-2 rounded-xl font-bold hover:shadow-lg transition text-sm text-center">Decline</button>
                  </div>
                  <?php else: ?>
                  <span class="text-sm font-bold text-green-600">Action taken</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="border-2 border-gray-100 rounded-xl p-8 text-center">
                <p class="text-gray-500">No assignments currently.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Donation History -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📋</div>
              <h2 class="text-xl font-bold text-gray-900" data-i18n="donation_history">Donation History</h2>
            </div>
            <a href="profile.php" class="text-red-600 text-sm font-semibold hover:underline" data-i18n="view_all">View all →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100">
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="date">Date</th>
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="hospital_col">Hospital</th>
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="units">Units</th>
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="status">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <?php if (count($donations) > 0): ?>
                  <?php foreach ($donations as $d): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium"><?= date('M j, Y', strtotime($d['donation_date'])) ?></td>
                      <td class="py-3 text-gray-600"><?= htmlspecialchars($d['blood_gp_name'] ?? '-') ?></td>
                      <td class="py-3 text-gray-600"><?= htmlspecialchars($d['units'] ?? '1') ?> unit(s)</td>
                      <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ <?= htmlspecialchars($d['status'] ?? 'Completed') ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="py-6 text-center text-gray-400" data-i18n="no_donations_yet">No donations yet.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right Column -->
      <div class="space-y-6">

        <!-- Profile Card -->
        <div class="bg-white rounded-2xl shadow p-6 text-center animate-fade-up">
          <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center text-4xl mx-auto mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-xl"><?= htmlspecialchars($donorData['username'] ?? $username ?: 'Donor') ?></h3>
          <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($donorData['address'] ?? 'Location not set') ?></p>
          <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-5 py-1.5 rounded-full text-lg mb-4"><?= htmlspecialchars($donorData['blood_group_name'] ?? 'N/A') ?></span>
          <div class="bg-green-50 border border-green-200 rounded-xl py-2 px-3 mb-4">
            <p class="text-green-700 text-sm font-semibold">✅ <?= $donationCount > 0 ? '<span data-i18n="active_donor">Active Donor</span>' : '<span data-i18n="ready_to_donate">Ready to Donate</span>' ?></p>
          </div>
          <a href="profile.php" class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition block text-sm" data-i18n="edit_profile">Edit Profile</a>
        </div>


        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">⚡</div>
            <h2 class="text-lg font-bold text-gray-900" data-i18n="quick_actions">Quick Actions</h2>
          </div>
          <div class="space-y-3">
            <a href="bloodrequest.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">🚨</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="view_urgent_requests">View Urgent Requests</span>
            </a>
            <a href="requestblood.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">📋</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="submit_blood_request_link">Submit Blood Request</span>
            </a>
            <a href="hospital.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">🏥</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="find_nearby_hospitals">Find Nearby Hospitals</span>
            </a>
            <a href="profile.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">👤</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="update_my_profile">Update My Profile</span>
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script>
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

    function bloodlifeLogout() {
      if (!confirm('Are you sure you want to logout?')) return;
      localStorage.removeItem('bloodlife_logged_in');
      localStorage.removeItem('bloodlife_user_name');
      window.location.href = 'logout.php';
    }

    function openAcceptModal(id) {
      document.getElementById('confirmAcceptBtn').href = 'donordashboard.php?action=accept&req_id=' + id;
      document.getElementById('acceptConfirmModal').classList.remove('hidden');
      document.getElementById('acceptConfirmModal').classList.add('flex');
    }
    function closeAcceptModal() {
      document.getElementById('acceptConfirmModal').classList.remove('flex');
      document.getElementById('acceptConfirmModal').classList.add('hidden');
    }
    function openDeclineModal(id) {
      document.getElementById('confirmDeclineBtn').href = 'donordashboard.php?action=decline&req_id=' + id;
      document.getElementById('declineConfirmModal').classList.remove('hidden');
      document.getElementById('declineConfirmModal').classList.add('flex');
    }
    function closeDeclineModal() {
      document.getElementById('declineConfirmModal').classList.remove('flex');
      document.getElementById('declineConfirmModal').classList.add('hidden');
    }
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