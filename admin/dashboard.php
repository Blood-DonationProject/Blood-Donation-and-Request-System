<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Stats
$stats = [
    'total_users'          => 0,
    'total_donors'         => 0,
    'total_requests'       => 0,
    'pending'              => 0,
    'approved'             => 0,
    'completed'            => 0,
    'completed_donations'  => 0,
    'certificates_issued'  => 0,
    'today_donations'      => 0,
];

try {
    $stats['total_users']          = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
    $stats['total_donors']         = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0;
    $stats['total_requests']       = $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0;
    $stats['pending']              = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
    $stats['approved']             = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Approved'")->fetch_assoc()['c'] ?? 0;
    $stats['completed']            = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
    $stats['completed_donations']  = $conn->query("SELECT COUNT(*) AS c FROM donation_history WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
    $stats['certificates_issued']  = $stats['completed_donations'];
} catch (Exception $e) {}

// Blood group donor counts for pie chart
$blood_group_stats = [];
try {
    $bg_result = $conn->query("
        SELECT bg.blood_gp_name AS blood_group, COUNT(d.id) AS donor_count
        FROM blood_groups bg
        LEFT JOIN donor d ON bg.blood_gp_name = d.blood_groups
        GROUP BY bg.blood_gp_name
        ORDER BY donor_count DESC
    ");
    if ($bg_result && $bg_result->num_rows > 0) {
        $blood_group_stats = $bg_result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}

// Monthly donation stats for bar chart (last 12 months)
$monthly_donations = [];
try {
    $md_result = $conn->query("
        SELECT DATE_FORMAT(donation_date, '%Y-%m') AS month_key,
               DATE_FORMAT(donation_date, '%b %Y') AS month_label,
               COUNT(*) AS donation_count,
               SUM(units) AS total_units
        FROM donation_history
        WHERE status = 'Completed'
          AND donation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month_key, month_label
        ORDER BY month_key ASC
    ");
    if ($md_result && $md_result->num_rows > 0) {
        $monthly_donations = $md_result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}



// Pending blood requests for notification bar & action cards
$pending_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.requester_name, bg.blood_gp_name AS blood_group, r.units, r.hospital, r.required_date, r.status
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        WHERE r.status = 'Pending'
        ORDER BY r.required_date ASC
        LIMIT 10
    ");
    if ($result && $result->num_rows > 0) {
        $pending_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}







$admin_name = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$current_date = date('l, F j, Y');
$current_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BloodLife Admin</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .animate-slide-in { animation: slideIn 0.6s ease-out; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        .pulse-dot { animation: pulse-dot 2s infinite; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -10px rgba(220, 38, 38, 0.2); }
        .action-card { transition: all 0.3s ease; }
        .action-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -8px rgba(220, 38, 38, 0.25); }
        .btn-approve { transition: all 0.2s ease; }
        .btn-approve:hover { transform: scale(1.05); }
        .btn-reject { transition: all 0.2s ease; }
        .btn-reject:hover { transform: scale(1.05); }
        .btn-assign { transition: all 0.2s ease; }
        .btn-assign:hover { transform: scale(1.05); }
        /* Assign Modal */
        .assign-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .assign-modal-overlay.active { display: flex; }
        .assign-modal { background: white; border-radius: 1rem; width: 90%; max-width: 520px; max-height: 85vh; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.3); animation: fadeIn 0.3s ease; }
        .assign-modal-body { max-height: 60vh; overflow-y: auto; padding: 1rem; }
        .assign-modal-donor { padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s ease; margin-bottom: 0.5rem; }
        .assign-modal-donor:hover { border-color: #3b82f6; background: #eff6ff; }
        .assign-modal-donor.selected { border-color: #16a34a; background: #f0fdf4; }
        .assign-modal-donor.best-match { border-color: #22c55e; background: #f0fdf4; }
    </style>
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #f9fafb !important; }
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
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
        html.dark .stat-card:hover { box-shadow: 0 20px 40px -10px rgba(220, 38, 38, 0.35); }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">

    <!-- Sidebar Navigation -->
    <div class="flex">
        <!-- Sidebar -->
         <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 min-h-screen">
            <!-- Top Navigation Bar -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <!-- Notifications Dropdown Panel -->
            <div id="notificationsPanel" class="hidden mx-6 mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 animate-fade-in">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">
                        <i class="fas fa-bell text-red-500 mr-2"></i>Pending Requests
                    </h3>
                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full"><?= $stats['pending'] ?> pending</span>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <?php if (count($pending_requests) > 0): ?>
                        <?php foreach ($pending_requests as $pr): ?>
                        <div class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 bg-red-100 text-red-600 rounded-lg flex items-center justify-center font-bold text-xs">
                                    <?= strtoupper(substr($pr['requester_name'] ?? 'U', 0, 2)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($pr['blood_group']) ?> - <?= (int)$pr['units'] ?> units</p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($pr['hospital']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <a href="blood_requests_crud.php?approve=<?= $pr['id'] ?>" class="btn-approve bg-green-500 hover:bg-green-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg" onclick="return confirm('Approve this request?')">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </a>
                                <button type="button" onclick="window.location.href='blood_requests_crud.php?auto_assign='+<?= $pr['id'] ?>);" class="btn-assign bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-user-plus mr-1"></i>Assign
                                </button>
                                <a href="blood_requests_crud.php?reject=<?= $pr['id'] ?>" class="btn-reject bg-white border border-red-200 text-red-600 hover:bg-red-50 text-xs font-bold px-3 py-1.5 rounded-lg" onclick="return confirm('Reject this request?')">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-400">
                            <i class="fas fa-check-circle text-3xl text-green-400 mb-3"></i>
                            <p class="text-sm">No pending requests</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($stats['pending'] > 0): ?>
                <div class="p-3 border-t border-gray-100">
                    <a href="requests.php" class="block w-full text-center bg-red-50 text-red-700 py-2 rounded-xl font-semibold hover:bg-red-100 transition text-sm">View All Requests</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Main Content Area -->
            <div class="p-6 md:p-8">

                <!-- Live Clock Script -->
                <script>
                (function() {
                    function updateClock() {
                        var now = new Date();
                        var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        var dateStr = now.toLocaleDateString('en-US', options);
                        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        var dateEl = document.getElementById('welcomeDate');
                        var timeEl = document.getElementById('welcomeTime');
                        if (dateEl) dateEl.textContent = dateStr;
                        if (timeEl) timeEl.textContent = timeStr;
                    }
                    updateClock();
                    setInterval(updateClock, 1000);
                })();
                </script>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">

                    <!-- Total Users -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-100 shadow-sm animate-slide-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-pink-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-users text-red-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_users'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Total Users</p>
                    </div>

                    <!-- Total Donors -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-100 shadow-sm animate-slide-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-hand-holding-heart text-red-600 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">Active</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_donors'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Total Donors</p>
                    </div>

                    <!-- Total Blood Requests -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-100 shadow-sm animate-slide-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-pink-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-file-medical text-red-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-full"><?= $stats['pending'] ?> new</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_requests'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Total Blood Requests</p>
                    </div>

                    <!-- Pending Requests -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-100 shadow-sm animate-slide-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-50 px-2.5 py-1 rounded-full">Awaiting</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['pending'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Pending Requests</p>
                    </div>

                    <!-- Completed Donations -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-100 shadow-sm animate-slide-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">Done</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['completed_donations'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Completed Donations</p>
                    </div>

                    <!-- Certificates Issued -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-100 shadow-sm animate-slide-in" style="animation-delay: 0.5s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-certificate text-red-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">Issued</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['certificates_issued'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Certificates Issued</p>
                    </div>

                </div>

                <!-- Blood Group Statistics Chart -->
                <div class="mb-8">
                    <div class="bg-white rounded-2xl border border-pink-100 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    <i class="fas fa-chart-pie text-red-500 mr-2"></i>Blood Group Statistics
                                </h3>
                                <p class="text-sm text-gray-400 mt-1">Donor distribution across blood groups</p>
                            </div>
                        </div>
                        <div class="flex flex-col lg:flex-row items-center gap-8">
                            <!-- Pie Chart -->
                            <div class="w-full lg:w-1/2 flex justify-center">
                                <div class="relative" style="max-width: 340px; width: 100%;">
                                    <canvas id="bloodGroupPieChart"></canvas>
                                </div>
                            </div>
                            <!-- Legend / Summary -->
                            <div class="w-full lg:w-1/2">
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach ($blood_group_stats as $bg): ?>
                                    <div class="flex items-center space-x-3 p-3 rounded-xl bg-pink-50 border border-pink-100">
                                        <div class="w-10 h-10 bg-red-600 text-white rounded-lg flex items-center justify-center font-bold text-xs">
                                            <?= htmlspecialchars($bg['blood_group']) ?>
                                        </div>
                                        <div>
                                            <p class="text-lg font-bold text-gray-900"><?= (int)$bg['donor_count'] ?></p>
                                            <p class="text-xs text-gray-400">Donors</p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 p-3 bg-red-50 rounded-xl border border-red-100 flex items-center">
                                    <i class="fas fa-info-circle text-red-500 mr-2"></i>
                                    <p class="text-sm text-red-700 font-medium">Total registered donors: <span class="font-bold"><?= $stats['total_donors'] ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Donation Statistics Chart -->
                <div class="mb-8">
                    <div class="bg-white rounded-2xl border border-pink-100 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    <i class="fas fa-chart-bar text-red-500 mr-2"></i>Monthly Donation Statistics
                                </h3>
                                <p class="text-sm text-gray-400 mt-1">Completed donations over the last 12 months</p>
                            </div>
                        </div>
                        <div class="relative" style="height: 320px;">
                            <canvas id="monthlyDonationChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Quick Actions + Blood Availability -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-bold text-gray-900 mb-5">
                            <i class="fas fa-bolt text-red-500 mr-2"></i>Quick Actions
                        </h3>
                        <div class="space-y-3">
                            <a href="requests.php" class="flex items-center space-x-3 p-3 rounded-xl bg-red-50 hover:bg-red-100 transition group">
                                <div class="w-10 h-10 bg-red-600 text-white rounded-lg flex items-center justify-center group-hover:scale-105 transition">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">New Blood Request</p>
                                    <p class="text-xs text-gray-400">Create a request</p>
                                </div>
                            </a>
                            <a href="donor_crud.php" class="flex items-center space-x-3 p-3 rounded-xl bg-gray-50 hover:bg-red-50 transition group">
                                <div class="w-10 h-10 bg-gray-200 text-gray-600 rounded-lg flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">Manage Donors</p>
                                    <p class="text-xs text-gray-400">View donor list</p>
                                </div>
                            </a>
                            <a href="donation_history_crud.php" class="flex items-center space-x-3 p-3 rounded-xl bg-gray-50 hover:bg-red-50 transition group">
                                <div class="w-10 h-10 bg-gray-200 text-gray-600 rounded-lg flex items-center justify-center group-hover:bg-red-600 group-hover:text-white transition">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">Donation History</p>
                                    <p class="text-xs text-gray-400">View past donations</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Blood Availability -->
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="font-bold text-gray-900 mb-5">
                            <i class="fas fa-tint text-red-500 mr-2"></i>Blood Availability Overview
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 rounded-xl bg-red-50 border border-red-100">
                                <div class="w-14 h-14 bg-red-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl font-bold">A+</div>
                                <p class="text-2xl font-bold text-gray-900">45</p>
                                <p class="text-xs text-gray-400">Units</p>
                            </div>
                            <div class="text-center p-4 rounded-xl bg-red-50 border border-red-100">
                                <div class="w-14 h-14 bg-red-500 text-white rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl font-bold">B+</div>
                                <p class="text-2xl font-bold text-gray-900">38</p>
                                <p class="text-xs text-gray-400">Units</p>
                            </div>
                            <div class="text-center p-4 rounded-xl bg-red-50 border border-red-100">
                                <div class="w-14 h-14 bg-red-700 text-white rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl font-bold">O+</div>
                                <p class="text-2xl font-bold text-gray-900">52</p>
                                <p class="text-xs text-gray-400">Units</p>
                            </div>
                            <div class="text-center p-4 rounded-xl bg-red-50 border border-red-100">
                                <div class="w-14 h-14 bg-red-800 text-white rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl font-bold">AB+</div>
                                <p class="text-2xl font-bold text-gray-900">25</p>
                                <p class="text-xs text-gray-400">Units</p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <script>
        // Notifications toggle
        function toggleNotifications() {
            var panel = document.getElementById('notificationsPanel');
            panel.classList.toggle('hidden');
            // Close admin dropdown if open
            var dd = document.getElementById('adminDropdown');
            if (dd) dd.classList.add('hidden');
        }

        // Admin dropdown
        function toggleAdminDropdown() {
            document.getElementById('adminDropdown').classList.toggle('hidden');
            // Close notifications if open
            var panel = document.getElementById('notificationsPanel');
            if (panel) panel.classList.add('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            var bell = e.target.closest('button[onclick*="toggleNotifications"]');
            var admin = e.target.closest('#adminMenu');
            var notifPanel = e.target.closest('#notificationsPanel');

            if (!bell && !notifPanel) {
                var np = document.getElementById('notificationsPanel');
                if (np) np.classList.add('hidden');
            }
            if (!admin) {
                var dd = document.getElementById('adminDropdown');
                if (dd) dd.classList.add('hidden');
            }
        });
    </script>

    


    <!-- Blood Group Pie Chart -->
    <script>
    (function() {
        var ctx = document.getElementById('bloodGroupPieChart');
        if (!ctx) return;

        var labels = <?= json_encode(array_column($blood_group_stats, 'blood_group')) ?>;
        var data = <?= json_encode(array_map('intval', array_column($blood_group_stats, 'donor_count'))) ?>;

        var colors = [
            '#DC2626', // red-600
            '#EF4444', // red-500
            '#B91C1C', // red-700
            '#991B1B', // red-800
            '#F87171', // red-400
            '#FCA5A5', // red-300
            '#7F1D1D', // red-900
            '#FEE2E2'  // red-100
        ];

        var borderColors = colors.map(function(c) { return c; });

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverBorderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1F2937',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var value = context.parsed;
                                var pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return ' ' + value + ' donors (' + pct + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    })();

    // Monthly Donation Bar Chart
    (function() {
        var ctx = document.getElementById('monthlyDonationChart');
        if (!ctx) return;

        var labels = <?= json_encode(array_column($monthly_donations, 'month_label')) ?>;
        var counts = <?= json_encode(array_map('intval', array_column($monthly_donations, 'donation_count'))) ?>;
        var units = <?= json_encode(array_map('intval', array_column($monthly_donations, 'total_units'))) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Donations',
                        data: counts,
                        backgroundColor: 'rgba(220, 38, 38, 0.8)',
                        hoverBackgroundColor: 'rgba(220, 38, 38, 1)',
                        borderColor: '#DC2626',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    },
                    {
                        label: 'Units Donated',
                        data: units,
                        backgroundColor: 'rgba(254, 202, 202, 0.7)',
                        hoverBackgroundColor: 'rgba(254, 202, 202, 1)',
                        borderColor: '#FCA5A5',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#9CA3AF',
                            font: { size: 11, weight: '500' }
                        },
                        border: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F3F4F6' },
                        ticks: {
                            color: '#9CA3AF',
                            font: { size: 11 },
                            stepSize: 1,
                            callback: function(value) {
                                if (Number.isInteger(value)) return value;
                                return '';
                            }
                        },
                        border: { display: false }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 20,
                            font: { size: 12, weight: '500' },
                            color: '#6B7280'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1F2937',
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                return ' ' + label + ': ' + context.parsed.y;
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    })();
    </script>

</body>
</html>
