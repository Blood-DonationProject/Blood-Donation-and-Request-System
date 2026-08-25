<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// If already logged in, redirect to home
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$isValidToken = false;
$userRow = null;
$errorMessage = '';
$successMessage = '';

if (!empty($token)) {
    // Validate token and expiration time
    $stmt = $conn->prepare("SELECT id, username, email, reset_expires_at FROM users WHERE reset_token = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $userRow = $res->fetch_assoc();
            $expiresAt = strtotime($userRow['reset_expires_at']);
            if ($expiresAt && $expiresAt > time()) {
                $isValidToken = true;
            } else {
                $errorMessage = 'This password reset link has expired. Please request a new one.';
            }
        } else {
            $errorMessage = 'Invalid or used password reset token. Please request a new link.';
        }
        $stmt->close();
    }
} else {
    $errorMessage = 'No password reset token provided. Please use the link sent to your email.';
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken && $userRow) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = 'Please fill in both password fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'Passwords do not match. Please try again.';
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = 'Password must be at least 8 characters long.';
    } else {
        // Hash the new password securely
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and invalidate the reset token
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        if ($updateStmt) {
            $updateStmt->bind_param("si", $hashedPassword, $userRow['id']);
            if ($updateStmt->execute()) {
                $updateStmt->close();
                // Redirect to login with success flag
                header('Location: login.php?reset=1&email=' . urlencode($userRow['email']));
                exit;
            } else {
                $errorMessage = 'Failed to update password. Please try again.';
            }
            $updateStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password – BloodLife</title>
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
  <style id="dark-mode-styles">
    html:not(.dark) body { background-color: #f9fafb !important; }
    html.dark body { background-color: #111827 !important; color: #e5e7eb; }
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark .text-gray-600, html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark input { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
    html.dark label { color: #d1d5db !important; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col justify-between transition-colors duration-200">

  <!-- Header / Nav -->
  <header class="bg-white border-b border-gray-200 py-4 px-6 shadow-sm">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <a href="index.php" class="flex items-center space-x-2">
        <span class="text-2xl">🩸</span>
        <span class="font-bold text-xl text-red-600">BloodLife</span>
      </a>
      <div class="flex items-center space-x-4">
        <button onclick="toggleTheme()" class="theme-toggle-btn p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition" title="Toggle Dark/Light Mode">
          <span class="theme-icon-sun">☀️</span>
          <span class="theme-icon-moon hidden">🌙</span>
        </button>
        <a href="login.php" class="text-sm font-semibold text-gray-600 hover:text-red-600 dark:text-gray-300">Back to Login</a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
      
      <!-- Top banner -->
      <div class="bg-gradient-to-r from-red-600 to-red-700 p-6 text-white text-center">
        <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl">
          🔒
        </div>
        <h1 class="text-2xl font-bold">Create New Password</h1>
        <p class="text-red-100 text-sm mt-1">
          <?= $isValidToken ? 'Choose a strong, secure password for your account.' : 'Password Reset Verification' ?>
        </p>
      </div>

      <div class="p-8">
        <?php if ($errorMessage): ?>
          <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded mb-6 dark:bg-red-950/40">
            <div class="flex items-start">
              <span class="text-red-500 text-xl mr-2">⚠</span>
              <p class="text-red-700 text-sm dark:text-red-300"><?= htmlspecialchars($errorMessage) ?></p>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($isValidToken): ?>
          <form method="POST" action="reset_password.php" class="space-y-5">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div>
              <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-1">New Password</label>
              <div class="relative">
                <input type="password" name="new_password" id="new_password" required minlength="8"
                  placeholder="At least 8 characters"
                  class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition text-gray-800" />
                <button type="button" onclick="togglePass('new_password', 'eye1')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">
                  <span id="eye1">👁️</span>
                </button>
              </div>
            </div>

            <div>
              <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-1">Confirm New Password</label>
              <div class="relative">
                <input type="password" name="confirm_password" id="confirm_password" required minlength="8"
                  placeholder="Re-enter your new password"
                  class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition text-gray-800" />
                <button type="button" onclick="togglePass('confirm_password', 'eye2')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">
                  <span id="eye2">👁️</span>
                </button>
              </div>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-3.5 rounded-xl font-bold hover:shadow-lg hover:from-red-700 hover:to-red-800 transition transform hover:scale-[1.01] text-base">
              Save New Password &rarr;
            </button>
          </form>
        <?php else: ?>
          <div class="text-center space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              The link you followed may be invalid, expired, or already used.
            </p>
            <a href="forgot_password.php" class="inline-block bg-red-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-red-700 transition">
              Request a New Reset Link
            </a>
          </div>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center">
          <p class="text-sm text-gray-500">
            Remember your credentials?
            <a href="login.php" class="text-red-600 font-bold hover:underline ml-1">Sign In</a>
          </p>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-white border-t border-gray-200 py-6 text-center text-sm text-gray-400">
    <p>&copy; <?= date('Y') ?> BloodLife. All rights reserved.</p>
  </footer>

  <script>
    function togglePass(fieldId, iconId) {
      var field = document.getElementById(fieldId);
      if (field.type === 'password') {
        field.type = 'text';
      } else {
        field.type = 'password';
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
        var next = (getTheme() === 'dark') ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        apply(next);
      };
    })();
  </script>
</body>
</html>
