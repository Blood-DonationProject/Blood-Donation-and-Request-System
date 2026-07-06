<?php

include "auth_check.php";
require_once __DIR__ . '/../config/db.php';

$total =
    $conn->query("SELECT COUNT(*) total FROM requests")->fetch_assoc()['total'];

$critical =
    $conn->query("SELECT COUNT(*) total FROM requests WHERE status='Critical'")->fetch_assoc()['total'];

$pending =
    $conn->query("SELECT COUNT(*) total FROM requests WHERE status='Pending'")->fetch_assoc()['total'];

$fulfilled =
    $conn->query("SELECT COUNT(*) total FROM requests WHERE status='Fulfilled'")->fetch_assoc()['total'];

$data = $conn->query("SELECT * FROM requests");

?>



<!DOCTYPE html>

<html>

<head>

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

    <div class="flex">



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
                <a href="donation_histories.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>⚡</span>
                    <span data-i18n="donation_histories">Donation Histories</span>
                </a>
                <a href="hospitals.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>🏥</span>
                    <span data-i18n="hospitals">Hospitals</span>
                </a>
                <a href="requests.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
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

        <!-- Top bar -->
        <main class="flex-1">
            <header class="bg-white border-b px-8 py-4 flex justify-between items-center sticky top-0 z-30">

                <div class="mb-2">
                <h2 class="text-3xl font-bold text-red-800" data-i18n="manage_requests_title">
                    Manage Blood Requests
                </h2>

                <p class="text-gray-500 mt-1">
                        Manage and monitor live hospital requirements
                    </p>
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
                                <a href="logout.php" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-lg font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>

            </header>
        


            <!-- Content -->

            <div class="flex-1 p-8">
                <div class="w-96 mb-8">
                    <input
                        id="searchInput"
                        type="text"
                        data-i18n-placeholder="search_requests_placeholder"
                        placeholder="Search by patient name or hospital..."
                        class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-red-500 outline-none">
                </div>

                <div class="grid grid-cols-4 gap-6">

                    <div class="bg-white p-6 rounded-lg shadow">

                        <p class="text-gray-500" data-i18n="critical_requests">

                            Urgent Requests

                        </p>

                        <h1 class="text-4xl font-bold text-red-700">

                            <?= $critical ?>

                        </h1>

                    </div>

                    <div class="bg-white p-6 rounded-lg shadow">

                        <p class="text-gray-500" data-i18n="pending_requests">

                            Pending Fulfillment

                        </p>

                        <h1 class="text-4xl font-bold">

                            <?= $pending ?>

                        </h1>

                    </div>

                    <div class="bg-white p-6 rounded-lg shadow">

                        <p class="text-gray-500" data-i18n="total_units_needed">

                            Total Units Needed

                        </p>

                        <h1 class="text-4xl font-bold">

                            <?php

                            $units = $conn->query("SELECT SUM(units_required) total FROM requests")
                                ->fetch_assoc()['total'];

                            echo $units;

                            ?>

                        </h1>

                    </div>

                    <div class="bg-white p-6 rounded-lg shadow">

                        <p class="text-gray-500" data-i18n="fulfilled_requests">

                            Fulfilled Today

                        </p>

                        <h1 class="text-4xl font-bold text-green-600">

                            <?= $fulfilled ?>

                        </h1>


                    </div>

                </div>

                <!-- filter -->
                <div class="bg-white border rounded-xl p-4 mt-8">

                    <div class="flex flex-wrap gap-4">

                        <select class="border rounded-lg px-4 py-2">
                            <option>All Blood Types</option>
                            <option>O+</option>
                            <option>O-</option>
                            <option>A+</option>
                            <option>B+</option>
                        </select>


                    </div>

                </div>

                <div class="bg-white mt-8 rounded-lg shadow">

                    <div class="flex justify-between p-6">

                        <h2 class="font-bold text-xl" data-i18n="blood_requests">

                            Active Blood Requests

                        </h2>

                        <a href="add.php" class="rounded-2xl bg-red-700 px-6 py-3 text-white font-semibold hover:bg-red-800 transition inline-block" data-i18n="add_request">
                        + New Request
                    </a>

                    </div>

                    <table class="w-full">

                        <thead class="bg-gray-100">

                            <tr class="bg-gray-50 text-slate-600">

                                <th class="p-4" data-i18n="patient_name_col">Patient Name</th>

                                <th class="p-4" data-i18n="age">Age</th>

                                <th class="p-4" data-i18n="gender">Gender</th>

                                <th class="p-4" data-i18n="blood_type_col_req">Blood Type</th>

                                <th class="p-4" data-i18n="hospital_col_req">Hospital</th>

                                <th class="p-4" data-i18n="units_col_req">Units</th>

                                <th class="p-4" data-i18n="date_col_req">Date</th>

                                <th class="p-4" data-i18n="status_col_req">Status</th>

                                <th class="p-4" data-i18n="actions_col_req">Actions</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php while ($row = $data->fetch_assoc()): ?>

                                <tr class="border-t">

                                    <td class="p-4">

                                        <?= $row['patient_name'] ?>

                                    </td>

                                    <td>

                                        <?= $row['age'] ?>

                                    </td>

                                    <td>

                                        <?= $row['gender'] ?>

                                    </td>

                                    <td>

                                        <?= $row['blood_group'] ?>

                                    </td>

                                    <td>

                                        <?= $row['hospital'] ?>

                                    </td>

                                    <td>

                                        <?= $row['units_required'] ?>

                                    </td>

                                    <td>

                                        <?= $row['date'] ?>

                                    </td>

                                    <td>
                                        
                                        <?php

                                        $color = '';

                                        switch ($row['status']) {

                                            case 'Critical':

                                                $color = 'bg-red-100 text-red-700';

                                                break;

                                            case 'Pending':

                                                $color = 'bg-blue-100 text-blue-700';

                                                break;

                                            case 'Fulfilled':

                                                $color = 'bg-green-100 text-green-700';

                                                break;

                                            default:

                                                $color = 'bg-orange-100 text-orange-700';
                                        }

                                        ?>

                                        <span class="px-3 py-1 rounded-full text-sm <?= $color ?>">

                                            <?= $row['status'] ?>

                                        </span>

                                    </td>

                                    <td>

                                        <a href="edit.php?id=<?= $row['id'] ?>"

                                            class="text-blue-500" data-i18n="edit">

                                            Edit

                                        </a>

                                        |

                                        <a href="delete.php?id=<?= $row['id'] ?>"

                                            class="text-red-500"

                                            onclick="return confirm('Are you sure you want to delete this request?')" data-i18n="delete">

                                            Delete

                                        </a>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

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