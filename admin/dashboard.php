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

// Assign donor action
$assign_success = '';
$assign_error = '';
if (isset($_POST['assign_donor'])) {
    $request_id = (int)$_POST['request_id'];
    $donor_id = (int)$_POST['donor_id'];

    if ($request_id > 0 && $donor_id > 0) {
        // Verify the request exists and is in a valid state
        $check = $conn->prepare("SELECT id, status FROM blood_request WHERE id = ?");
        $check->bind_param("i", $request_id);
        $check->execute();
        $result = $check->get_result();
        if ($result && $result->num_rows > 0) {
            $req = $result->fetch_assoc();
            if (in_array($req['status'], ['Pending', 'Approved'])) {
                // Verify donor exists and is available
                $donor_check = $conn->prepare("SELECT id, available_status FROM donor WHERE id = ?");
                $donor_check->bind_param("i", $donor_id);
                $donor_check->execute();
                $donor_result = $donor_check->get_result();
                if ($donor_result && $donor_result->num_rows > 0) {
                    $donor = $donor_result->fetch_assoc();
                    if ($donor['available_status'] === 'Available') {
                        // Assign donor and update status to Approved
                        $assign = $conn->prepare("UPDATE blood_request SET assigned_donor_id = ?, status = 'Approved' WHERE id = ?");
                        $assign->bind_param("ii", $donor_id, $request_id);
                        if ($assign->execute()) {
                            // Create donation_history record for the verified assignment
                            $reqDetail = $conn->prepare("SELECT users_id, blood_groups_id, units FROM blood_request WHERE id = ?");
                            $reqDetail->bind_param("i", $request_id);
                            $reqDetail->execute();
                            $reqRow = $reqDetail->get_result()->fetch_assoc();
                            $reqDetail->close();

                            if ($reqRow) {
                                $donorUser = $conn->prepare("SELECT user_id FROM donor WHERE id = ?");
                                $donorUser->bind_param("i", $donor_id);
                                $donorUser->execute();
                                $donorUserRow = $donorUser->get_result()->fetch_assoc();
                                $donorUser->close();

                                if ($donorUserRow) {
                                    $dhStmt = $conn->prepare("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, units, donation_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Completed')");
                                    $dhDate = date('Y-m-d');
                                    $dhStmt->bind_param("iiiiis", $donor_id, $reqRow['users_id'], $request_id, $reqRow['blood_groups_id'], $reqRow['units'], $dhDate);
                                    $dhStmt->execute();
                                    $dhStmt->close();
                                }
                            }

                            $assign_success = 'Donor assigned successfully!';
                        } else {
                            $assign_error = 'Error assigning donor: ' . $conn->error;
                        }
                        $assign->close();
                    } else {
                        $assign_error = 'Selected donor is not available.';
                    }
                } else {
                    $assign_error = 'Donor not found.';
                }
                $donor_check->close();
            } else {
                $assign_error = 'Request cannot be assigned (status: ' . htmlspecialchars($req['status']) . ').';
            }
        } else {
            $assign_error = 'Blood request not found.';
        }
        $check->close();
    } else {
        $assign_error = 'Please select both a blood request and a donor.';
    }
    header('Location: dashboard.php');
    exit;
}

// Unassign donor action
if (isset($_GET['unassign'])) {
    $id = (int)$_GET['unassign'];
    $stmt = $conn->prepare("UPDATE blood_request SET assigned_donor_id = NULL, status = 'Pending' WHERE id = ? AND assigned_donor_id IS NOT NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: dashboard.php');
    exit;
}

// Fetch assignable requests (Pending or Approved without donor)
$assignable_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.requester_name, bg.blood_gp_name AS blood_group, bg.id AS blood_groups_id,
               r.units, r.hospital, r.required_date, r.status, r.assigned_donor_id
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        WHERE r.status IN ('Pending', 'Approved') AND r.assigned_donor_id IS NULL
        ORDER BY r.required_date ASC
    ");
    if ($result && $result->num_rows > 0) {
        $assignable_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}

// Fetch available donors
$available_donors = [];
try {
    $result = $conn->query("
        SELECT d.id, d.blood_groups, d.phone, d.weight, d.age, d.available_status,
               d.last_donation_date, u.username
        FROM donor d
        JOIN users u ON d.user_id = u.id
        WHERE d.available_status = 'Available'
        ORDER BY d.blood_groups ASC, u.username ASC
    ");
    if ($result && $result->num_rows > 0) {
        $available_donors = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}

// Fetch already assigned requests for display
$assigned_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.requester_name, bg.blood_gp_name AS blood_group, r.units,
               r.hospital, r.required_date, r.status,
               u.username AS donor_name, d.blood_groups AS donor_blood_group, d.phone AS donor_phone
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        LEFT JOIN donor d ON r.assigned_donor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE r.assigned_donor_id IS NOT NULL AND r.status IN ('Approved', 'Completed')
        ORDER BY r.required_date DESC
    ");
    if ($result && $result->num_rows > 0) {
        $assigned_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {}
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
        .btn-assign { transition: all 0.2s ease; }
        .btn-assign:hover { transform: scale(1.05); }
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
                                <button type="button" onclick="scrollToAssign(<?= $pr['id'] ?>); toggleNotifications();" class="btn-assign bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition">
                                    <i class="fas fa-user-plus mr-1"></i>Assign
                                </button>
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

                            <div class="flex gap-2">
                                <a href="dashboard.php?approve=<?= $pr['id'] ?>" onclick="return confirm('Approve this blood request?')" class="btn-approve flex-1 bg-green-500 hover:bg-green-600 text-white text-center py-2.5 rounded-xl font-semibold text-sm shadow-sm">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </a>
                                <button type="button" onclick="scrollToAssign(<?= $pr['id'] ?>)" class="btn-assign flex-1 bg-blue-500 hover:bg-blue-600 text-white text-center py-2.5 rounded-xl font-semibold text-sm shadow-sm transition">
                                    <i class="fas fa-user-plus mr-1"></i>Assign
                                </button>
                                <a href="dashboard.php?reject=<?= $pr['id'] ?>" onclick="return confirm('Reject this blood request?')" class="btn-reject flex-1 bg-white border-2 border-red-200 text-red-600 hover:bg-red-50 text-center py-2.5 rounded-xl font-semibold text-sm">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Donor Assignment Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                <i class="fas fa-user-check text-blue-500 mr-2"></i>Assign Donor to Request
                            </h3>
                            <p class="text-sm text-gray-400 mt-1">Match available donors with pending blood requests</p>
                        </div>
                    </div>

                    <?php if ($assign_success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-5 flex items-center animate-fade-in">
                        <i class="fas fa-check-circle text-green-500 text-lg mr-3"></i>
                        <p class="text-green-700 font-semibold text-sm"><?= htmlspecialchars($assign_success) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($assign_error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5 flex items-center animate-fade-in">
                        <i class="fas fa-exclamation-circle text-red-500 text-lg mr-3"></i>
                        <p class="text-red-700 font-semibold text-sm"><?= htmlspecialchars($assign_error) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (count($assignable_requests) > 0): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Requests List -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                <span class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-lg flex items-center justify-center mr-2">
                                    <i class="fas fa-file-medical text-sm"></i>
                                </span>
                                Requests Awaiting Assignment
                                <span class="ml-auto bg-yellow-100 text-yellow-700 text-xs font-bold px-2.5 py-1 rounded-full"><?= count($assignable_requests) ?></span>
                            </h4>
                            <div class="space-y-3 max-h-96 overflow-y-auto" id="requestList">
                                <?php foreach ($assignable_requests as $ar): ?>
                                <div class="request-item p-4 rounded-xl border-2 border-gray-200 hover:border-blue-300 cursor-pointer transition group"
                                     data-id="<?= $ar['id'] ?>"
                                     data-blood-group="<?= htmlspecialchars($ar['blood_group']) ?>"
                                     data-blood-groups-id="<?= (int)$ar['blood_groups_id'] ?>"
                                     data-units="<?= (int)$ar['units'] ?>"
                                     onclick="selectRequest(this)">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-11 h-11 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-sm">
                                                <?= strtoupper(substr($ar['blood_group'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($ar['blood_group']) ?> - <?= (int)$ar['units'] ?> Unit<?= (int)$ar['units'] > 1 ? 's' : '' ?></p>
                                                <p class="text-xs text-gray-400">Request #<?= $ar['id'] ?> - <?= htmlspecialchars($ar['requester_name'] ?? 'Unknown') ?></p>
                                            </div>
                                        </div>
                                        <span class="text-xs font-semibold <?= $ar['status'] === 'Pending' ? 'text-yellow-600 bg-yellow-50' : 'text-blue-600 bg-blue-50' ?> px-2.5 py-1 rounded-full">
                                            <?= htmlspecialchars($ar['status']) ?>
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-center text-xs text-gray-500 space-x-4">
                                        <span><i class="fas fa-hospital mr-1"></i><?= htmlspecialchars($ar['hospital']) ?></span>
                                        <span><i class="fas fa-calendar mr-1"></i><?= htmlspecialchars($ar['required_date']) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Donor Selection & Assignment -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                <span class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center mr-2">
                                    <i class="fas fa-hand-holding-heart text-sm"></i>
                                </span>
                                Select Matching Donor
                            </h4>

                            <div id="noRequestSelected" class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-hand-pointer text-gray-300 text-2xl"></i>
                                </div>
                                <p class="text-gray-400 text-sm">Select a blood request from the left to see matching donors</p>
                            </div>

                            <div id="donorSelection" class="hidden">
                                <div class="mb-4 p-3 bg-blue-50 rounded-xl flex items-center">
                                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                    <p class="text-blue-700 text-sm font-medium">Showing donors with blood type: <span id="selectedBloodType" class="font-bold"></span></p>
                                </div>

                                <div class="mb-4">
                                    <input type="text" id="donorSearch" placeholder="Search donor by name or phone..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition outline-none">
                                </div>

                                <div class="space-y-2 max-h-64 overflow-y-auto" id="donorList">
                                    <!-- Donors will be populated by JS -->
                                </div>

                                <form method="POST" id="assignForm" class="mt-4">
                                    <input type="hidden" name="request_id" id="assignRequestId">
                                    <input type="hidden" name="donor_id" id="assignDonorId">
                                    <button type="submit" name="assign_donor" id="assignBtn" disabled
                                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold py-3 rounded-xl hover:shadow-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center">
                                        <i class="fas fa-user-check mr-2"></i>Assign Selected Donor
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                        </div>
                        <p class="text-gray-600 font-semibold">All requests have been assigned</p>
                        <p class="text-gray-400 text-sm mt-1">No pending requests awaiting donor assignment</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Donors Summary -->
                <?php if (count($assigned_requests) > 0): ?>
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                <i class="fas fa-clipboard-check text-green-500 mr-2"></i>Active Assignments
                            </h3>
                            <p class="text-sm text-gray-400 mt-1">Requests with assigned donors</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-100">
                                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Request</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Blood Type</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Hospital</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Assigned Donor</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Donor Phone</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Status</th>
                                        <th class="px-5 py-3 text-center font-semibold text-gray-600">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_requests as $asr): ?>
                                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center space-x-2">
                                                <span class="font-bold text-gray-900">#<?= $asr['id'] ?></span>
                                                <span class="text-gray-400">-</span>
                                                <span class="text-gray-600"><?= htmlspecialchars($asr['requester_name'] ?? 'Unknown') ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="bg-red-100 text-red-700 font-bold px-2.5 py-1 rounded-full text-xs"><?= htmlspecialchars($asr['blood_group']) ?></span>
                                        </td>
                                        <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($asr['hospital']) ?></td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center font-bold text-xs">
                                                    <?= strtoupper(substr($asr['donor_name'] ?? 'U', 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($asr['donor_name'] ?? '-') ?></p>
                                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($asr['donor_blood_group'] ?? '') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($asr['donor_phone'] ?? '-') ?></td>
                                        <td class="px-5 py-3">
                                            <?php
                                            $asStatusClass = $asr['status'] === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700';
                                            ?>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $asStatusClass ?>"><?= htmlspecialchars($asr['status']) ?></span>
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            <a href="dashboard.php?unassign=<?= $asr['id'] ?>" onclick="return confirm('Remove this donor assignment? The request will return to Pending status.')" class="text-red-500 hover:text-red-700 text-xs font-semibold">
                                                <i class="fas fa-user-minus mr-1"></i>Unassign
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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

    <script>
        // Donor Assignment Logic
        var allDonors = <?= json_encode($available_donors) ?>;
        var selectedDonorId = null;

        function selectRequest(el) {
            // Remove previous selection
            document.querySelectorAll('.request-item').forEach(function(item) {
                item.classList.remove('border-blue-500', 'bg-blue-50');
                item.classList.add('border-gray-200');
            });

            // Select this request
            el.classList.remove('border-gray-200');
            el.classList.add('border-blue-500', 'bg-blue-50');

            var requestId = el.getAttribute('data-id');
            var bloodGroup = el.getAttribute('data-blood-group');
            var bloodGroupsId = el.getAttribute('data-blood-groups-id');
            var units = el.getAttribute('data-units');

            document.getElementById('assignRequestId').value = requestId;
            document.getElementById('selectedBloodType').textContent = bloodGroup + ' (' + units + ' units needed)';

            // Show donor selection
            document.getElementById('noRequestSelected').classList.add('hidden');
            document.getElementById('donorSelection').classList.remove('hidden');

            // Filter and display matching donors
            selectedDonorId = null;
            document.getElementById('assignDonorId').value = '';
            document.getElementById('assignBtn').disabled = true;
            renderDonors(bloodGroup);
        }

        function renderDonors(bloodGroup, searchQuery) {
            var donorList = document.getElementById('donorList');
            var filtered = allDonors.filter(function(d) {
                var matchesBlood = d.blood_groups === bloodGroup;
                if (!searchQuery) return matchesBlood;
                var q = searchQuery.toLowerCase();
                return matchesBlood && (d.username.toLowerCase().includes(q) || d.phone.toLowerCase().includes(q));
            });

            if (filtered.length === 0) {
                donorList.innerHTML = '<div class="text-center py-6"><div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-2"><i class="fas fa-user-slash text-gray-300"></i></div><p class="text-gray-400 text-sm">No matching donors available</p><p class="text-gray-300 text-xs mt-1">Try a different blood type or check donor availability</p></div>';
                return;
            }

            var html = '';
            filtered.forEach(function(d) {
                var lastDonation = d.last_donation_date ? d.last_donation_date : 'Never';
                var canDonate = true;
                if (d.last_donation_date) {
                    var lastDate = new Date(d.last_donation_date);
                    var now = new Date();
                    var diffDays = Math.floor((now - lastDate) / (1000 * 60 * 60 * 24));
                    canDonate = diffDays >= 56; // 8 weeks between donations
                }

                html += '<div class="donor-item p-3 rounded-xl border-2 border-gray-200 hover:border-green-300 cursor-pointer transition" data-donor-id="' + d.id + '" onclick="selectDonor(this, ' + d.id + ')">';
                html += '  <div class="flex items-center justify-between">';
                html += '    <div class="flex items-center space-x-3">';
                html += '      <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center font-bold text-xs">';
                html += '        ' + d.username.substring(0, 2).toUpperCase();
                html += '      </div>';
                html += '      <div>';
                html += '        <p class="font-semibold text-gray-900 text-sm">' + escapeHtml(d.username) + '</p>';
                html += '        <p class="text-xs text-gray-400">' + escapeHtml(d.phone) + ' | Age: ' + d.age + ' | ' + d.weight + 'kg</p>';
                html += '      </div>';
                html += '    </div>';
                html += '    <div class="text-right">';
                if (!canDonate) {
                    html += '      <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded-full"><i class="fas fa-clock mr-1"></i>Cooldown</span>';
                } else {
                    html += '      <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"><i class="fas fa-check mr-1"></i>Ready</span>';
                }
                html += '      <p class="text-xs text-gray-400 mt-1">Last: ' + escapeHtml(lastDonation) + '</p>';
                html += '    </div>';
                html += '  </div>';
                html += '</div>';
            });

            donorList.innerHTML = html;
        }

        function selectDonor(el, donorId) {
            document.querySelectorAll('.donor-item').forEach(function(item) {
                item.classList.remove('border-green-500', 'bg-green-50');
                item.classList.add('border-gray-200');
            });
            el.classList.remove('border-gray-200');
            el.classList.add('border-green-500', 'bg-green-50');

            selectedDonorId = donorId;
            document.getElementById('assignDonorId').value = donorId;
            document.getElementById('assignBtn').disabled = false;
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // Donor search
        var donorSearch = document.getElementById('donorSearch');
        if (donorSearch) {
            donorSearch.addEventListener('input', function() {
                var selectedRequest = document.querySelector('.request-item.border-blue-500');
                if (selectedRequest) {
                    var bloodGroup = selectedRequest.getAttribute('data-blood-group');
                    renderDonors(bloodGroup, this.value);
                }
            });
        }

        // Scroll to assignment section and pre-select request
        function scrollToAssign(requestId) {
            var target = document.querySelector('.request-item[data-id="' + requestId + '"]');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function() {
                    selectRequest(target);
                }, 400);
            }
        }
    </script>

</body>
</html>
