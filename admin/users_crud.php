<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

// Check if role column exists
$roleCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$hasRoleColumn = ($roleCheck && $roleCheck->num_rows > 0);

// Handle status toggle
if (isset($_POST['toggle_status'])) {
    $id = (int)$_POST['id'];
    $newStatus = $_POST['new_status'];
    if ($newStatus === 'Active' || $newStatus === 'Inactive') {
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $newStatus, $id);
        if ($stmt->execute()) {
            $success = "User status updated to {$newStatus}.";
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
        header('Location: users_crud.php');
        exit;
    }
}

if (isset($_POST['add'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $status = $_POST['status'];

    if ($username !== '' && $_POST['password'] !== '') {
        if ($hasRoleColumn) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $password, $role, $status);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $password, $status);
        }
        if ($stmt->execute()) {
            $success = 'User created successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Username and password are required.';
    }
}

if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    if ($username !== '') {
        if (!empty(trim($_POST['password']))) {
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            if ($hasRoleColumn) {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=?, role=?, status=? WHERE id=?");
                $stmt->bind_param("sssssi", $username, $email, $password, $role, $status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, password=?, status=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $password, $status, $id);
            }
        } else {
            if ($hasRoleColumn) {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, status=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $role, $status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, status=? WHERE id=?");
                $stmt->bind_param("sssi", $username, $email, $status, $id);
            }
        }
        if ($stmt->execute()) {
            $success = 'User updated successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Username is required.';
    }
}

$users = [];
$edit_row = null;

$result = $conn->query("SELECT * FROM users ORDER BY id DESC");
if ($result && $result->num_rows > 0) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ($u['id'] == $edit_id) {
            $edit_row = $u;
            break;
        }
    }
}

$stats = [
    'total' => $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0,
    'users' => 0,
    'admins' => 0,
    'active' => $conn->query("SELECT COUNT(*) AS c FROM users WHERE status='Active'")->fetch_assoc()['c'] ?? 0,
    'inactive' => $conn->query("SELECT COUNT(*) AS c FROM users WHERE status='Inactive'")->fetch_assoc()['c'] ?? 0,
    'pending' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0,
];
if ($hasRoleColumn) {
    $stats['users'] = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='User'")->fetch_assoc()['c'] ?? 0;
    $stats['admins'] = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='Admin'")->fetch_assoc()['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users CRUD - BloodLife</title>
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
            <div class="grid grid-cols-1 <?= $hasRoleColumn ? 'md:grid-cols-4' : 'md:grid-cols-3' ?> gap-6 mb-8">
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Total Users</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['total'] ?></h3>
                </div>
                <?php if ($hasRoleColumn): ?>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Admins</p>
                    <h3 class="text-3xl font-bold mt-2 text-purple-600"><?= $stats['admins'] ?></h3>
                </div>
                <?php endif; ?>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Active Users</p>
                    <h3 class="text-3xl font-bold mt-2 text-green-600"><?= $stats['active'] ?></h3>
                </div>
                <div class="bg-white rounded-xl border p-5 stat-card">
                    <p class="text-gray-500 text-sm">Inactive Users</p>
                    <h3 class="text-3xl font-bold mt-2 text-red-600"><?= $stats['inactive'] ?></h3>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <input id="searchInput" type="text" placeholder="Search by username, email, or name..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                </div>
                <div class="flex gap-4">
                    <?php if ($hasRoleColumn): ?>
                    <select id="roleFilter" class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                        <option value="">All Roles</option>
                        <option value="Admin">Admin</option>
                        <option value="User">User</option>
                    </select>
                    <?php endif; ?>
                    <select id="statusFilter" class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Toggle Form -->
            <div class="mb-8">
                <button onclick="toggleForm()" id="toggleFormBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold px-6 py-3 rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <span>+</span>
                    <span><?= $edit_row ? 'Edit User' : 'Add New User' ?></span>
                </button>
            </div>

            <div id="crudForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 <?= $edit_row ? '' : 'hidden' ?>">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?= $edit_row ? 'Edit User' : 'New User' ?></h3>
                    <button onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Username *</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($edit_row['username'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($edit_row['email'] ?? '') ?>" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Password <?= $edit_row ? '(leave blank to keep)' : '*' ?></label>
                        <input type="password" name="password" value="" <?= $edit_row ? '' : 'required' ?> class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <?php if ($hasRoleColumn): ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Role *</label>
                        <select name="role" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <?php foreach (['Admin','User'] as $role): ?>
                                <option value="<?= $role ?>" <?= (($edit_row['role'] ?? 'User') === $role) ? 'selected' : '' ?>><?= $role ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status *</label>
                        <select name="status" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <?php foreach (['Active','Inactive'] as $st): ?>
                                <option value="<?= $st ?>" <?= (($edit_row['status'] ?? 'Active') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="<?= $edit_row ? 'update' : 'add' ?>" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition">
                            <?= $edit_row ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($edit_row): ?>
                            <a href="users_crud.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">User Records</h3>
                        <p class="text-sm text-gray-500">All registered users.</p>
                    </div>
                    <span class="text-sm text-gray-500">Total: <span id="filteredCount"><?= count($users) ?></span></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3">Name</th>
                                <th class="p-3">Username</th>
                                <th class="p-3">Email</th>
                                <?php if ($hasRoleColumn): ?>
                                <th class="p-3">Role</th>
                                <?php endif; ?>
                                <th class="p-3">Status</th>
                                <th class="p-3">Created</th>
                                <th class="p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $u): ?>
                                    <?php
                                    $roleBadges = ['Admin'=>'bg-purple-100 text-purple-700','User'=>'bg-green-100 text-green-700'];
                                    $statusColor = ($u['status'] ?? 'Active') === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                    $displayName = $u['name'] ?? $u['username'] ?? '-';
                                    ?>
                                    <tr class="user-row border-t border-slate-200 hover:bg-gray-50" data-role="<?= htmlspecialchars($u['role'] ?? 'User') ?>" data-status="<?= htmlspecialchars($u['status'] ?? 'Active') ?>">
                                        <td class="p-3 font-medium">#<?= $u['id'] ?></td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-xs font-bold text-red-700">
                                                    <?= strtoupper(substr(htmlspecialchars($displayName), 0, 1)) ?>
                                                </div>
                                                <span class="font-medium"><?= htmlspecialchars($displayName) ?></span>
                                            </div>
                                        </td>
                                        <td class="p-3"><?= htmlspecialchars($u['username']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                        <?php if ($hasRoleColumn): ?>
                                        <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $roleBadges[$u['role'] ?? 'User'] ?? 'bg-gray-100 text-gray-700' ?>"><?= htmlspecialchars($u['role'] ?? 'User') ?></span></td>
                                        <?php endif; ?>
                                        <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusColor ?>"><?= htmlspecialchars($u['status'] ?? 'Active') ?></span></td>
                                        <td class="p-3 text-gray-500"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td class="p-3">
                                            <div class="flex gap-2 items-center">
                                                <button onclick="viewUser(<?= htmlspecialchars(json_encode($u)) ?>)" class="text-gray-600 hover:text-gray-800 font-semibold" title="View Details">View</button>
                                                <a href="users_crud.php?edit=<?= $u['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold" title="Edit User">Edit</a>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to <?= ($u['status'] ?? 'Active') === 'Active' ? 'deactivate' : 'activate' ?> this user?')">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= ($u['status'] ?? 'Active') === 'Active' ? 'Inactive' : 'Active' ?>">
                                                    <button type="submit" name="toggle_status" class="<?= ($u['status'] ?? 'Active') === 'Active' ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800' ?> font-semibold" title="<?= ($u['status'] ?? 'Active') === 'Active' ? 'Deactivate' : 'Activate' ?>"><?= ($u['status'] ?? 'Active') === 'Active' ? 'Deactivate' : 'Activate' ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= $hasRoleColumn ? 8 : 7 ?>" class="p-8 text-center text-gray-500">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- View User Modal -->
<div id="viewUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-800">User Details</h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4 mb-6">
                <div id="modalAvatar" class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center text-2xl font-bold text-red-700"></div>
                <div>
                    <h4 id="modalName" class="text-lg font-bold text-gray-800"></h4>
                    <p id="modalUsername" class="text-sm text-gray-500"></p>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-500 text-sm">Email</span>
                    <span id="modalEmail" class="font-medium text-gray-800"></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-500 text-sm">Role</span>
                    <span id="modalRole" class="font-semibold text-sm"></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-500 text-sm">Status</span>
                    <span id="modalStatus" class="font-semibold text-sm"></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-500 text-sm">Created</span>
                    <span id="modalCreated" class="text-gray-800"></span>
                </div>
            </div>
        </div>
        <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
            <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">Close</button>
            <a id="modalEditLink" href="#" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition">Edit User</a>
        </div>
    </div>
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

// View User Modal
function viewUser(user) {
    const modal = document.getElementById('viewUserModal');
    const displayName = user.name || user.username || '-';
    document.getElementById('modalAvatar').textContent = displayName.charAt(0).toUpperCase();
    document.getElementById('modalName').textContent = displayName;
    document.getElementById('modalUsername').textContent = '@' + (user.username || '-');
    document.getElementById('modalEmail').textContent = user.email || '-';
    
    const roleBadges = {'Admin':'bg-purple-100 text-purple-700','User':'bg-green-100 text-green-700'};
    const roleBadge = roleBadges[user.role] || 'bg-gray-100 text-gray-700';
    document.getElementById('modalRole').innerHTML = '<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ' + roleBadge + '">' + (user.role || 'User') + '</span>';
    
    const statusColor = user.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
    document.getElementById('modalStatus').innerHTML = '<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ' + statusColor + '">' + (user.status || 'Active') + '</span>';
    
    const createdDate = new Date(user.created_at);
    document.getElementById('modalCreated').textContent = createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    document.getElementById('modalEditLink').href = 'users_crud.php?edit=' + user.id;
    modal.classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewUserModal').classList.add('hidden');
}

// Close modal on backdrop click
document.getElementById('viewUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});

// Search and Filter
const searchInput = document.getElementById('searchInput');
const roleFilter = document.getElementById('roleFilter');
const statusFilter = document.getElementById('statusFilter');
const rows = document.querySelectorAll('.user-row');
const filteredCount = document.getElementById('filteredCount');

function applyFilters() {
    const q = searchInput.value.toLowerCase();
    const role = roleFilter ? roleFilter.value : '';
    const status = statusFilter.value;
    let count = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowRole = row.getAttribute('data-role');
        const rowStatus = row.getAttribute('data-status');
        
        const matchesSearch = text.includes(q);
        const matchesRole = !role || rowRole === role;
        const matchesStatus = !status || rowStatus === status;
        
        if (matchesSearch && matchesRole && matchesStatus) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    
    filteredCount.textContent = count;
}

searchInput.addEventListener('keyup', applyFilters);
if (roleFilter) roleFilter.addEventListener('change', applyFilters);
statusFilter.addEventListener('change', applyFilters);
</script>

</body>
</html>
