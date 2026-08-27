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

$greeting = '';

$donorData = [];
$donationCount = 0;
$donations = [];
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
    $stmt2 = $conn->prepare("SELECT da.completed_at as donation_date, da.status, bg.blood_gp_name, br.hospital, br.units
                                 FROM donor_assignments da
                                 JOIN blood_request br ON br.id = da.request_id
                                 LEFT JOIN blood_groups bg ON bg.id = br.blood_groups_id
                                 WHERE da.status = 'Completed' AND da.donor_id = ?
                                 ORDER BY da.completed_at DESC");
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

    html.dark nav.bg-slate-100,
    html.dark nav.bg-slate-100.shadow-lg {
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
                      <td class="py-3 text-gray-600">1 Unit</td>
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
          My blood group
          <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-5 py-1.5 rounded-full text-lg mb-4"><?= htmlspecialchars($donorData['blood_group_name'] ?? 'N/A') ?></span>

          <a href="profile.php" class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition block text-sm" data-i18n="edit_profile">Edit Profile</a>
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