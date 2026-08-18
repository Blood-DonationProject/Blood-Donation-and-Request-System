<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Stats
$stats = [
    'total_users'          => 0,
    'total_donors'         => 0,
    'pending'              => 0, // For notification bar
    'pending_requests'     => 0,
    'awaiting_assignment'  => 0,
    'completed_requests'   => 0,
];

try {
    $stats['total_users']          = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
    $stats['total_donors']         = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0;
    $stats['pending']              = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Pending', 'Accepted', 'Rejected')")->fetch_assoc()['c'] ?? 0;
    $stats['pending_requests']     = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
    $stats['awaiting_assignment']  = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Pending', 'Approved', 'Rejected') AND assigned_donor_id IS NULL")->fetch_assoc()['c'] ?? 0;
    $stats['completed_requests']   = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {
}







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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        .animate-slide-in {
            animation: slideIn 0.6s ease-out;
        }

        .animate-fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        .pulse-dot {
            animation: pulse-dot 2s infinite;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(220, 38, 38, 0.2);
        }

        .action-card {
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(220, 38, 38, 0.25);
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
            background-color: #f9fafb !important;
        }

        html.dark body {
            background-color: #111827 !important;
            background-image: none !important;
            color: #e5e7eb;
        }

        html.dark .w-64.bg-white {
            background-color: #1f2937 !important;
        }

        html.dark nav.bg-white,
        html.dark nav.bg-white.shadow-md {
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

        html.dark thead.bg-gray-50 {
            background-color: #111827 !important;
        }

        html.dark .border-gray-200,
        html.dark .border-2.border-gray-200,
        html.dark .border {
            border-color: #4b5563 !important;
        }

        html.dark .border-t {
            border-color: #374151 !important;
        }

        html.dark tbody tr {
            border-color: #374151 !important;
        }

        html.dark tbody tr:hover {
            background-color: #374151 !important;
        }

        html.dark .stat-card:hover {
            box-shadow: 0 20px 40px -10px rgba(220, 38, 38, 0.35);
        }
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



            <!-- Main Content Area -->
            <div class="p-6 md:p-8">

                <!-- Live Clock Script -->
                <script>
                    (function() {
                        function updateClock() {
                            var now = new Date();
                            var options = {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            };
                            var dateStr = now.toLocaleDateString('en-US', options);
                            var timeStr = now.toLocaleTimeString('en-US', {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            });
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
                    <div class="stat-card bg-pink rounded-2xl p-6 border border-blue-300 shadow-sm animate-slide-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-pink-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-users text-red-500 text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-pink-100 px-2.5 py-1 rounded-full">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_users'] ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Total Users</p>
                    </div>

                    <!-- Active Donors -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-pink-300 shadow-sm animate-slide-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-hand-holding-heart text-red-600 text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-green-700 bg-green-100 px-2.5 py-1 rounded-full">Active</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_donors'] ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Active Donors</p>
                    </div>

                    <!-- Pending Blood Requests -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-red-300 shadow-sm animate-slide-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-500 text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-100 px-2.5 py-1 rounded-full">Pending</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['pending_requests'] ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Pending Blood Requests</p>
                    </div>

                    <!-- Requests Awaiting Assignment -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-yellow-300 shadow-sm animate-slide-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-user-plus text-blue-500 text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2.5 py-1 rounded-full">Awaiting</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['awaiting_assignment'] ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Requests Awaiting Assignment</p>
                    </div>

                    <!-- Completed Requests -->
                    <div class="stat-card bg-white rounded-2xl p-6 border border-green-300 shadow-sm animate-slide-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 px-2.5 py-1 rounded-full">Done</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['completed_requests'] ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Completed Requests</p>
                    </div>

                </div>



            </div>
        </div>
    </div>

    <script>
        // Admin dropdown
        function toggleAdminDropdown() {
            document.getElementById('adminDropdown').classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            var bell = e.target.closest('button[onclick*="toggleNotifications"]');
            var admin = e.target.closest('#adminMenu');
            var notifPanel = e.target.closest('#adminNotifDropdown');

            if (!bell && !notifPanel) {
                var np = document.getElementById('adminNotifDropdown');
                if (np) np.classList.add('hidden');
            }
            if (!admin) {
                var dd = document.getElementById('adminDropdown');
                if (dd) dd.classList.add('hidden');
            }
        });
    </script>





</body>

</html>