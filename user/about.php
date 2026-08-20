<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us - Blood Donation & Request Communication System</title>
  <script>
    (function() {
      var t = localStorage.getItem('bloodlife-theme');
      if (t === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class'
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style id="dark-mode-styles">
        html:not(.dark) body {
            background-color: #ffffff !important;
            background-image: none !important;
        }

        html:not(.dark) .bg-gray-50 {
            background-color: #ffffff !important;
        }

        html:not(.dark) .bg-gray-100 {
            background-color: #ffffff !important;
        }

        html.dark body {
            background-color: #111827 !important;
            background-image: none !important;
            color: #e5e7eb;
        }

        html.dark nav.bg-white,
        html.dark nav.bg-white.shadow-lg,
        html.dark .w-64.bg-white {
            background-color: #1f2937 !important;
        }

        html.dark .bg-white {
            background-color: #1f2937 !important;
        }

        html.dark .text-gray-900,
        html.dark .text-gray-800 {
            color: #f3f4f6 !important;
        }

        html.dark .text-gray-700 {
            color: #d1d5db !important;
        }

        html.dark .text-gray-600 {
            color: #9ca3af !important;
        }

        html.dark .text-gray-500 {
            color: #9ca3af !important;
        }

        html.dark input,
        html.dark select,
        html.dark textarea {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }

        html.dark label {
            color: #d1d5db !important;
        }

        html.dark .bg-gray-50,
        html.dark .bg-gray-100 {
            background-color: #374151 !important;
        }

        html.dark thead.bg-gray-50 {
            background-color: #111827 !important;
        }

        html.dark .border-gray-200,
        html.dark .border-2.border-gray-200 {
            border-color: #4b5563 !important;
        }

        html.dark .border-t {
            border-color: #374151 !important;
        }

        html.dark .bg-red-50 {
            background-color: rgba(220, 38, 38, 0.15) !important;
        }

        html.dark .bg-green-50 {
            background-color: rgba(34, 197, 94, 0.15) !important;
        }

        html.dark .bg-yellow-50 {
            background-color: rgba(234, 179, 8, 0.15) !important;
        }

        html.dark .bg-blue-50 {
            background-color: rgba(59, 130, 246, 0.15) !important;
        }

        html.dark .bg-purple-50 {
            background-color: rgba(168, 85, 247, 0.15) !important;
        }

        html.dark .bg-orange-50 {
            background-color: rgba(249, 115, 22, 0.15) !important;
        }

        html.dark .bg-white.rounded-xl.shadow-xl {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
        }

        html.dark tbody tr {
            border-color: #374151 !important;
        }

        html.dark tbody tr:hover {
            background-color: #374151 !important;
        }

        html.dark ::-webkit-scrollbar {
            width: 8px;
        }

        html.dark ::-webkit-scrollbar-track {
            background: #1f2937;
        }

        html.dark ::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 4px;
        }
  </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col transition-colors duration-200">

  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Main Content -->
  <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
    <!-- Header -->
    <div class="text-center mb-16 animate-fade-down">
      <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-500 mb-4">About Us</h1>
      <p class="text-lg sm:text-xl text-red-600 max-w-2xl mx-auto font-bold">Blood Communication System</p>
    </div>

    <!-- Top Grid: About, Purpose, Goal -->
    <div class="grid md:grid-cols-3 gap-8 mb-16">
      <!-- About Our System -->
      <div class="bg-pink-50/50 dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-pink-100 dark:border-gray-700 flex flex-col items-center text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="w-16 h-16 bg-pink-100 dark:bg-gray-700 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center text-2xl mb-6 shadow-sm">
          <i class="fa-solid fa-droplet"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">About Our System</h2>
        <p class="text-gray-600 text-lg leading-relaxed">
          Blood Communication System is a platform that connects blood donors with people who need blood.
        </p>
      </div>

      <!-- Our Purpose -->
      <div class="bg-rose-50/50 dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-rose-100 dark:border-gray-700 flex flex-col items-center text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="w-16 h-16 bg-rose-100 dark:bg-gray-700 text-rose-600 dark:text-red-400 rounded-full flex items-center justify-center text-2xl mb-6 shadow-sm">
          <i class="fa-solid fa-bullseye"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Purpose</h2>
        <p class="text-gray-600 text-lg leading-relaxed">
          Make blood request and donor coordination easier, faster, and more organized.
        </p>
      </div>

      <!-- Our Goal -->
      <div class="bg-red-50/50 dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-red-100 dark:border-gray-700 flex flex-col items-center text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="w-16 h-16 bg-red-100 dark:bg-gray-700 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center text-2xl mb-6 shadow-sm">
          <i class="fa-solid fa-hand-holding-heart"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Goal</h2>
        <p class="text-gray-600 text-lg leading-relaxed">
          To make blood donation coordination more convenient and help connect suitable donors with people in need.
        </p>
      </div>
    </div>


  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
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