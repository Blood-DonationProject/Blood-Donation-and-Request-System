<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $_SESSION['username'] ?? 'User';
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

                <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
                    <?php if ($isLoggedIn): ?>
                        <!-- Bell Icon -->
                        <div class="relative" id="notifMenu">
                            <button onclick="toggleNotifDropdown()" class="relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 hover:bg-red-50 transition">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                                </svg>
                                <span id="notifBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-600 text-white text-xs font-bold rounded-full flex items-center justify-center hidden">0</span>
                            </button>
                            <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                                    <p class="font-semibold text-gray-800">Notifications</p>
                                    <span class="text-xs text-gray-400">0 new</span>
                                </div>
                                <div class="p-4 text-center text-gray-400 text-sm">
                                    No new notifications
                                </div>
                            </div>
                        </div>

                        <div class="relative" id="userMenu">
                            <div class="flex items-center gap-2 cursor-pointer" onclick="toggleUserDropdown()">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">
                                    <?= strtoupper(substr($username, 0, 1)) ?>
                                </div>
                                <span class="font-medium text-gray-700"><?= $username ?></span>
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
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