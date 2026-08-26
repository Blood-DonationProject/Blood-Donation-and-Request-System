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

// Check if role column exists
$roleCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$hasRoleColumn = ($roleCheck && $roleCheck->num_rows > 0);

// Handle add user
if (isset($_POST['add'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $role = 'User';
    $status = $_POST['status'] ?? 'Active';

    if ($username !== '' && !empty(trim($_POST['password']))) {
        if ($hasRoleColumn) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $password, $role, $status);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $password, $status);
        }
        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;
            require_once __DIR__ . '/../includes/notification_helper.php';
            require_once __DIR__ . '/../includes/mailer.php';

            // Website notification
            create_notification($conn, $newUserId, 'User_Registration', 'Welcome to BloodLife', 'Your account has been created by the administrator.', null, null, null, $newUserId);
            
            // Welcome email
            $emailRes = send_welcome_user_email($newUserId, $username, $email);
            $success = format_action_feedback('User created', $emailRes);
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Username and password are required.';
    }
}

// Handle status toggle (only for normal users)
if (isset($_POST['toggle_status'])) {
    $id = (int)$_POST['id'];
    $newStatus = $_POST['new_status'];
    if ($newStatus === 'Active' || $newStatus === 'Inactive') {
        if ($hasRoleColumn) {
            $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? AND role='User'");
            $stmt->bind_param("si", $newStatus, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? AND username != 'admin'");
            $stmt->bind_param("si", $newStatus, $id);
        }
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Website notification for user
            require_once __DIR__ . '/../includes/notification_helper.php';
            create_notification($conn, $id, 'StatusUpdate', 'Account Status Updated', "Your BloodLife account status is now {$newStatus}.", null, null, null, $id);

            // Fail-safe decoupled email to user
            require_once __DIR__ . '/../includes/mailer.php';
            $emailRes = send_account_status_email($id, $newStatus);

            $_SESSION['success'] = format_action_feedback("User status updated to {$newStatus}", $emailRes);
        } else {
            $_SESSION['error'] = 'Error updating user or user is an Admin account.';
        }
        $stmt->close();
        header('Location: users_crud.php');
        exit;
    }
}

$users = [];

if ($hasRoleColumn) {
    $result = $conn->query("SELECT * FROM users WHERE role = 'User' ORDER BY id DESC");
} else {
    $result = $conn->query("SELECT * FROM users WHERE username != 'admin' ORDER BY id DESC");
}

if ($result && $result->num_rows > 0) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

$userFilter = $hasRoleColumn ? "WHERE role = 'User'" : "WHERE username != 'admin'";
$activeFilter = $hasRoleColumn ? "WHERE status='Active' AND role='User'" : "WHERE status='Active' AND username != 'admin'";
$inactiveFilter = $hasRoleColumn ? "WHERE status='Inactive' AND role='User'" : "WHERE status='Inactive' AND username != 'admin'";

$stats = [
    'total' => $conn->query("SELECT COUNT(*) AS c FROM users {$userFilter}")->fetch_assoc()['c'] ?? 0,
    'users' => $conn->query("SELECT COUNT(*) AS c FROM users {$userFilter}")->fetch_assoc()['c'] ?? 0,
    'admins' => 0,
    'active' => $conn->query("SELECT COUNT(*) AS c FROM users {$activeFilter}")->fetch_assoc()['c'] ?? 0,
    'inactive' => $conn->query("SELECT COUNT(*) AS c FROM users {$inactiveFilter}")->fetch_assoc()['c'] ?? 0,
    'pending' => $conn->query("
        SELECT COUNT(*) AS c 
        FROM blood_request r
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ")->fetch_assoc()['c'] ?? 0,
];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

        <div class="p-8">

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded mb-6"><p class="text-red-700"><?= htmlspecialchars($error) ?></p></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded mb-6"><p class="text-green-700"><?= htmlspecialchars($success) ?></p></div>
            <?php endif; ?>


            <!-- Top Action / Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">User Management</h3>
                    <p class="text-sm text-gray-500">Manage and monitor all system user accounts.</p>
                </div>
                <button onclick="toggleForm()" id="toggleFormBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold px-5 py-2.5 rounded-xl hover:shadow-lg transition flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span>Add New User</span>
                </button>
            </div>

            <!-- Search and Filter -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <input id="searchInput" type="text" placeholder="Search by username, email, or name..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                </div>
                <div class="flex gap-4">
                    <select id="statusFilter" class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div id="crudForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 hidden">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800">New User</h3>
                    <button type="button" onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <input type="hidden" name="role" value="User">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Username *</label>
                        <input type="text" name="username" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Password *</label>
                        <input type="password" name="password" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status *</label>
                        <select name="status" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="add" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition">
                            Create
                        </button>
                        <button type="button" onclick="toggleForm()" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</button>
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
                                <th class="p-3 hidden">ID</th>
                                <th class="p-3">Name</th>
                                <th class="p-3">Username</th>
                                <th class="p-3">Email</th>
                                <?php if ($hasRoleColumn): ?>
                                <th class="p-3">Role</th>
                                <?php endif; ?>
                                <th class="p-3">Status</th>
                                <th class="p-3 whitespace-nowrap">Last Login</th>
                                <th class="p-3 whitespace-nowrap">Last Activity</th>
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
                                        <td class="p-3 font-medium hidden">#<?= $u['id'] ?></td>
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
                                        <td class="p-3 text-gray-500 text-xs whitespace-nowrap"><?= !empty($u['last_login']) ? date('M d, Y h:i A', strtotime($u['last_login'])) : 'Never' ?></td>
                                        <td class="p-3 text-gray-500 text-xs whitespace-nowrap"><?= !empty($u['last_activity']) ? date('M d, Y h:i A', strtotime($u['last_activity'])) : 'Never' ?></td>
                                        <td class="p-3 text-gray-500 text-xs whitespace-nowrap"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td class="p-3">
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to <?= ($u['status'] ?? 'Active') === 'Active' ? 'deactivate' : 'activate' ?> this user?')">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= ($u['status'] ?? 'Active') === 'Active' ? 'Inactive' : 'Active' ?>">
                                                <button type="submit" name="toggle_status" class="<?= ($u['status'] ?? 'Active') === 'Active' ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800' ?> font-semibold text-xs inline-flex items-center gap-1" title="<?= ($u['status'] ?? 'Active') === 'Active' ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="fas <?= ($u['status'] ?? 'Active') === 'Active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                                    <span><?= ($u['status'] ?? 'Active') === 'Active' ? 'Deactivate' : 'Activate' ?></span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= $hasRoleColumn ? 10 : 9 ?>" class="p-8 text-center text-gray-500">No users found.</td></tr>
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
    const form = document.getElementById('crudForm');
    if (form) {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }
}

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
