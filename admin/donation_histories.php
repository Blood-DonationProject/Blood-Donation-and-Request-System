<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
$donors_list = $conn->query("SELECT d.id, u.username AS donor_name FROM donor d JOIN users u ON d.user_id = u.id ORDER BY u.username");
$requests_list = $conn->query("SELECT br.id, br.blood_groups_id, br.units, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id ORDER BY br.id DESC LIMIT 100");
$blood_groups_list = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");

if (isset($_POST['add'])) {
    $donor_id = (int)$_POST['donor_id'];
    $request_id = (int)$_POST['request_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = (int)$_POST['units'];
    $donation_date = $_POST['donation_date'];

    // Auto-derive users_id (requester) from the selected blood request
    $users_id = 0;
    if ($request_id > 0) {
        $reqStmt = $conn->prepare("SELECT users_id FROM blood_request WHERE id = ?");
        $reqStmt->bind_param("i", $request_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqRow = $reqResult->fetch_assoc()) {
            $users_id = (int)$reqRow['users_id'];
        }
        $reqStmt->close();
    }

    if ($donor_id && $blood_groups_id && $units > 0 && $donation_date !== '' && $users_id > 0) {
        $stmt = $conn->prepare("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, units, donation_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Completed')");
        $stmt->bind_param("iiiiis", $donor_id, $users_id, $request_id, $blood_groups_id, $units, $donation_date);
        if ($stmt->execute()) {
            $success = 'Action registered successfully.';
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
    $donor_id = (int)$_POST['donor_id'];
    $request_id = (int)$_POST['request_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = (int)$_POST['units'];
    $donation_date = $_POST['donation_date'];

    // Auto-derive users_id (requester) from the selected blood request
    $users_id = 0;
    if ($request_id > 0) {
        $reqStmt = $conn->prepare("SELECT users_id FROM blood_request WHERE id = ?");
        $reqStmt->bind_param("i", $request_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqRow = $reqResult->fetch_assoc()) {
            $users_id = (int)$reqRow['users_id'];
        }
        $reqStmt->close();
    }

    if ($donor_id && $blood_groups_id && $units > 0 && $donation_date !== '' && $users_id > 0) {
        $stmt = $conn->prepare("UPDATE donation_history SET donor_id=?, users_id=?, request_id=?, blood_groups_id=?, units=?, donation_date=? WHERE id=?");
        $stmt->bind_param("iiiiisi", $donor_id, $users_id, $request_id, $blood_groups_id, $units, $donation_date, $id);
        if ($stmt->execute()) {
            $success = 'Action updated successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM donation_history WHERE id = $id");
    header('Location: donation_histories.php');
    exit;
}

$actions = [];
$edit_row = null;
$result = $conn->query("
    SELECT dh.*, u.username AS donor_name, u2.username AS requester_name, bg.blood_gp_name
    FROM donation_history dh
    LEFT JOIN donor d ON dh.donor_id = d.id
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN users u2 ON dh.users_id = u2.id
    LEFT JOIN blood_groups bg ON dh.blood_groups_id = bg.id
    ORDER BY dh.donation_date DESC
");
if ($result && $result->num_rows > 0) {
    $actions = $result->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($actions as $a) {
        if ($a['id'] == $edit_id) {
            $edit_row = $a;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actions - Blood Donation System</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
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
        html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

<div class="flex min-h-screen">

    <!-- Sidebar -->
     <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1">

        <!-- Top Bar -->
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="p-8">

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded mb-6">
                    <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded mb-6">
                    <p class="text-green-700"><?= htmlspecialchars($success) ?></p>
                </div>
            <?php endif; ?>

            <!-- Toggle Button & Form -->
            <div class="mb-8">
                <button onclick="toggleForm()" id="toggleFormBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold px-6 py-3 rounded-xl hover:shadow-lg hover:from-red-700 hover:to-red-800 transition flex items-center gap-2">
                    <span>+</span>
                    <span>Register New Donation</span>
                </button>
            </div>

            <div id="actionForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 <?= $edit_row ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?= $edit_row ? 'Edit Donation' : 'Register New Donation' ?></h3>
                    <button onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Donor *</label>
                        <select name="donor_id" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select Donor --</option>
                            <?php if ($donors_list): mysqli_data_seek($donors_list, 0); while ($d = $donors_list->fetch_assoc()): ?>
                                <option value="<?= $d['id'] ?>" <?= (($edit_row['donor_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['donor_name']) ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Request</label>
                        <select name="request_id"
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="0">-- None --</option>
                            <?php if ($requests_list): mysqli_data_seek($requests_list, 0); while ($req = $requests_list->fetch_assoc()): ?>
                                <option value="<?= $req['id'] ?>" <?= (($edit_row['request_id'] ?? 0) == $req['id']) ? 'selected' : '' ?>>
                                    #<?= $req['id'] ?> (<?= htmlspecialchars($req['blood_gp_name'] ?? '-') ?>, <?= $req['units'] ?> units)
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group *</label>
                        <select name="blood_groups_id" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select --</option>
                            <?php if ($blood_groups_list): mysqli_data_seek($blood_groups_list, 0); while ($bg = $blood_groups_list->fetch_assoc()): ?>
                                <option value="<?= $bg['id'] ?>" <?= (($edit_row['blood_groups_id'] ?? 0) == $bg['id']) ? 'selected' : '' ?>><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Units *</label>
                        <input type="number" name="units" value="<?= htmlspecialchars($edit_row['units'] ?? '') ?>" required min="1"
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Donation Date *</label>
                        <input type="date" name="donation_date" value="<?= htmlspecialchars($edit_row['donation_date'] ?? date('Y-m-d')) ?>" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="<?= $edit_row ? 'update' : 'add' ?>"
                            class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg hover:from-red-700 hover:to-red-800 transition">
                            <?= $edit_row ? 'Update Donation' : 'Register Donation' ?>
                        </button>
                        <?php if ($edit_row): ?>
                            <a href="donation_histories.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Actions Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800" data-i18n="donation_histories">Donation Histories</h3>
                        <p class="text-sm text-gray-500">All registered actions and activities.</p>
                    </div>
                    <span class="text-sm text-gray-500">Total: <?= count($actions) ?></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3" >Donor Name</th>
                                <th class="p-3" >Requester Name</th>                         
                                <th class="p-3" >Blood Group</th>
                                <th class="p-3" >Units</th>
                                <th class="p-3" >Donation Date</th>
                                <th class="p-3" >Status</th>
                                <th class="p-3" >Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($actions) > 0): ?>
                                <?php foreach ($actions as $a): ?>
                                <tr class="border-t border-slate-200 hover:bg-gray-50">
                                    <td class="p-3 font-medium">#<?= $a['id'] ?></td>
                                    <td class="p-3"><?= htmlspecialchars($a['donor_name'] ?? '-') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($a['requester_name'] ?? '-') ?></td>                                    
                                    <td class="p-3"><span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs"><?= htmlspecialchars($a['blood_gp_name'] ?? '-') ?></span></td>
                                    <td class="p-3"><?= (int)$a['units'] ?></td>
                                    <td class="p-3"><?= htmlspecialchars($a['donation_date']) ?></td>
                                    <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-green-100 text-green-700"><?= htmlspecialchars($a['status']) ?></span></td>
                                    <td class="p-3">
                                        <div class="flex gap-2">
                                            <a href="donation_histories.php?edit=<?= $a['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold" data-i18n="edit">Edit</a>
                                            <a href="donation_histories.php?delete=<?= $a['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm('Delete this action?')" data-i18n="delete">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-gray-500" data-i18n="no_donation_histories">No donation histories found.</td>
                                </tr>
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
        const form = document.getElementById('actionForm');
        form.classList.toggle('hidden');
    }
</script>

</body>
</html>
