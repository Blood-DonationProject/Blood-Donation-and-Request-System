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
                ? 'notif-item-unread bg-red-50 hover:bg-red-100 border-l-4 border-red-500' 
                : 'bg-white hover:bg-gray-50';
            
            var dot = isUnread ? '<span class="unread-indicator-dot w-2 h-2 rounded-full bg-red-500 shadow-sm mt-1 flex-shrink-0"></span>' : '';
            var actionUrl = n.action_url || 'notifications.php';
            var safeTitle = escapeHtml(n.title || 'Notification');
            var safeMsg = escapeHtml(n.message || '');
            var safeTime = escapeHtml(n.time_ago || '');
            var safeIcon = n.icon || 'fa-bell';
            var safeIconColor = n.icon_color || 'text-red-500';
            var safeActionText = escapeHtml(n.action_text || 'View →');

            html += `
                <div class="block p-4 border-b border-gray-100 transition cursor-pointer ${unreadClass}" onclick="markNotificationReadAndGo(${n.id}, '${actionUrl}')">
                    <div class="flex items-start gap-3.5">
                        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0 mt-0.5 border border-red-100">
                            <i class="fas ${safeIcon} ${safeIconColor} text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-xs ${isUnread ? 'font-black text-gray-900' : 'font-bold text-gray-800'} truncate">${safeTitle}</p>
                                ${dot}
                            </div>
                            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2 leading-relaxed">${safeMsg}</p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-[10px] text-gray-400 font-medium">${safeTime}</span>
                                <span class="text-[11px] font-bold text-red-600 hover:text-red-800">${safeActionText}</span>
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

<nav class="bg-slate-100/95 backdrop-blur-md shadow-sm sticky top-0 z-30 border-b-2 border-pink-100 transition-colors duration-300">
    <div class="px-6 sm:px-8 py-3.5 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <div>
                <h2 class="text-2xl sm:text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-red-600 via-rose-600 to-red-700 tracking-tight"><?= htmlspecialchars($pageData['title']) ?></h2>
                <?php if ($pageData['description']): ?>
                    <p class="text-gray-500 font-semibold mt-0.5 text-xs sm:text-sm"><?= htmlspecialchars($pageData['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center space-x-3 sm:space-x-4">
            <!-- Theme Toggle -->
            <button type="button" class="theme-toggle-btn relative w-11 h-11 rounded-2xl border-2 border-pink-200 bg-pink-50/60 hover:bg-pink-100 hover:border-red-400 flex items-center justify-center cursor-pointer shadow-xs transition" aria-label="Toggle theme" onclick="toggleTheme()">
                <span class="theme-icon-sun"><i class="fas fa-sun text-amber-500 text-lg"></i></span>
                <span class="theme-icon-moon" style="display:none"><i class="fas fa-moon text-rose-700 text-lg"></i></span>
            </button>
            
            <!-- Notifications Bell -->
            <div class="relative">
                <button onclick="toggleNotifications()" id="adminNotifBellBtn" class="relative w-11 h-11 rounded-2xl border-2 border-pink-200 bg-pink-50/60 hover:bg-pink-100 hover:border-red-400 flex items-center justify-center shadow-xs transition cursor-pointer" aria-label="View notifications">
                    <i class="fas fa-bell text-red-600 text-lg"></i>
                    <span id="notifBadgeCount" class="<?= $notifCount > 0 ? '' : 'hidden' ?> absolute -top-1 -right-1 bg-gradient-to-r from-red-600 to-rose-600 text-white text-[11px] font-black rounded-full h-5 min-w-[20px] px-1 flex items-center justify-center shadow-md shadow-red-200 pulse-dot">
                        <?= $notifCount > 99 ? '99+' : $notifCount ?>
                    </span>
                </button>
                
                <!-- Dropdown -->
                <div id="adminNotifDropdown" class="hidden absolute right-0 mt-3 w-88 sm:w-96 bg-white rounded-3xl shadow-2xl shadow-red-500/30 border-2 border-red-200 z-50 overflow-hidden">
                    <div class="p-4 bg-gradient-to-r from-red-500 to-pink-500 rounded-t-2xl flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <p class="font-bold text-white shadow-sm text-sm">Notifications</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="notifHeaderNewCount" class="text-xs text-white/90 font-medium"><?= $notifCount ?> new</span>
                            <button id="markAllReadBtn" onclick="markAllNotificationsReadAjax(event)" class="<?= $notifCount > 0 ? '' : 'hidden' ?> text-xs font-bold text-white hover:underline drop-shadow-sm">Mark all read</button>
                        </div>
                    </div>
                    <div id="adminNotifItems" class="max-h-96 overflow-y-auto divide-y-2 divide-gray-100">
                        <?php if (count($admin_notifs) > 0): ?>
                            <?php foreach ($admin_notifs as $n):
                                $is_read = $n['is_read'] == 1;
                                $meta = get_notification_meta($n);
                                $bgClass = $is_read 
                                    ? 'bg-white hover:bg-gray-50' 
                                    : 'notif-item-unread bg-red-50 hover:bg-red-100 border-l-4 border-red-500';
                                
                                $action_url = $meta['action_url'];
                            ?>
                                <div class="block p-4 border-b border-gray-100 transition cursor-pointer <?= $bgClass ?>" onclick="markNotificationReadAndGo(<?= (int)$n['id'] ?>, '<?= htmlspecialchars($action_url) ?>')">
                                    <div class="flex items-start gap-3.5">
                                        <div class="w-9 h-9 rounded-xl <?= $is_read ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-red-100 text-red-600 border-red-200' ?> flex items-center justify-center flex-shrink-0 mt-0.5 border">
                                            <i class="fas <?= htmlspecialchars($meta['icon']) ?> text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-1">
                                                <p class="text-xs <?= $is_read ? 'font-bold text-gray-800' : 'font-black text-gray-900' ?> truncate"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></p>
                                                <?php if (!$is_read): ?>
                                                    <span class="unread-indicator-dot w-2 h-2 rounded-full bg-red-500 shadow-sm mt-1 flex-shrink-0"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2 leading-relaxed font-medium"><?= htmlspecialchars($n['message']) ?></p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <span class="text-[10px] text-gray-400 font-semibold"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></span>
                                                <span class="text-[11px] font-bold text-red-600 hover:text-red-800"><?= htmlspecialchars($meta['action_text']) ?></span>
                                            </div>v>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center bg-white">
                                <div class="w-14 h-14 bg-gray-50 text-gray-400 border border-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-2 text-xl">
                                    <i class="fas fa-bell-slash"></i>
                                </div>
                                <p class="text-gray-800 font-bold text-sm">No notifications yet</p>
                                <p class="text-gray-400 text-xs mt-0.5">All caught up!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3.5 text-center bg-red-50 hover:bg-red-100 transition rounded-b-3xl">
                        <a href="notifications.php" class="text-xs font-bold text-red-600 flex items-center justify-center gap-1.5 transition">
                            <span>View All Notifications</span>
                            <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Admin Profile -->
            <div class="relative" id="adminMenu">
                <div class="flex items-center space-x-3 cursor-pointer pl-4 border-l-2 border-pink-100" onclick="toggleAdminDropdown()">
                    <div class="text-right hidden sm:block">
                        <p class="font-black text-sm text-gray-900"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                        <p class="text-[11px] font-bold text-rose-600 uppercase tracking-wider" data-i18n="administrator">Administrator</p>
                    </div>
                    <div class="w-11 h-11 bg-gradient-to-br from-red-500 to-rose-600 text-white rounded-2xl flex items-center justify-center font-black text-sm shadow-md shadow-red-200">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                    </div>
                </div>
                <div id="adminDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-3xl shadow-2xl border-2 border-pink-100 z-50 overflow-hidden">
                    <div class="p-5 border-b-2 border-pink-50 bg-gradient-to-r from-rose-50/90 to-pink-50/40">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-rose-600 text-white rounded-2xl flex items-center justify-center font-black text-lg shadow-md shadow-red-200 flex-shrink-0">
                                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="font-black text-gray-900 truncate text-sm"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                <p class="text-xs text-rose-600 font-semibold truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 space-y-1.5">
                        <a href="notifications.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-800 hover:bg-pink-50 hover:text-red-600 rounded-2xl transition font-bold">
                            <i class="fas fa-bell text-red-500 text-xs w-4 text-center"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white py-2.5 rounded-2xl font-black transition mt-2 shadow-md shadow-red-200" data-i18n="logout">Logout</a>
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