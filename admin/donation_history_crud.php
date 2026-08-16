<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

$donors_list = $conn->query("SELECT d.id, u.username FROM donor d JOIN users u ON d.user_id = u.id ORDER BY u.username");
$requests_list = $conn->query("SELECT br.id, br.blood_groups_id, br.units, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id ORDER BY br.id DESC LIMIT 100");
$blood_groups_list = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");

// Read-only view for completed donor assignments

$records = [];
$edit_row = null;

$result = $conn->query("
    SELECT da.id, da.donor_id, da.request_id, da.completed_at AS donation_date, da.status,
           u1.username AS donor_name,
           u2.username AS requester_name,
           bg.blood_gp_name,
           br.units,
           br.hospital
    FROM donor_assignments da
    LEFT JOIN donor d ON da.donor_id = d.id
    LEFT JOIN users u1 ON d.user_id = u1.id
    LEFT JOIN blood_request br ON da.request_id = br.id
    LEFT JOIN users u2 ON br.users_id = u2.id
    LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
    WHERE da.status = 'Completed'
    ORDER BY da.completed_at DESC
");
if ($result && $result->num_rows > 0) {
    $records = $result->fetch_all(MYSQLI_ASSOC);
}

$stats = [
    'total' => $conn->query("SELECT COUNT(*) AS c FROM donor_assignments WHERE status='Completed'")->fetch_assoc()['c'] ?? 0,
    'total_units' => $conn->query("SELECT COUNT(*) AS c FROM donor_assignments da JOIN blood_request br ON da.request_id = br.id WHERE da.status='Completed'")->fetch_assoc()['c'] ?? 0,
    'pending' => $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Pending'")->fetch_assoc()['c'] ?? 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History CRUD - BloodLife</title>
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


            <!-- Form Removed as History is now Auto-Generated -->

            <!-- Filters -->
            <div class="flex flex-wrap items-end gap-4 mb-6">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
                    <input id="searchInput" type="text" placeholder="Search by name, hospital, or date..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
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
                <div class="w-40">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                    <select id="filterStatus" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                        <option value="">All</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <button onclick="clearFilters()" class="px-4 py-2.5 text-sm text-gray-600 border-2 border-gray-200 rounded-xl hover:bg-gray-100 transition font-semibold whitespace-nowrap">Clear Filters</button>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Donation History Records</h3>
                        <p class="text-sm text-gray-500">All completed donation records.</p>
                    </div>
                    <span class="text-sm text-gray-500">Total: <?= count($records) ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3">Donor Name</th>
                                <th class="p-3">Requester Name</th>                                
                                <th class="p-3">Hospital</th>                                
                                <th class="p-3">Blood Group</th>
                                <th class="p-3">Units</th>
                                <th class="p-3">Donation Date</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $r): ?>
                                    <tr class="history-row border-t border-slate-200 hover:bg-gray-50"
                                        data-blood-group="<?= htmlspecialchars($r['blood_gp_name'] ?? '') ?>"
                                        data-status="<?= htmlspecialchars($r['status'] ?? '') ?>">
                                        <td class="p-3 font-medium">#<?= $r['id'] ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['donor_name'] ?? '-') ?></td>
                                        <td class="p-3"><?= htmlspecialchars($r['requester_name'] ?? '-') ?></td>                                        
                                        <td class="p-3"><?= htmlspecialchars($r['hospital'] ?? '-') ?></td>                                        
                                        <td class="p-3"><span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs"><?= htmlspecialchars($r['blood_gp_name'] ?? '-') ?></span></td>
                                        <td class="p-3">1 Unit</td>
                                        <td class="p-3"><?= htmlspecialchars($r['donation_date'] ?? 'N/A') ?></td>
                                        <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-green-100 text-green-700"><?= htmlspecialchars($r['status']) ?></span></td>
                                            <!-- Actions Removed for Read-Only View -->
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="p-8 text-center text-gray-500">No donation history records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const filterBloodGroup = document.getElementById('filterBloodGroup');
const filterStatus = document.getElementById('filterStatus');
const rows = document.querySelectorAll('.history-row');

function applyFilters() {
    const q = searchInput.value.toLowerCase();
    const bg = filterBloodGroup.value;
    const status = filterStatus.value;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowBg = row.getAttribute('data-blood-group');
        const rowStatus = row.getAttribute('data-status');
        
        const matchesSearch = text.includes(q);
        const matchesBg = !bg || rowBg === bg;
        const matchesStatus = !status || rowStatus === status;
        
        if (matchesSearch && matchesBg && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function clearFilters() {
    if(searchInput) searchInput.value = '';
    if(filterBloodGroup) filterBloodGroup.value = '';
    if(filterStatus) filterStatus.value = '';
    applyFilters();
}

if(searchInput) searchInput.addEventListener('keyup', applyFilters);
if(filterBloodGroup) filterBloodGroup.addEventListener('change', applyFilters);
if(filterStatus) filterStatus.addEventListener('change', applyFilters);

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

</body>
</html>
