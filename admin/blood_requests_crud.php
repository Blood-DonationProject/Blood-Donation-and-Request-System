<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
$users_list = $conn->query("SELECT id, username FROM users ORDER BY username");
$blood_groups_list = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");

if (isset($_POST['add'])) {
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = (int)$_POST['units'];
    $hospital = trim($_POST['hospital']);
    $required_date = $_POST['required_date'];
    $status = $_POST['status'];

    if ($users_id && $blood_groups_id && $units > 0 && $hospital !== '' && $required_date !== '') {
        $stmt = $conn->prepare("INSERT INTO blood_request (users_id, blood_groups_id, units, hospital, required_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $users_id, $blood_groups_id, $units, $hospital, $required_date, $status);
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

    if ($users_id && $blood_groups_id && $units > 0 && $hospital !== '' && $required_date !== '') {
        $stmt = $conn->prepare("UPDATE blood_request SET users_id=?, blood_groups_id=?, units=?, hospital=?, required_date=?, status=? WHERE id=?");
        $stmt->bind_param("iiisssi", $users_id, $blood_groups_id, $units, $hospital, $required_date, $status, $id);
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

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM blood_request WHERE id = $id");
    header('Location: blood_requests_crud.php');
    exit;
}

$requests = [];
$edit_row = null;

$result = $conn->query("
    SELECT br.*, u.username AS requester_name, bg.blood_gp_name
    FROM blood_request br
    JOIN users u ON br.users_id = u.id
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Requests CRUD - BloodLife</title>
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
    <div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <span class="text-3xl">🩸</span>
                <div>
                    <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500">CRUD Panel</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>📊</span> <span data-i18n="overview">Overview</span>
            </a>
            <a href="blood_requests_crud.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                <span>🩸</span> <span>Blood Requests</span>
            </a>
            <a href="users_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>👤</span> <span>Users</span>
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
        <header class="bg-white border-b px-8 py-4 flex justify-between items-center sticky top-0 z-30">
            <div>
                <h2 class="text-3xl font-bold text-red-800">Blood Requests CRUD</h2>
                <p class="text-gray-500 mt-1">Create, read, update, delete blood requests.</p>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
                <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
                    <option value="en">EN</option>
                    <option value="my">MY</option>
                </select>
                <div class="relative" id="adminMenu">
                    <div class="flex items-center gap-2 cursor-pointer" onclick="toggleAdminDropdown()">
                        <div class="w-10 h-10 bg-gradient-to-br from-red-400 to-red-600 text-white rounded-full flex items-center justify-center font-bold">
                            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                        </div>
                        <span class="font-medium"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                    </div>
                    <div id="adminDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-600 text-white rounded-full flex items-center justify-center font-bold text-lg">
                                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-3">
                            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

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

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Blood Request Records</h3>
                        <p class="text-sm text-gray-500">All blood requests in the system.</p>
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
