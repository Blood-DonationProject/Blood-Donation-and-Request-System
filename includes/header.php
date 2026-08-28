<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = 'User';
$notifications = [];
if ($isLoggedIn && isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    require_once __DIR__ . '/../config/db.php';
    $stmt = $conn->prepare("SELECT username, role, status, last_activity, last_login FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $userRole = (isset($row['role']) && $row['role'] === 'Admin') || ($_SESSION['user_role'] ?? '') === 'Admin' ? 'Admin' : 'User';
            $isInactive = false;

            // Only normal User accounts are subject to automatic inactivity
            // Admin accounts are NEVER automatically deactivated
            if ($userRole === 'User') {
                // Check status
                if (isset($row['status']) && $row['status'] === 'Inactive') {
                    $isInactive = true;
                }

                // Check 3-year inactivity using actual activity/login date
                $activityTimestamp = !empty($row['last_activity']) ? $row['last_activity'] : (!empty($row['last_login']) ? $row['last_login'] : null);
                if (!$isInactive && !empty($activityTimestamp)) {
                    $lastActivityDate = new DateTime($activityTimestamp);
                    $threeYearsAgo = new DateTime('-3 years');
                    if ($lastActivityDate <= $threeYearsAgo) {
                        $isInactive = true;
                        // Update status in DB strictly for normal user
                        $updateStmt = $conn->prepare("UPDATE users SET status = 'Inactive' WHERE id = ? AND role = 'User'");
                        if ($updateStmt) {
                            $updateStmt->bind_param("i", $_SESSION['user_id']);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                    }
                }

                if ($isInactive) {
                    session_unset();
                    session_destroy();
                    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
                    header("Location: " . $basePath . "/user/login.php?inactive=1");
                    exit;
                }
            }
            $username = $row['username'];

            // Track last_activity (update every 5 minutes)
            if (!isset($_SESSION['last_activity_update']) || (time() - $_SESSION['last_activity_update']) > 300) {
                $updateActivity = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                if ($updateActivity) {
                    $updateActivity->bind_param("i", $_SESSION['user_id']);
                    $updateActivity->execute();
                    $updateActivity->close();
                }
                $_SESSION['last_activity_update'] = time();
            }
        } else {
            session_unset();
            session_destroy();
            $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
            header("Location: " . $basePath . "/user/login.php");
            exit;
        }
        $stmt->close();
    }

    // Mark notifications as read if requested
    if (isset($_GET['read_notifs'])) {
        $stmt_read = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND is_deleted = 0");
        if ($stmt_read) {
            $stmt_read->bind_param('i', $_SESSION['user_id']);
            $stmt_read->execute();
            $stmt_read->close();
        }
        $current_url = strtok($_SERVER["REQUEST_URI"], '?');
        echo "<script>window.location.href = '" . addslashes($current_url) . "';</script>";
        exit;
    }

    if (isset($_GET['mark_read'])) {
        $notif_id = (int)$_GET['mark_read'];
        $stmt_mark = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        if ($stmt_mark) {
            $stmt_mark->bind_param('ii', $notif_id, $_SESSION['user_id']);
            $stmt_mark->execute();
            $stmt_mark->close();
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

    // Fetch user notifications
    $stmt_notif = $conn->prepare("SELECT id, request_id, assignment_id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notifCount = 0;
    if ($stmt_notif) {
        $stmt_notif->bind_param('i', $_SESSION['user_id']);
        $stmt_notif->execute();
        $res_notif = $stmt_notif->get_result();
        while ($n = $res_notif->fetch_assoc()) {
            $notifications[] = $n;
            if ($n['is_read'] == 0) $notifCount++;
        }
        $stmt_notif->close();
    }
} elseif ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'User';
    $notifCount = 0;
}

?>
<nav class="bg-slate-100 shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <a href="index.php" class="flex items-center space-x-3 animate-fade-down">
                <span class="text-3xl bg-red-200 p-2 rounded-full shadow-black/35 shadow-md flex items-center justify-center">🩸</span>
                <div>
                    <h1 class="font-bold text-2xl text-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500 font-medium" data-i18n="save_lives_together">Save Lives Together</p>
                </div>
            </a>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="text-gray-700 hover:text-red-600 font-semibold text-base transition" data-i18n="home">Home</a>
                <a href="donor.php" class="text-gray-700 hover:text-red-600 font-semibold text-base transition" data-i18n="donors">Donors</a>
                <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-semibold text-base transition" data-i18n="requests">Requests</a>
                <a href="about.php" class="text-gray-700 hover:text-red-600 font-semibold text-base transition">About Us</a>

                <button type="button" class="theme-toggle-btn relative w-11 h-11 rounded-xl border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 hover:bg-red-50 transition" aria-label="Toggle theme" onclick="toggleTheme()">
                    <span class="theme-icon-sun"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <circle cx="12" cy="12" r="5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
                        </svg></span>
                    <span class="theme-icon-moon" style="display:none"><svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                        </svg></span>
                </button>
                <?php if ($isLoggedIn): ?>
                    <!-- Bell Icon -->
                    <div class="relative" id="notifMenu">
                        <button onclick="toggleNotifDropdown()" class="relative w-11 h-11 rounded-xl border-2 border-gray-200 bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:border-red-400 transition cursor-pointer">
                            <span class="text-xl">🔔</span>
                            <?php if ($notifCount > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm pulse-dot"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                                <p class="font-semibold text-gray-800">Notifications</p>
                                <div>
                                    <span class="text-xs text-gray-400 mr-2"><?= $notifCount ?> new</span>
                                    <?php if ($notifCount > 0): ?>
                                        <a href="?read_notifs=1" class="text-xs text-blue-600 hover:underline">Mark all as read</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (count($notifications) > 0): ?>
                                <div class="max-h-80 overflow-y-auto">
                                    <?php foreach ($notifications as $n):
                                        $is_read = $n['is_read'] == 1;
                                        $bgClass = $is_read ? 'bg-white hover:bg-gray-50' : 'bg-red-50 hover:bg-red-100';

                                        // Fetch extra contact info for successful assignments
                                        $contactHtml = '';
                                        $nTitle = $n['title'] ?? '';
                                        if (!empty($n['assignment_id']) && (strpos($nTitle, 'Assignment') !== false || strpos($nTitle, 'Assigned') !== false)) {
                                            $detailQuery = "SELECT br.users_id AS requester_user_id, br.hospital, br.required_date, bg.blood_gp_name, ru.username AS requester_name, ru.email AS requester_email, rd.phone AS requester_phone, du.username AS donor_name, du.email AS donor_email, d.phone AS donor_phone FROM donor_assignments da JOIN blood_request br ON da.request_id = br.id LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id JOIN donor d ON da.donor_id = d.id JOIN users du ON d.user_id = du.id JOIN users ru ON br.users_id = ru.id LEFT JOIN donor rd ON ru.id = rd.user_id WHERE da.id = ?";
                                            $stmtDetail = $conn->prepare($detailQuery);
                                            if ($stmtDetail) {
                                                $stmtDetail->bind_param("i", $n['assignment_id']);
                                                $stmtDetail->execute();
                                                $detailRes = $stmtDetail->get_result();
                                                if ($detail = $detailRes->fetch_assoc()) {
                                                    if ($nTitle == 'New Assignment' || strpos($nTitle, 'Assignment') !== false) {
                                                        $n['title'] = 'Donor Assignment';
                                                        $contactHtml = '<div class="mt-2 p-2 bg-red-50 rounded-lg text-xs space-y-1 text-gray-700 border border-red-100 shadow-inner">';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Blood Group:</span> ' . htmlspecialchars($detail['blood_gp_name'] ?? '') . '</p>';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Hospital:</span> ' . htmlspecialchars($detail['hospital'] ?? '') . '</p>';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Required Date:</span> ' . htmlspecialchars($detail['required_date'] ?? '') . '</p>';
                                                        $contactHtml .= '</div>';
                                                    } elseif ($nTitle == 'Donor Assigned') {
                                                        $contactHtml = '<div class="mt-2 p-2 bg-blue-50 rounded-lg text-xs space-y-1 text-gray-700 border border-blue-100 shadow-inner">';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Blood Group:</span> ' . htmlspecialchars($detail['blood_gp_name'] ?? '') . '</p>';
                                                        $contactHtml .= '<div class="h-px bg-blue-200 my-1"></div>';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Donor Name:</span> ' . htmlspecialchars($detail['donor_name'] ?? '') . '</p>';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Donor Phone:</span> ' . htmlspecialchars($detail['donor_phone'] ?? '') . '</p>';
                                                        $contactHtml .= '<p><span class="font-bold text-gray-900">Donor Email:</span> ' . htmlspecialchars($detail['donor_email'] ?? '') . '</p>';
                                                        $contactHtml .= '</div>';
                                                    }
                                                }
                                                $stmtDetail->close();
                                            }
                                        }

                                        // Determine notification link dynamically based on type and title
                                        $link = "profile.php"; // default

                                        $nType = strtolower($n['type'] ?? '');
                                        $nTitle2 = strtolower($n['title'] ?? '');

                                        if ($n['title'] === 'Donor Assigned') {
                                            $link = "bloodrequest.php";
                                        } elseif (in_array($nType, ['assignment', 'donation']) || strpos($nTitle2, 'assign') !== false || strpos($nTitle2, 'donat') !== false) {
                                            $link = "donor.php";
                                        } elseif (in_array($nType, ['statusupdate', 'system']) || strpos($nTitle2, 'request') !== false || strpos($nTitle2, 'receive') !== false || strpos($nTitle2, 'status') !== false) {
                                            $link = "bloodrequest.php";
                                        } elseif (isset($_SESSION['role']) && strtolower($_SESSION['role']) == 'donor') {
                                            $link = "donor.php";
                                        }

                                        if (!empty($n['request_id']) && in_array($link, ['donor.php', 'bloodrequest.php'])) {
                                            $link .= "#req-" . $n['request_id'];
                                        }

                                        $read_link = "?mark_read=" . $n['id'] . "&redirect=" . urlencode($link);
                                    ?>
                                        <div class="block p-4 border-b border-gray-100 transition <?= $bgClass ?>">
                                            <div class="flex items-start justify-between">
                                                <p class="text-sm text-gray-900 font-bold flex items-center">
                                                    <i class="fas fa-bell text-red-500 mr-2"></i> <?= htmlspecialchars($n['title'] ?? 'Notification') ?>
                                                </p>
                                                <?php if (!$is_read): ?>
                                                    <span class="w-2 h-2 rounded-full bg-red-600 mt-1"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs text-gray-700 mt-1 font-medium"><?= htmlspecialchars($n['message']) ?></p>
                                            <?= $contactHtml ?>
                                            <div class="mt-2 flex items-center justify-between">
                                                <p class="text-[11px] text-gray-500 font-semibold"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></p>
                                                <a href="<?= htmlspecialchars($read_link) ?>" class="text-[11px] font-bold text-red-600 hover:text-red-700">
                                                    View Details →
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-gray-400 text-sm">
                                    No new notifications
                                </div>
                            <?php endif; ?>
                            <div class="p-3 border-t border-gray-100 text-center bg-gray-50 rounded-b-xl">
                                <a href="notifications.php" class="text-sm font-semibold text-red-600 hover:text-red-700">View All Notifications &rarr;</a>
                            </div>
                        </div>
                    </div>

                    <div class="relative" id="userMenu">
                        <div class="flex items-center gap-3 cursor-pointer p-1.5 rounded-xl hover:bg-gray-50 transition" onclick="toggleUserDropdown()">
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-base font-bold text-red-700 shadow-sm">
                                <?= strtoupper(substr($username, 0, 1)) ?>
                            </div>
                            <span class="font-semibold text-gray-800 text-base"><?= htmlspecialchars($username) ?></span>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                        <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-100">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($username) ?></p>
                                <p class="text-sm text-gray-500">Logged in</p>
                            </div>
                            <div class="p-2">
                                <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition font-medium">
                                    <span>👤</span> <span data-i18n="profile">Profile</span>
                                </a>
                                <a href="change_password.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition font-medium">
                                    <span>🔑</span> <span data-i18n="change_password">Change Password</span>
                                </a>
                                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition font-medium">
                                    <span>🚪</span> <span data-i18n="logout">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-7 py-2.5 rounded-xl font-bold hover:shadow-lg transition text-base" data-i18n="login">
                        Login
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Action & Hamburger Button -->
            <div class="flex md:hidden items-center space-x-3">
                <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()">
                    <span class="theme-icon-sun"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></span>
                    <span class="theme-icon-moon" style="display:none"><svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg></span>
                </button>

                <?php if ($isLoggedIn): ?>
                    <div class="relative" id="mobileNotifMenu">
                        <button onclick="toggleNotifDropdown()" class="relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:border-red-400 transition">
                            <span class="text-lg">🔔</span>
                            <?php if ($notifCount > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm pulse-dot"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endif; ?>

                <button onclick="toggleMobileMenu()" type="button" class="w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center text-gray-700 hover:text-red-600 hover:border-red-400 transition" aria-label="Toggle navigation">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Drawer -->
        <div id="mobileNavMenu" class="hidden md:hidden border-t border-gray-100 py-4 space-y-3">
            <a href="index.php" class="block px-3 py-2 text-base font-semibold text-gray-700 hover:text-red-600 hover:bg-red-50 rounded-lg transition" data-i18n="home">Home</a>
            <a href="donor.php" class="block px-3 py-2 text-base font-semibold text-gray-700 hover:text-red-600 hover:bg-red-50 rounded-lg transition" data-i18n="donors">Donors</a>
            <a href="bloodrequest.php" class="block px-3 py-2 text-base font-semibold text-gray-700 hover:text-red-600 hover:bg-red-50 rounded-lg transition" data-i18n="requests">Requests</a>
            <a href="about.php" class="block px-3 py-2 text-base font-semibold text-gray-700 hover:text-red-600 hover:bg-red-50 rounded-lg transition">About Us</a>

            <?php if ($isLoggedIn): ?>
                <div class="border-t border-gray-100 pt-3 mt-2">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <div class="w-9 h-9 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($username) ?></p>
                            <p class="text-xs text-gray-500">Logged in</p>
                        </div>
                    </div>
                    <a href="profile.php" class="block px-3 py-2 text-sm font-semibold text-gray-700 hover:text-red-600 hover:bg-red-50 rounded-lg transition">Profile</a>
                    <a href="change_password.php" class="block px-3 py-2 text-sm font-semibold text-gray-700 hover:text-red-600 hover:bg-red-50 rounded-lg transition">Change Password</a>
                    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 rounded-lg transition">Logout</a>
                </div>
            <?php else: ?>
                <div class="pt-2">
                    <a href="login.php" class="block text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2.5 rounded-xl font-bold hover:shadow-lg transition text-base" data-i18n="login">
                        Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    function toggleMobileMenu() {
        const mobileNav = document.getElementById('mobileNavMenu');
        if (mobileNav) {
            mobileNav.classList.toggle('hidden');
        }
    }

    function toggleNotifDropdown() {
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            dropdown.classList.toggle('hidden');
        }
        // close user dropdown if open
        const userDropdown = document.getElementById('userDropdown');
        if (userDropdown && !userDropdown.classList.contains('hidden')) {
            userDropdown.classList.add('hidden');
        }
    }

    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown) {
            dropdown.classList.toggle('hidden');
        }
        // close notif dropdown if open
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifDropdown && !notifDropdown.classList.contains('hidden')) {
            notifDropdown.classList.add('hidden');
        }
    }

    document.addEventListener('click', function(e) {
        const notifMenu = document.getElementById('notifMenu');
        const notifDropdown = document.getElementById('notifDropdown');
        const mobileNotifMenu = document.getElementById('mobileNotifMenu');
        if (notifMenu && notifDropdown && !notifMenu.contains(e.target) && (!mobileNotifMenu || !mobileNotifMenu.contains(e.target))) {
            notifDropdown.classList.add('hidden');
        }

        const userMenu = document.getElementById('userMenu');
        const userDropdown = document.getElementById('userDropdown');
        if (userMenu && userDropdown && !userMenu.contains(e.target)) {
            userDropdown.classList.add('hidden');
        }
    });
</script>