<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// If already logged in, redirect to home/dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = ''; // 'success' or 'error'
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $submittedEmail = $email;

    if (empty($email)) {
        $message = 'Please enter your registered email address.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        // Query user by email using prepared statement
        $stmt = $conn->prepare("SELECT id, username, status FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();

                // Only generate and send token if user account is Active
                if (($user['status'] ?? 'Active') === 'Active') {
                    // Generate secure 64-character token
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store token and expiration securely in database
                    $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("ssi", $token, $expiresAt, $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();

                        // Construct absolute reset URL
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? "https://" : "http://";
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                        $resetUrl = $protocol . $host . $scriptDir . '/reset_password.php?token=' . urlencode($token);

                        // Send password reset email via PHPMailer + Gmail SMTP
                        $mailResult = send_password_reset_email($email, $user['username'], $resetUrl);
                        if (!$mailResult['success']) {
                            error_log("[Password Reset] Email sending failed for {$email}: " . ($mailResult['error'] ?? 'Unknown error'));
                        }
                    }
                }
            }
            $stmt->close();
        }

        // Generic response to prevent user enumeration
        $messageType = 'success';
        $message = 'If an account exists with this email address, a password reset link has been sent. Please check your inbox and spam folder.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password – BloodLife</title>
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
          🔑
        </div>
        <h1 class="text-2xl font-bold">Forgot Password?</h1>
        <p class="text-red-100 text-sm mt-1">Enter your registered email and we'll send you a secure link to reset your password.</p>
      </div>

      <div class="p-8">
        <?php if ($message): ?>
          <?php if ($messageType === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-6 dark:bg-green-950/40">
              <div class="flex items-start">
                <span class="text-green-500 text-xl mr-2">✓</span>
                <p class="text-green-800 text-sm dark:text-green-200"><?= htmlspecialchars($message) ?></p>
              </div>
            </div>
          <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded mb-6 dark:bg-red-950/40">
              <div class="flex items-start">
                <span class="text-red-500 text-xl mr-2">⚠</span>
                <p class="text-red-700 text-sm dark:text-red-300"><?= htmlspecialchars($message) ?></p>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($messageType !== 'success'): ?>
          <form method="POST" action="forgot_password.php" class="space-y-5">
            <div>
              <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Registered Email Address</label>
              <input type="email" name="email" id="email" required
                placeholder="name@example.com"
                value="<?= htmlspecialchars($submittedEmail) ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-gray-800" />
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-3.5 rounded-xl font-bold hover:shadow-lg hover:from-red-700 hover:to-red-800 transition transform hover:scale-[1.01] text-base">
              Send Reset Link &rarr;
            </button>
          </form>
        <?php else: ?>
          <div class="text-center space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Didn't receive an email? Check your spam folder or try again in a few minutes.
            </p>
            <a href="forgot_password.php" class="inline-block text-sm text-red-600 font-semibold hover:underline">
              Try another email address
            </a>
          </div>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center">
          <p class="text-sm text-gray-500">
            Remembered your password?
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
