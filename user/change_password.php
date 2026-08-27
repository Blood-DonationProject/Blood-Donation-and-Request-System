<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
if (!$isLoggedIn) {
  header('Location: login.php');
  exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $currentPassword = $_POST['current_password'] ?? '';
  $newPassword = $_POST['new_password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    $message = 'Please fill in all password fields.';
    $messageType = 'error';
  } elseif ($newPassword !== $confirmPassword) {
    $message = 'New passwords do not match.';
    $messageType = 'error';
  } elseif (strlen($newPassword) < 8) {
    $message = 'New password must be at least 8 characters long.';
    $messageType = 'error';
  } elseif ($newPassword === $currentPassword) {
    $message = 'New password cannot be the same as the current password.';
    $messageType = 'error';
  } else {
    // Check current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
      if (password_verify($currentPassword, $row['password'])) {
        // Correct, update password
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hashed, $userId);
        if ($upd->execute()) {
          $upd->close();

          require_once __DIR__ . '/../includes/notification_helper.php';
          require_once __DIR__ . '/../includes/mailer.php';

          // Get user email
          $userEmail = $_SESSION['email'] ?? '';
          $userName = $_SESSION['username'] ?? 'User';
          if (empty($userEmail)) {
            $uQ = $conn->query("SELECT email, username FROM users WHERE id = " . (int)$userId);
            if ($uQ && $uR = $uQ->fetch_assoc()) {
              $userEmail = $uR['email'];
              $userName = $uR['username'];
            }
          }

          // Website Notification
          create_notification($conn, $userId, 'Security', 'Password Changed', 'Your BloodLife account password was successfully updated.', null, null, null, $userId);

          // Security Alert Email
          if (!empty($userEmail)) {
            send_password_changed_security_email($userId, $userEmail, $userName);
          }

          $_SESSION['success_msg'] = 'Password updated successfully.';
          header('Location: index.php');
          exit;
        } else {
          $message = 'Error updating password. Please try again.';
          $messageType = 'error';
        }
        $upd->close();
      } else {
        $message = 'Current password is incorrect.';
        $messageType = 'error';
      }
    } else {
        $message = 'User not found.';
        $messageType = 'error';
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password – BloodLife</title>
  <script>
    (function() {
      var t = localStorage.getItem('bloodlife-theme');
      if (t === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>
  <script>
    tailwind.config = { darkMode: 'class' }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
    .animate-fade-up { animation: fadeInUp 0.6s ease-out; }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
    html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
    html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
    html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
    html.dark nav.bg-slate-100, html.dark nav.bg-slate-100.shadow-lg { background-color: #1f2937 !important; }
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark .text-gray-600, html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
    html.dark label { color: #d1d5db !important; }
    html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #374151 !important; }
    html.dark .border-gray-200, html.dark .border-2.border-gray-200 { border-color: #4b5563 !important; }
    html.dark .border-t { border-color: #374151 !important; }
    html.dark .bg-red-50 { background-color: rgba(220, 38, 38, 0.15) !important; }
    html.dark .bg-green-50 { background-color: rgba(34, 197, 94, 0.15) !important; }
  </style>
</head>
<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen flex flex-col">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <main class="flex-grow container mx-auto px-4 py-12 max-w-lg animate-fade-up">
    <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 relative overflow-hidden">
      <!-- Decorative element -->
      <div class="absolute top-0 right-0 w-32 h-32 bg-red-100 rounded-bl-full -mr-16 -mt-16 opacity-50"></div>

      <!-- Close / Back Button -->
      <a href="profile.php" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center bg-gray-100 hover:bg-red-100 text-gray-500 hover:text-red-600 rounded-full transition z-10" title="Back to profile">
        <i class="fas fa-times text-lg"></i>
      </a>
      
      <div class="relative">
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-sm border border-red-200">
          🔑
        </div>
        <h1 class="text-3xl font-bold mb-2 text-gray-900">Change Password</h1>
        <p class="text-gray-500 text-sm mb-8">Ensure your account is using a long, random password to stay secure.</p>
        
        <?php if ($message): ?>
          <div class="mb-6 rounded-xl border px-4 py-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700' ?>">
            <?= htmlspecialchars($message) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="space-y-5">
            <div>
              <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
              <div class="relative">
                <input type="password" id="current_password" name="current_password" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 bg-gray-50 focus:bg-white focus:outline-none focus:border-red-500 transition shadow-sm text-gray-800" placeholder="Enter current password" />
                <button type="button" onclick="togglePassword('current_password', 'eyeIcon1')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition cursor-pointer p-1" title="Show/Hide Password" aria-label="Toggle Current Password visibility">
                  <i id="eyeIcon1" class="fa-solid fa-eye-slash text-base"></i>
                </button>
              </div>
            </div>
            <div>
              <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
              <div class="relative">
                <input type="password" id="new_password" name="new_password" required minlength="8" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 bg-gray-50 focus:bg-white focus:outline-none focus:border-red-500 transition shadow-sm text-gray-800" placeholder="Minimum 8 characters" />
                <button type="button" onclick="togglePassword('new_password', 'eyeIcon2')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition cursor-pointer p-1" title="Show/Hide Password" aria-label="Toggle New Password visibility">
                  <i id="eyeIcon2" class="fa-solid fa-eye-slash text-base"></i>
                </button>
              </div>
            </div>
            <div>
              <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
              <div class="relative">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 bg-gray-50 focus:bg-white focus:outline-none focus:border-red-500 transition shadow-sm text-gray-800" placeholder="Confirm new password" />
                <button type="button" onclick="togglePassword('confirm_password', 'eyeIcon3')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition cursor-pointer p-1" title="Show/Hide Password" aria-label="Toggle Confirm Password visibility">
                  <i id="eyeIcon3" class="fa-solid fa-eye-slash text-base"></i>
                </button>
              </div>
            </div>
            <div class="pt-4">
              <button type="submit" name="change_password" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-bold py-3.5 rounded-xl hover:shadow-lg hover:from-red-700 hover:to-red-800 transition transform hover:-translate-y-0.5">Update Password</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    function togglePassword(inputId, iconId) {
      var input = document.getElementById(inputId);
      var icon = document.getElementById(iconId);
      if (!input || !icon) return;

      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      }
    }

    (function() {
      var KEY = 'bloodlife-theme';
      function getTheme() { return localStorage.getItem(KEY) || 'light'; }
      function apply(t) {
        if (t === 'dark') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
          var sun = btn.querySelector('.theme-icon-sun');
          var moon = btn.querySelector('.theme-icon-moon');
          if (sun) sun.style.display = t === 'dark' ? 'none' : 'inline';
          if (moon) moon.style.display = t === 'dark' ? 'inline' : 'none';
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
  </script>
</body>
</html>
