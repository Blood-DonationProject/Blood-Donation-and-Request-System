<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);



if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $last_donation_date = empty($_POST['last_donation_date']) ? null : $_POST['last_donation_date'];
    $today = date('Y-m-d');

    if ($last_donation_date && $last_donation_date > $today) {
        $error = 'Last Donation Date cannot be a future date.';
    } else {
        // Auto-calculate Available / Unavailable based on 3-month rule
        if ($last_donation_date) {
            $lastDonated = new DateTime($last_donation_date);
            $threeMonthsLater = (clone $lastDonated)->modify('+3 months');
            $todayObj = new DateTime('today');
            $available_status = ($todayObj >= $threeMonthsLater) ? 'Available' : 'Unavailable';
        } else {
            $available_status = 'Available';
        }

        $stmt = $conn->prepare("UPDATE donor SET last_donation_date=?, available_status=? WHERE id=?");
        $stmt->bind_param("ssi", $last_donation_date, $available_status, $id);
        if ($stmt->execute()) {
            $success = 'Donor updated successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM donor WHERE id = $id");
    header('Location: donor_crud.php');
    exit;
}

// Assign donor to blood request
if (isset($_POST['assign_donor'])) {
    $request_id = (int)$_POST['request_id'];
    $donor_id = (int)$_POST['donor_id'];

    if ($request_id > 0 && $donor_id > 0) {
        // Verify the request exists and is in a valid state
        $check = $conn->prepare("SELECT br.id, br.users_id, br.status, br.assigned_donor_id, br.hospital, br.required_date, br.units, bg.blood_gp_name AS blood_group_name, u.email AS requester_email, u.username AS requester_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id LEFT JOIN users u ON br.users_id = u.id WHERE br.id = ?");
        $check->bind_param("i", $request_id);
        $check->execute();
        $result = $check->get_result();
        if ($result && $result->num_rows > 0) {
            $req = $result->fetch_assoc();
            if ($req['status'] === 'Expired' || strtotime($req['required_date']) < strtotime('today')) {
                $_SESSION['error'] = 'Cannot assign donor: this blood request has expired.';
            } else if (in_array($req['status'], ['Pending', 'Approved', 'Assigned', 'Accepted', 'Rejected'])) {
                // Verify donor exists and is available
                $donor_check = $conn->prepare("SELECT id, user_id, available_status FROM donor WHERE id = ?");
                $donor_check->bind_param("i", $donor_id);
                $donor_check->execute();
                $donor_result = $donor_check->get_result();
                if ($donor_result && $donor_result->num_rows > 0) {
                    $donor = $donor_result->fetch_assoc();
                    if (isset($req['users_id']) && isset($donor['user_id']) && $req['users_id'] == $donor['user_id']) {
                        $_SESSION['error'] = 'The requester cannot be assigned as a donor for their own blood request.';
                    } else if ($donor['available_status'] === 'Available') {
                        // Assign donor and update status to Assigned
                        $assign = $conn->prepare("UPDATE blood_request SET assigned_donor_id = ?, status = 'Assigned' WHERE id = ?");
                        $assign->bind_param("ii", $donor_id, $request_id);
                        if ($assign->execute()) {
                            // If there was an old donor, free them
                            if (!empty($req['assigned_donor_id']) && $req['assigned_donor_id'] != $donor_id) {
                                $freeOld = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
                                $freeOld->bind_param("i", $req['assigned_donor_id']);
                                $freeOld->execute();
                                $freeOld->close();
                            }
                            // Mark donor as Unavailable after assignment
                            $donorUpdate = $conn->prepare("UPDATE donor SET available_status = 'Unavailable' WHERE id = ?");
                            $donorUpdate->bind_param("i", $donor_id);
                            $donorUpdate->execute();
                            $donorUpdate->close();

                            // Create donor_assignments record
                            $assign_admin_id = $_SESSION['user_id'] ?? 1; // get admin user id
                            $assignStmt = $conn->prepare("INSERT INTO donor_assignments (request_id, donor_id, assigned_by, status) VALUES (?, ?, ?, 'Assigned')");
                            $assignStmt->bind_param("iii", $request_id, $donor_id, $assign_admin_id);
                            $assignStmt->execute();
                            $assignment_id = $conn->insert_id;
                            $assignStmt->close();

                            // Get donor's user_id and email for notification
                            $donorUser = $conn->prepare("SELECT d.user_id, u.email AS donor_email, u.username FROM donor d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
                            $donorUser->bind_param("i", $donor_id);
                            $donorUser->execute();
                            $donorUserRow = $donorUser->get_result()->fetch_assoc();
                            $donorUser->close();

                            require_once __DIR__ . '/../includes/notification_helper.php';
                            require_once __DIR__ . '/../includes/mailer.php';

                            $donorEmailRes = null;
                            if ($donorUserRow) {
                                $notifMsg = "You have been assigned as a donor for a blood request. Blood Group: " . ($req['blood_group_name'] ?? 'Blood') . " | Hospital: " . ($req['hospital'] ?? '') . " | Required Date: " . ($req['required_date'] ?? '');
                                $notifType = 'Assignment';
                                $notifTitle = 'New Blood Request Assignment';
                                $donorNotifId = create_notification($conn, $donorUserRow['user_id'], $notifType, $notifTitle, $notifMsg, $request_id, $assignment_id, $donor_id, $req['users_id']);
                                
                                // Fail-safe decoupled email to donor
                                $donorEmailRes = send_donor_assignment_email($donorUserRow['user_id'], [
                                    'id'             => $request_id,
                                    'blood_group'    => $req['blood_group_name'] ?? 'Blood',
                                    'hospital'       => $req['hospital'] ?? '',
                                    'units'          => $req['units'] ?? 1,
                                    'required_date'  => $req['required_date'] ?? '',
                                    'requester_name' => $req['requester_name'] ?? 'Patient'
                                ], $assignment_id, $donorNotifId);
                            }

                            // Send notification to Requester
                            $reqEmailRes = null;
                            if (!empty($req['users_id'])) {
                                $reqNotifMsg = "Good news! A donor has been assigned to your blood request #" . $request_id . ".";
                                $reqNotifId = create_notification($conn, $req['users_id'], 'Assignment', 'Donor Assigned', $reqNotifMsg, $request_id, $assignment_id, $donor_id, $req['users_id']);
                                
                                // Fail-safe decoupled email to requester
                                $reqEmailRes = send_requester_donor_assigned_email($req['users_id'], [
                                    'id'       => $request_id,
                                    'hospital' => $req['hospital'] ?? ''
                                ], $donorUserRow['username'] ?? 'Volunteer Donor', $reqNotifId);
                            }

                            // Notify Admin
                            $adminConfirmMsg = "Donor assigned successfully to Request #{$request_id}.";
                            notify_admins($conn, 'Assignment', 'Donor assigned successfully', $adminConfirmMsg, $request_id, $assignment_id, $donor_id);

                            $emailSuccess = (!empty($donorEmailRes['success']) && ($reqEmailRes === null || !empty($reqEmailRes['success'])));
                            $_SESSION['success'] = format_action_feedback('Donor assigned', $emailSuccess);
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
    header('Location: donor_crud.php');
    exit;
}

$donors = [];
$edit_row = null;

$result = $conn->query("
    SELECT d.*, u.username, u.email
    FROM donor d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.id DESC
");
if ($result && $result->num_rows > 0) {
    $donors = $result->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($donors as $d) {
        if ($d['id'] == $edit_id) {
            $edit_row = $d;
            break;
        }
    }
}

$roleCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$hasRoleColumn = ($roleCheck && $roleCheck->num_rows > 0);
$userFilter = $hasRoleColumn ? "WHERE role = 'User'" : "WHERE username != 'admin'";
$users_list = $conn->query("SELECT id, username FROM users {$userFilter} ORDER BY username");
$stats = [
    'total' => $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0,
    'available' => $conn->query("SELECT COUNT(*) AS c FROM donor WHERE available_status='Available'")->fetch_assoc()['c'] ?? 0,
    'unavailable' => $conn->query("SELECT COUNT(*) AS c FROM donor WHERE available_status='Unavailable'")->fetch_assoc()['c'] ?? 0,
    'pending' => $conn->query("
        SELECT COUNT(*) AS c 
        FROM blood_request r
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ")->fetch_assoc()['c'] ?? 0,
];

// Fetch pending blood requests for assign modal (non-expired)
$pendingRequests = $conn->query("
    SELECT br.id, br.users_id, br.requester_name, br.units, br.hospital, br.required_date, br.status,
           bg.blood_gp_name
    FROM blood_request br
    JOIN blood_groups bg ON br.blood_groups_id = bg.id
    LEFT JOIN donor d ON COALESCE(br.assigned_donor_id, br.donor_id) = d.id
    LEFT JOIN users u_donor ON d.user_id = u_donor.id
    WHERE br.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
      AND br.required_date >= CURDATE()
      AND (COALESCE(br.assigned_donor_id, br.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ORDER BY br.required_date ASC
");

// Fetch donation history grouped by donor
$donationHistory = [];
$dhResult = $conn->query("
    SELECT dh.donor_id, dh.donation_date, dh.units, dh.status,
           bg.blood_gp_name,
           u.username AS donor_name,
           br.requester_name, br.hospital
    FROM donation_history dh
    JOIN blood_groups bg ON dh.blood_groups_id = bg.id
    JOIN donor d ON dh.donor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN blood_request br ON dh.request_id = br.id
    ORDER BY dh.donation_date DESC
");
if ($dhResult && $dhResult->num_rows > 0) {
    while ($dhRow = $dhResult->fetch_assoc()) {
        $donationHistory[$dhRow['donor_id']][] = $dhRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donors CRUD - BloodLife</title>
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
    </style>
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50:not(.sidebar):not(nav):not(nav *) { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        
        
        html.dark .bg-white:not(.sidebar):not(nav) { background-color: #1f2937 !important; }
        html.dark .text-gray-900:not(.sidebar *):not(nav *), html.dark .text-gray-800:not(.sidebar *):not(nav *) { color: #f3f4f6 !important; }
        html.dark .text-gray-700:not(.sidebar *):not(nav *) { color: #d1d5db !important; }
        html.dark .text-gray-600:not(.sidebar *):not(nav *) { color: #9ca3af !important; }
        html.dark .text-gray-500:not(.sidebar *):not(nav *) { color: #9ca3af !important; }
        html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        html.dark label { color: #d1d5db !important; }
        html.dark .bg-gray-50:not(.sidebar *):not(nav *), html.dark .bg-gray-100:not(.sidebar *):not(nav *) { background-color: #374151 !important; }
        html.dark thead.bg-gray-50 { background-color: #111827 !important; }
        html.dark .border-gray-200:not(.sidebar):not(nav), html.dark .border-2.border-gray-200:not(.sidebar):not(nav), html.dark .border:not(.sidebar):not(nav) { border-color: #4b5563 !important; }
        html.dark .border-t:not(.sidebar *) { border-color: #374151 !important; }
        html.dark .bg-red-50:not(.sidebar *) { background-color: rgba(220,38,38,0.15) !important; }
        html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
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
    <main class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="p-4 md:p-8 overflow-x-auto flex-1">

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded mb-6"><p class="text-red-700"><?= htmlspecialchars($error) ?></p></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded mb-6"><p class="text-green-700"><?= htmlspecialchars($success) ?></p></div>
            <?php endif; ?>



            <!-- Filters -->
            <div class="flex flex-wrap items-end gap-4 mb-6">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
                    <input id="searchInput" type="text" placeholder="Search by name, phone, or address..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                </div>
                <div class="w-40">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group</label>
                    <select id="filterBloodGroup" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                        <option value="">All</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                            <option value="<?= $bg ?>"><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-36">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Gender</label>
                    <select id="filterGender" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                        <option value="">All</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="w-40">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select id="filterStatus" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                        <option value="">All</option>
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>
                <button onclick="clearFilters()" class="px-4 py-2.5 text-sm text-gray-600 border-2 border-gray-200 rounded-xl hover:bg-gray-100 transition font-semibold whitespace-nowrap">Clear Filters</button>
            </div>



            <?php if ($edit_row): ?>
            <div id="crudForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800">Edit Donor</h3>
                    <a href="donor_crud.php" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</a>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Donor ID / User ID</label>
                        <input type="text" value="D-<?= $edit_row['id'] ?> / U-<?= $edit_row['user_id'] ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name</label>
                        <input type="text" value="<?= htmlspecialchars($edit_row['username'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" value="<?= htmlspecialchars($edit_row['email'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Gender</label>
                        <input type="text" value="<?= htmlspecialchars($edit_row['gender'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" value="<?= htmlspecialchars($edit_row['date_of_birth'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Age</label>
                        <input type="number" value="<?= htmlspecialchars($edit_row['age'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group</label>
                        <input type="text" value="<?= htmlspecialchars($edit_row['blood_groups'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Weight (kg)</label>
                        <input type="number" value="<?= htmlspecialchars($edit_row['weight'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Address / Township</label>
                        <input type="text" value="<?= htmlspecialchars($edit_row['address'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                        <input type="text" value="<?= htmlspecialchars($edit_row['phone'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Last Donation Date</label>
                        <input type="date" name="last_donation_date" value="<?= htmlspecialchars($edit_row['last_donation_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status (Auto-calculated)</label>
                        <input type="text" value="<?= htmlspecialchars($edit_row['available_status'] ?? 'Available') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed outline-none" title="Automatically calculated based on Last Donation Date">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="update" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition">
                            Update
                        </button>
                        <a href="donor_crud.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Donor Records</h3>
                        <p class="text-sm text-gray-500">All registered donors.</p>
                    </div>
                    <span class="text-sm text-gray-500">Total: <?= count($donors) ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3 hidden">ID</th>
                                <th class="p-3">Username</th>                                
                                <th class="p-3">Gender</th>
                                <th class="p-3">Date of birth</th>
                                <th class="p-3">Age</th>
                                <th class="p-3">Weight</th>
                                <th class="p-3">Blood Group</th>                              
                                <th class="p-3">Phone</th>                               
                                <th class="p-3">Address</th>                                
                                <th class="p-3">Last Donation Date</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($donors) > 0): ?>
                                <?php foreach ($donors as $d): ?>
                                    <?php $availColor = ($d['available_status'] ?? 'Available') === 'Available' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>
                                    <tr class="donor-row border-t border-slate-200 hover:bg-gray-50" data-bloodgroup="<?= htmlspecialchars($d['blood_groups']) ?>" data-gender="<?= htmlspecialchars($d['gender']) ?>" data-status="<?= htmlspecialchars($d['available_status'] ?? 'Available') ?>">
                                        <td class="p-3 font-medium hidden">#<?= $d['id'] ?></td>
                                        <td class="p-3"><?= htmlspecialchars($d['username'] ?? '-') ?></td>                                        
                                        <td class="p-3"><?= htmlspecialchars($d['gender']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($d['date_of_birth']) ?></td>
                                        <td class="p-3"><?= (int)$d['age'] ?></td>
                                        <td class="p-3"><?= htmlspecialchars($d['weight']) ?></td>
                                        <td class="p-3"><span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs"><?= htmlspecialchars($d['blood_groups']) ?></span></td>
                                        <td class="p-3"><?= htmlspecialchars($d['phone']) ?></td>                                        
                                        <td class="p-3"><?= htmlspecialchars($d['address']) ?></td>                                        
                                        <td class="p-3"><?= htmlspecialchars($d['last_donation_date'] ?? '-') ?></td>
                                        <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $availColor ?>"><?= htmlspecialchars($d['available_status']) ?></span></td>
                                        <td class="p-3">
                                            <div class="flex gap-2 flex-wrap">
                                                <a href="donor_crud.php?edit=<?= $d['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Edit</a>
                                                <button onclick="openHistoryModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['username'] ?? '') ?>')" class="text-purple-600 hover:text-purple-800 font-semibold text-xs">History</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="p-8 text-center text-gray-500">No donors found.</td></tr>
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

function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('mobileOverlay');
    sidebar.classList.toggle('hidden');
    sidebar.classList.toggle('fixed');
    sidebar.classList.toggle('inset-0');
    sidebar.classList.toggle('z-50');
    sidebar.classList.toggle('md:relative');
    sidebar.classList.remove('md:flex');
    overlay.classList.toggle('hidden');
}
document.getElementById('mobileOverlay')?.addEventListener('click', function() {
    toggleMobileSidebar();
});
const searchInput = document.getElementById('searchInput');
const filterBloodGroup = document.getElementById('filterBloodGroup');
const filterGender = document.getElementById('filterGender');
const filterStatus = document.getElementById('filterStatus');
const rows = document.querySelectorAll('.donor-row');

function applyFilters() {
    const q = searchInput.value.toLowerCase();
    const bg = filterBloodGroup.value;
    const gender = filterGender.value;
    const status = filterStatus.value;
    let visible = 0;
    rows.forEach(row => {
        const matchSearch = !q || row.textContent.toLowerCase().includes(q);
        const matchBg = !bg || row.dataset.bloodgroup === bg;
        const matchGender = !gender || row.dataset.gender === gender;
        const matchStatus = !status || row.dataset.status === status;
        const show = matchSearch && matchBg && matchGender && matchStatus;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
}

searchInput.addEventListener('keyup', applyFilters);
filterBloodGroup.addEventListener('change', applyFilters);
filterGender.addEventListener('change', applyFilters);
filterStatus.addEventListener('change', applyFilters);

function clearFilters() {
    searchInput.value = '';
    filterBloodGroup.value = '';
    filterGender.value = '';
    filterStatus.value = '';
    applyFilters();
}
</script>

<!-- Donation History Modal -->
<div id="historyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div>
                <h3 class="text-xl font-bold text-gray-800">Donation History</h3>
                <p id="historyDonorName" class="text-sm text-gray-500"></p>
                <p id="historyTotalDonations" class="text-sm font-semibold text-blue-600 mt-1"></p>
            </div>
            <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div id="historyContent"></div>
        </div>
    </div>
</div>

<!-- Assign Donor Modal -->
<div id="assignModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <div>
                <h3 class="text-xl font-bold text-gray-800">Assign Donor to Request</h3>
                <p id="assignDonorInfo" class="text-sm text-gray-500"></p>
            </div>
            <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="assign_donor" value="1">
            <input type="hidden" name="donor_id" id="assignDonorId">
            <?php if ($pendingRequests && $pendingRequests->num_rows > 0): ?>
            <div class="space-y-3 mb-6">
                <label class="block text-sm font-semibold text-gray-700">Select Blood Request</label>
                <select name="request_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    <option value="">-- Select Request --</option>
                    <?php mysqli_data_seek($pendingRequests, 0); while ($req = $pendingRequests->fetch_assoc()): ?>
                        <option value="<?= $req['id'] ?>" data-users-id="<?= $req['users_id'] ?>">
                            #<?= $req['id'] ?> — <?= htmlspecialchars($req['requester_name'] ?? 'N/A') ?> | <?= htmlspecialchars($req['blood_gp_name']) ?> | <?= $req['units'] ?> unit(s) | <?= htmlspecialchars($req['hospital']) ?> | <?= htmlspecialchars($req['required_date']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold py-3 rounded-xl hover:shadow-lg transition">Assign Donor</button>
                <button type="button" onclick="closeAssignModal()" class="flex-1 bg-gray-200 text-gray-700 font-semibold py-3 rounded-xl hover:bg-gray-300 transition">Cancel</button>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-500">
                <p>No pending blood requests available.</p>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="closeAssignModal()" class="bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-xl hover:bg-gray-300 transition">Close</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div id="mobileOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"></div>

<script>
var donationHistory = <?= json_encode($donationHistory) ?>;

function openHistoryModal(donorId, donorName) {
    document.getElementById('historyDonorName').textContent = donorName;
    var content = document.getElementById('historyContent');
    var records = donationHistory[donorId];
    var totalDonations = (records && records.length) ? records.length : 0;
    
    document.getElementById('historyTotalDonations').textContent = 'Total Donations: ' + totalDonations;
    
    if (records && records.length > 0) {
        var html = '<table class="w-full text-sm border-collapse"><thead><tr class="bg-gray-50 text-gray-600"><th class="p-3 text-left">Date</th><th class="p-3 text-left">Blood Group</th><th class="p-3 text-left">Units</th><th class="p-3 text-left">Requester</th><th class="p-3 text-left">Hospital</th><th class="p-3 text-left">Status</th></tr></thead><tbody>';
        records.forEach(function(r) {
            html += '<tr class="border-t border-gray-100 hover:bg-gray-50"><td class="p-3">' + (r.donation_date || '-') + '</td><td class="p-3"><span class="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-bold">' + (r.blood_gp_name || '') + '</span></td><td class="p-3">' + r.units + '</td><td class="p-3">' + (r.requester_name || '-') + '</td><td class="p-3">' + (r.hospital || '-') + '</td><td class="p-3"><span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-semibold">' + r.status + '</span></td></tr>';
        });
        html += '</tbody></table>';
        content.innerHTML = html;
    } else {
        content.innerHTML = '<div class="text-center py-8 text-gray-500"><p>No donation history found for this donor.</p></div>';
    }
    document.getElementById('historyModal').classList.remove('hidden');
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.add('hidden');
}

function openAssignModal(donorId, donorName, bloodGroup, donorUserId) {
    document.getElementById('assignDonorId').value = donorId;
    document.getElementById('assignDonorInfo').textContent = donorName + ' (' + bloodGroup + ')';
    
    var select = document.querySelector('select[name="request_id"]');
    if (select) {
        Array.from(select.options).forEach(function(opt) {
            if (opt.value === "") return;
            if (opt.dataset.usersId == donorUserId) {
                opt.style.display = 'none';
                opt.disabled = true;
            } else {
                opt.style.display = '';
                opt.disabled = false;
            }
        });
        select.value = ""; 
    }

    document.getElementById('assignModal').classList.remove('hidden');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

document.getElementById('historyModal').addEventListener('click', function(e) {
    if (e.target === this) closeHistoryModal();
});
document.getElementById('assignModal').addEventListener('click', function(e) {
    if (e.target === this) closeAssignModal();
});

// Delete Modal Logic
function openDeleteModal(url) {
    document.getElementById('confirmDeleteBtn').href = url;
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
    document.getElementById('deleteConfirmModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.remove('flex');
    document.getElementById('deleteConfirmModal').classList.add('hidden');
}
</script>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
        <div class="p-8 text-center space-y-6">
            <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div>
                <h2 class="font-bold text-2xl text-gray-900 mb-2">Delete Record</h2>
                <p class="text-gray-500">Are you sure you want to delete this? This action cannot be undone.</p>
            </div>
        </div>
        <div class="px-8 pb-8 flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
            <a href="#" id="confirmDeleteBtn" onclick="this.classList.add('opacity-50', 'pointer-events-none');" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition text-center shadow-md flex items-center justify-center">Delete</a>
        </div>
    </div>
</div>

</body>
</html>
