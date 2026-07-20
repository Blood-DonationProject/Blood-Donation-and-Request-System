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

// Latest 5 blood requests for Recent section
$recent_requests = [];
try {
    $rr_result = $conn->query("
        SELECT r.id, r.requester_name, bg.blood_gp_name AS blood_group,
               r.units, r.required_date, r.status, r.assigned_donor_id
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        ORDER BY r.id DESC
        LIMIT 5
    ");
    if ($rr_result && $rr_result->num_rows > 0) {
        $recent_requests = $rr_result->fetch_all(MYSQLI_ASSOC);
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
    <script src="../assets/js/translations.js"></script>
    <script src="../assets/js/i18n.js"></script>
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

                <!-- Welcome Hero Section -->
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-red-600 via-red-500 to-red-700 shadow-lg mb-8 animate-slide-in">
                    <!-- Background Decorations -->
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white opacity-5 rounded-full -translate-y-32 translate-x-32"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-white opacity-5 rounded-full translate-y-24 -translate-x-24"></div>
                    <div class="absolute top-1/2 right-1/4 w-32 h-32 bg-white opacity-5 rounded-full -translate-y-1/2"></div>

                    <div class="relative flex flex-col md:flex-row items-center justify-between p-8 md:p-10">
                        <!-- Left Content -->
                        <div class="flex-1 mb-6 md:mb-0">
                            <div class="flex items-center space-x-2 mb-3">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-white bg-opacity-20 text-white text-xs font-semibold backdrop-blur-sm">
                                    <i class="fas fa-shield-alt mr-1.5"></i>Admin Panel
                                </span>
                            </div>
                            <h1 class="text-3xl md:text-4xl font-extrabold text-white mb-2 tracking-tight">
                                Welcome, <?= $admin_name ?>
                            </h1>
                            <p class="text-red-100 text-base md:text-lg mb-4 max-w-lg">
                                Manage blood donations, donor assignments, and requests all from one place.
                            </p>
                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <div class="flex items-center space-x-2 bg-white bg-opacity-15 backdrop-blur-sm rounded-xl px-4 py-2.5">
                                    <i class="fas fa-calendar-alt text-white"></i>
                                    <span class="text-white font-medium" id="welcomeDate"><?= $current_date ?></span>
                                </div>
                                <div class="flex items-center space-x-2 bg-white bg-opacity-15 backdrop-blur-sm rounded-xl px-4 py-2.5">
                                    <i class="fas fa-clock text-white"></i>
                                    <span class="text-white font-medium" id="welcomeTime"><?= $current_time ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-28 h-28 md:w-36 md:h-36 bg-white bg-opacity-15 backdrop-blur-sm rounded-3xl flex items-center justify-center border border-white border-opacity-20 shadow-2xl">
                                <div class="text-center">
                                    <i class="fas fa-hand-holding-heart text-white text-4xl md:text-5xl mb-2 drop-shadow-lg"></i>
                                    <p class="text-white text-xs font-bold tracking-wider uppercase opacity-80">BloodLife</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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

                                <div id="matchInfoBox" class="mb-4 hidden">
                                    <div class="p-3 bg-green-50 border border-green-200 rounded-xl">
                                        <div class="flex items-center mb-2">
                                            <i class="fas fa-magic text-green-600 mr-2"></i>
                                            <p class="text-green-700 text-sm font-bold">Best Match Found</p>
                                        </div>
                                        <p class="text-green-600 text-xs" id="matchInfoText"></p>
                                    </div>
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

                <!-- Recent Blood Requests -->
                <?php if (count($recent_requests) > 0): ?>
                <div class="mb-8">
                    <div class="bg-white rounded-2xl border border-pink-100 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-5 border-b border-pink-50">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    <i class="fas fa-clock-rotate-left text-red-500 mr-2"></i>Recent Blood Requests
                                </h3>
                                <p class="text-sm text-gray-400 mt-1">Latest 5 blood requests from users</p>
                            </div>
                            <a href="requests.php" class="text-sm font-semibold text-red-600 hover:text-red-700 transition">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-pink-50 border-b border-pink-100">
                                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Requester</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Blood Group</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Units</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Required Date</th>
                                        <th class="px-6 py-3 text-left font-semibold text-gray-600">Status</th>
                                        <th class="px-6 py-3 text-center font-semibold text-gray-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_requests as $rr): ?>
                                    <tr class="border-b border-pink-50 hover:bg-red-50/30 transition">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-9 h-9 bg-red-100 text-red-600 rounded-lg flex items-center justify-center font-bold text-xs">
                                                    <?= strtoupper(substr($rr['requester_name'] ?? 'U', 0, 2)) ?>
                                                </div>
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($rr['requester_name'] ?? 'Unknown') ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                                <?= htmlspecialchars($rr['blood_group'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-gray-800"><?= (int)$rr['units'] ?></td>
                                        <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($rr['required_date']) ?></td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $statusColors = [
                                                'Pending'   => 'bg-yellow-100 text-yellow-700',
                                                'Approved'  => 'bg-blue-100 text-blue-700',
                                                'Completed' => 'bg-green-100 text-green-700',
                                                'Rejected'  => 'bg-red-100 text-red-700',
                                            ];
                                            $statusColor = $statusColors[$rr['status']] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                                                <?= htmlspecialchars($rr['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="requests.php?view=<?= (int)$rr['id'] ?>"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                                <?php if (empty($rr['assigned_donor_id']) && in_array($rr['status'], ['Pending', 'Approved'])): ?>
                                                <button type="button" onclick="scrollToAssign(<?= (int)$rr['id'] ?>)"
                                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-500 text-white hover:bg-red-600 transition">
                                                    <i class="fas fa-user-plus mr-1"></i>Assign Donor
                                                </button>
                                                <?php endif; ?>
                                            </div>
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
        // Donor Assignment Logic with Blood Compatibility Matching
        var allDonors = <?= json_encode($available_donors) ?>;
        var selectedDonorId = null;

        // Blood compatibility chart: which blood types can donate TO each recipient
        var bloodCompatibility = {
            'O-': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
            'O+': ['O+', 'A+', 'B+', 'AB+'],
            'A-': ['A-', 'A+', 'AB-', 'AB+'],
            'A+': ['A+', 'AB+'],
            'B-': ['B-', 'B+', 'AB-', 'AB+'],
            'B+': ['B+', 'AB+'],
            'AB-': ['AB-', 'AB+'],
            'AB+': ['AB+']
        };

        // Calculate match score for a donor (higher = better match)
        function calculateMatchScore(donor, requestBloodGroup) {
            var score = 0;
            var reasons = [];

            // 1. Exact blood type match (40 points)
            if (donor.blood_groups === requestBloodGroup) {
                score += 40;
                reasons.push('Exact blood type match');
            }

            // 2. Blood compatibility (30 points if compatible)
            var compatibleTypes = bloodCompatibility[donor.blood_groups] || [];
            if (compatibleTypes.indexOf(requestBloodGroup) !== -1) {
                score += 30;
                reasons.push('Compatible blood type');
            }

            // 3. Readiness / cooldown status (20 points)
            var canDonate = true;
            var daysSinceLastDonation = 999;
            if (donor.last_donation_date) {
                var lastDate = new Date(donor.last_donation_date);
                var now = new Date();
                daysSinceLastDonation = Math.floor((now - lastDate) / (1000 * 60 * 60 * 24));
                canDonate = daysSinceLastDonation >= 56;
            }
            if (canDonate) {
                score += 20;
                reasons.push('Ready to donate');
            }

            // 4. Time since last donation bonus (up to 10 points)
            // Longer gap = more time for recovery = better
            var timeBonus = Math.min(10, Math.floor(daysSinceLastDonation / 14));
            score += timeBonus;
            if (timeBonus > 5) reasons.push('Extended recovery time');

            return { score: score, reasons: reasons, canDonate: canDonate, daysSince: daysSinceLastDonation };
        }

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
            var matchInfoBox = document.getElementById('matchInfoBox');

            // Score and filter donors
            var scored = [];
            allDonors.forEach(function(d) {
                var match = calculateMatchScore(d, bloodGroup);
                if (!searchQuery) {
                    scored.push({ donor: d, match: match });
                } else {
                    var q = searchQuery.toLowerCase();
                    if (d.username.toLowerCase().indexOf(q) !== -1 || d.phone.toLowerCase().indexOf(q) !== -1) {
                        scored.push({ donor: d, match: match });
                    }
                }
            });

            // Sort by match score descending (best match first)
            scored.sort(function(a, b) { return b.match.score - a.match.score; });

            if (scored.length === 0) {
                matchInfoBox.classList.add('hidden');
                donorList.innerHTML = '<div class="text-center py-6"><div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-2"><i class="fas fa-user-slash text-gray-300"></i></div><p class="text-gray-400 text-sm">No matching donors available</p><p class="text-gray-300 text-xs mt-1">Try a different blood type or check donor availability</p></div>';
                return;
            }

            // Show best match info
            var best = scored[0];
            if (best.match.score > 0) {
                matchInfoBox.classList.remove('hidden');
                var infoText = escapeHtml(best.donor.username) + ' — ' + best.match.reasons.join(', ') + ' (Score: ' + best.match.score + '/100)';
                document.getElementById('matchInfoText').textContent = infoText;
            } else {
                matchInfoBox.classList.add('hidden');
            }

            var html = '';
            scored.forEach(function(item, idx) {
                var d = item.donor;
                var m = item.match;
                var isBest = idx === 0 && m.score > 0;
                var borderColor = isBest ? 'border-green-500 bg-green-50' : 'border-gray-200';
                var bestBadge = isBest ? '<span class="ml-2 text-xs font-bold text-green-700 bg-green-200 px-2 py-0.5 rounded-full"><i class="fas fa-star mr-1"></i>Best Match</span>' : '';

                // Score bar color
                var barColor = m.score >= 70 ? 'bg-green-500' : m.score >= 40 ? 'bg-yellow-500' : 'bg-gray-300';

                html += '<div class="donor-item p-3 rounded-xl border-2 ' + borderColor + ' hover:border-green-300 cursor-pointer transition" data-donor-id="' + d.id + '" onclick="selectDonor(this, ' + d.id + ')">';
                html += '  <div class="flex items-start justify-between">';
                html += '    <div class="flex items-center space-x-3">';
                html += '      <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center font-bold text-xs">';
                html += '        ' + d.username.substring(0, 2).toUpperCase();
                html += '      </div>';
                html += '      <div>';
                html += '        <p class="font-semibold text-gray-900 text-sm">' + escapeHtml(d.username) + bestBadge + '</p>';
                html += '        <p class="text-xs text-gray-400">' + escapeHtml(d.phone) + ' | Age: ' + d.age + ' | ' + d.weight + 'kg</p>';
                html += '        <p class="text-xs text-gray-400 mt-0.5">Last donation: ' + escapeHtml(m.daysSince < 999 ? m.daysSince + ' days ago' : 'Never') + '</p>';
                html += '      </div>';
                html += '    </div>';
                html += '    <div class="text-right flex flex-col items-end">';
                if (!m.canDonate) {
                    html += '      <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded-full"><i class="fas fa-clock mr-1"></i>Cooldown</span>';
                } else {
                    html += '      <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"><i class="fas fa-check mr-1"></i>Ready</span>';
                }
                html += '      <div class="mt-1.5 flex items-center gap-1">';
                html += '        <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden"><div class="h-full ' + barColor + ' rounded-full" style="width:' + m.score + '%"></div></div>';
                html += '        <span class="text-[10px] font-bold text-gray-500">' + m.score + '</span>';
                html += '      </div>';
                html += '    </div>';
                html += '  </div>';
                html += '</div>';
            });

            donorList.innerHTML = html;

            // Auto-select the best match
            if (scored.length > 0 && scored[0].match.score > 0) {
                var bestItem = donorList.querySelector('.donor-item[data-donor-id="' + scored[0].donor.id + '"]');
                if (bestItem) {
                    selectDonor(bestItem, scored[0].donor.id);
                }
            }
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
