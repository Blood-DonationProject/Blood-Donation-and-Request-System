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
    $roleCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    $hasRoleColumn = ($roleCheck && $roleCheck->num_rows > 0);
    $userFilter = $hasRoleColumn ? "WHERE role = 'User'" : "WHERE username != 'admin'";

    $stats['total_users']          = $conn->query("SELECT COUNT(*) AS c FROM users {$userFilter}")->fetch_assoc()['c'] ?? 0;
    $stats['total_donors']         = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0;
    $stats['pending']              = (int)($conn->query("
        SELECT COUNT(*) AS c 
        FROM blood_request r
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ")->fetch_assoc()['c'] ?? 0);
    $stats['pending_requests']     = (int)($conn->query("
        SELECT COUNT(*) AS c 
        FROM blood_request r
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ")->fetch_assoc()['c'] ?? 0);
    $stats['awaiting_assignment']  = (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Pending', 'Approved') AND assigned_donor_id IS NULL AND required_date >= CURDATE()")->fetch_assoc()['c'] ?? 0);
    $stats['completed_requests']   = (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0);
} catch (Exception $e) {
}

$recent_blood_requests = [];
try {
    $br_query = "
        SELECT 
            br.id, 
            COALESCE(br.requester_name, u.username) AS requester_name, 
            bg.blood_gp_name, 
            br.units, 
            br.hospital, 
            br.required_date, 
            br.urgency, 
            br.status,
            br.created_at
        FROM blood_request br
        LEFT JOIN users u ON br.users_id = u.id
        LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
        ORDER BY br.created_at DESC, br.id DESC
        LIMIT 5
    ";
    $br_res = $conn->query($br_query);
    if ($br_res) {
        while ($row = $br_res->fetch_assoc()) {
            $recent_blood_requests[] = $row;
        }
    }
} catch (Exception $e) {
}

$recent_donors = [];
try {
    $donor_query = "
        SELECT 
            d.id,
            u.username AS donor_name,
            d.gender,
            d.age,
            d.blood_groups,
            d.phone,
            d.address,
            d.available_status,
            d.created_at
        FROM donor d
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY d.created_at DESC, d.id DESC
        LIMIT 5
    ";
    $donor_res = $conn->query($donor_query);
    if ($donor_res) {
        while ($row = $donor_res->fetch_assoc()) {
            $recent_donors[] = $row;
        }
    }
} catch (Exception $e) {
}

$recent_users = [];
try {
    if ($hasRoleColumn) {
        $user_query = "
            SELECT 
                id,
                username,
                email,
                role,
                status,
                created_at
            FROM users
            WHERE role = 'User'
            ORDER BY created_at DESC, id DESC
            LIMIT 5
        ";
    } else {
        $user_query = "
            SELECT 
                u.id,
                u.username,
                u.email,
                u.status,
                u.created_at,
                CASE 
                    WHEN d.user_id IS NOT NULL THEN 'Donor'
                    WHEN br.users_id IS NOT NULL THEN 'Requester'
                    ELSE 'User'
                END AS role
            FROM users u
            LEFT JOIN (SELECT DISTINCT user_id FROM donor) d ON d.user_id = u.id
            LEFT JOIN (SELECT DISTINCT users_id FROM blood_request) br ON br.users_id = u.id
            WHERE u.username != 'admin'
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT 5
        ";
    }
    $user_res = $conn->query($user_query);
    if ($user_res) {
        while ($row = $user_res->fetch_assoc()) {
            $recent_users[] = $row;
        }
    }
} catch (Exception $e) {
}

if (!function_exists('getRelativeTime')) {
    function getRelativeTime($timestamp)
    {
        if (!$timestamp) return '-';
        $time_ago = strtotime($timestamp);
        $current_time = time();
        $time_difference = $current_time - $time_ago;
        $seconds = $time_difference;
        $minutes = round($seconds / 60);
        $hours = round($seconds / 3600);
        $days = round($seconds / 86400);

        if ($seconds <= 60) {
            return "Just now";
        } else if ($minutes <= 60) {
            return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
        } else if ($hours <= 24) {
            return $hours == 1 ? "1 hour ago" : "$hours hours ago";
        } else if ($days == 1) {
            return "Yesterday";
        } else {
            return date('d M Y', $time_ago);
        }
    }
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

        html:not(.dark) .bg-gray-50:not(.sidebar):not(nav):not(nav *) {
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





        html.dark .bg-white:not(.sidebar):not(nav) {
            background-color: #1f2937 !important;
        }

        html.dark .text-gray-900,
        html.dark .text-gray-800 {
            color: #f3f4f6 !important;
        }

        html.dark .text-gray-700:not(.sidebar *):not(nav *) {
            color: #d1d5db !important;
        }

        html.dark .text-gray-600:not(.sidebar *):not(nav *) {
            color: #9ca3af !important;
        }

        html.dark .text-gray-500:not(.sidebar *):not(nav *) {
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

        html.dark .border-t:not(.sidebar *) {
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
                    <div class="stat-card bg-pink-100 rounded-2xl p-6 border border-pink-300 shadow-sm animate-slide-in">
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
                    <div class="stat-card bg-green-100 rounded-2xl p-6 border border-green-300 shadow-sm animate-slide-in" style="animation-delay: 0.1s;">
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
                    <div class="stat-card bg-yellow-100 rounded-2xl p-6 border border-yellow-300 shadow-sm animate-slide-in" style="animation-delay: 0.2s;">
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
                    <div class="stat-card bg-blue-100 rounded-2xl p-6 border border-blue-300 shadow-sm animate-slide-in" style="animation-delay: 0.3s;">
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
                    <div class="stat-card bg-violet-100 rounded-2xl p-6 border border-violet-300 shadow-sm animate-slide-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-violet-500 text-xl"></i>
                            </div>
                            <span class="text-xs font-semibold text-violet-600 bg-violet-100 px-2.5 py-1 rounded-full">Done</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['completed_requests'] ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Completed Requests</p>
                    </div>

                </div>

                <!-- Recent Blood Requests -->
                <div class="mt-8 bg-white dark:bg-white rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm animate-slide-in" style="animation-delay: 0.5s;">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-900 flex items-center gap-2">
                            <i class="fas fa-tint text-red-500"></i> Recent Blood Requests
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <?php if (empty($recent_blood_requests)): ?>
                            <div class="text-center py-8 text-gray-900 dark:text-gray-900 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                                No recent blood requests
                            </div>
                        <?php else: ?>
                            <table class="w-full text-left border-collapse min-w-max">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Requester</th>
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Blood Group</th>
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Units</th>
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Hospital</th>
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Required Date</th>
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Urgency</th>
                                        <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_blood_requests as $req): ?>
                                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-00/25 transition-colors">
                                            <td class="py-3 px-4 text-sm text-gray-800 dark:text-gray-700"><?php echo htmlspecialchars($req['requester_name'] ?? 'Unknown'); ?></td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-300 text-red-600 dark:bg-red-500/30 dark:text-red-600">
                                                    <?php echo htmlspecialchars($req['blood_gp_name'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-900"><?php echo htmlspecialchars($req['units']); ?></td>
                                            <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-900 truncate max-w-[150px]" title="<?php echo htmlspecialchars($req['hospital']); ?>"><?php echo htmlspecialchars($req['hospital']); ?></td>
                                            <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-900 whitespace-nowrap"><?php echo date('d M Y', strtotime($req['required_date'])); ?></td>
                                            <td class="py-3 px-4">
                                                <?php
                                                $urgencyClass = 'bg-blue-200 text-blue-600 dark:bg-blue-500/20 dark:text-blue-700';
                                                if ($req['urgency'] === 'Urgent') $urgencyClass = 'bg-red-200 text-red-600 dark:bg-red-500/20 dark:text-red-600';
                                                elseif ($req['urgency'] === 'High') $urgencyClass = 'bg-orange-200 text-orange-600 dark:bg-orange-500/20 dark:text-orange-600';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $urgencyClass; ?>">
                                                    <?php echo htmlspecialchars($req['urgency']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <?php
                                                $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-200 dark:text-gray-700';
                                                if ($req['status'] === 'Pending') $statusClass = 'bg-yellow-300 text-yellow-800 dark:bg-yellow-500/30 dark:text-yellow-600';
                                                elseif ($req['status'] === 'Assigned') $statusClass = 'bg-blue-300 text-blue-800 dark:bg-blue-500/30 dark:text-blue-600';
                                                elseif ($req['status'] === 'Accepted') $statusClass = 'bg-indigo-300 text-indigo-800 dark:bg-indigo-500/30 dark:text-indigo-600';
                                                elseif ($req['status'] === 'Completed' || $req['status'] === 'Received') $statusClass = 'bg-green-300 text-green-600 dark:bg-green-500/30 dark:text-green-600';
                                                elseif ($req['status'] === 'Rejected' || $req['status'] === 'Cancelled') $statusClass = 'bg-red-300 text-red-600 dark:bg-red-500/30 dark:text-red-600';
                                                elseif ($req['status'] === 'Expired') $statusClass = 'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($req['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <a href="assignments.php" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-2 transition-colors">
                            View All Blood Requests <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Recent Donors -->
                <div class="mt-8 bg-white dark:bg-white rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm animate-slide-in" style="animation-delay: 0.6s;">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-pink-100 dark:bg-pink-500/30 text-red-600 dark:text-red-600 flex items-center justify-center text-lg shadow-xs">
                                <i class="fas fa-hand-holding-heart"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-800 flex items-center gap-2">
                                    Recent Donors
                                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-pink-50 text-pink-700 dark:bg-pink-500/30 dark:text-pink-500 border border-pink-200 dark:border-pink-800/80">
                                        <?= count($recent_donors) ?> Recent
                                    </span>
                                </h3>
                                <p class="text-xs text-gray-700 dark:text-gray-700 mt-0.5">Recently registered blood donation volunteers</p>
                            </div>
                        </div>

                    </div>

                    <div class="overflow-x-auto rounded-xl">
                        <?php if (empty($recent_donors)): ?>
                            <div class="text-center py-12 px-4 bg-gray-300 dark:bg-gray-500/30">

                                <h4 class="text-base font-semibold text-gray-900 dark:text-white">No recent donors</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-300 mt-1 max-w-sm mx-auto">No blood donors have been registered yet in the system.</p>
                            </div>
                        <?php else: ?>
                            <table class="w-full text-left border-collapse min-w-max">
                                <thead>
                                    <tr class="bg-white dark:bg-white text-gray-700 dark:text-gray-800 text-xs font-semibold uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                                        <th class="py-3.5 px-4">Donor Profile</th>
                                        <th class="py-3.5 px-4">Blood Group</th>
                                        <th class="py-3.5 px-4">Contact Info</th>
                                        <th class="py-3.5 px-4">Address / Township</th>
                                        <th class="py-3.5 px-4">Status</th>
                                        <th class="py-3.5 px-4">Registered</th>
                                        <th class="py-3.5 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-sm">
                                    <?php foreach ($recent_donors as $donor): ?>
                                        <tr class="hover:bg-gray-500/30 dark:hover:bg-gray-500/30 transition-colors">
                                            <!-- Donor Profile -->
                                            <td class="py-3.5 px-4">
                                                <div class="flex items-center gap-3">
                                                    <?php
                                                    $gender = $donor['gender'] ?? 'Other';

                                                    if ($gender === 'Male') $avatarBg = 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-800';
                                                    elseif ($gender === 'Female') $avatarBg = 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-800';
                                                    ?>
                                                </div>
                                                <div>
                                                    <span class="font-bold text-gray-800 dark:text-gray-800 block leading-tight">
                                                        <?= htmlspecialchars($donor['donor_name'] ?? 'Unknown Donor') ?>
                                                    </span>
                                                    <span class="text-xs text-gray-600 dark:text-gray-600 mt-0.5 inline-block">
                                                        <?= htmlspecialchars($donor['gender'] ?? '-') ?><?= !empty($donor['age']) ? ' • ' . (int)$donor['age'] . ' yrs' : '' ?>
                                                    </span>
                                                </div>
                    </div>
                    </td>

                    <!-- Blood Group -->
                    <td class="py-3.5 px-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-red-50 text-red-700 dark:bg-red-500/20 dark:text-red-600 border border-red-200 dark:border-red-500/30 shadow-2xs">

                            <?= htmlspecialchars($donor['blood_groups'] ?? '-') ?>
                        </span>
                    </td>

                    <!-- Phone -->
                    <td class="py-3.5 px-4">
                        <?php if (!empty($donor['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($donor['phone']) ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-800 dark:text-gray-800 hover:text-red-600 dark:hover:text-red-400 transition-colors">

                                <?= htmlspecialchars($donor['phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-800 dark:text-gray-800">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Address -->
                    <td class="py-3.5 px-4">
                        <div class="flex items-center gap-1.5 text-xs text-gray-800 dark:text-gray-800 max-w-[200px]" title="<?= htmlspecialchars($donor['address'] ?? '') ?>">

                            <span class="truncate"><?= htmlspecialchars($donor['address'] ?? '-') ?></span>
                        </div>
                    </td>

                    <!-- Availability Status -->
                    <td class="py-3.5 px-4">
                        <?php if (($donor['available_status'] ?? 'Available') === 'Available'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-200 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-800 border border-emerald-200 dark:border-emerald-800/80 whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Available
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-800 dark:bg-gray-500/20 dark:text-gray-800 border border-gray-200 dark:border-gray-700 whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                Unavailable
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Registered Date -->
                    <td class="py-3.5 px-4 text-xs text-gray-800 dark:text-gray-800 whitespace-nowrap" title="<?= !empty($donor['created_at']) ? date('d M Y, h:i A', strtotime($donor['created_at'])) : '' ?>">
                        <span class="inline-flex items-center gap-1.5">
                            <?= getRelativeTime($donor['created_at'] ?? null) ?>
                        </span>
                    </td>

                    <!-- Action -->
                    <td class="py-3.5 px-4 text-right">
                        <a href="donor_crud.php?edit=<?= $donor['id'] ?>" class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg bg-gray-300 hover:bg-pink-150 text-red-500 hover:text-pink-700 dark:bg-gray-500/30 dark:hover:bg-pink-500/20 dark:text-blue-500 dark:hover:text-pink-700 transition-colors">
                            <i class="fas fa-pen text-[10px]"></i> Edit
                        </a>
                    </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            <?php endif; ?>
                </div>

                <div class="mt-5 flex items-center justify-between">
                    <span class="text-xs text-gray-600 dark:text-gray-600">Showing 5 latest registered donors</span>
                    <a href="donor_crud.php" class="text-sm font-semibold text-pink-600 hover:text-pink-700 dark:text-pink-400 dark:hover:text-pink-300 flex items-center gap-2 transition-colors group">
                        <span>View All Donors</span>
                        <i class="fas fa-arrow-right text-xs transform group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="mt-8 bg-white dark:bg-white rounded-2xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm animate-slide-in" style="animation-delay: 0.7s;">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-900 flex items-center gap-2">
                        <i class="fas fa-users text-blue-500"></i> Recent Users
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($recent_users)): ?>
                        <div class="text-center py-8 text-gray-700 dark:text-gray-700 bg-gray-50 dark:bg-gray-500/20 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                            No recent users
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left border-collapse min-w-max">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">User</th>
                                    <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Email</th>
                                    <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Role</th>
                                    <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-700">Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $u): ?>
                                    <tr class="border-b border-gray-100 dark:border-gray-500/30 hover:bg-gray-50 dark:hover:bg-gray-700/25 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-red-100 dark:bg-red-500/30 rounded-full flex items-center justify-center text-xs font-bold text-red-700 dark:text-red-700">
                                                    <?php echo strtoupper(substr(htmlspecialchars($u['username'] ?? '-'), 0, 1)); ?>
                                                </div>
                                                <span class="text-sm font-medium text-gray-800 dark:text-gray-700">
                                                    <?php echo htmlspecialchars($u['username'] ?? 'Unknown'); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-700">
                                            <?php if (!empty($u['email'])): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($u['email']); ?>" class="hover:underline text-blue-700 dark:text-blue-600">
                                                    <?php echo htmlspecialchars($u['email']); ?>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php
                                            $role = $u['role'] ?? 'User';
                                            $roleClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                            if ($role === 'Admin') $roleClass = 'bg-purple-100 text-purple-800 dark:bg-purple-500/30 dark:text-purple-600';
                                            elseif ($role === 'User') $roleClass = 'bg-blue-100 text-blue-800 dark:bg-blue-500/30 dark:text-blue-600';
                                            elseif ($role === 'Donor') $roleClass = 'bg-pink-100 text-pink-800 dark:bg-pink-500/30 dark:text-pink-600';
                                            elseif ($role === 'Requester') $roleClass = 'bg-orange-100 text-orange-800 dark:bg-orange-500/30 dark:text-orange-600';
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?php echo $roleClass; ?>">
                                                <?php echo htmlspecialchars($role); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php
                                            $status = $u['status'] ?? 'Active';
                                            $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-600';
                                            if ($status === 'Active') $statusClass = 'bg-green-100 text-green-800 dark:bg-green-500/30 dark:text-green-600';
                                            elseif ($status === 'Inactive') $statusClass = 'bg-red-100 text-red-800 dark:bg-red-500/30 dark:text-red-600';
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                            <?php echo getRelativeTime($u['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex justify-end">
                    <a href="users_crud.php" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-600 dark:hover:text-blue-700 flex items-center gap-2 transition-colors">
                        View All Users <i class="fas fa-arrow-right"></i>
                    </a>
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