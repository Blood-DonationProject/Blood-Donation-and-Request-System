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
    /* Light mode resets */
    html:not(.dark) body {
      background-color: #ffffff !important;
    }

    html.dark body {
      background-color: #111827 !important;
      color: #e5e7eb;
    }

    html.dark .bg-white {
      background-color: #1f2937 !important;
    }

    html.dark .bg-gray-50 {
      background-color: #374151 !important;
    }

    html.dark .text-gray-900 {
      color: #f3f4f6 !important;
    }

    html.dark .text-gray-600 {
      color: #9ca3af !important;
    }

    html.dark .text-gray-500 {
      color: #9ca3af !important;
    }

    html.dark .border-gray-200 {
      border-color: #4b5563 !important;
    }

    html.dark .border-gray-100 {
      border-color: #374151 !important;
    }

    html.dark footer {
      background-color: #1f2937 !important;
    }
  </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col transition-colors duration-200">

  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Main Content -->
  <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
    <!-- Header -->
    <div class="text-center mb-16 animate-fade-down">
      <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4">About Us</h1>
      <p class="text-lg sm:text-xl text-red-600 max-w-2xl mx-auto font-bold">Blood Donation & Request Communication System</p>
    </div>

    <!-- Top Grid: About, Purpose, Goal -->
    <div class="grid md:grid-cols-3 gap-8 mb-16">
      <!-- About Our System -->
      <div class="bg-pink-50/50 dark:bg-red-200 p-8 rounded-3xl shadow-sm border border-pink-100 dark:border-gray-700 flex flex-col items-center text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="w-16 h-16 bg-pink-100 dark:bg-red-100 text-red-600 dark:text-red-700 rounded-full flex items-center justify-center text-2xl mb-6 shadow-sm">
          <i class="fa-solid fa-droplet"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">About Our System</h2>
        <p class="text-gray-600 text-lg leading-relaxed">
          Blood Donation & Request Communication System is a platform that connects blood donors with people who need blood.
        </p>
      </div>

      <!-- Our Purpose -->
      <div class="bg-rose-50/50 dark:bg-red-200 p-8 rounded-3xl shadow-sm border border-rose-100 dark:border-gray-700 flex flex-col items-center text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="w-16 h-16 bg-rose-100 dark:bg-red-100 text-rose-600 dark:text-red-700 rounded-full flex items-center justify-center text-2xl mb-6 shadow-sm">
          <i class="fa-solid fa-bullseye"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Purpose</h2>
        <p class="text-gray-600 text-lg leading-relaxed">
          Make blood request and donor coordination easier, faster, and more organized.
        </p>
      </div>

      <!-- Our Goal -->
      <div class="bg-red-50/50 dark:bg-red-200 p-8 rounded-3xl shadow-sm border border-red-100 dark:border-gray-700 flex flex-col items-center text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
        <div class="w-16 h-16 bg-red-100 dark:bg-red-100 text-red-600 dark:text-red-700 rounded-full flex items-center justify-center text-2xl mb-6 shadow-sm">
          <i class="fa-solid fa-hand-holding-heart"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Goal</h2>
        <p class="text-gray-600 text-lg leading-relaxed">
          To make blood donation coordination more convenient and help connect suitable donors with people in need.
        </p>
      </div>
    </div>

    <!-- How It Works Section -->
    <div class="mb-12">
      <div class="text-center mb-10">
        <h2 class="text-3xl font-extrabold text-gray-900 mb-4">How It Works</h2>
        <p class="text-gray-600 text-lg">A simple and efficient process to save lives</p>
      </div>

      <div class="flex flex-col md:flex-row items-center justify-between gap-4 relative w-full">

        <!-- Background connector line (desktop) -->
        <div class="hidden md:block absolute top-10 left-[10%] right-[10%] h-1 bg-pink-100 dark:bg-gray-700 -translate-y-1/2 z-0 rounded-full"></div>

        <!-- Step 1 -->
        <div class="flex-1 w-full flex flex-col items-center relative z-10 group">
          <div class="w-20 h-20 bg-white border-4 border-pink-100 dark:border-gray-600 text-pink-600 dark:text-pink-400 rounded-full flex items-center justify-center text-3xl mb-4 shadow-md group-hover:scale-110 group-hover:border-pink-300 dark:group-hover:border-pink-500 transition-all duration-300">
            <i class="fas fa-hand-holding-medical"></i>
          </div>
          <div class="bg-white p-4 rounded-xl shadow-sm border border-pink-100 dark:border-gray-700 text-center w-full max-w-[220px]">
            <h3 class="font-bold text-gray-900 text-base sm:text-lg">1. Request Blood</h3>
          </div>
          <!-- Mobile Arrow -->
          <div class="block md:hidden text-pink-200 dark:text-gray-600 text-3xl my-4">
            <i class="fas fa-arrow-down"></i>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="flex-1 w-full flex flex-col items-center relative z-10 group">
          <div class="w-20 h-20 bg-white border-4 border-pink-100 dark:border-gray-600 text-pink-600 dark:text-pink-400 rounded-full flex items-center justify-center text-3xl mb-4 shadow-md group-hover:scale-110 group-hover:border-pink-300 dark:group-hover:border-pink-500 transition-all duration-300">
            <i class="fas fa-user-check"></i>
          </div>
          <div class="bg-white p-4 rounded-xl shadow-sm border border-pink-100 dark:border-gray-700 text-center w-full max-w-[220px]">
            <h3 class="font-bold text-gray-900 text-base sm:text-lg">2. Assign Suitable Donor</h3>
          </div>
          <!-- Mobile Arrow -->
          <div class="block md:hidden text-pink-200 dark:text-gray-600 text-3xl my-4">
            <i class="fas fa-arrow-down"></i>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="flex-1 w-full flex flex-col items-center relative z-10 group">
          <div class="w-20 h-20 bg-white border-4 border-pink-100 dark:border-gray-600 text-pink-600 dark:text-pink-400 rounded-full flex items-center justify-center text-3xl mb-4 shadow-md group-hover:scale-110 group-hover:border-pink-300 dark:group-hover:border-pink-500 transition-all duration-300">
            <i class="fas fa-heart"></i>
          </div>
          <div class="bg-white p-4 rounded-xl shadow-sm border border-pink-100 dark:border-gray-700 text-center w-full max-w-[220px]">
            <h3 class="font-bold text-gray-900 text-base sm:text-lg">3. Donate Blood</h3>
          </div>
          <!-- Mobile Arrow -->
          <div class="block md:hidden text-pink-200 dark:text-gray-600 text-3xl my-4">
            <i class="fas fa-arrow-down"></i>
          </div>
        </div>

        <!-- Step 4 -->
        <div class="flex-1 w-full flex flex-col items-center relative z-10 group">
          <div class="w-20 h-20 bg-white border-4 border-pink-100 dark:border-gray-600 text-pink-600 dark:text-pink-400 rounded-full flex items-center justify-center text-3xl mb-4 shadow-md group-hover:scale-110 group-hover:border-pink-300 dark:group-hover:border-pink-500 transition-all duration-300">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="bg-white p-4 rounded-xl shadow-sm border border-pink-100 dark:border-gray-700 text-center w-full max-w-[220px]">
            <h3 class="font-bold text-gray-900 text-base sm:text-lg">4. Blood Received</h3>
          </div>
        </div>

      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>