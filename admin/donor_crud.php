<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if (isset($_POST['add'])) {
    $user_id = (int)$_POST['user_id'];
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $age = (int)$_POST['age'];
    $blood_groups = trim($_POST['blood_groups']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $weight = (float)$_POST['weight'];
    $last_donation_date = $_POST['last_donation_date'] ?: null;
    $available_status = $_POST['available_status'];

    if ($user_id && $blood_groups !== '' && $phone !== '' && $address !== '' && $weight > 0) {
        $stmt = $conn->prepare("INSERT INTO donor (user_id, gender, date_of_birth, age, blood_groups, phone, address, weight, last_donation_date, available_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississsdss", $user_id, $gender, $date_of_birth, $age, $blood_groups, $phone, $address, $weight, $last_donation_date, $available_status);
        if ($stmt->execute()) {
            $success = 'Donor created successfully.';
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
    $user_id = (int)$_POST['user_id'];
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $age = (int)$_POST['age'];
    $blood_groups = trim($_POST['blood_groups']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $weight = (float)$_POST['weight'];
    $last_donation_date = $_POST['last_donation_date'] ?: null;
    $available_status = $_POST['available_status'];

    if ($user_id && $blood_groups !== '' && $phone !== '' && $address !== '' && $weight > 0) {
        $stmt = $conn->prepare("UPDATE donor SET user_id=?, gender=?, date_of_birth=?, age=?, blood_groups=?, phone=?, address=?, weight=?, last_donation_date=?, available_status=? WHERE id=?");
        $stmt->bind_param("ississsdssi", $user_id, $gender, $date_of_birth, $age, $blood_groups, $phone, $address, $weight, $last_donation_date, $available_status, $id);
        if ($stmt->execute()) {
            $success = 'Donor updated successfully.';
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
    $conn->query("DELETE FROM donor WHERE id = $id");
    header('Location: donor_crud.php');
    exit;
}

$donors = [];
$edit_row = null;

$result = $conn->query("
    SELECT d.*, u.username
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

$users_list = $conn->query("SELECT id, username FROM users ORDER BY username");
$stats = [
    'total' => $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0,
    'available' => $conn->query("SELECT COUNT(*) AS c FROM donor WHERE available_status='Available'")->fetch_assoc()['c'] ?? 0,
    'unavailable' => $conn->query("SELECT COUNT(*) AS c FROM donor WHERE available_status='Unavailable'")->fetch_assoc()['c'] ?? 0,
];

// Fetch available donors for the dedicated section
$availableDonors = $conn->query("
    SELECT u.username AS donor_name, d.blood_groups, d.phone, d.address, d.available_status
    FROM donor d
    JOIN users u ON d.user_id = u.id
    WHERE d.available_status = 'Available'
    ORDER BY d.created_at DESC
");
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
                    <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500">CRUD Panel</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>📊</span> <span data-i18n="overview">Overview</span>
            </a>
            <a href="users_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>👥</span> <span>Users</span>
            </a>
            <a href="donor_crud.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                <span>🩸</span> <span>Donors</span>
            </a>
            
            
            <a href="donation_history_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>⚡</span> <span>Donation History</span>
            </a>
            <a href="blood_requests_crud.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <span>📋</span> <span>Blood Requests</span>
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
                <h2 class="text-3xl font-bold text-red-800">Manage Donors</h2>
                <p class="text-gray-500 mt-1">Manage and monitor the blood donor network.</p>
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Total Donors</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['total'] ?></h3>
                </div>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Available</p>
                    <h3 class="text-3xl font-bold mt-2 text-green-600"><?= $stats['available'] ?></h3>
                </div>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Unavailable</p>
                    <h3 class="text-3xl font-bold mt-2 text-red-600"><?= $stats['unavailable'] ?></h3>
                </div>
            </div>

            <!-- Search -->
            <div class="w-96 mb-6">
                <input id="searchInput" type="text" placeholder="Search by name, blood group, or phone..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
            </div>

            <!-- Toggle Form -->
            <div class="mb-8">
                <button onclick="toggleForm()" id="toggleFormBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold px-6 py-3 rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <span>+</span>
                    <span><?= $edit_row ? 'Edit Donor' : 'Add New Donor' ?></span>
                </button>
            </div>

            <div id="crudForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 <?= $edit_row ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?= $edit_row ? 'Edit Donor' : 'New Donor' ?></h3>
                    <button onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">User *</label>
                        <?php if ($edit_row): ?>
                            <input type="hidden" name="user_id" value="<?= $edit_row['user_id'] ?>">
                            <input type="text" value="<?= htmlspecialchars($edit_row['username'] ?? '') ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 bg-gray-100 text-gray-600 cursor-not-allowed">
                        <?php else: ?>
                            <select name="user_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                                <option value="">-- Select User --</option>
                                <?php if ($users_list): mysqli_data_seek($users_list, 0); while ($u = $users_list->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Gender *</label>
                        <select name="gender" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= (($edit_row['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth *</label>
                        <input type="date" name="date_of_birth" value="<?= htmlspecialchars($edit_row['date_of_birth'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Age *</label>
                        <input type="number" name="age" value="<?= htmlspecialchars($edit_row['age'] ?? '') ?>" required min="1" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group *</label>
                        <select name="blood_groups" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="">-- Select --</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= (($edit_row['blood_groups'] ?? '') === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Phone *</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($edit_row['phone'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Weight (kg) *</label>
                        <input type="number" step="0.01" name="weight" value="<?= htmlspecialchars($edit_row['weight'] ?? '') ?>" required min="1" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Last Donation Date</label>
                        <input type="date" name="last_donation_date" value="<?= htmlspecialchars($edit_row['last_donation_date'] ?? '') ?>" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status *</label>
                        <select name="available_status" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <?php foreach (['Available','Unavailable'] as $st): ?>
                                <option value="<?= $st ?>" <?= (($edit_row['available_status'] ?? 'Available') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Address *</label>
                        <textarea name="address" required rows="2" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none"><?= htmlspecialchars($edit_row['address'] ?? '') ?></textarea>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="<?= $edit_row ? 'update' : 'add' ?>" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition">
                            <?= $edit_row ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($edit_row): ?>
                            <a href="donor_crud.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

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
                                <th class="p-3">ID</th>
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
                                    <tr class="donor-row border-t border-slate-200 hover:bg-gray-50">
                                        <td class="p-3 font-medium">#<?= $d['id'] ?></td>
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
                                            <div class="flex gap-2">
                                                <a href="donor_crud.php?edit=<?= $d['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Edit</a>
                                                <a href="donor_crud.php?delete=<?= $d['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm('Delete this donor?')">Delete</a>
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

            <!-- Available Donors Section -->
            <div class="mt-8 bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                            Available Donors
                        </h3>
                        <p class="text-sm text-gray-500">Donors currently available to donate blood.</p>
                    </div>
                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                        <?= $stats['available'] ?> available
                    </span>
                </div>
                <?php if ($availableDonors && $availableDonors->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-green-50 text-green-800">
                                <th class="p-3">#</th>
                                <th class="p-3">Donor Name</th>
                                <th class="p-3">Blood Group</th>
                                <th class="p-3">Phone</th>
                                <th class="p-3">Address</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($row = $availableDonors->fetch_assoc()): ?>
                            <tr class="border-t border-slate-200 hover:bg-green-50 transition">
                                <td class="p-3 text-gray-500"><?= $i++ ?></td>
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-xs font-bold text-red-700">
                                            <?= strtoupper(substr(htmlspecialchars($row['donor_name']), 0, 1)) ?>
                                        </div>
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($row['donor_name']) ?></span>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs">
                                        <?= htmlspecialchars($row['blood_groups']) ?>
                                    </span>
                                </td>
                                <td class="p-3"><?= htmlspecialchars($row['phone']) ?></td>
                                <td class="p-3 max-w-[200px] truncate"><?= htmlspecialchars($row['address']) ?></td>
                                <td class="p-3">
                                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                        <?= htmlspecialchars($row['available_status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-user-slash text-3xl text-gray-300 mb-3"></i>
                    <p>No donors are currently available.</p>
                </div>
                <?php endif; ?>
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
const rows = document.querySelectorAll('.donor-row');
searchInput.addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
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
