<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$donors = [];
$donor_stats = ['total' => 0, 'available' => 0, 'unavailable' => 0];

try {
    $data = $conn->query("
        SELECT d.*, u.username, d.blood_groups AS blood_gp_name
        FROM donor d
        JOIN users u ON d.user_id = u.id
        ORDER BY d.last_donation_date DESC
    ");
    if ($data && $data->num_rows > 0) {
        $donors = $data->fetch_all(MYSQLI_ASSOC);
    }

    $donor_stats['total']       = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'];
    $donor_stats['available']   = $conn->query("SELECT COUNT(*) AS c FROM donor WHERE available_status='Available'")->fetch_assoc()['c'];
    $donor_stats['unavailable'] = $conn->query("SELECT COUNT(*) AS c FROM donor WHERE available_status='Unavailable'")->fetch_assoc()['c'];
} catch (Exception $e) {
    // silent
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Ledger - Donor Directory</title>

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
                    <span>🎬</span>
                    <span data-i18n="overview">Overview</span>
                </a>
                 <a href="logindata.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700  hover:bg-gray-100 rounded-lg transition">
                    <span>📊</span>
                    <span data-i18n="users">Users</span>
                </a>
                <a href="donors.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                    <span>📍</span>
                    <span data-i18n="donors">Donors</span>
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
            <header class="bg-white border-b px-8 py-4 flex justify-between items-center sticky top-0 z-30">

                <div>
                    <h2 class="text-3xl font-bold text-red-800" data-i18n="manage_donors_title">
                        Manage Donors
                    </h2>

                    <p class="text-gray-500 mt-1">
                        Manage and monitor the hospital's verified blood donor network.
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
                                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">
                                    Logout</a>
                            </div>
                        </div>
                    </div>
                </div>

            </header>

            <div class="p-8">

                <!-- Header -->

                <div class="flex justify-between items-center">
                    <div class="w-96">
                        <input
                            id="searchInput"
                            type="text"
                            data-i18n-placeholder="search_donors_placeholder"
                            placeholder="Search by name, email, or blood type..."
                            class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 outline-none">
                    </div>



                </div>

                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">

                    <div class="bg-white rounded-xl border p-5">
                        <p class="text-gray-500 text-sm" data-i18n="total_registered_donors">Total Registered Donors</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $donor_stats['total'] ?></h3>
                    </div>

                    <div class="bg-white rounded-xl border p-5">
                        <p class="text-gray-500 text-sm" data-i18n="available_donors">Available Donors</p>
                        <h3 class="text-3xl font-bold mt-2 text-green-600"><?= $donor_stats['available'] ?></h3>
                    </div>

                    <div class="bg-white rounded-xl border p-5">
                        <p class="text-gray-500 text-sm" data-i18n="donors_on_cooldown">Donors on Cooldown</p>
                        <h3 class="text-3xl font-bold mt-2 text-red-500"><?= $donor_stats['unavailable'] ?></h3>
                    </div>

                    <div class="bg-white rounded-xl border p-5">
                        <p class="text-gray-500 text-sm" data-i18n="blood_types">Blood Types</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $conn->query("SELECT COUNT(*) AS c FROM blood_groups")->fetch_assoc()['c'] ?></h3>
                    </div>

                </div>

                <!-- Filters -->
                <div class="bg-white border rounded-xl p-4 mt-8">
                    <div class="flex flex-wrap gap-4">
                        <select id="filterBlood" class="border rounded-lg px-4 py-2" onchange="filterTable()">
                            <option value="">All Blood Types</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="A+">A+</option>
                            <option value="B+">B+</option>
                        </select>
                        <select id="filterStatus" class="border rounded-lg px-4 py-2" onchange="filterTable()">
                            <option value="">All Statuses</option>
                            <option value="AVAILABLE">Available</option>
                            <option value="UNAVAILABLE">Unavailable</option>
                            <option value="PENDING">Pending</option>
                        </select>
                    </div>
                </div>



                <!-- Donor Table -->
                <div class="bg-white rounded-3xl shadow-sm p-6 overflow-x-auto mt-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold" data-i18n="donors">Donor Records</h2>
                            <p class="text-sm text-gray-500">View and search the current donor roster.</p>
                        </div>
                        <a href="../user/donateform.php" class="inline-block rounded-2xl bg-red-700 px-6 py-3 text-white font-semibold hover:bg-red-800 transition" data-i18n="add_donor">
                            + Register New Donor
                        </a>
                    </div>

                    <table class="w-full min-w-[700px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-4" data-i18n="name_col">Full Name</th>
                                <th class="p-4" data-i18n="gender">Gender</th>
                                <th class="p-4" data-i18n="age">Age</th>
                                <th class="p-4" data-i18n="blood_type_col">Blood Type</th>
                                <th class="p-4" data-i18n="phone">Phone</th>
                                <th class="p-4" data-i18n="email">Email</th>
                                <th class="p-4" data-i18n="address">Address</th>
                                <th class="p-4" data-i18n="weight">Weight (kg)</th>
                                <th class="p-4" data-i18n="last_donation">Last Donation</th>
                                <th class="p-4" data-i18n="status">Available Status</th>
                                
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($donors) > 0): ?>
                                <?php foreach ($donors as $donor): ?>
                                    <?php
                                    $status = $donor['status'];
                                    $statusClasses = [
                                        'AVAILABLE'   => 'bg-green-100 text-green-700',
                                        'UNAVAILABLE' => 'bg-red-100 text-red-700',
                                        'PENDING'     => 'bg-yellow-100 text-yellow-700',
                                    ];
                                    $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <tr class="donor-row border-t border-slate-200">
                                        <td class="p-4 donor-name font-semibold"><?= htmlspecialchars($donor['donor_name'] ?? 'Unknown') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['gender'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['age'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['blood_gp_name'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['phone'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['email'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['address'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['weight'] ?? '-') ?></td>
                                        <td class="p-4"><?= htmlspecialchars($donor['last_donation'] ?? 'Never') ?></td>
                                        <td class="p-4">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                                                <?= htmlspecialchars(ucfirst(strtolower($status))) ?>
                                            </span>
                                        </td>                                        <
                                        <td class="p-4">
                                            <div class="flex flex-wrap gap-2">
                                                <a href="/Blood-Donation-and-Request-System/user/profile.php" class="rounded-full border border-red-500 px-3 py-2 text-red-600 hover:bg-red-50">View</a>
                                                <a href="/Blood-Donation-and-Request-System/user/profile.php" class="rounded-full border border-slate-300 px-3 py-2 text-slate-700 hover:bg-slate-100">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-gray-500" data-i18n="no_donors_found">No donors found.</td>
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
        const rows = document.querySelectorAll('.donor-row');

        searchInput.addEventListener('keyup', filterTable);
        document.getElementById('filterStatus')?.addEventListener('change', filterTable);
        document.getElementById('filterBlood')?.addEventListener('change', filterTable);

        function filterTable() {
            const query = searchInput.value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value;
            const bloodFilter = document.getElementById('filterBlood').value;

            rows.forEach(row => {
                const name = row.querySelector('.donor-name').textContent.toLowerCase();
                const id = row.querySelector('.donor-id').textContent.toLowerCase();
                const status = row.querySelector('.donor-status')?.textContent.trim() || '';
                const blood = row.querySelector('.donor-blood')?.textContent.trim() || '';

                const matchesSearch = name.includes(query) || id.includes(query);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesBlood = !bloodFilter || blood === bloodFilter;

                row.style.display = matchesSearch && matchesStatus && matchesBlood ? '' : 'none';
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