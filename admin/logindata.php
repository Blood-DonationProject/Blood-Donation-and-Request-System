<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$users = [];
$user_stats = ['total' => 0, 'donors' => 0, 'requesters' => 0];

try {
    $data = $conn->query("
        SELECT u.id, u.username, u.email, u.role, u.status, u.created_at,
               CASE WHEN d.id IS NOT NULL THEN 1 ELSE 0 END AS is_donor,
               d.available_status AS donor_status,
               d.blood_groups
        FROM users u
        LEFT JOIN donor d ON u.id = d.user_id
        ORDER BY u.id DESC
    ");
    if ($data && $data->num_rows > 0) {
        $users = $data->fetch_all(MYSQLI_ASSOC);
    }

    $user_stats['total']      = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
    $user_stats['donors']     = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0;
    $user_stats['requesters'] = $conn->query("SELECT COUNT(*) AS c FROM requester")->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {
    // silent
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users – BloodLife Admin</title>

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
        @keyframes countUp { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
        .animate-fade-up   { animation: fadeInUp   0.6s ease-out; }
        .animate-count-up  { animation: countUp 0.8s ease-out; }
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

<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen" style="font-family: 'Pyidaungsu', Noto Sans Myanmar, sans-serif;">

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
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>📊</span>
                    <span data-i18n="overview">Overview</span>
                </a>
                <a href="logindata.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                    <span>👤</span>
                    <span data-i18n="users">Users</span>
                </a>
                <a href="donors.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>🩸</span>
                    <span data-i18n="donors">Donors</span>
                </a>
                <a href="requesters.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>👥</span>
                    <span data-i18n="requesters">Requesters</span>
                </a>
                <a href="donation_histories.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>⚡</span>
                    <span data-i18n="donation_histories">Donation Histories</span>
                </a>
                <a href="requests.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>📋</span>
                    <span data-i18n="blood_requests">Blood Requests</span>
                </a>
            </nav>

            <div class="p-4 border-t border-gray-200">
                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1">

            <!-- Top Bar -->
            <header class="bg-white shadow sticky top-0 z-30">
                <div class="px-8 py-4 flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-red-800 animate-fade-down" data-i18n="Manage Users">
                            Manage Users
                        </h2>
                        <p class="text-gray-500 mt-1">
                            View and manage all registered users in the system.
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
<button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
                        <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
                            <option value="en">EN</option>
                            <option value="my">MY</option>
                        </select>
                        <button onclick="alert('Notifications feature coming soon')" class="text-xl hover:text-red-600 transition">🔔</button>
                        <button onclick="alert('Settings feature coming soon')" class="text-xl hover:text-red-600 transition">⚙️</button>

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
                </div>
            </header>

            <div class="p-8">

                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl border p-5 stat-card">
                        <p class="text-gray-500 text-sm">Total Users</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $user_stats['total'] ?></h3>
                    </div>
                    <div class="bg-white rounded-xl border p-5 stat-card">
                        <p class="text-gray-500 text-sm">Total Donors</p>
                        <h3 class="text-3xl font-bold mt-2 text-green-600"><?= $user_stats['donors'] ?></h3>
                    </div>
                    <div class="bg-white rounded-xl border p-5 stat-card">
                        <p class="text-gray-500 text-sm">Total Requesters</p>
                        <h3 class="text-3xl font-bold mt-2 text-blue-600"><?= $user_stats['requesters'] ?></h3>
                    </div>
                </div>

                <!-- Search -->
                <div class="flex justify-between items-center mb-6">
                    <div class="w-96">
                        <input
                            id="searchInput"
                            type="text"
                            placeholder="Search by name, email, or phone..."
                            class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white border rounded-xl p-4 mb-6 shadow-sm">
                    <div class="flex flex-wrap gap-4">
                        <select id="filterRole" class="border rounded-lg px-4 py-2" onchange="filterTable()">
                            <option value="">All Roles</option>
                            <option value="Admin">Admin</option>
                            <option value="Donor">Donor</option>
                            <option value="Requester">Requester</option>
                        </select>
                        <select id="filterStatus" class="border rounded-lg px-4 py-2" onchange="filterTable()">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- User Table -->
                <div class="bg-white rounded-3xl shadow-sm p-6 overflow-x-auto animate-fade-up">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold" data-i18n="user_records">User Records</h2>
                            <p class="text-sm text-gray-500">Browse all registered users and their roles.</p>
                        </div>
                    </div>

                    <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-4">ID</th>                                
                                <th class="p-4">Username</th>
                                <th class="p-4">Email</th>                                
                                <th class="p-4">Role</th>
                                <th class="p-4">password</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $role = $user['role'] ?? 'User';
                                    $roleBadgeColors = [
                                        'Admin'     => 'bg-purple-100 text-purple-700',
                                        'Donor'     => 'bg-green-100 text-green-700',
                                        'Requester' => 'bg-blue-100 text-blue-700',
                                    ];
                                    $badgeColor = $roleBadgeColors[$role] ?? 'bg-gray-100 text-gray-600';

                                    $status = $user['status'] ?? 'Active';
                                    $statusColor = $status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                    ?>
                                    <tr class="user-row border-t border-slate-200 hover:bg-gray-50 transition">
                                        <td class="p-4 font-semibold">#U<?= str_pad($user['user_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td class="p-4 user-name font-semibold"><?= htmlspecialchars($user['name'] ?? '-') ?></td>
                                        
                                        <td class="p-4"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                        
                                        <td class="p-4">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badgeColor ?>">
                                                <?= htmlspecialchars($role) ?>
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusColor ?>">
                                                <?= htmlspecialchars($password) ?>
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex flex-wrap gap-2">
                                                <a href="edit.php?id=<?= $user['user_id'] ?>" class="rounded-full border border-red-500 px-3 py-2 text-red-600 hover:bg-red-50 transition">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-gray-500">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>

    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const rows = document.querySelectorAll('.user-row');

        searchInput.addEventListener('keyup', filterTable);

        function filterTable() {
            const query = searchInput.value.toLowerCase();
            const roleFilter = document.getElementById('filterRole').value;
            const statusFilter = document.getElementById('filterStatus').value;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matchesSearch = text.includes(query);
                const matchesRole = !roleFilter || text.includes(roleFilter.toLowerCase());
                const matchesStatus = !statusFilter || text.includes(statusFilter.toLowerCase());

                row.style.display = matchesSearch && matchesRole && matchesStatus ? '' : 'none';
            });
        }
    </script>

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
