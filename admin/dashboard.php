<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$donations = [];
try {
    $result = $conn->query("
        SELECT r.id, r.users_id, r.hospital, bg.blood_gp_name AS blood_group, r.units AS units_required, r.status, r.required_date
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        ORDER BY r.id DESC
    ");
    if ($result && $result->num_rows > 0) {
        $donations = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // silently fail
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Blood Donation System</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/translations.js"></script>
    <script src="../assets/js/i18n.js"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes countUp {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .animate-slide-in { animation: slideIn 0.6s ease-out; }
        .animate-count-up { animation: countUp 0.8s ease-out; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .modal-overlay { display: none; }
        .modal-overlay.active { display: flex; }
    </style>
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        html.dark .w-64.bg-white { background-color: #1f2937 !important; }
        html.dark nav.bg-white, html.dark nav.bg-white.shadow-md { background-color: #1f2937 !important; }
        html.dark .bg-white { background-color: #1f2937 !important; }
        html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
        html.dark .text-gray-700 { color: #d1d5db !important; }
        html.dark .text-gray-600 { color: #9ca3af !important; }
        html.dark .text-gray-500 { color: #9ca3af !important; }
        html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        html.dark label { color: #d1d5db !important; }
        html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #374151 !important; }
        html.dark thead.bg-gray-50 { background-color: #111827 !important; }
        html.dark .border-gray-200, html.dark .border-2.border-gray-200, html.dark .border { border-color: #4b5563 !important; }
        html.dark .border-t { border-color: #374151 !important; }
        html.dark .bg-red-50 { background-color: rgba(220,38,38,0.15) !important; }
        html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
        html.dark .bg-yellow-50 { background-color: rgba(234,179,8,0.15) !important; }
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
        html.dark .stat-card:hover { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-50 to-red-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900">

    <!-- Sidebar Navigation -->
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <span class="text-3xl">🩸</span>
                    <div>
                        <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                        <p class="text-xs text-gray-500">Dashboard</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                    <span>📊</span>
                    <span data-i18n="overview">Overview</span>
                </a>
                <a href="logindata.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700  hover:bg-gray-100 rounded-lg transition">
                    <span>📊</span>
                    <span data-i18n="users">Users</span>
                </a>
                <a href="donors.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700  hover:bg-gray-100 rounded-lg transition">
                    <span>👥</span>
                    <span data-i18n="donors">Donors</span>
                </a>
                <a href="requesters.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>📂</span>
                    <span data-i18n="requesters">Requesters</span>
                </a>   
                <a href="donation_histories.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>⚡</span>
                    <span data-i18n="donation_histories">Donation Histories</span>
                </a>
                
                <a href="requests.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>📋</span>
                    <span data-i18n="blood_requests">Blood Requests</span>
                </a>

            </nav>

            <div class="p-4 border-t border-gray-200">
                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Navigation -->
            <nav class="bg-white shadow-md sticky top-0 z-40">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="text-2xl font-bold text-gray-800">
                       Dashboard 👋
                    </div>
                    <div class="flex items-center space-x-6">
<button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
                        <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
                            <option value="en">EN</option>
                            <option value="my">MY</option>
                        </select>
                        <button onclick="alert('Notifications feature coming soon')" class="relative">
                            <span class="text-2xl">🔔</span>
                            <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                        </button>
                        <div class="relative" id="adminMenu">
                            <div class="flex items-center space-x-3 cursor-pointer" onclick="toggleAdminDropdown()">
                                <div class="text-right">
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                    <p class="text-xs text-gray-500" data-i18n="administrator">Administrator</p>
                                </div>
                                <div class="w-10 h-10 bg-gradient-to-br from-red-400 to-red-600 text-white rounded-full flex items-center justify-center font-bold">
                                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                </div>
                            </div>
                            <div id="adminDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-600 text-white rounded-full flex items-center justify-center font-bold text-lg">
                                            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <div class="p-4 md:p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

                    <!-- Total Donors Card -->
                    <div class="stat-card bg-gradient-to-br from-red-600 to-red-800 text-white p-8 rounded-2xl shadow-lg animate-slide-in">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-red-100 text-sm font-medium" data-i18n="total_donors">Total Donors</p>
                                <h2 class="text-5xl font-bold mt-2 animate-count-up">250</h2>
                            </div>
                            <span class="text-4xl opacity-30">👥</span>
                        </div>
                        <div class="flex items-center text-red-100 text-sm">
                            <span class="text-green-300">↑</span>
                            <span data-i18n="increase_this_month">12% increase this month</span>
                        </div>
                    </div>

                    <!-- Blood Requests Card -->
                    <div class="stat-card bg-white shadow-lg p-8 rounded-2xl animate-slide-in" style="animation-delay: 0.1s;">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-600 text-sm font-medium" data-i18n="blood_requests_card">Blood Requests</p>
                                <h2 class="text-5xl font-bold mt-2 text-red-700 animate-count-up">120</h2>
                            </div>
                            <span class="text-4xl">🩸</span>
                        </div>
                        <div class="flex items-center text-gray-500 text-sm">
                            <span class="text-orange-500">→</span>
                            <span data-i18n="pending_approvals">5 pending approvals</span>
                        </div>
                    </div>

                    <!-- Hospitals Card -->
                    <div class="stat-card bg-white shadow-lg p-8 rounded-2xl animate-slide-in" style="animation-delay: 0.2s;">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-600 text-sm font-medium" data-i18n="partner_hospitals_card">Partner Hospitals</p>
                                <h2 class="text-5xl font-bold mt-2 text-blue-700 animate-count-up">35</h2>
                            </div>
                            <span class="text-4xl">🏥</span>
                        </div>
                        <div class="flex items-center text-gray-500 text-sm">
                            <span class="text-green-500">✓</span>
                            <span data-i18n="all_operational">All operational</span>
                        </div>
                    </div>

                    <!-- Certificates Card -->
                    <div class="stat-card bg-white shadow-lg p-8 rounded-2xl animate-slide-in" style="animation-delay: 0.3s;">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-600 text-sm font-medium" data-i18n="certificates_issued">Certificates Issued</p>
                                <h2 class="text-5xl font-bold mt-2 text-purple-700 animate-count-up">180</h2>
                            </div>
                            <span class="text-4xl">🎓</span>
                        </div>
                        <div class="flex items-center text-gray-500 text-sm">
                            <span class="text-blue-500">✓</span>
                            <span data-i18n="life_changing_impact">Life-changing impact</span>
                        </div>
                    </div>

                </div>

                <!-- Charts and Activities Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Recent Activities -->
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-6" data-i18n="recent_donation_activity">Recent Donation Activity</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-xl border-l-4 border-green-500">
                                <div>
                                    <p class="font-semibold text-gray-800" data-i18n="blood_type_collected">Blood Type A+ Collected</p>
                                    <p class="text-sm text-gray-600" data-i18n="from_donor">From John Donor · 2 hours ago</p>
                                </div>
                                <span class="text-3xl">✓</span>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl border-l-4 border-blue-500">
                                <div>
                                    <p class="font-semibold text-gray-800" data-i18n="blood_type_used">Blood Type B- Used</p>
                                    <p class="text-sm text-gray-600" data-i18n="emergency_transfusion">Emergency transfusion · General Hospital</p>
                                </div>
                                <span class="text-3xl">→</span>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl border-l-4 border-purple-500">
                                <div>
                                    <p class="font-semibold text-gray-800" data-i18n="certificate_issued_activity">Certificate Issued</p>
                                    <p class="text-sm text-gray-600" data-i18n="donation_milestone">Donation milestone reached · 50 donations</p>
                                </div>
                                <span class="text-3xl">🎓</span>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl border-l-4 border-orange-500">
                                <div>
                                    <p class="font-semibold text-gray-800" data-i18n="request_accepted">Request Accepted</p>
                                    <p class="text-sm text-gray-600" data-i18n="request_id_info">Request ID #5234 · Type O+ · City Hospital</p>
                                </div>
                                <span class="text-3xl">👍</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="space-y-4">
                        <!-- Blood Availability -->
                        <div class="bg-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4" data-i18n="blood_availability_admin">Blood Availability</h3>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">A+</span>
                                        <span class="font-semibold text-red-600">45 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: 75%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">B+</span>
                                        <span class="font-semibold text-blue-600">38 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: 63%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">O+</span>
                                        <span class="font-semibold text-purple-600">52 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-purple-500 h-2 rounded-full" style="width: 87%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">AB+</span>
                                        <span class="font-semibold text-pink-600">25 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-pink-500 h-2 rounded-full" style="width: 42%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="bg-gradient-to-br from-red-600 to-red-800 text-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-lg font-bold mb-4" data-i18n="quick_actions">Quick Actions</h3>
                            <div class="space-y-3">
                                <button onclick="openDonationModal()" class="w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-2 rounded-lg font-semibold transition" data-i18n="schedule_donation">
                                    Schedule Donation
                                </button>
                                <a href="requests.php" class="block w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-2 rounded-lg font-semibold transition text-center" data-i18n="view_requests_admin">
                                    View Requests
                                </a>
                                <a href="donors.php" class="block w-full bg-white text-red-700 py-2 rounded-lg font-semibold hover:bg-gray-100 transition text-center" data-i18n="manage_donors">
                                    Manage Donors
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Donation History Modal -->
    <div id="donationModal" class="modal-overlay fixed inset-0 z-50 bg-black bg-opacity-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">📅 <span data-i18n="donation_history_modal">Donation History</span></h3>
                    <p class="text-sm text-gray-500" data-i18n="donation_history_desc">Complete record of all blood donation requests.</p>
                </div>
                <button onclick="closeDonationModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(85vh-80px)]">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-slate-600">
                            <th class="p-3" data-i18n="patient_col">Patient</th>
                            <th class="p-3" data-i18n="blood_group_col">Blood Group</th>
                            <th class="p-3" data-i18n="hospital_col_head">Hospital</th>
                            <th class="p-3" data-i18n="units_col">Units</th>
                            <th class="p-3" data-i18n="status_col">Status</th>
                            <th class="p-3" data-i18n="date_col">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donations) > 0): ?>
                            <?php foreach ($donations as $d): ?>
                            <tr class="border-t border-slate-200 hover:bg-gray-50">
                                <td class="p-3 font-medium"><?= htmlspecialchars($d['hospital'] ?: '-') ?></td>
                                <td class="p-3"><?= htmlspecialchars($d['blood_group'] ?: '-') ?></td>
                                <td class="p-3 text-gray-600"><?= htmlspecialchars($d['hospital'] ?: '-') ?></td>
                                <td class="p-3"><?= (int)$d['units_required'] ?></td>
                                <td class="p-3">
                                    <?php
                                        $s = $d['status'];
                                        $badge = match ($s) {
                                            'Pending'   => 'bg-yellow-100 text-yellow-700',
                                            'Approved'  => 'bg-blue-100 text-blue-700',
                                            'Completed' => 'bg-green-100 text-green-700',
                                            'Rejected'  => 'bg-red-100 text-red-700',
                                            default     => 'bg-gray-100 text-gray-700',
                                        };
                                    ?>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>">
                                        <?= htmlspecialchars($s ?: '-') ?>
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500" data-i18n="no_donation_records">No donation records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openDonationModal() { document.getElementById('donationModal').classList.add('active'); }
        function closeDonationModal() { document.getElementById('donationModal').classList.remove('active'); }
        document.getElementById('donationModal').addEventListener('click', function(e) {
            if (e.target === this) closeDonationModal();
        });

        // Animate count-up numbers
        function animateCounter(element, target, duration = 1000) {
            let current = 0;
            const increment = target / (duration / 30);
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 30);
        }

        // Start counter animations when page loads
        document.querySelectorAll('.animate-count-up').forEach(counter => {
            const target = parseInt(counter.textContent);
            animateCounter(counter, target);
        });

        // Sidebar active state styling
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarItems = document.querySelectorAll('nav a');
            sidebarItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    sidebarItems.forEach(i => i.classList.remove('bg-red-50', 'text-red-700'));
                    this.classList.add('bg-red-50', 'text-red-700');
                });
            });
        });
    </script>

    <script>
        function toggleAdminDropdown() {
            document.getElementById('adminDropdown').classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('adminMenu');
            const dropdown = document.getElementById('adminDropdown');
            if (menu && dropdown && !menu.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    <script>
    (function() {
      var KEY = 'bloodlife-theme';
      function getTheme() { return localStorage.getItem(KEY) || 'light'; }
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