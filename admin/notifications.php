<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_helper.php';

$adminId = (int)$_SESSION['user_id'];

// Handle standard server-side actions (with redirect)
if (isset($_GET['action'])) {
    $act = $_GET['action'];
    $targetId = (int)($_GET['id'] ?? 0);

    if ($act === 'mark_read' && $targetId > 0) {
        mark_notification_read($conn, $targetId, $adminId);
    } elseif ($act === 'mark_unread' && $targetId > 0) {
        mark_notification_unread($conn, $targetId, $adminId);
    } elseif ($act === 'delete' && $targetId > 0) {
        delete_notification($conn, $targetId, $adminId);
    } elseif ($act === 'mark_all_read') {
        mark_all_notifications_read($conn, $adminId);
    } elseif ($act === 'delete_all_read') {
        delete_all_read_notifications($conn, $adminId);
    }

    if (isset($_GET['redirect'])) {
        header('Location: ' . $_GET['redirect']);
        exit;
    }
    
    // Clean up query string
    $cleanQuery = $_GET;
    unset($cleanQuery['action'], $cleanQuery['id']);
    $queryString = http_build_query($cleanQuery);
    header('Location: notifications.php' . ($queryString ? '?' . $queryString : ''));
    exit;
}

// Filters & Pagination
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'unread', 'read']) ? $_GET['filter'] : 'all';
$typeFilter = isset($_GET['type']) && $_GET['type'] !== '' ? trim($_GET['type']) : null;
$search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;

$limit = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Total counts for stats
$totalAll = get_admin_notifications_count($conn, $adminId, 'all');
$totalUnread = get_admin_unread_count($conn, $adminId);
$totalRead = max(0, $totalAll - $totalUnread);

// Filtered total for current view
$currentFilteredTotal = get_admin_notifications_count($conn, $adminId, $filter, $typeFilter, $search);
$totalPages = max(1, ceil($currentFilteredTotal / $limit));
if ($page > $totalPages) $page = $totalPages;

// Fetch current page notifications
$notificationsList = get_admin_notifications($conn, $adminId, $limit, $offset, $filter, $typeFilter, $search);

$currentPage = 'notifications.php';
$pageData = ['title' => 'Notifications', 'description' => 'View and manage all system alerts, registrations, and blood requests.'];

// Helper for relative time
function get_time_ago_string($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 172800) return 'Yesterday';
    return date('M j, Y · g:i A', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BloodLife Admin</title>
    <script>
        (function() {
            var t = localStorage.getItem('bloodlife-theme');
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style id="clean-soft-styles">
        /* Clean Soft Light Theme */
        html:not(.dark) body {
            background-color: #ffffff !important;
            background-image: none !important;
            color: #1e293b !important;
        }

        /* Dark Mode Theme */
        html.dark body {
            background-color: #0f172a !important;
            background-image: none !important;
            color: #e2e8f0 !important;
        }
        html.dark .bg-white:not(.sidebar):not(nav) {
            background-color: #1e293b !important;
        }
        html.dark .text-slate-900,
        html.dark .text-slate-800 {
            color: #f1f5f9 !important;
        }
        html.dark .text-slate-700 {
            color: #cbd5e1 !important;
        }
        html.dark .text-slate-600 {
            color: #94a3b8 !important;
        }
        html.dark .text-slate-500 {
            color: #64748b !important;
        }
        html.dark .border-slate-200,
        html.dark .border-gray-200 {
            border-color: #334155 !important;
        }
        html.dark input,
        html.dark select {
            background-color: #1e293b !important;
            border-color: #475569 !important;
            color: #f1f5f9 !important;
        }

        .pulse-dot { animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    </style>
</head>
<body class="bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 min-h-screen">

    <div class="flex min-h-screen bg-white dark:bg-slate-900">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 min-h-screen flex flex-col bg-white dark:bg-slate-900">
            <!-- Top Navigation Bar -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <!-- Page Container (Clean White & Soft Palette) -->
            <div class="p-6 md:p-8 flex-1 max-w-6xl w-full mx-auto">
                
                <!-- Stat Cards (Soft, Clean, Airy) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                    <!-- Total Alerts -->
                    <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between transition-all hover:shadow-md hover:border-slate-300">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Alerts</p>
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1"><?= number_format($totalAll) ?></h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">All system activities</p>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-base border border-blue-100 dark:border-blue-900/50">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>

                    <!-- Unread Alerts -->
                    <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between transition-all hover:shadow-md hover:border-slate-300">
                        <div>
                            <p class="text-xs font-semibold text-rose-600 dark:text-rose-400">Unread</p>
                            <h3 class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1"><?= number_format($totalUnread) ?></h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Needs review</p>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center text-base border border-rose-100 dark:border-rose-900/50">
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>

                    <!-- Completed / Read Alerts -->
                    <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between transition-all hover:shadow-md hover:border-slate-300">
                        <div>
                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Completed / Read</p>
                            <h3 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1"><?= number_format($totalRead) ?></h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Acknowledged</p>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-base border border-emerald-100 dark:border-emerald-900/50">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar (Soft Rounded Box) -->
                <div class="bg-white dark:bg-slate-800/90 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-700/80 mb-6">
                    <form method="GET" class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
                            <a href="?filter=all<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs md:text-sm font-semibold rounded-xl transition-all flex items-center gap-2 whitespace-nowrap <?= $filter === 'all' ? 'bg-rose-600 text-white shadow-sm shadow-rose-200 dark:shadow-none' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600' ?>">
                                <span>All</span>
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold <?= $filter === 'all' ? 'bg-rose-700/90 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300' ?>"><?= $totalAll ?></span>
                            </a>
                            <a href="?filter=unread<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs md:text-sm font-semibold rounded-xl transition-all flex items-center gap-2 whitespace-nowrap <?= $filter === 'unread' ? 'bg-rose-600 text-white shadow-sm shadow-rose-200 dark:shadow-none' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600' ?>">
                                <span>Unread</span>
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold <?= $filter === 'unread' ? 'bg-rose-700/90 text-white' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' ?>"><?= $totalUnread ?></span>
                            </a>
                            <a href="?filter=read<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs md:text-sm font-semibold rounded-xl transition-all flex items-center gap-2 whitespace-nowrap <?= $filter === 'read' ? 'bg-rose-600 text-white shadow-sm shadow-rose-200 dark:shadow-none' : 'bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600' ?>">
                                <span>Read</span>
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold <?= $filter === 'read' ? 'bg-rose-700/90 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300' ?>"><?= $totalRead ?></span>
                            </a>
                        </div>

                        <!-- Type Selector & Search -->
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            
                            <!-- Type Dropdown -->
                            <select name="type" onchange="this.form.submit()" class="text-xs md:text-sm px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium focus:outline-none focus:ring-2 focus:ring-rose-500 shadow-2xs">
                                <option value="">All Types</option>
                                <option value="User_Registration" <?= $typeFilter === 'User_Registration' ? 'selected' : '' ?>>New Users</option>
                                <option value="Donor_Registration" <?= $typeFilter === 'Donor_Registration' ? 'selected' : '' ?>>New Donors</option>
                                <option value="Blood_Request" <?= $typeFilter === 'Blood_Request' ? 'selected' : '' ?>>Blood Requests</option>
                                <option value="Blood_Request_Update" <?= $typeFilter === 'Blood_Request_Update' ? 'selected' : '' ?>>Request Updates</option>
                                <option value="Assignment" <?= $typeFilter === 'Assignment' ? 'selected' : '' ?>>Assignments</option>
                                <option value="Assignment_Accepted" <?= $typeFilter === 'Assignment_Accepted' ? 'selected' : '' ?>>Donor Accepted</option>
                                <option value="Assignment_Rejected" <?= $typeFilter === 'Assignment_Rejected' ? 'selected' : '' ?>>Donor Rejected</option>
                                <option value="Blood_Received" <?= $typeFilter === 'Blood_Received' ? 'selected' : '' ?>>Blood Received</option>
                            </select>

                            <!-- Search Input -->
                            <div class="relative flex-1 sm:w-60">
                                <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search alerts..." class="w-full text-xs md:text-sm pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 font-medium focus:outline-none focus:ring-2 focus:ring-rose-500 shadow-2xs">
                                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            </div>

                            <?php if ($search || $typeFilter): ?>
                                <a href="?filter=<?= htmlspecialchars($filter) ?>" class="text-xs text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 px-1.5 py-1.5" title="Clear search">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>

                            <button type="submit" class="hidden">Search</button>
                        </div>
                    </form>

                    <!-- Bulk Actions Row -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3.5 mt-3.5 border-t border-slate-100 dark:border-slate-700/60">
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                            Showing <span class="font-semibold text-slate-800 dark:text-slate-200"><?= count($notificationsList) ?></span> of <span class="font-semibold text-slate-800 dark:text-slate-200"><?= $currentFilteredTotal ?></span> notification(s)
                        </span>

                        <div class="flex items-center gap-2">
                            <?php if ($totalUnread > 0): ?>
                                <button onclick="markAllNotificationsReadPage()" class="text-xs font-semibold text-blue-700 dark:text-blue-400 bg-blue-50/80 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/50 px-3 py-1.5 rounded-xl transition border border-blue-200 dark:border-blue-800/60 flex items-center gap-1.5">
                                    <i class="fas fa-check-double text-[10px]"></i>
                                    <span>Mark all as read</span>
                                </button>
                            <?php endif; ?>

                            <?php if ($totalRead > 0): ?>
                                <button onclick="deleteAllReadNotificationsPage()" class="text-xs font-semibold text-rose-700 dark:text-rose-400 bg-rose-50/80 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/50 px-3 py-1.5 rounded-xl transition border border-rose-200 dark:border-rose-800/60 flex items-center gap-1.5">
                                    <i class="fas fa-trash-alt text-[10px]"></i>
                                    <span>Clear read alerts</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifications List (Clean, Airy Card Design) -->
                <div class="bg-white dark:bg-slate-800/90 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 overflow-hidden">
                    <?php if (count($notificationsList) > 0): ?>
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/60" id="notifListContainer">
                            <?php foreach ($notificationsList as $n): 
                                $is_read = $n['is_read'] == 1;
                                $meta = get_notification_meta($n);
                                $rowBg = $is_read 
                                    ? 'bg-white dark:bg-slate-800/90 hover:bg-slate-50/60 dark:hover:bg-slate-750' 
                                    : 'bg-rose-50/20 dark:bg-rose-950/15 hover:bg-rose-50/40 dark:hover:bg-rose-900/25 border-l-4 border-l-rose-500';
                                
                                $actionUrl = $meta['action_url'];
                                $timeAgo = get_time_ago_string($n['created_at']);
                            ?>
                                <div class="p-5 transition flex flex-col md:flex-row md:items-center justify-between gap-4 <?= $rowBg ?>" id="notif-row-<?= $n['id'] ?>">
                                    <!-- Left Section: Icon + Text Details -->
                                    <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                        <!-- Soft Icon Badge -->
                                        <div class="w-10 h-10 rounded-xl bg-slate-100/80 dark:bg-slate-700/80 flex items-center justify-center flex-shrink-0 mt-0.5 border border-slate-200/60 dark:border-slate-600">
                                            <i class="fas <?= htmlspecialchars($meta['icon']) ?> <?= htmlspecialchars($meta['icon_color']) ?> text-sm"></i>
                                        </div>

                                        <!-- Text Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                <!-- Type Capsule Badge -->
                                                <span class="text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded-full <?= htmlspecialchars($meta['badge_bg']) ?>">
                                                    <?= htmlspecialchars($meta['label']) ?>
                                                </span>
                                                <span class="text-[11px] text-slate-400 dark:text-slate-500 font-medium">#<?= (int)$n['id'] ?></span>
                                                
                                                <?php if (!$is_read): ?>
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded-full border border-rose-200/80 dark:border-rose-900/50">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 pulse-dot"></span> Unread
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <span class="text-xs text-slate-400 dark:text-slate-500 font-normal ml-auto whitespace-nowrap">
                                                    <i class="far fa-clock mr-1 text-[10px]"></i> <?= $timeAgo ?> · <span class="hidden sm:inline"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></span>
                                                </span>
                                            </div>

                                            <!-- Notification Title -->
                                            <h3 class="text-sm md:text-base <?= $is_read ? 'font-semibold text-slate-800 dark:text-slate-200' : 'font-bold text-slate-900 dark:text-slate-100' ?> leading-snug">
                                                <?= htmlspecialchars($n['title']) ?>
                                            </h3>

                                            <!-- Notification Message -->
                                            <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">
                                                <?= htmlspecialchars($n['message']) ?>
                                            </p>

                                            <!-- Contextual Tags (Soft & Clean) -->
                                            <div class="flex flex-wrap items-center gap-1.5 mt-2.5">
                                                <?php if (!empty($n['request_id'])): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-700 dark:text-slate-300 bg-slate-100/80 dark:bg-slate-700/60 px-2.5 py-0.5 rounded-lg border border-slate-200/70 dark:border-slate-600">
                                                        <i class="fas fa-file-medical text-rose-500 text-[10px]"></i> Request #<?= (int)$n['request_id'] ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($n['blood_group'])): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2.5 py-0.5 rounded-lg border border-rose-200/80 dark:border-rose-900/50">
                                                        🩸 <?= htmlspecialchars($n['blood_group']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($n['hospital'])): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-sky-800 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 px-2.5 py-0.5 rounded-lg border border-sky-200/80 dark:border-sky-900/50">
                                                        <i class="fas fa-hospital text-sky-600 text-[10px]"></i> <?= htmlspecialchars($n['hospital']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($n['donor_username'])): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-0.5 rounded-lg border border-emerald-200/80 dark:border-emerald-900/50">
                                                        <i class="fas fa-heart text-emerald-600 text-[10px]"></i> Donor: <?= htmlspecialchars($n['donor_username']) ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!empty($n['registered_username'])): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-purple-800 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 px-2.5 py-0.5 rounded-lg border border-purple-200/80 dark:border-purple-900/50">
                                                        <i class="fas fa-user text-purple-600 text-[10px]"></i> User: <?= htmlspecialchars($n['registered_username']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Section: Actions -->
                                    <div class="flex items-center gap-2 self-end md:self-center flex-shrink-0 pt-2 md:pt-0">
                                        <!-- Primary Target Action Button -->
                                        <a href="<?= htmlspecialchars($actionUrl) ?>" 
                                           onclick="markNotificationReadAndGo(<?= (int)$n['id'] ?>, '<?= htmlspecialchars($actionUrl) ?>'); return false;" 
                                           class="px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-sm shadow-rose-200 dark:shadow-none transition-all flex items-center gap-1.5 hover:scale-[1.02]">
                                            <span><?= htmlspecialchars($meta['action_text']) ?></span>
                                            <i class="fas fa-arrow-right text-[9px]"></i>
                                        </a>

                                        <!-- Read / Unread Toggle -->
                                        <?php if (!$is_read): ?>
                                            <button onclick="toggleReadStatusPage(<?= (int)$n['id'] ?>, true)" title="Mark as read" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:text-emerald-600 hover:border-emerald-200 transition flex items-center justify-center text-xs">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleReadStatusPage(<?= (int)$n['id'] ?>, false)" title="Mark as unread" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-400 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-600 hover:text-rose-600 transition flex items-center justify-center text-xs">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Delete Button -->
                                        <button onclick="deleteNotificationPage(<?= (int)$n['id'] ?>)" title="Delete alert" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:border-rose-200 transition flex items-center justify-center text-xs">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination Bar -->
                        <?php if ($totalPages > 1): ?>
                            <div class="p-4 bg-slate-50/50 dark:bg-slate-800/90 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    Page <span class="font-bold text-slate-800 dark:text-slate-200"><?= $page ?></span> of <span class="font-bold text-slate-800 dark:text-slate-200"><?= $totalPages ?></span>
                                </span>

                                <div class="flex items-center gap-1">
                                    <?php 
                                        $baseParams = $_GET; 
                                        unset($baseParams['page']);
                                        $buildPageUrl = function($p) use ($baseParams) {
                                            $baseParams['page'] = $p;
                                            return '?' . http_build_query($baseParams);
                                        };
                                    ?>

                                    <?php if ($page > 1): ?>
                                        <a href="<?= $buildPageUrl(1) ?>" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs hover:bg-slate-100 transition shadow-2xs" title="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                        <a href="<?= $buildPageUrl($page - 1) ?>" class="px-2.5 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs hover:bg-slate-100 transition font-semibold shadow-2xs">
                                            Prev
                                        </a>
                                    <?php endif; ?>

                                    <!-- Page Number Buttons -->
                                    <?php
                                        $startP = max(1, $page - 2);
                                        $endP = min($totalPages, $page + 2);
                                        for ($p = $startP; $p <= $endP; $p++):
                                    ?>
                                        <a href="<?= $buildPageUrl($p) ?>" class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition <?= $p === $page ? 'bg-rose-600 text-white shadow-xs' : 'border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100' ?>">
                                            <?= $p ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= $buildPageUrl($page + 1) ?>" class="px-2.5 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs hover:bg-slate-100 transition font-semibold shadow-2xs">
                                            Next
                                        </a>
                                        <a href="<?= $buildPageUrl($totalPages) ?>" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs hover:bg-slate-100 transition shadow-2xs" title="Last Page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="p-16 text-center bg-white dark:bg-slate-800">
                            <div class="w-16 h-16 bg-slate-50 dark:bg-slate-700 rounded-2xl flex items-center justify-center mx-auto mb-3.5 text-slate-400 dark:text-slate-500 border border-slate-200/80 dark:border-slate-600">
                                <i class="fas fa-bell-slash text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">No notifications found</h3>
                            <p class="text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto text-xs leading-relaxed">
                                <?php if ($search || $typeFilter || $filter !== 'all'): ?>
                                    No alerts match your current filter criteria. Try resetting your search filters.
                                <?php else: ?>
                                    You're all caught up! New alerts and requests from users will appear here automatically.
                                <?php endif; ?>
                            </p>
                            <?php if ($search || $typeFilter || $filter !== 'all'): ?>
                                <a href="notifications.php" class="inline-flex items-center gap-2 mt-4 px-3.5 py-1.5 rounded-xl bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 transition shadow-sm">
                                    <i class="fas fa-redo-alt text-[10px]"></i>
                                    <span>Reset All Filters</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Client-side Page AJAX Scripts -->
    <script>
        function toggleReadStatusPage(id, markAsRead) {
            var action = markAsRead ? 'mark_read' : 'mark_unread';
            fetch('notifications_ajax.php?action=' + action + '&id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof updateNotificationBadge === 'function') {
                        updateNotificationBadge(data.unread_count);
                    }
                    window.location.reload();
                }
            })
            .catch(() => window.location.reload());
        }

        function deleteNotificationPage(id) {
            if (!confirm('Are you sure you want to delete this notification?')) return;
            fetch('notifications_ajax.php?action=delete&id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    var row = document.getElementById('notif-row-' + id);
                    if (row) {
                        row.classList.add('opacity-0', 'transition-opacity');
                        setTimeout(function() { window.location.reload(); }, 200);
                    } else {
                        window.location.reload();
                    }
                }
            })
            .catch(() => window.location.reload());
        }

        function markAllNotificationsReadPage() {
            fetch('notifications_ajax.php?action=mark_all_read', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(() => window.location.reload());
        }

        function deleteAllReadNotificationsPage() {
            if (!confirm('Are you sure you want to delete all read notifications? This action cannot be undone.')) return;
            fetch('notifications_ajax.php?action=delete_all_read', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(() => window.location.reload());
        }
    </script>
</body>
</html>