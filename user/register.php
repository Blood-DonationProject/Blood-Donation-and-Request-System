<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';
  $termsAccepted = isset($_POST['terms']) && $_POST['terms'] === '1';

  if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
    $message = 'Please fill in all required fields.';
    $messageType = 'error';
  } elseif ($password !== $confirmPassword) {
    $message = 'Passwords do not match.';
    $messageType = 'error';
  } elseif (strlen($password) < 8) {
    $message = 'Password must be at least 8 characters long.';
    $messageType = 'error';
  } elseif (!$termsAccepted) {
    $message = 'Please accept the terms and privacy policy to continue.';
    $messageType = 'error';
  } else {
    $checkEmail = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
      $message = 'An account with this email already exists.';
      $messageType = 'error';
    } else {
      $role = !empty($_POST['role']) ? trim($_POST['role']) : 'Donor';

      $baseUsername = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
      $username = $baseUsername !== '' ? $baseUsername : 'user';
      $suffix = 0;
      $candidate = $username;
      while (true) {
        $checkU = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $checkU->bind_param('s', $candidate);
        $checkU->execute();
        $checkU->store_result();
        if ($checkU->num_rows === 0) {
          $checkU->close();
          break;
        }
        $checkU->close();
        $suffix++;
        $candidate = $username . $suffix;
      }

      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
      $stmt->bind_param('ssss', $candidate, $email, $hashedPassword, $role);

      if ($stmt->execute()) {
        header('Location: login.php?registered=1');
        exit;
      } else {
        $message = 'Registration failed. Please try again.';
        $messageType = 'error';
      }
      $stmt->close();
    }

    $checkEmail->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register – BloodLife</title>
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
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fade-down {
      animation: fadeInDown 0.6s ease-out;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out;
    }
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

<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen flex flex-col">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg">
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
          <a href="index.php" class="text-gray-700 hover:text-red-600 font-medium transition">Home</a>
          <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition">Donors</a>
          
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition">Requests</a>
<button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition">Login</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main -->
  <div class="flex-1 flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md animate-fade-up">

      <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

        <!-- Header -->
          <div class="bg-gradient-to-r from-red-600 to-red-800 text-white px-8 py-8 text-center">
          <span class="text-5xl mb-3 block">🩸</span>
          <h1 class="text-2xl font-bold" data-i18n="create_an_account">Create an Account</h1>
          <p class="text-red-200 text-sm mt-1" data-i18n="join_bloodlife_desc">Join BloodLife and start making a difference</p>
        </div>

        <!-- Form -->
        <div class="px-8 py-8 space-y-5">
          <?php if ($message): ?>
            <div class="rounded-xl border px-4 py-3 text-sm <?= $messageType === 'error' ? 'border-red-200 text-red-700' : 'border-green-200 text-green-700' ?>">
              <?= htmlspecialchars($message) ?>
            </div>
          <?php endif; ?>

          <form method="POST" class="space-y-5" onsubmit="return validateRegisterForm(event)">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="full_name_label">Full Name <span class="text-red-500">*</span></label>
              <input id="reg_name" name="name" type="text" data-i18n-placeholder="enter_full_name" placeholder="Your full name" required
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="email_label">Email Address <span class="text-red-500">*</span></label>
              <input id="reg_email" name="email" type="email" placeholder="you@example.com" required
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
              <select id="reg_role" name="role" required
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm">
                <option value="" disabled selected>Select your role</option>
                <option value="Donor">Donor</option>
                <option value="Requester">Requester</option>
              </select>
            </div>

            

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="password_label">Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <input id="reg_password" name="password" type="password" data-i18n-placeholder="min_8_chars" placeholder="Min. 8 characters" required
                  class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition text-sm" />
                <button type="button" onclick="togglePass('reg_password','eye1')" id="eye1"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">👁</button>
              </div>
              <div class="flex gap-1 mt-2">
                <div class="h-1 flex-1 rounded bg-gray-200" id="str1"></div>
                <div class="h-1 flex-1 rounded bg-gray-200" id="str2"></div>
                <div class="h-1 flex-1 rounded bg-gray-200" id="str3"></div>
                <div class="h-1 flex-1 rounded bg-gray-200" id="str4"></div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="confirm_password_label">Confirm Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <input id="reg_confirm" name="confirm_password" type="password" data-i18n-placeholder="re_enter_password" placeholder="Re-enter your password" required
                  class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition text-sm" />
                <button type="button" onclick="togglePass('reg_confirm','eye2')" id="eye2"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">👁</button>
              </div>
            </div>

            <label class="flex items-start gap-3 cursor-pointer pt-1">
              <input id="reg_terms" name="terms" value="1" type="checkbox" class="accent-red-600 w-4 h-4 mt-0.5 flex-shrink-0" />
              <span class="text-sm text-gray-600"><span data-i18n="agree_terms">I agree to BloodLife's</span> <a href="#" class="text-red-600 underline" data-i18n="terms_of_service">Terms of Service</a> <span data-i18n="and">and</span> <a href="#" class="text-red-600 underline" data-i18n="privacy_policy">Privacy Policy</a></span>
            </label>

            <button type="submit"
              class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-3.5 rounded-xl font-bold hover:shadow-xl transition transform hover:scale-[1.02] text-sm" data-i18n="create_account_btn">
              Create Account →
            </button>
          </form>

          <p class="text-center text-sm text-gray-500">
            <span data-i18n="already_have_account">Already have an account?</span> <a href="login.php" class="text-red-600 font-bold hover:underline" data-i18n="sign_in_link">Sign in</a>
          </p>
        </div>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-6">
    <div class="max-w-7xl mx-auto px-4 text-center text-sm">
      <p>&copy; BloodLife. <span data-i18n="all_rights_reserved">All rights reserved.</span> |
        <a href="#" class="hover:text-red-400" data-i18n="privacy_policy">Privacy Policy</a> ·
        <a href="#" class="hover:text-red-400" data-i18n="terms_of_service">Terms of Service</a>
      </p>
    </div>
  </footer>

  <script>
    function togglePass(fieldId, btnId) {
      const f = document.getElementById(fieldId);
      const b = document.getElementById(btnId);
      f.type = f.type === 'password' ? 'text' : 'password';
      b.textContent = f.type === 'password' ? '👁' : '🙈';
    }

    document.getElementById('reg_password').addEventListener('input', function() {
      const len = this.value.length;
      const colors = ['bg-red-400', 'bg-orange-400', 'bg-yellow-400', 'bg-green-500'];
      [1, 2, 3, 4].forEach(i => {
        const el = document.getElementById('str' + i);
        const filled = i <= Math.ceil(len / 3) && len > 0;
        el.className = 'h-1 flex-1 rounded transition-all ' + (filled ? colors[Math.min(Math.ceil(len / 3) - 1, 3)] : 'bg-gray-200');
      });
    });

    function validateRegisterForm(event) {
      const name = document.getElementById('reg_name').value.trim();
      const email = document.getElementById('reg_email').value.trim();
      const password = document.getElementById('reg_password').value;
      const confirm = document.getElementById('reg_confirm').value;
      const terms = document.getElementById('reg_terms').checked;

      if (!name || !email || !password) {
        event.preventDefault();
        alert('Please fill in all required fields.');
        return false;
      }
      if (password !== confirm) {
        event.preventDefault();
        alert('Passwords do not match.');
        return false;
      }
      if (password.length < 8) {
        event.preventDefault();
        alert('Password must be at least 8 characters.');
        return false;
      }
      if (!terms) {
        event.preventDefault();
        alert('Please accept the Terms of Service to continue.');
        return false;
      }

      return true;
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