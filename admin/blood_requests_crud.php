<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
$users_list = $conn->query("SELECT id, username FROM users ORDER BY username");
$blood_groups_list = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");

if (isset($_POST['add'])) {
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = (int)$_POST['units'];
    $hospital = trim($_POST['hospital']);
    $required_date = $_POST['required_date'];
    $status = $_POST['status'];

    // Get the username for the selected user
    $requester_name = '';
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $users_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_result && $user_result->num_rows > 0) {
        $requester_name = $user_result->fetch_assoc()['username'];
    }
    $user_stmt->close();

    if ($users_id && $blood_groups_id && $units > 0 && $hospital !== '' && $required_date !== '') {
        $stmt = $conn->prepare("INSERT INTO blood_request (users_id, requester_name, blood_groups_id, units, hospital, required_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiisss", $users_id, $requester_name, $blood_groups_id, $units, $hospital, $required_date, $status);
        if ($stmt->execute()) {
            $success = 'Blood request created successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = (int)$_POST['units'];
    $hospital = trim($_POST['hospital']);
    $required_date = $_POST['required_date'];
    $status = $_POST['status'];

    // Get the username for the selected user
    $requester_name = '';
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $users_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_result && $user_result->num_rows > 0) {
        $requester_name = $user_result->fetch_assoc()['username'];
    }
    $user_stmt->close();

    if ($users_id && $blood_groups_id && $units > 0 && $hospital !== '' && $required_date !== '') {
        $stmt = $conn->prepare("UPDATE blood_request SET users_id=?, requester_name=?, blood_groups_id=?, units=?, hospital=?, required_date=?, status=? WHERE id=?");
        $stmt->bind_param("isiisssi", $users_id, $requester_name, $blood_groups_id, $units, $hospital, $required_date, $status, $id);
        if ($stmt->execute()) {
            $success = 'Blood request updated successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Approve action
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Approved' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: blood_requests_crud.php');
    exit;
}

// Reject action
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Rejected' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: blood_requests_crud.php');
    exit;
}

// Assign donor action
if (isset($_POST['assign_donor'])) {
    $request_id = (int)$_POST['request_id'];
    $donor_id = (int)$_POST['donor_id'];

    if ($request_id > 0 && $donor_id > 0) {
        // Verify the request exists and is in a valid state
        $check = $conn->prepare("SELECT id, status, assigned_donor_id FROM blood_request WHERE id = ?");
        $check->bind_param("i", $request_id);
        $check->execute();
        $result = $check->get_result();
        if ($result && $result->num_rows > 0) {
            $req = $result->fetch_assoc();
            if (in_array($req['status'], ['Pending', 'Approved']) && empty($req['assigned_donor_id'])) {
                // Verify donor exists and is available
                $donor_check = $conn->prepare("SELECT id, available_status FROM donor WHERE id = ?");
                $donor_check->bind_param("i", $donor_id);
                $donor_check->execute();
                $donor_result = $donor_check->get_result();
                if ($donor_result && $donor_result->num_rows > 0) {
                    $donor = $donor_result->fetch_assoc();
                    if ($donor['available_status'] === 'Available') {
                        // Assign donor and update status to Assigned
                        $assign = $conn->prepare("UPDATE blood_request SET assigned_donor_id = ?, status = 'Assigned' WHERE id = ?");
                        $assign->bind_param("ii", $donor_id, $request_id);
                        if ($assign->execute()) {
                            // Mark donor as Unavailable after assignment
                            $donorUpdate = $conn->prepare("UPDATE donor SET available_status = 'Unavailable' WHERE id = ?");
                            $donorUpdate->bind_param("i", $donor_id);
                            $donorUpdate->execute();
                            $donorUpdate->close();

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

                            $_SESSION['success'] = 'Donor assigned successfully!';
                        } else {
                            $_SESSION['error'] = 'Error assigning donor: ' . $conn->error;
                        }
                        $assign->close();
                    } else {
                        $_SESSION['error'] = 'Selected donor is not available.';
                    }
                } else {
                    $_SESSION['error'] = 'Donor not found.';
                }
                $donor_check->close();
            } else {
                $_SESSION['error'] = 'Request is already assigned or cannot be assigned (status: ' . htmlspecialchars($req['status']) . ').';
            }
        } else {
            $_SESSION['error'] = 'Blood request not found.';
        }
        $check->close();
    } else {
        $_SESSION['error'] = 'Please select both a blood request and a donor.';
    }
    header('Location: blood_requests_crud.php');
    exit;
}

// Unassign donor action
if (isset($_GET['unassign'])) {
    $id = (int)$_GET['unassign'];
    // Get the assigned donor_id before unassigning
    $getDonor = $conn->prepare("SELECT assigned_donor_id FROM blood_request WHERE id = ? AND assigned_donor_id IS NOT NULL");
    $getDonor->bind_param("i", $id);
    $getDonor->execute();
    $donorRow = $getDonor->get_result()->fetch_assoc();
    $getDonor->close();

    $stmt = $conn->prepare("UPDATE blood_request SET assigned_donor_id = NULL, status = 'Pending' WHERE id = ? AND assigned_donor_id IS NOT NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Restore donor availability to Available
    if ($donorRow && $donorRow['assigned_donor_id']) {
        $restoreDonor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
        $restoreDonor->bind_param("i", $donorRow['assigned_donor_id']);
        $restoreDonor->execute();
        $restoreDonor->close();
    }

    header('Location: blood_requests_crud.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM blood_request WHERE id = $id");
    header('Location: blood_requests_crud.php');
    exit;
}

$stats = [
    'total'     => 0,
    'pending'   => 0,
    'approved'  => 0,
    'completed' => 0
];
try {
    $stats['total']     = $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0;
    $stats['pending']   = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
    $stats['approved']  = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Approved', 'Assigned')")->fetch_assoc()['c'] ?? 0;
    $stats['completed'] = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {}

$requests = [];
$edit_row = null;

$result = $conn->query("
    SELECT br.*, bg.blood_gp_name
    FROM blood_request br
    LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
    ORDER BY br.required_date DESC
");
if ($result && $result->num_rows > 0) {
    $requests = $result->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($requests as $r) {
        if ($r['id'] == $edit_id) {
            $edit_row = $r;
            break;
        }
    }
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

// Pending blood requests for action cards
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
        WHERE r.assigned_donor_id IS NOT NULL AND r.status IN ('Assigned', 'Approved', 'Completed')
        ORDER BY r.required_date DESC
    ");
    if ($result && $result->num_rows > 0) {
        $assigned_requests = $result->fetch_all(MYSQLI_ASSOC);
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

$stats = [
    'total' => $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0,
    'pending' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0,
    'approved' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Approved'")->fetch_assoc()['c'] ?? 0,
    'completed' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Requests CRUD - BloodLife</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <style>
        @keyframes fadeInDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeInUp   { from { opacity:0; transform:translateY( 20px); } to { opacity:1; transform:translateY(0); } }
        .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
        .animate-fade-up   { animation: fadeInUp   0.6s ease-out; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
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
        html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        html.dark .w-64.bg-white { background-color: #1f2937 !important; }
        html.dark header.bg-white, html.dark header.bg-white.border-b { background-color: #1f2937 !important; }
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
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
        html.dark .stat-card:hover { box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 w-full min-w-0 overflow-x-hidden">
        
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="p-8">

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded mb-6"><p class="text-red-700"><?= htmlspecialchars($error) ?></p></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded mb-6"><p class="text-green-700"><?= htmlspecialchars($success) ?></p></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Total Requests</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['total'] ?></h3>
                </div>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Pending</p>
                    <h3 class="text-3xl font-bold mt-2 text-yellow-600"><?= $stats['pending'] ?></h3>
                </div>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Approved</p>
                    <h3 class="text-3xl font-bold mt-2 text-blue-600"><?= $stats['approved'] ?></h3>
                </div>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Completed</p>
                    <h3 class="text-3xl font-bold mt-2 text-green-600"><?= $stats['completed'] ?></h3>
                </div>
            </div>

            <!-- Toggle Form -->
            <div class="mb-8">
                <button onclick="toggleForm()" id="toggleFormBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold px-6 py-3 rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <span>+</span>
                    <span><?= $edit_row ? 'Edit Request' : 'Add New Request' ?></span>
                </button>
            </div>

            <div id="crudForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 <?= $edit_row ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?= $edit_row ? 'Edit Blood Request' : 'New Blood Request' ?></h3>
                    <button onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">User *</label>
                        <select name="users_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select User --</option>
                            <?php if ($users_list): mysqli_data_seek($users_list, 0); while ($u = $users_list->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= (($edit_row['users_id'] ?? 0) == $u['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['username']) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group *</label>
                        <select name="blood_groups_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select --</option>
                            <?php if ($blood_groups_list): mysqli_data_seek($blood_groups_list, 0); while ($bg = $blood_groups_list->fetch_assoc()): ?>
                                <option value="<?= $bg['id'] ?>" <?= (($edit_row['blood_groups_id'] ?? 0) == $bg['id']) ? 'selected' : '' ?>><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Units *</label>
                        <input type="number" name="units" value="<?= htmlspecialchars($edit_row['units'] ?? '') ?>" required min="1" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Hospital *</label>
                        <input type="text" name="hospital" value="<?= htmlspecialchars($edit_row['hospital'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Required Date *</label>
                        <input type="date" name="required_date" value="<?= htmlspecialchars($edit_row['required_date'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status *</label>
                        <select name="status" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <?php foreach (['Pending','Approved','Completed','Rejected'] as $st): ?>
                                <option value="<?= $st ?>" <?= (($edit_row['status'] ?? 'Pending') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="<?= $edit_row ? 'update' : 'add' ?>" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition">
                            <?= $edit_row ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($edit_row): ?>
                            <a href="blood_requests_crud.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
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
                                <a href="blood_requests_crud.php?approve=<?= $pr['id'] ?>" onclick="return confirm('Approve this blood request?')" class="btn-approve flex-1 bg-green-500 hover:bg-green-600 text-white text-center py-2.5 rounded-xl font-semibold text-sm shadow-sm">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </a>
                                <button type="button" onclick="openAssignModal(<?= $pr['id'] ?>)" class="btn-assign flex-1 bg-blue-500 hover:bg-blue-600 text-white text-center py-2.5 rounded-xl font-semibold text-sm shadow-sm transition">
                                    <i class="fas fa-user-plus mr-1"></i>Assign
                                </button>
                                <a href="blood_requests_crud.php?reject=<?= $pr['id'] ?>" onclick="return confirm('Reject this blood request?')" class="btn-reject flex-1 bg-white border-2 border-red-200 text-red-600 hover:bg-red-50 text-center py-2.5 rounded-xl font-semibold text-sm">
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
                                    <p class="text-blue-700 text-sm font-medium">Donors with same blood type: <span id="selectedBloodType" class="font-bold"></span></p>
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
                                            <a href="blood_requests_crud.php?unassign=<?= $asr['id'] ?>" onclick="return confirm('Remove this donor assignment? The request will return to Pending status.')" class="text-red-500 hover:text-red-700 text-xs font-semibold">
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
                                                <button type="button" onclick="openAssignModal(<?= (int)$rr['id'] ?>)"
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
                                            
                    </div>
                    <span class="text-sm text-gray-500">Total: <?= count($requests) ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3">Requester</th>
                                <th class="p-3">Blood Group</th>
                                <th class="p-3">Units</th>
                                <th class="p-3">Hospital</th>
                                <th class="p-3">Required Date</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requests) > 0): ?>
                                <?php foreach ($requests as $r): ?>
                                    <?php
                                    $statusColors = [
                                        'Pending'   => 'bg-yellow-100 text-yellow-700',
                                        'Approved'  => 'bg-blue-100 text-blue-700',
                                        'Completed' => 'bg-green-100 text-green-700',
                                        'Rejected'  => 'bg-red-100 text-red-700',
                                    ];
                                    $sc = $statusColors[$r['status']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <tr class="border-t border-slate-200 hover:bg-gray-50">
                                        <td class="p-3 font-medium">#<?= $r['id'] ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['requester_name'] ?? '-') ?></td>
                                        <td class="p-3"><span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs"><?= htmlspecialchars($r['blood_gp_name'] ?? '-') ?></span></td>
                                        <td class="p-3"><?= (int)$r['units'] ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['hospital']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['required_date']) ?></td>
                                        <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $sc ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                                        <td class="p-3">
                                            <div class="flex gap-2">
                                                <a href="blood_requests_crud.php?edit=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Edit</a>
                                                <a href="blood_requests_crud.php?delete=<?= $r['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm('Delete this request?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="p-8 text-center text-gray-500">No blood requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

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
function toggleForm() {
    document.getElementById('crudForm').classList.toggle('hidden');
}
</script>

<script>
        // Donor Assignment Logic with Blood Compatibility Matching
        var allDonors = <?= json_encode($available_donors) ?>;
        var pendingRequestsData = <?= json_encode($pending_requests) ?>;
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

            // Only show donors with the exact same blood type
            var scored = [];
            allDonors.forEach(function(d) {
                if (d.blood_groups !== bloodGroup) return;
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
                donorList.innerHTML = '<div class="text-center py-6"><div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-2"><i class="fas fa-user-slash text-gray-300"></i></div><p class="text-gray-400 text-sm">No donors with blood type ' + escapeHtml(bloodGroup) + ' available</p><p class="text-gray-300 text-xs mt-1">Check donor availability or registration</p></div>';
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

        // Modal Assign Donor Logic
        var modalSelectedDonorId = null;
        var modalRequestId = null;
        var modalBloodGroup = null;

        // Find assignable request info from PHP data
        var assignableRequests = <?= json_encode($assignable_requests) ?>;

        function openAssignModal(requestId) {
            modalRequestId = requestId;
            modalSelectedDonorId = null;

            // Find request info
            var reqInfo = null;
            for (var i = 0; i < assignableRequests.length; i++) {
                if (assignableRequests[i].id == requestId) {
                    reqInfo = assignableRequests[i];
                    break;
                }
            }

            // If not in assignable list, try pending requests
            if (!reqInfo) {
                for (var j = 0; j < pendingRequestsData.length; j++) {
                    if (pendingRequestsData[j].id == requestId) {
                        reqInfo = pendingRequestsData[j];
                        break;
                    }
                }
            }

            if (!reqInfo) return;

            modalBloodGroup = reqInfo.blood_group;
            document.getElementById('modalRequestInfo').textContent = 'Request #' + requestId + ' — ' + reqInfo.requester_name;
            document.getElementById('modalBloodType').textContent = reqInfo.blood_group + ' (' + reqInfo.units + ' units needed)';
            document.getElementById('modalDonorSearch').value = '';
            document.getElementById('modalAssignBtn').disabled = true;

            renderModalDonors(reqInfo.blood_group, '');

            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
            modalSelectedDonorId = null;
            modalRequestId = null;
        }

        function renderModalDonors(bloodGroup, searchQuery) {
            var donorList = document.getElementById('modalDonorList');
            var noDonors = document.getElementById('modalNoDonors');
            var bestMatchBox = document.getElementById('modalBestMatch');

            // Only show donors with the exact same blood type
            var scored = [];
            allDonors.forEach(function(d) {
                if (d.blood_groups !== bloodGroup) return;
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

            scored.sort(function(a, b) { return b.match.score - a.match.score; });

            if (scored.length === 0) {
                donorList.innerHTML = '';
                noDonors.classList.remove('hidden');
                bestMatchBox.classList.add('hidden');
                return;
            }

            noDonors.classList.add('hidden');

            // Show best match
            var best = scored[0];
            if (best.match.score > 0) {
                bestMatchBox.classList.remove('hidden');
                document.getElementById('modalBestMatchText').textContent =
                    best.donor.username + ' — ' + best.match.reasons.join(', ') + ' (Score: ' + best.match.score + '/100)';
            } else {
                bestMatchBox.classList.add('hidden');
            }

            var html = '';
            scored.forEach(function(item, idx) {
                var d = item.donor;
                var m = item.match;
                var isBest = idx === 0 && m.score > 0;
                var borderColor = isBest ? 'best-match' : '';
                var bestBadge = isBest ? '<span class="ml-2 text-xs font-bold text-green-700 bg-green-200 px-2 py-0.5 rounded-full"><i class="fas fa-star mr-1"></i>Best</span>' : '';
                var barColor = m.score >= 70 ? 'bg-green-500' : m.score >= 40 ? 'bg-yellow-500' : 'bg-gray-300';

                html += '<div class="assign-modal-donor ' + borderColor + '" data-donor-id="' + d.id + '" onclick="selectModalDonor(this, ' + d.id + ')">';
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

            // Auto-select best match
            if (scored.length > 0 && scored[0].match.score > 0) {
                var bestItem = donorList.querySelector('.assign-modal-donor[data-donor-id="' + scored[0].donor.id + '"]');
                if (bestItem) {
                    selectModalDonor(bestItem, scored[0].donor.id);
                }
            }
        }

        function selectModalDonor(el, donorId) {
            document.querySelectorAll('.assign-modal-donor').forEach(function(item) {
                item.classList.remove('selected');
            });
            el.classList.add('selected');
            modalSelectedDonorId = donorId;
            document.getElementById('modalAssignBtn').disabled = false;
        }

        function submitModalAssign() {
            if (!modalRequestId || !modalSelectedDonorId) return;

            // Create and submit a form
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'blood_requests_crud.php';

            var ridInput = document.createElement('input');
            ridInput.type = 'hidden';
            ridInput.name = 'request_id';
            ridInput.value = modalRequestId;
            form.appendChild(ridInput);

            var didInput = document.createElement('input');
            didInput.type = 'hidden';
            didInput.name = 'donor_id';
            didInput.value = modalSelectedDonorId;
            form.appendChild(didInput);

            var submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'assign_donor';
            submitInput.value = '1';
            form.appendChild(submitInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Modal donor search
        var modalDonorSearch = document.getElementById('modalDonorSearch');
        if (modalDonorSearch) {
            modalDonorSearch.addEventListener('input', function() {
                if (modalBloodGroup) {
                    renderModalDonors(modalBloodGroup, this.value);
                }
            });
        }
    </script>

    <!-- Assign Donor Modal -->
    <div id="assignModal" class="assign-modal-overlay" onclick="if(event.target===this)closeAssignModal()">
        <div class="assign-modal">
            <div class="flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Assign Donor</h3>
                        <p class="text-xs text-gray-400" id="modalRequestInfo"></p>
                    </div>
                </div>

<?php if (isset($_GET['auto_assign'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        openAssignModal(<?= (int)$_GET['auto_assign'] ?>);
    }, 500);
});
</script>
<?php endif; ?>
    </main>
</div>
</body>
</html>
