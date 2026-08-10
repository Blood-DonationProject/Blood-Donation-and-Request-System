<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = 'User';
$notifications = [];
if ($isLoggedIn && isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    require_once __DIR__ . '/../config/db.php';
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $username = $row['username'];
        }
        $stmt->close();
    }

    // Fetch donor notifications
    $stmt_donor = $conn->prepare("SELECT id FROM donor WHERE user_id = ? LIMIT 1");
    if ($stmt_donor) {
        $stmt_donor->bind_param('i', $_SESSION['user_id']);
        $stmt_donor->execute();
        $res_donor = $stmt_donor->get_result();
        if ($donorRow = $res_donor->fetch_assoc()) {
            $donor_id = $donorRow['id'];
            $stmt_notif = $conn->prepare("
                SELECT r.id, r.hospital, r.required_date, r.status, bg.blood_gp_name 
                FROM blood_request r 
                LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id 
                WHERE r.assigned_donor_id = ? AND r.status IN ('Assigned', 'Pending') 
                ORDER BY r.required_date ASC
            ");
            if ($stmt_notif) {
                $stmt_notif->bind_param('i', $donor_id);
                $stmt_notif->execute();
                $res_notif = $stmt_notif->get_result();
                while ($n = $res_notif->fetch_assoc()) {
                    $notifications[] = $n;
                }
                $stmt_notif->close();
            }
        }
        $stmt_donor->close();
    }
} elseif ($isLoggedIn) {
    $username = $_SESSION['username'] ?? 'User';
}
$notifCount = count($notifications);

?>
<nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-3 animate-fade-down">
                <span class="text-2xl bg-red-200 p-1 rounded-full shadow-black/35 shadow-md">🩸</span>
                <div>
                    <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
                    <p class="text-xs text-gray-500" data-i18n="save_lives_together">Save Lives Together</p>
                </div>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
                <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
                <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
                <a href="donordashboard.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Dashboard</a>

                <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()">
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
                        <button onclick="toggleNotifDropdown()" class="relative w-10 h-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:border-red-300 transition">
                            <span class="text-xl">🔔</span>
                            <?php if ($notifCount > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-600 text-yellow-800 text-[20px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm pulse-dot"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                                <p class="font-semibold text-gray-800">Notifications</p>
                                <span class="text-xs text-gray-400"><?= $notifCount ?> new</span>
                            </div>
                            <?php if ($notifCount > 0): ?>
                                <div class="max-h-64 overflow-y-auto">
                                    <?php foreach ($notifications as $n): ?>
                                        <a href="donordashboard.php" class="block p-4 border-b border-gray-100 hover:bg-gray-50 transition">
                                            <p class="text-sm text-gray-800 font-semibold">You have been assigned as a donor!</p>
                                            <p class="text-xs text-gray-500 mt-1">Hospital: <?= htmlspecialchars($n['hospital']) ?></p>
                                            <p class="text-xs text-gray-500">Date: <?= htmlspecialchars($n['required_date']) ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center text-gray-400 text-sm">
                                    No new notifications
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="relative" id="userMenu">
                        <div class="flex items-center gap-2 cursor-pointer" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">
                                <?= strtoupper(substr($username, 0, 1)) ?>
                            </div>
                            <span class="font-medium text-gray-700"><?= $username ?></span>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                        <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-100">
                                <p class="font-semibold text-gray-800"><?= $username ?></p>
                                <p class="text-sm text-gray-500">Logged in</p>
                            </div>
                            <div class="p-2">
                                <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                                    <span>👤</span> <span data-i18n="profile">Profile</span>
                                </a>
                                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                                    <span>🚪</span> <span data-i18n="logout">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition" data-i18n="login">
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
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

    document.addEventListener('click', function(e) {
        const notifMenu = document.getElementById('notifMenu');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
            notifDropdown.classList.add('hidden');
        }
    });
</script>