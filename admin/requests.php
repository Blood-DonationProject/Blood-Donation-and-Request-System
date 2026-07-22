<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
$users_list = $conn->query("SELECT id, username FROM users ORDER BY username");
$blood_groups_list = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");

// Add
if (isset($_POST['add'])) {
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = max(1, (int)$_POST['units']);
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

// Update
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = max(1, (int)$_POST['units']);
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

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM blood_request WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: requests.php');
    exit;
}

// Fetch data
$requesters = [];
$data = $conn->query("
    SELECT br.id, br.blood_groups_id, br.units, br.hospital, br.required_date, br.status,
           br.users_id, br.requester_name, bg.blood_gp_name
    FROM blood_request br
    LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
    ORDER BY br.required_date DESC
");
if ($data && $data->num_rows > 0) {
    $requesters = $data->fetch_all(MYSQLI_ASSOC);
}

// Stats
$stats = [
    'total'     => $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0,
    'pending'   => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0,
    'approved'  => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Approved'")->fetch_assoc()['c'] ?? 0,
    'completed' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0,
];

// Edit row
$edit_row = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($requesters as $r) {
        if ($r['id'] == $edit_id) {
            $edit_row = $r;
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
    <title>BloodLife - Blood Requests</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <style>
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
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
        html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
        html.dark .stat-card:hover { box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900">

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <span class="text-3xl">🩸</span>
                <div>
                    <h1 class="font-bold text-lg text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500">CRUD Panel</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>📊</span> <span data-i18n="overview">Overview</span>
            </a>
            <a href="users_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>👤</span> <span>Users</span>
            </a>
            <a href="requests.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                <span>📋</span> <span>Blood Requests</span>
            </a>
            <a href="donation_history_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>⚡</span> <span>Donation History</span>
            </a>
            <a href="donor_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>🩸</span> <span>Donors</span>
            </a>
        </nav>
        <div class="p-4 border-t border-gray-200">
            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1">
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

            <!-- Search -->
            <div class="w-96 mb-6">
                <input id="searchInput" type="text" placeholder="Search by username, blood type, or hospital..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
            </div>

            <!-- Toggle Form -->
            <div class="mb-8">
                <button onclick="toggleForm()" id="toggleFormBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold px-6 py-3 rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <span>+</span>
                    <span><?= $edit_row ? 'Edit Request' : 'Add New Request' ?></span>
                </button>
            </div>

            <!-- CRUD Form -->
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
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Requester *</label>
                        <select name="users_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">Select user</option>
                            <?php while ($u = $users_list->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= (($edit_row['users_id'] ?? '') == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type *</label>
                        <select name="blood_groups_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">Select blood type</option>
                            <?php while ($bg = $blood_groups_list->fetch_assoc()): ?>
                                <option value="<?= $bg['id'] ?>" <?= (($edit_row['blood_groups_id'] ?? '') == $bg['id']) ? 'selected' : '' ?>><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Units *</label>
                        <input type="number" name="units" min="1" value="<?= htmlspecialchars($edit_row['units'] ?? '1') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
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
                            <a href="requests.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Blood Request Records</h3>
                        <p class="text-sm text-gray-500">All blood requests submitted by users.</p>
                    </div>
                    <span class="text-sm text-gray-500">Total: <?= count($requesters) ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3">Requester</th>
                                <th class="p-3">Blood Type</th>
                                <th class="p-3">Units</th>
                                <th class="p-3">Hospital</th>
                                <th class="p-3">Required Date</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requesters) > 0): ?>
                                <?php foreach ($requesters as $r): ?>
                                    <?php
                                    $status = $r['status'] ?? 'Pending';
                                    $statusClasses = [
                                        'Pending'   => 'bg-yellow-100 text-yellow-700',
                                        'Approved'  => 'bg-blue-100 text-blue-700',
                                        'Completed' => 'bg-green-100 text-green-700',
                                        'Rejected'  => 'bg-red-100 text-red-700',
                                    ];
                                    $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <tr class="requester-row border-t border-slate-200 hover:bg-gray-50">
                                        <td class="p-3 font-medium">#<?= $r['id'] ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['requester_name'] ?? '-') ?></td>
                                        <td class="p-3">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-red-100 text-red-700">
                                                <?= htmlspecialchars($r['blood_gp_name'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td class="p-3"><?= htmlspecialchars($r['units']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['hospital']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['required_date']) ?></td>
                                        <td class="p-3">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <div class="flex gap-2">
                                                <a href="requests.php?edit=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Edit</a>
                                                <a href="requests.php?delete=<?= $r['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm('Delete this request?')">Delete</a>
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
const searchInput = document.getElementById('searchInput');
const rows = document.querySelectorAll('.requester-row');
searchInput.addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

</body>
</html>
