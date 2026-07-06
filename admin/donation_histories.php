<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$conn->query("CREATE TABLE IF NOT EXISTS request_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT,
    donor_name VARCHAR(255),
    blood_group VARCHAR(10),
    action_type VARCHAR(50),
    action_date DATE,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$error = '';
$success = '';

if (isset($_POST['add'])) {
    $donor_name = trim($_POST['donor_name']);
    $blood_group = trim($_POST['blood_group']);
    $action_type = $_POST['action_type'];
    $action_date = $_POST['action_date'];
    $remarks = trim($_POST['remarks']);

    if ($donor_name === '' || $action_type === '' || $action_date === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO request_actions (donor_name, blood_group, action_type, action_date, remarks) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $donor_name, $blood_group, $action_type, $action_date, $remarks);
        if ($stmt->execute()) {
            $success = 'Action registered successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    }
}

if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $donor_name = trim($_POST['donor_name']);
    $blood_group = trim($_POST['blood_group']);
    $action_type = $_POST['action_type'];
    $action_date = $_POST['action_date'];
    $remarks = trim($_POST['remarks']);

    $stmt = $conn->prepare("UPDATE request_actions SET donor_name=?, blood_group=?, action_type=?, action_date=?, remarks=? WHERE id=?");
    $stmt->bind_param('sssssi', $donor_name, $blood_group, $action_type, $action_date, $remarks, $id);
    if ($stmt->execute()) {
        $success = 'Action updated successfully.';
    } else {
        $error = 'Error: ' . $conn->error;
    }
    $stmt->close();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM request_actions WHERE id = $id");
    header('Location: donation_histories.php');
    exit;
}

$actions = [];
$edit_row = null;
$result = $conn->query("SELECT * FROM request_actions ORDER BY created_at DESC");
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
    <script src="../assets/js/translations.js"></script>
    <script src="../assets/js/i18n.js"></script>
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
    <div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <span class="text-3xl">🩸</span>
                <div>
                    <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500">Dashboard</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700  hover:bg-gray-100 rounded-lg transition">
                    <span>📊</span>
                    <span data-i18n="overview">Overview</span>
                </a>
                <a href="donors.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700  hover:bg-gray-100 rounded-lg transition">
                    <span>👥</span>
                    <span data-i18n="donors">Donors</span>
                </a>
                <a href="donation_histories.php" class="flex items-center space-x-3 px-4 py-3  bg-red-50 text-red-700 rounded-lg font-semibold">
                    <span>⚡</span>
                    <span data-i18n="donation_histories">Donation Histories</span>
                </a>
                <a href="hospitals.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>🏥</span>
                    <span data-i18n="hospitals">Hospitals</span>
                </a>
                <a href="requests.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>📋</span>
                    <span data-i18n="blood_requests">Blood Requests</span>
                </a>
            </nav>

        <div class="p-4 border-t border-gray-200">
            <a href="logout.php" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">
                Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1">

        <!-- Top Bar -->
        <header class="bg-white border-b px-8 py-4 flex justify-between items-center sticky top-0 z-30">
            <div>
                <h2 class="text-3xl font-bold text-red-800" data-i18n="donation_histories_title">Donation Histories</h2>
                <p class="text-gray-500 mt-1">Track and manage all donation-related actions.</p>
            </div>
            <div class="flex items-center gap-4">
                <select class="theme-toggle-select" aria-label="Theme">
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
                <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
                    <option value="en">EN</option>
                    <option value="my">MY</option>
                </select>
            </div>
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
                        <a href="logout.php" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
                    </div>
                </div>
            </div>
        </header>

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
                    <span>Register New Action</span>
                </button>
            </div>

            <div id="actionForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 <?= $edit_row ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?= $edit_row ? 'Edit Action' : 'Register New Action' ?></h3>
                    <button onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Donor Name *</label>
                        <input type="text" name="donor_name" value="<?= htmlspecialchars($edit_row['donor_name'] ?? '') ?>" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group</label>
                        <select name="blood_group"
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select --</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= (($edit_row['blood_group'] ?? '') === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Action Type *</label>
                        <select name="action_type" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select --</option>
                            <?php foreach (['ACCEPTED','REJECTED','DONATED','CANCELLED','PENDING'] as $at): ?>
                                <option value="<?= $at ?>" <?= (($edit_row['action_type'] ?? '') === $at) ? 'selected' : '' ?>><?= $at ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Action Date *</label>
                        <input type="date" name="action_date" value="<?= htmlspecialchars($edit_row['action_date'] ?? date('Y-m-d')) ?>" required
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div class="md:col-span-2 lg:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Remarks</label>
                        <input type="text" name="remarks" value="<?= htmlspecialchars($edit_row['remarks'] ?? '') ?>" placeholder="Optional notes..."
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="<?= $edit_row ? 'update' : 'add' ?>"
                            class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg hover:from-red-700 hover:to-red-800 transition">
                            <?= $edit_row ? 'Update Action' : 'Register Action' ?>
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
                                <th class="p-3" data-i18n="donor_name_col">Donor Name</th>
                                <th class="p-3" data-i18n="blood_group_col">Blood Group</th>
                                <th class="p-3" data-i18n="actions_col">Action Type</th>
                                <th class="p-3" data-i18n="date_col">Action Date</th>
                                <th class="p-3" data-i18n="remark">Remarks</th>
                                <th class="p-3" data-i18n="date_col">Created</th>
                                <th class="p-3" data-i18n="actions_col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($actions) > 0): ?>
                                <?php foreach ($actions as $a): ?>
                                <tr class="border-t border-slate-200 hover:bg-gray-50">
                                    <td class="p-3 font-medium">#<?= $a['id'] ?></td>
                                    <td class="p-3"><?= htmlspecialchars($a['donor_name']) ?></td>
                                    <td class="p-3">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-red-100 text-red-700">
                                            <?= htmlspecialchars($a['blood_group'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <?php
                                            $at = $a['action_type'];
                                            $badge = match ($at) {
                                                'ACCEPTED'  => 'bg-green-100 text-green-700',
                                                'REJECTED'  => 'bg-red-100 text-red-700',
                                                'DONATED'   => 'bg-blue-100 text-blue-700',
                                                'CANCELLED' => 'bg-orange-100 text-orange-700',
                                                'PENDING'   => 'bg-yellow-100 text-yellow-700',
                                                default     => 'bg-gray-100 text-gray-700',
                                            };
                                        ?>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badge ?>">
                                            <?= htmlspecialchars($at) ?>
                                        </span>
                                    </td>
                                    <td class="p-3"><?= htmlspecialchars($a['action_date']) ?></td>
                                    <td class="p-3 text-gray-500 max-w-[200px] truncate"><?= htmlspecialchars($a['remarks'] ?: '-') ?></td>
                                    <td class="p-3 text-gray-500"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
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
                                    <td colspan="8" class="p-8 text-center text-gray-500" data-i18n="no_donation_histories">No donation histories found.</td>
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

<script>
(function() {
  var KEY = 'bloodlife-theme';
  function getTheme() { return localStorage.getItem(KEY) || 'light'; }
  function apply(t) {
    if (t === 'dark') document.documentElement.classList.add('dark');
    else document.documentElement.classList.remove('dark');
    document.querySelectorAll('.theme-toggle-select').forEach(function(s){ s.value = t; });
  }
  apply(getTheme());
  document.querySelectorAll('.theme-toggle-select').forEach(function(s) {
    s.value = getTheme();
    s.addEventListener('change', function() {
      localStorage.setItem(KEY, this.value);
      apply(this.value);
    });
  });
})();
</script>

</body>
</html>
