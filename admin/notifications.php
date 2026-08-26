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
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'unread', 'read', 'important']) ? $_GET['filter'] : 'all';
$typeFilter = isset($_GET['type']) && $_GET['type'] !== '' ? trim($_GET['type']) : null;
$search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;

$limit = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Total counts for tabs
$totalAll = get_admin_notifications_count($conn, $adminId, 'all');
$totalUnread = get_admin_unread_count($conn, $adminId);
$totalRead = max(0, $totalAll - $totalUnread);
$totalImportant = get_admin_notifications_count($conn, $adminId, 'important');

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .pulse-dot { animation: pulse-dot 1.8s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.2); } }

        /* Custom clean scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 min-h-screen flex flex-col bg-transparent">
            <!-- Top Navigation Bar -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <!-- Page Container (Clean, Minimal, Professional) -->
            <div class="p-6 md:p-8 flex-1 max-w-6xl w-full mx-auto">
                
                <!-- 1. Clean Page Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl shadow-xs border border-gray-200/80">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-xl flex-shrink-0 border border-red-100">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">Notifications</h1>
                                <?php if ($totalUnread > 0): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700 border border-red-200">
                                        <?= $totalUnread ?> Unread
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-500 font-medium mt-0.5">Stay updated with system activities, donor assignments, and blood requests.</p>
                        </div>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex items-center gap-2 self-start sm:self-center">
                        <?php if ($totalUnread > 0): ?>
                            <button onclick="markAllNotificationsReadPage()" class="px-4 py-2 rounded-xl text-xs font-semibold bg-red-600 hover:bg-red-700 text-white shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                                <i class="fas fa-check-double text-[11px]"></i>
                                <span>Mark All Read</span>
                            </button>
                        <?php endif; ?>

                        <?php if ($totalRead > 0): ?>
                            <button onclick="deleteAllReadNotificationsPage()" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                                <i class="fas fa-trash-alt text-gray-400 text-[11px]"></i>
                                <span>Clear Read</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Clean Minimal Filter & Search Toolbar -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-xs border border-gray-200/80 mb-5">
                    <form method="GET" class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3.5">
                        <!-- Filter Tabs (Clean Neutral & Red) -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0 scrollbar-none">
                            <a href="?filter=all<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs font-semibold rounded-xl transition flex items-center gap-2 whitespace-nowrap <?= $filter === 'all' ? 'bg-red-600 text-white font-bold shadow-xs' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' ?>">
                                <span>All</span>
                                <span class="px-1.5 py-0.2 rounded-md text-[11px] <?= $filter === 'all' ? 'bg-white/20 text-white' : 'bg-white text-gray-600' ?>"><?= $totalAll ?></span>
                            </a>

                            <a href="?filter=unread<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs font-semibold rounded-xl transition flex items-center gap-2 whitespace-nowrap <?= $filter === 'unread' ? 'bg-red-600 text-white font-bold shadow-xs' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' ?>">
                                <span>Unread</span>
                                <span class="px-1.5 py-0.2 rounded-md text-[11px] <?= $filter === 'unread' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-700' ?>"><?= $totalUnread ?></span>
                            </a>

                            <a href="?filter=read<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs font-semibold rounded-xl transition flex items-center gap-2 whitespace-nowrap <?= $filter === 'read' ? 'bg-red-600 text-white font-bold shadow-xs' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' ?>">
                                <span>Read</span>
                                <span class="px-1.5 py-0.2 rounded-md text-[11px] <?= $filter === 'read' ? 'bg-white/20 text-white' : 'bg-white text-gray-600' ?>"><?= $totalRead ?></span>
                            </a>

                            <a href="?filter=important<?= $typeFilter ? '&type=' . urlencode($typeFilter) : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="px-3.5 py-1.5 text-xs font-semibold rounded-xl transition flex items-center gap-2 whitespace-nowrap <?= $filter === 'important' ? 'bg-red-600 text-white font-bold shadow-xs' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' ?>">
                                <span>Important</span>
                                <span class="px-1.5 py-0.2 rounded-md text-[11px] <?= $filter === 'important' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700' ?>"><?= $totalImportant ?></span>
                            </a>
                        </div>

                        <!-- Type Selector & Search Input -->
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            
                            <!-- Type Dropdown -->
                            <select name="type" onchange="this.form.submit()" class="text-xs px-3 py-2 rounded-xl border border-gray-300 bg-white text-gray-800 font-medium focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 shadow-2xs transition">
                                <option value="">All Types</option>
                                <option value="Blood_Request" <?= $typeFilter === 'Blood_Request' ? 'selected' : '' ?>>Blood Requests</option>
                                <option value="Assignment" <?= $typeFilter === 'Assignment' ? 'selected' : '' ?>>Donor Assignments</option>
                                <option value="Assignment_Accepted" <?= $typeFilter === 'Assignment_Accepted' ? 'selected' : '' ?>>Donor Accepted</option>
                                <option value="Assignment_Rejected" <?= $typeFilter === 'Assignment_Rejected' ? 'selected' : '' ?>>Donor Rejected</option>
                                <option value="Blood_Received" <?= $typeFilter === 'Blood_Received' ? 'selected' : '' ?>>Blood Received</option>
                                <option value="Donor_Registration" <?= $typeFilter === 'Donor_Registration' ? 'selected' : '' ?>>New Donors</option>
                                <option value="User_Registration" <?= $typeFilter === 'User_Registration' ? 'selected' : '' ?>>New Users</option>
                                <option value="Security" <?= $typeFilter === 'Security' ? 'selected' : '' ?>>Security Alerts</option>
                            </select>

                            <!-- Search Input -->
                            <div class="relative flex-1 sm:w-56">
                                <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search..." class="w-full text-xs pl-8 pr-3 py-2 rounded-xl border border-gray-300 bg-white text-gray-800 placeholder-gray-400 font-medium focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 shadow-2xs transition">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            </div>

                            <?php if ($search || $typeFilter): ?>
                                <a href="?filter=<?= htmlspecialchars($filter) ?>" class="text-xs text-gray-400 hover:text-red-600 p-2 font-semibold" title="Clear search">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>

                            <button type="submit" class="hidden">Search</button>
                        </div>
                    </form>

                    <!-- Filter Results Summary -->
                    <div class="flex items-center justify-between pt-3 mt-3 border-t border-gray-100 text-xs text-gray-500 font-medium">
                        <span>
                            Showing <span class="font-bold text-gray-800"><?= count($notificationsList) ?></span> of <span class="font-bold text-gray-800"><?= $currentFilteredTotal ?></span> notification(s)
                        </span>
                        <?php if ($search || $typeFilter || $filter !== 'all'): ?>
                            <a href="notifications.php" class="text-red-600 hover:underline font-semibold flex items-center gap-1">
                                <i class="fas fa-undo-alt text-[10px]"></i>
                                <span>Reset Filters</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Clean & Organized Notification List -->
                <div class="bg-white rounded-2xl shadow-xs border border-gray-200/80 overflow-hidden mb-8">
                    <?php if (count($notificationsList) > 0): ?>
                        <div class="divide-y divide-gray-100" id="notifListContainer">
                            <?php foreach ($notificationsList as $n): 
                                $is_read = $n['is_read'] == 1;
                                $meta = get_notification_meta($n);
                                $isUrgent = (!empty($n['request_urgency']) && strtolower($n['request_urgency']) === 'urgent') || (strpos(strtolower($n['title']), 'urgent') !== false);
                                
                                // Clean row styling: unread gets soft pinkish-red tint with a red left border
                                $cardBg = $is_read 
                                    ? 'bg-white hover:bg-gray-50/70' 
                                    : 'bg-red-50/30 hover:bg-red-50/60 border-l-4 border-l-red-500';
                                
                                $actionUrl = $meta['action_url'];
                                $timeAgo = get_time_ago_string($n['created_at']);

                                // Prepare detail JSON payload for the modal
                                $detailPayload = [
                                    'id' => (int)$n['id'],
                                    'type' => $n['type'],
                                    'type_label' => $meta['label'],
                                    'icon' => $meta['icon'],
                                    'title' => $n['title'],
                                    'message' => $n['message'],
                                    'created_at' => date('M j, Y · g:i A', strtotime($n['created_at'])),
                                    'time_ago' => $timeAgo,
                                    'is_read' => (bool)$is_read,
                                    'action_url' => $actionUrl,
                                    'action_text' => $meta['action_text'],
                                    'is_urgent' => (bool)$isUrgent,
                                    'request_id' => $n['request_id'] ?? null,
                                    'blood_group' => $n['blood_group'] ?? null,
                                    'hospital' => $n['hospital'] ?? null,
                                    'request_units' => $n['request_units'] ?? null,
                                    'request_urgency' => $n['request_urgency'] ?? null,
                                    'requester_name' => $n['requester_name'] ?? null,
                                    'requester_email' => $n['requester_email'] ?? null,
                                    'donor_username' => $n['donor_username'] ?? null,
                                    'donor_phone' => $n['donor_phone'] ?? null,
                                    'donor_blood_group' => $n['donor_blood_group'] ?? null,
                                    'registered_username' => $n['registered_username'] ?? null,
                                    'registered_email' => $n['registered_email'] ?? null,
                                ];
                                $jsonAttr = htmlspecialchars(json_encode($detailPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="p-4 sm:p-5 transition-all flex flex-col md:flex-row md:items-center justify-between gap-3.5 <?= $cardBg ?>" id="notif-row-<?= $n['id'] ?>">
                                    <!-- Left: Icon + Clean Content Details -->
                                    <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                        <!-- Clean Minimal Icon Box -->
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 border <?= $is_read ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-red-100 text-red-600 border-red-200 shadow-2xs' ?>">
                                            <i class="fas <?= htmlspecialchars($meta['icon']) ?> text-sm"></i>
                                        </div>

                                        <!-- Text Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                <!-- Clean Type Tag -->
                                                <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-md <?= $is_read ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700 font-bold' ?>">
                                                    <?= htmlspecialchars($meta['label']) ?>
                                                </span>
                                                
                                                <?php if (!$is_read): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-red-700 bg-red-100 px-2 py-0.5 rounded-md">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600 pulse-dot"></span>
                                                        <span>New</span>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($isUrgent): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-white bg-red-600 px-2 py-0.5 rounded-md">
                                                        <i class="fas fa-fire text-[9px]"></i> Urgent
                                                    </span>
                                                <?php endif; ?>

                                                <span class="text-xs text-gray-400 font-medium ml-auto whitespace-nowrap">
                                                    <i class="far fa-clock mr-1 text-gray-400"></i> <?= $timeAgo ?>
                                                </span>
                                            </div>

                                            <!-- Notification Title -->
                                            <h3 class="text-sm sm:text-base <?= $is_read ? 'font-semibold text-gray-800' : 'font-bold text-gray-900' ?> leading-snug">
                                                <?= htmlspecialchars($n['title']) ?>
                                            </h3>

                                            <!-- Notification Message Snippet (Clean 1-line snippet) -->
                                            <p class="text-xs sm:text-sm text-gray-600 mt-0.5 line-clamp-1 leading-relaxed font-normal">
                                                <?= htmlspecialchars($n['message']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Right: Clean Actions (View Detail + Action + Mark + Delete) -->
                                    <div class="flex items-center gap-2 self-end md:self-center flex-shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-gray-100 w-full md:w-auto justify-end">
                                        <!-- View Detail Button (Opens Modal) -->
                                        <button type="button" 
                                                onclick='openNotificationDetail(<?= $jsonAttr ?>)' 
                                                class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-white hover:bg-gray-50 text-gray-700 hover:text-red-600 border border-gray-300 hover:border-red-400 shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                                            <i class="far fa-eye text-gray-400"></i>
                                            <span>View Detail</span>
                                        </button>

                                        <!-- Primary Action Navigation Button -->
                                        <a href="<?= htmlspecialchars($actionUrl) ?>" 
                                           onclick="markNotificationReadAndGo(<?= (int)$n['id'] ?>, '<?= htmlspecialchars($actionUrl) ?>'); return false;" 
                                           class="px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-red-600 hover:bg-red-700 text-white shadow-2xs transition flex items-center gap-1.5">
                                            <span><?= htmlspecialchars($meta['action_text']) ?></span>
                                            <i class="fas fa-arrow-right text-[9px]"></i>
                                        </a>

                                        <!-- Mark Read / Unread Button -->
                                        <?php if (!$is_read): ?>
                                            <button onclick="toggleReadStatusPage(<?= (int)$n['id'] ?>, true)" title="Mark as read" class="w-8 h-8 rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-red-600 hover:border-red-300 transition flex items-center justify-center text-xs shadow-2xs cursor-pointer">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="toggleReadStatusPage(<?= (int)$n['id'] ?>, false)" title="Mark as unread" class="w-8 h-8 rounded-xl border border-gray-200 bg-white text-gray-400 hover:bg-gray-50 hover:text-red-600 hover:border-red-300 transition flex items-center justify-center text-xs shadow-2xs cursor-pointer">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Delete Button -->
                                        <button onclick="deleteNotificationPage(<?= (int)$n['id'] ?>)" title="Delete alert" class="w-8 h-8 rounded-xl border border-gray-200 bg-white text-gray-400 hover:text-red-600 hover:bg-red-50 hover:border-red-300 transition flex items-center justify-center text-xs shadow-2xs cursor-pointer">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination Bar -->
                        <?php if ($totalPages > 1): ?>
                            <div class="p-4 sm:p-5 bg-gray-50/70 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                                <span class="text-xs text-gray-500 font-medium">
                                    Page <span class="font-bold text-gray-800"><?= $page ?></span> of <span class="font-bold text-gray-800"><?= $totalPages ?></span>
                                </span>

                                <div class="flex items-center gap-1.5">
                                    <?php 
                                        $baseParams = $_GET; 
                                        unset($baseParams['page']);
                                        $buildPageUrl = function($p) use ($baseParams) {
                                            $baseParams['page'] = $p;
                                            return '?' . http_build_query($baseParams);
                                        };
                                    ?>

                                    <?php if ($page > 1): ?>
                                        <a href="<?= $buildPageUrl(1) ?>" class="w-8 h-8 rounded-xl border border-gray-200 bg-white text-gray-600 flex items-center justify-center text-xs hover:bg-gray-50 hover:text-red-600 transition shadow-2xs" title="First Page">
                                            <i class="fas fa-angles-left"></i>
                                        </a>
                                        <a href="<?= $buildPageUrl($page - 1) ?>" class="px-3 h-8 rounded-xl border border-gray-200 bg-white text-gray-600 flex items-center justify-center text-xs hover:bg-gray-50 hover:text-red-600 transition font-semibold shadow-2xs">
                                            Prev
                                        </a>
                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <?php
                                        $startP = max(1, $page - 2);
                                        $endP = min($totalPages, $page + 2);
                                        for ($p = $startP; $p <= $endP; $p++):
                                    ?>
                                        <a href="<?= $buildPageUrl($p) ?>" class="w-8 h-8 rounded-xl text-xs font-bold flex items-center justify-center transition <?= $p === $page ? 'bg-red-600 text-white shadow-xs' : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 hover:text-red-600 shadow-2xs' ?>">
                                            <?= $p ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= $buildPageUrl($page + 1) ?>" class="px-3 h-8 rounded-xl border border-gray-200 bg-white text-gray-600 flex items-center justify-center text-xs hover:bg-gray-50 hover:text-red-600 transition font-semibold shadow-2xs">
                                            Next
                                        </a>
                                        <a href="<?= $buildPageUrl($totalPages) ?>" class="w-8 h-8 rounded-xl border border-gray-200 bg-white text-gray-600 flex items-center justify-center text-xs hover:bg-gray-50 hover:text-red-600 transition shadow-2xs" title="Last Page">
                                            <i class="fas fa-angles-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="p-12 text-center bg-white">
                            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl border border-red-100">
                                <i class="fas fa-bell-slash"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">No notifications found</h3>
                            <p class="text-gray-500 mt-1 max-w-md mx-auto text-xs sm:text-sm leading-relaxed">
                                <?php if ($search || $typeFilter || $filter !== 'all'): ?>
                                    No alerts match your filter criteria. Try clearing search keywords or resetting filters.
                                <?php else: ?>
                                    You're all caught up! New alerts regarding blood requests, donor assignments, or registrations will appear here.
                                <?php endif; ?>
                            </p>
                            <?php if ($search || $typeFilter || $filter !== 'all'): ?>
                                <a href="notifications.php" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition shadow-xs">
                                    <i class="fas fa-rotate-left text-[10px]"></i>
                                    <span>Reset All Filters</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- 4. Notification Detail Modal (Clean, Minimal, Informative) -->
    <div id="notifDetailModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 z-50 transition-opacity duration-200" onclick="closeNotificationDetailOnBackdrop(event)">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-gray-200 animate-fade-down" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gray-50/70">
                <div class="flex items-center gap-3">
                    <div id="modalIconBox" class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center text-base border border-red-200 flex-shrink-0">
                        <i id="modalIcon" class="fas fa-bell"></i>
                    </div>
                    <div>
                        <span id="modalTypeBadge" class="text-[11px] font-bold px-2.5 py-0.5 rounded-md bg-red-100 text-red-700">Type</span>
                        <p id="modalTimeAgo" class="text-xs text-gray-400 font-medium mt-0.5">Time</p>
                    </div>
                </div>
                <button type="button" onclick="closeNotificationDetail()" class="w-8 h-8 rounded-xl bg-white border border-gray-200 text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition cursor-pointer">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <!-- Title -->
                <div>
                    <h3 id="modalTitle" class="text-base font-bold text-gray-900 leading-snug">Notification Title</h3>
                </div>

                <!-- Message Box -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 text-sm text-gray-700 leading-relaxed" id="modalMessage">
                    Message content goes here...
                </div>

                <!-- Detailed Information Section (Dynamic) -->
                <div id="modalMetaSection" class="space-y-2.5 pt-2 border-t border-gray-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Related Details</p>
                    <div id="modalMetaGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                <button type="button" onclick="closeNotificationDetail()" class="px-4 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-300 hover:bg-gray-50 rounded-xl transition cursor-pointer">
                    Close
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" id="modalToggleReadBtn" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 hover:text-red-600 rounded-xl transition cursor-pointer">
                        Mark as Read
                    </button>
                    <a id="modalActionBtn" href="#" class="px-4 py-2 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                        <span id="modalActionBtnText">View Details</span>
                        <i class="fas fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-side Page AJAX & Modal Scripts -->
    <script>
        var currentModalNotifId = null;

        function openNotificationDetail(data) {
            currentModalNotifId = data.id;

            // Populate text
            document.getElementById('modalTitle').textContent = data.title || 'Notification';
            document.getElementById('modalMessage').textContent = data.message || '';
            document.getElementById('modalTimeAgo').textContent = data.created_at + ' (' + data.time_ago + ')';
            
            // Type badge & Icon
            var typeBadge = document.getElementById('modalTypeBadge');
            typeBadge.textContent = data.type_label || data.type;
            
            var iconElem = document.getElementById('modalIcon');
            iconElem.className = 'fas ' + (data.icon || 'fa-bell');

            // Action Button
            var actionBtn = document.getElementById('modalActionBtn');
            var actionBtnText = document.getElementById('modalActionBtnText');
            if (data.action_url) {
                actionBtn.href = data.action_url;
                actionBtnText.textContent = data.action_text || 'Open Page';
                actionBtn.style.display = 'inline-flex';
                actionBtn.onclick = function() {
                    markNotificationReadAndGo(data.id, data.action_url);
                };
            } else {
                actionBtn.style.display = 'none';
            }

            // Mark Read/Unread Button
            var toggleReadBtn = document.getElementById('modalToggleReadBtn');
            if (data.is_read) {
                toggleReadBtn.textContent = 'Mark as Unread';
                toggleReadBtn.onclick = function() {
                    toggleReadStatusPage(data.id, false);
                };
            } else {
                toggleReadBtn.textContent = 'Mark as Read';
                toggleReadBtn.onclick = function() {
                    toggleReadStatusPage(data.id, true);
                };
            }

            // Populate Meta Grid
            var metaGrid = document.getElementById('modalMetaGrid');
            metaGrid.innerHTML = '';
            var metaSection = document.getElementById('modalMetaSection');
            var hasMeta = false;

            function addMetaItem(label, value, iconClass) {
                if (!value) return;
                hasMeta = true;
                var item = document.createElement('div');
                item.className = 'p-2.5 rounded-xl bg-gray-50 border border-gray-200/80 flex items-center gap-2.5';
                item.innerHTML = '<i class="' + iconClass + ' text-red-500 text-xs w-4 text-center"></i>' +
                                 '<div class="min-w-0 flex-1">' +
                                 '<p class="text-[10px] text-gray-400 uppercase font-semibold">' + label + '</p>' +
                                 '<p class="text-xs font-bold text-gray-800 truncate">' + value + '</p>' +
                                 '</div>';
                metaGrid.appendChild(item);
            }

            if (data.request_id) addMetaItem('Request ID', '#' + data.request_id, 'fas fa-file-medical');
            if (data.blood_group) addMetaItem('Blood Group', data.blood_group, 'fas fa-droplet');
            if (data.request_units) addMetaItem('Units Required', data.request_units + ' unit(s)', 'fas fa-boxes-stacked');
            if (data.request_urgency) addMetaItem('Urgency', data.request_urgency, 'fas fa-fire');
            if (data.hospital) addMetaItem('Hospital', data.hospital, 'fas fa-hospital');
            if (data.requester_name) addMetaItem('Requester', data.requester_name, 'fas fa-user');
            if (data.requester_email) addMetaItem('Requester Email', data.requester_email, 'fas fa-envelope');
            if (data.donor_username) addMetaItem('Assigned Donor', data.donor_username, 'fas fa-heart');
            if (data.donor_phone) addMetaItem('Donor Phone', data.donor_phone, 'fas fa-phone');
            if (data.donor_blood_group) addMetaItem('Donor Blood', data.donor_blood_group, 'fas fa-droplet');
            if (data.registered_username) addMetaItem('Registered User', data.registered_username, 'fas fa-user-plus');
            if (data.registered_email) addMetaItem('User Email', data.registered_email, 'fas fa-envelope');

            metaSection.style.display = hasMeta ? 'block' : 'none';

            // Show Modal
            var modal = document.getElementById('notifDetailModal');
            modal.classList.remove('hidden');

            // If it was unread, automatically mark it as read in the background
            if (!data.is_read) {
                fetch('notifications_ajax.php?action=mark_read&id=' + encodeURIComponent(data.id), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(resData => {
                    if (resData.success && typeof updateNotificationBadge === 'function') {
                        updateNotificationBadge(resData.unread_count);
                    }
                })
                .catch(() => {});
            }
        }

        function closeNotificationDetail() {
            var modal = document.getElementById('notifDetailModal');
            modal.classList.add('hidden');
        }

        function closeNotificationDetailOnBackdrop(event) {
            if (event.target === document.getElementById('notifDetailModal')) {
                closeNotificationDetail();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeNotificationDetail();
        });

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