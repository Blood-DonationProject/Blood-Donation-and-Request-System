<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

// Resolve relative path to an absolute URL from document root
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

function absUrl($path) {
    global $scriptDir;
    $combined = $scriptDir . '/' . $path;
    $parts = explode('/', $combined);
    $result = [];
    foreach ($parts as $part) {
        if ($part === '.' || $part === '') continue;
        if ($part === '..') array_pop($result);
        else $result[] = $part;
    }
    return '/' . implode('/', $result);
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Determine redirect based on role
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'Admin') {
        $target = '../admin/dashboard.php';
    } else {
        $target = 'dashboard.php';
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => absUrl($target)]);
        exit;
    }
    header('Location: ' . $target);
    exit;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errorMessage = 'Please enter both username and password.';
    } else {
        $loginSuccess = false;
        $targetPath = '';

        // Hardcoded admin credentials (constant)
        if ($username === 'admin' && $password === 'password123') {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = 0;
            $_SESSION['username'] = 'admin';
            $_SESSION['user_email'] = 'admin@bloodlife.local';
            $_SESSION['user_role'] = 'Admin';
            $loginSuccess = true;
            $targetPath = '../admin/dashboard.php';
        }

        // Verify credentials against the database
        if (!$loginSuccess) {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $storedPassword = $row['password'];

                    if (password_verify($password, $storedPassword)) {
                        $_SESSION['logged_in'] = true;
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user_role'] = $row['role'];
                        $loginSuccess = true;

                        // Redirect based on role
                        $role = $row['role'];
                        if ($role === 'Admin') {
                            $targetPath = '../admin/dashboard.php';
                        } else {
                            $targetPath = 'dashboard.php';
                        }
                    }
                }
                $stmt->close();
            }
        }

        if ($loginSuccess) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => absUrl($targetPath)]);
                exit;
            }
            header('Location: ' . $targetPath);
            exit;
        }

        $errorMessage = 'Invalid username or password.';
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMessage]);
        exit;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login – BloodLife</title>
  <script>
    (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
  </script>
  <script>
    tailwind.config = { darkMode: 'class' }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="../assets/js/translations.js"></script>
  <script src="../assets/js/i18n.js"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <style>
    @keyframes fadeInDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeInUp   { from { opacity:0; transform:translateY( 20px); } to { opacity:1; transform:translateY(0); } }
    .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
    .animate-fade-up   { animation: fadeInUp   0.6s ease-out; }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
    html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
    html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
    html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
    html.dark nav.bg-white, html.dark nav.bg-white.shadow-lg { background-color: #1f2937 !important; }
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark .text-gray-600 { color: #9ca3af !important; }
    html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
    html.dark label { color: #d1d5db !important; }
    html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #374151 !important; }
    html.dark .border-gray-200, html.dark .border-2.border-gray-200 { border-color: #4b5563 !important; }
    html.dark .border-t { border-color: #374151 !important; }
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
  </style>
</head>
<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <a href="index.php" class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </a>
        <div class="hidden md:flex items-center space-x-8">
          <a href="index.php"    class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php"      class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="hospital.php"    class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="hospitals">Hospitals</a>
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
<button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="register.php" class="border-2 border-red-600 text-red-600 px-6 py-2 rounded-lg font-semibold hover:bg-red-50 transition" data-i18n="register">Register</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-5xl grid md:grid-cols-2 gap-0 bg-white rounded-3xl shadow-2xl overflow-hidden animate-fade-up">

      <!-- Left Panel -->
      <div class="bg-gradient-to-br from-red-600 to-red-800 text-white p-12 flex flex-col justify-between">
        <div>
          <span class="text-5xl mb-6 block">🩸</span>
          <h2 class="text-4xl font-bold mb-4 leading-tight" data-i18n="welcome_back_bloodlife">Welcome Back to BloodLife</h2>
          <p class="text-lg opacity-90 mb-8" data-i18n="login_desc">Sign in to manage your donations, track requests, and save more lives today.</p>
        </div>

        <div class="space-y-5">
          <div class="flex items-center gap-4 bg-white/10 rounded-2xl p-4">
            <span class="text-3xl">🎯</span>
            <div>
              <p class="font-bold" data-i18n="track_your_impact">Track Your Impact</p>
              <p class="text-sm opacity-80" data-i18n="track_impact_desc_login">See how many lives you've helped save</p>
            </div>
          </div>
          <div class="flex items-center gap-4 bg-white/10 rounded-2xl p-4">
            <span class="text-3xl">🔔</span>
            <div>
              <p class="font-bold" data-i18n="emergency_alerts">Emergency Alerts</p>
              <p class="text-sm opacity-80" data-i18n="emergency_alerts_desc">Get notified when your blood type is urgently needed</p>
            </div>
          </div>
          <div class="flex items-center gap-4 bg-white/10 rounded-2xl p-4">
            <span class="text-3xl">🏆</span>
            <div>
              <p class="font-bold" data-i18n="earn_rewards">Earn Rewards</p>
              <p class="text-sm opacity-80" data-i18n="earn_rewards_desc">Unlock badges and certificates for every donation</p>
            </div>
          </div>
        </div>

        <p class="text-sm opacity-70 mt-8"><span data-i18n="no_account_prompt">Don't have an account?</span> <a href="register.php" class="underline font-semibold hover:opacity-100" data-i18n="sign_up_free">Sign up free →</a></p>
      </div>

      <!-- Right Panel — Form -->
      <div class="p-10 sm:p-12 flex flex-col justify-center">
        <h3 class="text-3xl font-bold text-gray-900 mb-2" data-i18n="sign_in">Sign In</h3>
        <p class="text-gray-500 mb-8" data-i18n="enter_credentials">Enter your credentials to access your account.</p>



        <form method="POST" class="space-y-5">
          <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
            <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded">
              <p class="text-green-700 text-sm">Registration successful! Please sign in with your credentials.</p>
            </div>
          <?php endif; ?>
          <?php if (isset($_GET['access_denied']) && $_GET['access_denied'] === '1'): ?>
            <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded">
              <p class="text-red-700 text-sm">Access Denied. You do not have administrator privileges.</p>
            </div>
          <?php endif; ?>
          <?php if ($errorMessage): ?>
            <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded">
              <p class="text-red-700 text-sm"><?= htmlspecialchars($errorMessage) ?></p>
            </div>
          <?php endif; ?>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="username">Username</label>
            <input type="text" name="username" data-i18n-placeholder="enter_username" placeholder="Enter your username" required
                   class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="password">Password</label>
            <div class="relative">
              <input type="password" name="password" id="passwordField" data-i18n-placeholder="enter_password" placeholder="Enter your password" required
                     class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition" />
              <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700" id="eyeBtn">
                <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
              <input type="checkbox" class="accent-red-600 w-4 h-4" />
              <span data-i18n="remember_me">Remember me</span>
            </label>
            <a href="#" class="text-red-600 font-semibold hover:underline" data-i18n="forgot_password">Forgot password?</a>
          </div>

          <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-xl font-bold hover:shadow-xl hover:from-red-700 hover:to-red-800 transition transform hover:scale-[1.02] text-lg mt-2" data-i18n="sign_in">
            Sign In →
          </button>
        </form>

        <div class="flex items-center gap-4 my-6">
          <div class="flex-1 h-px bg-gray-200"></div>
          <span class="text-gray-400 text-sm" data-i18n="or_continue_with">or continue with</span>
          <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <button class="border-2 border-gray-200 rounded-xl py-3 font-semibold text-gray-700 hover:border-red-400 hover:text-red-600 transition flex items-center justify-center gap-2">
            <span>G</span> Google
          </button>
          <button class="border-2 border-gray-200 rounded-xl py-3 font-semibold text-gray-700 hover:border-red-400 hover:text-red-600 transition flex items-center justify-center gap-2">
            <span>f</span> Facebook
          </button>
        </div>

        <p class="text-center text-sm text-gray-500 mt-8">
          <span data-i18n="new_to_bloodlife_login">New to BloodLife?</span> <a href="register.php" class="text-red-600 font-bold hover:underline" data-i18n="create_account_login">Create an account</a>
        </p>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-8">
    <div class="max-w-7xl mx-auto px-4 text-center text-sm">
      <p>&copy; BloodLife. All rights reserved. | <a href="#" class="hover:text-red-400">Privacy Policy</a> | <a href="#" class="hover:text-red-400">Terms of Service</a></p>
    </div>
  </footer>

  <script>
    function setRole(role) {
      ['donor','hospital','admin'].forEach(r => {
        const tab = document.getElementById('tab-' + r);
        if (r === role) {
          tab.classList.add('bg-white','text-red-600','shadow');
          tab.classList.remove('text-gray-500');
        } else {
          tab.classList.remove('bg-white','text-red-600','shadow');
          tab.classList.add('text-gray-500');
        }
      });
    }

    function togglePassword() {
      const f = document.getElementById('passwordField');
      const open = document.getElementById('eyeOpen');
      const closed = document.getElementById('eyeClosed');
      if (f.type === 'password') {
        f.type = 'text';
        open.classList.add('hidden');
        closed.classList.remove('hidden');
      } else {
        f.type = 'password';
        closed.classList.add('hidden');
        open.classList.remove('hidden');
      }
    }

  </script>

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
        var current = localStorage.getItem(KEY) || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(KEY, next);
        apply(next);
      };
    })();
    </script>
</body>
</html>