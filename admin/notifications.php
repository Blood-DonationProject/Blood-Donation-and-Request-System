<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filterQuery = "";
if ($filter === 'unread') {
    $filterQuery = " AND is_read = 0";
} elseif ($filter === 'read') {
    $filterQuery = " AND is_read = 1";
}

// Fetch notifications
$stmt = $conn->prepare("SELECT id, request_id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ?" . $filterQuery . " ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notificationsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$currentPage = 'notifications.php';
$pageData = ['title' => 'Notifications', 'description' => 'View and manage all system notifications.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BloodLife Admin</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #f9fafb !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        html.dark .w-64.bg-white { background-color: #1f2937 !important; }
        html.dark nav.bg-slate-200, html.dark nav.bg-white { background-color: #1f2937 !important; border-color: #374151 !important; }
        html.dark .bg-white { background-color: #1f2937 !important; }
        html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
        html.dark .text-gray-700 { color: #d1d5db !important; }
        html.dark .text-gray-600 { color: #9ca3af !important; }
        html.dark .text-gray-500 { color: #9ca3af !important; }
        html.dark .border-gray-100, html.dark .border-gray-200 { border-color: #374151 !important; }
        html.dark .bg-gray-50 { background-color: #374151 !important; }
        html.dark .hover\:bg-gray-50:hover { background-color: #374151 !important; }
        html.dark .bg-red-50 { background-color: rgba(220,38,38,0.1) !important; }
        html.dark .hover\:bg-red-100:hover { background-color: rgba(220,38,38,0.2) !important; }
        .pulse-dot { animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">

    <div class="flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 min-h-screen">
            <!-- Top Navigation Bar -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <!-- Page Content -->
            <div class="p-8">
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">All Notifications</h2>
                    
                    <div class="flex items-center gap-3">
                        <!-- Filters -->
                        <div class="flex bg-white rounded-lg shadow-sm border p-1 border-gray-200">
                            <a href="?filter=all" class="px-4 py-1.5 text-sm font-medium rounded-md transition <?= $filter === 'all' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">All</a>
                            <a href="?filter=unread" class="px-4 py-1.5 text-sm font-medium rounded-md transition <?= $filter === 'unread' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">Unread</a>
                            <a href="?filter=read" class="px-4 py-1.5 text-sm font-medium rounded-md transition <?= $filter === 'read' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">Read</a>
                        </div>
                        
                        <?php if (count($notificationsList) > 0): ?>
                        <a href="?read_notifs=1" class="text-sm font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-lg transition shadow-sm border border-blue-100">
                            <i class="fas fa-check-double mr-1"></i> Mark all as read
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <?php if (count($notificationsList) > 0): ?>
                        <div class="divide-y divide-gray-100">
                        <?php foreach ($notificationsList as $n): 
                            $is_read = $n['is_read'] == 1;
                            $bgClass = $is_read ? 'bg-white hover:bg-gray-50' : 'bg-red-50 hover:bg-red-100/50';
                            
                            $link = "blood_requests_crud.php?view=" . (int)$n['request_id'];
                            $read_link = "?mark_read=" . $n['id'] . "&redirect=" . urlencode($link);
                            
                            $action_text = "View Request &rarr;";
                            if ($n['title'] == 'New Assignment' || strpos($n['title'], 'Assignment') !== false) {
                                $action_text = "View Assignment &rarr;";
                            }
                            if ($n['title'] == 'Donor Declined') {
                                $action_text = "Assign Another Donor &rarr;";
                            }
                        ?>
                            <div class="p-5 transition <?= $bgClass ?>">
                                <div class="flex items-start gap-4">
                                    <div class="mt-1">
                                    <?php if (!$is_read): ?>
                                        <div class="w-3 h-3 rounded-full bg-red-600 shadow-sm shadow-red-200"></div>
                                    <?php else: ?>
                                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                                    <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1">
                                    <div class="flex justify-between items-start gap-2">
                                        <h3 class="text-base font-bold text-gray-900"><?= htmlspecialchars($n['title']) ?></h3>
                                        <span class="text-xs font-semibold text-gray-400 whitespace-nowrap"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($n['message']) ?></p>
                                    
                                    <div class="mt-3">
                                        <?php if (!$is_read): ?>
                                        <a href="<?= htmlspecialchars($read_link) ?>" class="text-sm font-semibold text-red-600 hover:text-red-800 transition">
                                            <?= $action_text ?>
                                        </a>
                                        <?php else: ?>
                                        <a href="<?= htmlspecialchars($link) ?>" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition">
                                            <?= $action_text ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-bell-slash text-gray-300 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800">No notifications found</h3>
                            <p class="text-gray-500 mt-1">You don't have any <?= $filter !== 'all' ? htmlspecialchars($filter) . ' ' : '' ?>notifications at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>




            </div>
        </div>
    </div>
    <script>
        function toggleAdminDropdown() {
            var dropdown = document.getElementById('adminDropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const aMenu = document.getElementById('adminMenu');
            const aDrop = document.getElementById('adminDropdown');
            if (aMenu && aDrop && !aMenu.contains(e.target)) aDrop.classList.add('hidden');

            const nMenu = document.getElementById('adminNotifDropdown');
            const nBtn = document.querySelector('[onclick="toggleNotifications()"]');
            if (nMenu && nBtn && !nMenu.contains(e.target) && !nBtn.contains(e.target)) {
                nMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
