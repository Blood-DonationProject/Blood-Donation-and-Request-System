 <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $pageTitles = [
        'dashboard.php' => ['title' => 'Dashboard', 'description' => 'Overview of your blood donation system.'],
        'users_crud.php' => ['title' => 'Manage Users', 'description' => 'Manage and monitor the user network.'],
        'donor_crud.php' => ['title' => 'Manage Donors', 'description' => 'Manage and monitor the blood donor network.'],
        'donation_history_crud.php' => ['title' => 'Donation History', 'description' => 'Track and manage blood donation records.'],
        'blood_requests_crud.php' => ['title' => 'Blood Requests', 'description' => 'Manage blood request submissions from users.'],
        'assignments.php' => ['title' => 'Assignments', 'description' => 'Track and manage donor assignments.'],
        'notifications.php' => ['title' => 'Notifications', 'description' => 'View and manage all system notifications.'],
    ];
    $pageData = $pageTitles[$currentPage] ?? ['title' => 'Dashboard', 'description' => ''];

    // Fetch admin notifications
    $admin_notifs = [];
    if (isset($_SESSION['user_id'])) {
        if (isset($_GET['read_notifs'])) {
            $stmt_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
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
            $stmt_mark = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
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

        $stmt_notif = $conn->prepare("SELECT id, request_id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $notifCount = 0;
        if ($stmt_notif) {
            $stmt_notif->bind_param('i', $_SESSION['user_id']);
            $stmt_notif->execute();
            $res_notif = $stmt_notif->get_result();
            while ($n = $res_notif->fetch_assoc()) {
                $admin_notifs[] = $n;
                if ($n['is_read'] == 0) $notifCount++;
            }
            $stmt_notif->close();
        }
    }
    ?>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
 <script>
     if (typeof toggleNotifications === 'undefined') {
         window.toggleNotifications = function() {
             var dropdown = document.getElementById('adminNotifDropdown');
             if (dropdown) dropdown.classList.toggle('hidden');
         };
     }
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
 <nav class="bg-blue-100 shadow-sm sticky top-0 z-30 border-b border-gray-100 transition-colors duration-300">
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
             <div class="relative">
                 <button onclick="toggleNotifications()" class="relative w-10 h-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center hover:bg-red-50 hover:border-red-300 transition">
                     <i class="fas fa-bell text-gray-600"></i>
                     <?php if ($notifCount > 0): ?>
                         <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm pulse-dot"><?= $notifCount ?></span>
                     <?php endif; ?>
                 </button>
                 <!-- Dropdown -->
                 <div id="adminNotifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                     <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                         <p class="font-semibold text-gray-800">Notifications</p>
                         <div>
                             <span class="text-xs text-gray-400 mr-2"><?= $notifCount ?> new</span>
                             <?php if ($notifCount > 0): ?>
                                 <a href="?read_notifs=1" class="text-xs text-blue-600 hover:underline">Mark all as read</a>
                             <?php endif; ?>
                         </div>
                     </div>
                     <?php if (count($admin_notifs) > 0): ?>
                         <div class="max-h-80 overflow-y-auto">
                             <?php foreach ($admin_notifs as $n):
                                    $is_read = $n['is_read'] == 1;
                                    $bgClass = $is_read ? 'bg-white hover:bg-gray-50' : 'bg-red-50 hover:bg-red-100';
                                    $link = "blood_requests_crud.php?view=" . (int)$n['request_id'];
                                    $read_link = "?mark_read=" . $n['id'] . "&redirect=" . urlencode($link);

                                    $action_text = "View Request →";
                                    if ($n['title'] == 'New Assignment' || strpos($n['title'], 'Assignment') !== false) {
                                        $action_text = "View Assignment →";
                                    }
                                    if ($n['title'] == 'Donor Declined') {
                                        $action_text = "Assign Another Donor →";
                                    }
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
                                     <div class="mt-2 flex items-center justify-between">
                                         <p class="text-[11px] text-gray-500 font-semibold"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></p>
                                         <a href="<?= htmlspecialchars($read_link) ?>" class="text-[11px] font-bold text-red-600 hover:text-red-700">
                                             <?= $action_text ?>
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