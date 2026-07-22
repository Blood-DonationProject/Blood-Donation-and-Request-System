 <?php
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitles = [
    'dashboard.php' => ['title' => 'Dashboard', 'description' => 'Overview of your blood donation system.'],
    'users_crud.php' => ['title' => 'Manage Users', 'description' => 'Manage and monitor the user network.'],
    'donor_crud.php' => ['title' => 'Manage Donors', 'description' => 'Manage and monitor the blood donor network.'],
    'donation_history_crud.php' => ['title' => 'Donation History', 'description' => 'Track and manage blood donation records.'],
    'blood_requests_crud.php' => ['title' => 'Blood Requests', 'description' => 'Manage blood request submissions from users.'],
];
$pageData = $pageTitles[$currentPage] ?? ['title' => 'Dashboard', 'description' => ''];

// Ensure $stats['pending'] is always set for the notification badge
if (!isset($stats['pending'])) {
    $stats['pending'] = 0;
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script>
if (typeof toggleNotifications === 'undefined') {
    window.toggleNotifications = function() {};
}
if (typeof toggleTheme === 'undefined') {
  (function() {
    var KEY = 'bloodlife-theme';
    function getTheme() { return localStorage.getItem(KEY) || 'light'; }
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
 <nav class="bg-white shadow-sm sticky top-0 z-30 border-b border-gray-100">
                <div class="px-8 py-4 flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <div>
                            <h2 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-700"><?= htmlspecialchars($pageData['title']) ?></h2>
                            <?php if ($pageData['description']): ?>
                            <p class="text-gray-500 mt-1 text-sm"><?= htmlspecialchars($pageData['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Theme Toggle -->
                        <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-300 hover:bg-red-50 transition" aria-label="Toggle theme" onclick="toggleTheme()">
                            <span class="theme-icon-sun"><i class="fas fa-sun text-gray-600"></i></span>
                            <span class="theme-icon-moon" style="display:none"><i class="fas fa-moon text-gray-600"></i></span>
                        </button>
                        <!-- Notifications Bell -->
                        <button onclick="toggleNotifications()" class="relative w-10 h-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:border-red-300 transition">
                            <i class="fas fa-bell text-gray-600"></i>
                            <?php if ($stats['pending'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm pulse-dot"><?= $stats['pending'] ?></span>
                            <?php endif; ?>
                        </button>
                       
                        <!-- Admin Profile -->
                        <div class="relative" id="adminMenu">
                            <div class="flex items-center space-x-3 cursor-pointer pl-3 border-l border-gray-200" onclick="toggleAdminDropdown()">
                                <div class="text-right">
                                    <p class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                    <p class="text-xs text-gray-400" data-i18n="administrator">Administrator</p>
                                </div>
                                <div class="w-10 h-10 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-sm">
                                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                </div>
                            </div>
                            <div id="adminDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-xl border border-gray-100 z-50">
                                <div class="p-4 border-b border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-lg">
                                            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 2)) ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                                            <p class="text-sm text-gray-400"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="block w-full text-center bg-red-600 text-white py-2.5 rounded-xl font-semibold hover:bg-red-700 transition" data-i18n="logout">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>