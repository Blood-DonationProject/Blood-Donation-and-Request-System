<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Stats
$stats = [
    'total_donors'   => 0,
    'total_requests'  => 0,
    'pending'         => 0,
    'approved'        => 0,
    'completed'       => 0,
    'today_donations' => 0,
];

try {
    $stats['total_donors']   = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0;
    $stats['total_requests']  = $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0;
    $stats['pending']         = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
    $stats['approved']        = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Approved'")->fetch_assoc()['c'] ?? 0;
    $stats['completed']       = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
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

// Approve action
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Approved' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: dashboard.php');
    exit;
}

// Reject action
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Rejected' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: dashboard.php');
    exit;
}
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
    <script src="../assets/js/translations.js"></script>
    <script src="../assets/js/i18n.js"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        <div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-red-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-tint text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                        <p class="text-xs text-gray-400">Admin Panel</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-xl font-semibold border border-red-100">
                    <i class="fas fa-th-large w-5 text-center"></i>
                    <span data-i18n="overview">Overview</span>
                </a>
                <a href="logindata.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl transition">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span data-i18n="users">Users</span>
                </a>
                <a href="donor_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl transition">
                    <i class="fas fa-hand-holding-heart w-5 text-center"></i>
                    <span data-i18n="donors">Donors</span>
                </a>
                <a href="donation_histories.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl transition">
                    <i class="fas fa-history w-5 text-center"></i>
                    <span data-i18n="donation_histories">Donation Histories</span>
                </a>
                <a href="requests.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-xl transition">
                    <i class="fas fa-file-medical w-5 text-center"></i>
                    <span data-i18n="blood_requests">Blood Requests</span>
                </a>
            </nav>

            <div class="p-4 border-t border-gray-100">
                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="w-full bg-red-600 text-white flex justify-center items-center gap-2 py-2.5 rounded-xl font-semibold hover:bg-red-700 transition">
                    <i class="fas fa-sign-out-alt"></i>
                    <span data-i18n="logout">Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 min-h-screen">
            <!-- Top Navigation Bar -->
            <nav class="bg-white shadow-sm sticky top-0 z-40 border-b border-gray-100">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Theme Toggle -->
                        <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-300 hover:bg-red-50 transition" aria-label="Toggle theme" onclick="toggleTheme()">
                            <span class="theme-icon-sun"><i class="fas fa-sun text-gray-600"></i></span>
                            <span class="theme-icon-moon" style="display:none"><i class="fas fa-moon text-gray-600"></i></span>
                        </button>
                        <!-- Language -->
                        <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.75rem;border:1px solid #e5e7eb;background-color:#f9fafb;color:#374151;padding:8px 12px;cursor:pointer;">
                            <option value="en">EN</option>
                            <option value="my">MY</option>
                        </select>
                        <!-- Notifications Bell -->
                        <button onclick="toggleNotifications()" class="relative w-10 h-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:border-red-300 transition">
                            <i class="fas fa-bell text-gray-600"></i>
                            <?php if ($stats['pending'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm pulse-dot"><?= $stats['pending'] ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Admin Profile -->
                        <div class="relative" id="adminMenu">
                            <div class="flex items-center space-x-3 cursor-pointer pl-3 border-l border-gray-200" onclick="toggleAdminDropdown()">
                                <div class="text-right">
                                    <p class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                    <p class="text-xs text-gray-400" data-i18n="administrator">Administrator</p>
                                </div>
                                <div class="w-10 h-10 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-sm">
                                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                </div>
                            </div>
                            <div id="adminDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-xl border border-gray-100 z-50">
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-lg">
                                            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                            <p class="text-sm text-gray-400"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-xl font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

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
                                <a href="dashboard.php?approve=<?= $pr['id'] ?>" class="btn-approve bg-green-500 hover:bg-green-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg" onclick="return confirm('Approve this request?')">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </a>
                                <a href="dashboard.php?reject=<?= $pr['id'] ?>" class="btn-reject bg-white border border-red-200 text-red-600 hover:bg-red-50 text-xs font-bold px-3 py-1.5 rounded-lg" onclick="return confirm('Reject this request?')">
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

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

                    <!-- Total Donors -->
                    <div class="stat-card bg-gray-200 rounded-2xl p-6 border border-gray-100 shadow-sm animate-slide-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-users text-red-600 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">Active</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_donors'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Total Donors</p>
                    </div>

                    <!-- Blood Requests -->
                    <div class="stat-card bg-red-200 rounded-2xl p-6 border border-gray-100 shadow-sm animate-slide-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-file-medical text-red-600 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-full"><?= $stats['pending'] ?> new</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total_requests'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Blood Requests</p>
                    </div>

                    <!-- Pending -->
                    <div class="stat-card bg-red-100 rounded-2xl p-6 border border-gray-100 shadow-sm animate-slide-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-50 px-2.5 py-1 rounded-full">Awaiting</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['pending'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Pending Requests</p>
                    </div>

                    <!-- Approved -->
                    <div class="stat-card bg-green-100 rounded-2xl p-6 border border-gray-100 shadow-sm animate-slide-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">Done</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900"><?= $stats['approved'] + $stats['completed'] ?></h3>
                        <p class="text-sm text-gray-400 mt-1">Approved & Completed</p>
                    </div>

                </div>

                <!-- Pending Requests Action Section -->
                <?php if (count($pending_requests) > 0): ?>
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Pending Blood Requests
                            </h3>
                            <p class="text-sm text-gray-400 mt-1">Review and take action on incoming requests</p>
                        </div>
                        <a href="requests.php" class="text-sm font-semibold text-red-600 hover:text-red-700 transition">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php foreach ($pending_requests as $pr): ?>
                        <div class="action-card bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-11 h-11 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-sm">
                                        <?= strtoupper(substr($pr['blood_group'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?= htmlspecialchars($pr['blood_group']) ?></p>
                                        <p class="text-xs text-gray-400">Request #<?= $pr['id'] ?></p>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold text-yellow-600 bg-yellow-50 px-2.5 py-1 rounded-full">
                                    <i class="fas fa-clock mr-1"></i>Pending
                                </span>
                            </div>

                            <div class="space-y-2 mb-5">
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-user text-gray-400 w-5"></i>
                                    <span class="ml-2"><?= htmlspecialchars($pr['requester_name'] ?? 'Unknown') ?></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-hospital text-gray-400 w-5"></i>
                                    <span class="ml-2"><?= htmlspecialchars($pr['hospital']) ?></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-procedures text-gray-400 w-5"></i>
                                    <span class="ml-2"><?= (int)$pr['units'] ?> Unit<?= (int)$pr['units'] > 1 ? 's' : '' ?></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-calendar text-gray-400 w-5"></i>
                                    <span class="ml-2"><?= htmlspecialchars($pr['required_date']) ?></span>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <a href="dashboard.php?approve=<?= $pr['id'] ?>" onclick="return confirm('Approve this blood request?')" class="btn-approve flex-1 bg-green-500 hover:bg-green-600 text-white text-center py-2.5 rounded-xl font-semibold text-sm shadow-sm">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </a>
                                <a href="dashboard.php?reject=<?= $pr['id'] ?>" onclick="return confirm('Reject this blood request?')" class="btn-reject flex-1 bg-white border-2 border-red-200 text-red-600 hover:bg-red-50 text-center py-2.5 rounded-xl font-semibold text-sm">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

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
