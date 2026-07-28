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

// Assign donor to blood request
if (isset($_POST['assign_donor'])) {
    $donorId = (int)$_POST['donor_id'];
    $requestId = (int)$_POST['request_id'];
    if ($donorId > 0 && $requestId > 0) {
        $stmt = $conn->prepare("UPDATE blood_request SET assigned_donor_id = ?, status = 'Approved' WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param("ii", $donorId, $requestId);
        if ($stmt->execute()) {
            $success = 'Donor assigned to blood request successfully.';
        } else {
            $error = 'Error assigning donor.';
        }
        $stmt->close();
    }
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
    'pending' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0,
];

// Fetch pending blood requests for assign modal
$pendingRequests = $conn->query("
    SELECT br.id, br.requester_name, br.units, br.hospital, br.required_date, br.status,
           bg.blood_gp_name
    FROM blood_request br
    JOIN blood_groups bg ON br.blood_groups_id = bg.id
    WHERE br.status = 'Pending'
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
                                    <tr class="donor-row border-t border-slate-200 hover:bg-gray-50" data-bloodgroup="<?= htmlspecialchars($d['blood_groups']) ?>" data-gender="<?= htmlspecialchars($d['gender']) ?>" data-status="<?= htmlspecialchars($d['available_status'] ?? 'Available') ?>">
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
                                            <div class="flex gap-2 flex-wrap">
                                                <a href="donor_crud.php?edit=<?= $d['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Edit</a>
                                                <button onclick="openHistoryModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['username'] ?? '') ?>')" class="text-purple-600 hover:text-purple-800 font-semibold text-xs">History</button>
                                                <?php if (($d['available_status'] ?? '') === 'Available'): ?>
                                                <button onclick="openAssignModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['username'] ?? '') ?>', '<?= htmlspecialchars($d['blood_groups'] ?? '') ?>')" class="text-green-600 hover:text-green-800 font-semibold text-xs">Assign</button>
                                                <?php endif; ?>
                                                <a href="donor_crud.php?delete=<?= $d['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold text-xs" onclick="return confirm('Delete this donor?')">Delete</a>
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
function toggleForm() {
    document.getElementById('crudForm').classList.toggle('hidden');
}
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
                        <option value="<?= $req['id'] ?>">
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

function openAssignModal(donorId, donorName, bloodGroup) {
    document.getElementById('assignDonorId').value = donorId;
    document.getElementById('assignDonorInfo').textContent = donorName + ' (' + bloodGroup + ')';
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
</script>

</body>
</html>
