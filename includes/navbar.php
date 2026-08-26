<?php
    require_once __DIR__ . '/notification_helper.php';

    $currentPage = basename($_SERVER['PHP_SELF']);
    $pageTitles = [
        'dashboard.php' => ['title' => 'Dashboard', 'description' => 'Overview of your blood donation system.'],
        'users_crud.php' => ['title' => 'Manage Users', 'description' => 'Manage and monitor the user network.'],
        'donor_crud.php' => ['title' => 'Manage Donors', 'description' => 'Manage and monitor the blood donor network.'],
        'donation_history_crud.php' => ['title' => 'Donation History', 'description' => 'Track and manage blood donation records.'],
        'blood_requests_crud.php' => ['title' => 'Blood Requests', 'description' => 'Manage blood request submissions from users.'],
        'assignments.php' => ['title' => 'Assignments', 'description' => 'Track and manage donor assignments.'],
        'notifications.php' => ['title' => 'Notifications', 'description' => 'View and manage all system notifications.'],
        'email_logs.php' => ['title' => 'Email Logs', 'description' => 'Monitor system email delivery statuses and error logs.'],
    ];
    $pageData = $pageTitles[$currentPage] ?? ['title' => 'Dashboard', 'description' => ''];

    // Fetch admin notifications
    $admin_notifs = [];
    $notifCount = 0;
    $adminId = (int)($_SESSION['user_id'] ?? 0);

    if ($adminId > 0 && ($_SESSION['user_role'] ?? '') === 'Admin') {
        // Fallback GET actions if JS is disabled
        if (isset($_GET['read_notifs'])) {
            mark_all_notifications_read($conn, $adminId);
            $current_url = strtok($_SERVER["REQUEST_URI"], '?');
            echo "<script>window.location.href = '" . addslashes($current_url) . "';</script>";
            exit;
        }

        if (isset($_GET['mark_read'])) {
            $notif_id = (int)$_GET['mark_read'];
            if ($notif_id > 0) {
                mark_notification_read($conn, $notif_id, $adminId);
            }
            if (isset($_GET['redirect'])) {
                $redirect = $_GET['redirect'];
                echo "<script>window.location.href = '" . addslashes($redirect) . "';</script>";
                exit;
            }
            $current_url = strtok($_SERVER["REQUEST_URI"], '?');
            echo "<script>window.location.href = '" . addslashes($current_url) . "';</script>";
            exit;
        }

        $notifCount = get_admin_unread_count($conn, $adminId);
        $admin_notifs = get_admin_notifications($conn, $adminId, 6, 0, 'all');
    }
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script>
    if (typeof toggleNotifications === 'undefined') {
        window.toggleNotifications = function() {
            var dropdown = document.getElementById('adminNotifDropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
                if (!dropdown.classList.contains('hidden') && typeof fetchAdminNotifications === 'function') {
                    fetchAdminNotifications();
                }
            }
        };
    }

    if (typeof markAllNotificationsReadAjax === 'undefined') {
        window.markAllNotificationsReadAjax = function(e) {
            if (e) e.preventDefault();
            fetch('../admin/notifications_ajax.php?action=mark_all_read', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(0);
                    // Update all unread indicators in dropdown
                    document.querySelectorAll('#adminNotifItems .notif-item-unread').forEach(el => {
                        el.classList.remove('notif-item-unread', 'bg-red-50/70', 'dark:bg-red-950/20');
                        el.classList.add('bg-white', 'dark:bg-gray-800');
                    });
                    document.querySelectorAll('#adminNotifItems .unread-indicator-dot').forEach(el => el.remove());
                    var headerCount = document.getElementById('notifHeaderNewCount');
                    if (headerCount) headerCount.textContent = '0 new';
                    var markAllBtn = document.getElementById('markAllReadBtn');
                    if (markAllBtn) markAllBtn.classList.add('hidden');
                }
            })
            .catch(err => console.error('Error marking all notifications read:', err));
        };
    }

    if (typeof markNotificationReadAndGo === 'undefined') {
        window.markNotificationReadAndGo = function(notifId, targetUrl) {
            fetch('../admin/notifications_ajax.php?action=mark_read&id=' + encodeURIComponent(notifId), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .finally(() => {
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });
        };
    }

    function updateNotificationBadge(count) {
        var badge = document.getElementById('notifBadgeCount');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
        var headerCount = document.getElementById('notifHeaderNewCount');
        if (headerCount) {
            headerCount.textContent = count + ' new';
        }
        var markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            if (count > 0) markAllBtn.classList.remove('hidden');
            else markAllBtn.classList.add('hidden');
        }
    }

    function fetchAdminNotifications() {
        fetch('../admin/notifications_ajax.php?action=get_latest&limit=6', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                renderDropdownNotifications(data.notifications);
            }
        })
        .catch(err => console.error('Error fetching notifications:', err));
    }

    function renderDropdownNotifications(items) {
        var container = document.getElementById('adminNotifItems');
        if (!container) return;

        if (!items || items.length === 0) {
            container.innerHTML = '<div class="p-6 text-center text-gray-400 dark:text-gray-500 text-sm"><i class="fas fa-bell-slash text-2xl mb-2 block opacity-60"></i>No notifications yet</div>';
            return;
        }

        var html = '';
        items.forEach(function(n) {
            var isUnread = n.is_read == 0;
            var unreadClass = isUnread 
                ? 'notif-item-unread bg-red-50/60 dark:bg-red-950/20 hover:bg-red-100/50 dark:hover:bg-red-900/30' 
                : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750';
            
            var dot = isUnread ? '<span class="unread-indicator-dot w-2 h-2 rounded-full bg-red-600 shadow-sm shadow-red-300 mt-1 flex-shrink-0"></span>' : '';
            var actionUrl = n.action_url || 'notifications.php';
            var safeTitle = escapeHtml(n.title || 'Notification');
            var safeMsg = escapeHtml(n.message || '');
            var safeTime = escapeHtml(n.time_ago || '');
            var safeIcon = n.icon || 'fa-bell';
            var safeIconColor = n.icon_color || 'text-red-500';
            var safeActionText = escapeHtml(n.action_text || 'View →');

            html += `
                <div class="block p-3.5 border-b border-gray-100 dark:border-gray-700/60 transition cursor-pointer ${unreadClass}" onclick="markNotificationReadAndGo(${n.id}, '${actionUrl}')">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas ${safeIcon} ${safeIconColor} text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">${safeTitle}</p>
                                ${dot}
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5 line-clamp-2 leading-relaxed">${safeMsg}</p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium">${safeTime}</span>
                                <span class="text-[11px] font-semibold text-red-600 hover:text-red-700 dark:text-red-400">${safeActionText}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Auto-poll notifications every 30 seconds
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            fetchAdminNotifications();
        }
    }, 30000);

    if (typeof toggleTheme === 'undefined') {
        (function() {
            var KEY = 'bloodlife-theme';
            function getTheme() {
                return localStorage.getItem(KEY) || 'light';
            }
            function apply(t) {
                if (t === 'dark') document.documentElement.classList.add('dark');
                else document.documentElement.classList.remove('dark');
                document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
                    var sun = btn.querySelector('.theme-icon-sun');
                    var moon = btn.querySelector('.theme-icon-moon');
                    if (sun) sun.style.display = t === 'dark' ? 'none' : '';
                    if (moon) moon.style.display = t === 'dark' ? '' : 'none';
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
    }
</script>

<nav class="bg-blue-100 dark:bg-gray-800 shadow-sm sticky top-0 z-30 border-b border-gray-100 dark:border-gray-700 transition-colors duration-300">
    <div class="px-8 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <div>
                <h2 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-700"><?= htmlspecialchars($pageData['title']) ?></h2>
                <?php if ($pageData['description']): ?>
                    <p class="text-gray-500 dark:text-gray-400 mt-1 text-sm"><?= htmlspecialchars($pageData['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Theme Toggle -->
            <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-center cursor-pointer hover:border-red-300 hover:bg-red-50 dark:hover:bg-gray-600 transition" aria-label="Toggle theme" onclick="toggleTheme()">
                <span class="theme-icon-sun"><i class="fas fa-sun text-gray-600 dark:text-yellow-400"></i></span>
                <span class="theme-icon-moon" style="display:none"><i class="fas fa-moon text-gray-600 dark:text-gray-300"></i></span>
            </button>
            
            <!-- Notifications Bell -->
            <div class="relative">
                <button onclick="toggleNotifications()" id="adminNotifBellBtn" class="relative w-10 h-10 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex items-center justify-center hover:bg-red-50 hover:border-red-300 dark:hover:bg-gray-600 transition" aria-label="View notifications">
                    <i class="fas fa-bell text-gray-600 dark:text-gray-300"></i>
                    <span id="notifBadgeCount" class="<?= $notifCount > 0 ? '' : 'hidden' ?> absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full h-5 min-w-[20px] px-1 flex items-center justify-center shadow-sm pulse-dot">
                        <?= $notifCount > 99 ? '99+' : $notifCount ?>
                    </span>
                </button>
                
                <!-- Dropdown -->
                <div id="adminNotifDropdown" class="hidden absolute right-0 mt-3 w-88 sm:w-96 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 z-50 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/80">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-bell text-red-600 dark:text-red-400"></i>
                            <p class="font-bold text-gray-800 dark:text-gray-100 text-sm">Notifications</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="notifHeaderNewCount" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"><?= $notifCount ?> new</span>
                            <button id="markAllReadBtn" onclick="markAllNotificationsReadAjax(event)" class="<?= $notifCount > 0 ? '' : 'hidden' ?> text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 hover:underline">Mark all read</button>
                        </div>
                    </div>
                    
                    <div id="adminNotifItems" class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if (count($admin_notifs) > 0): ?>
                            <?php foreach ($admin_notifs as $n):
                                $is_read = $n['is_read'] == 1;
                                $meta = get_notification_meta($n);
                                $bgClass = $is_read 
                                    ? 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750' 
                                    : 'notif-item-unread bg-red-50/60 dark:bg-red-950/20 hover:bg-red-100/50 dark:hover:bg-red-900/30';
                                
                                $action_url = $meta['action_url'];
                            ?>
                                <div class="block p-3.5 border-b border-gray-100 dark:border-gray-700/60 transition cursor-pointer <?= $bgClass ?>" onclick="markNotificationReadAndGo(<?= (int)$n['id'] ?>, '<?= htmlspecialchars($action_url) ?>')">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <i class="fas <?= htmlspecialchars($meta['icon']) ?> <?= htmlspecialchars($meta['icon_color']) ?> text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-1">
                                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></p>
                                                <?php if (!$is_read): ?>
                                                    <span class="unread-indicator-dot w-2 h-2 rounded-full bg-red-600 shadow-sm shadow-red-300 mt-1 flex-shrink-0"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5 line-clamp-2 leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></span>
                                                <span class="text-[11px] font-semibold text-red-600 hover:text-red-700 dark:text-red-400"><?= htmlspecialchars($meta['action_text']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-400 dark:text-gray-500 text-sm">
                                <i class="fas fa-bell-slash text-2xl mb-2 block opacity-60"></i>
                                No notifications yet
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 border-t border-gray-100 dark:border-gray-700 text-center bg-gray-50 dark:bg-gray-800/80 rounded-b-2xl">
                        <a href="notifications.php" class="text-xs font-bold text-red-600 hover:text-red-700 dark:text-red-400 flex items-center justify-center gap-1.5 transition">
                            <span>View All Notifications</span>
                            <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Admin Profile -->
            <div class="relative" id="adminMenu">
                <div class="flex items-center space-x-3 cursor-pointer pl-3 border-l border-gray-200 dark:border-gray-700" onclick="toggleAdminDropdown()">
                    <div class="text-right">
                        <p class="font-semibold text-sm text-gray-800 dark:text-gray-200"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                        <p class="text-xs text-gray-400" data-i18n="administrator">Administrator</p>
                    </div>
                    <div class="w-10 h-10 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-sm shadow-sm">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                    </div>
                </div>
                <div id="adminDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 z-50">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-lg shadow-sm">
                                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-gray-100 truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 space-y-1">
                        <a href="notifications.php" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-xl transition font-medium">
                            <i class="fas fa-bell text-gray-400 text-xs w-4 text-center"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-xl font-semibold hover:bg-red-700 transition mt-2 shadow-sm" data-i18n="logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('click', function(e) {
        const aMenu = document.getElementById('adminMenu');
        const aDrop = document.getElementById('adminDropdown');
        if (aMenu && aDrop && !aMenu.contains(e.target)) aDrop.classList.add('hidden');

        const nBtn = document.getElementById('adminNotifBellBtn');
        const nMenu = document.getElementById('adminNotifDropdown');
        if (nMenu && nBtn && !nMenu.contains(e.target) && !nBtn.contains(e.target)) {
            nMenu.classList.add('hidden');
        }
    });
</script>