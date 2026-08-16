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
    tailwind.config = { darkMode: 'class' }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style id="dark-mode-styles">
    /* Light mode resets */
    html:not(.dark) body { background-color: #ffffff !important; }
    html.dark body { background-color: #111827 !important; color: #e5e7eb; }
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .bg-gray-50 { background-color: #374151 !important; }
    html.dark .text-gray-900 { color: #f3f4f6 !important; }
    html.dark .text-gray-600 { color: #9ca3af !important; }
    html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark .border-gray-200 { border-color: #4b5563 !important; }
    html.dark .border-gray-100 { border-color: #374151 !important; }
    html.dark footer { background-color: #1f2937 !important; }
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

    <!-- Grid sections -->
    <div class="grid md:grid-cols-2 gap-8 mb-8">
      <!-- About Our System -->
      <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center hover:shadow-md transition">
        <div class="w-16 h-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center text-2xl mb-6">
          <i class="fas fa-heartbeat"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">About Our System</h2>
        <p class="text-gray-600 text-lg">
          Blood Donation & Request Communication System is a platform that connects blood donors with people who need blood.
        </p>
      </div>

      <!-- Our Purpose -->
      <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center hover:shadow-md transition">
        <div class="w-16 h-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center text-2xl mb-6">
          <i class="fas fa-bullseye"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Purpose</h2>
        <p class="text-gray-600 text-lg">
          Make blood request and donor coordination easier, faster, and more organized.
        </p>
      </div>
    </div>

    <!-- How It Works & Goal -->
    <div class="grid md:grid-cols-2 gap-8">
      <!-- How It Works -->
      <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition">
        <div class="flex items-center gap-4 mb-6">
          <div class="w-16 h-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center text-2xl">
            <i class="fas fa-list-ol"></i>
          </div>
          <h2 class="text-2xl font-bold text-gray-900">How It Works</h2>
        </div>
        <ol class="space-y-4 text-gray-600 text-lg list-decimal list-inside">
          <li class="pl-2">Requester submits a blood request.</li>
          <li class="pl-2">Admin finds and assigns a suitable donor.</li>
          <li class="pl-2">Donor receives website and email notifications.</li>
          <li class="pl-2">Donor provides the blood.</li>
          <li class="pl-2">Requester confirms Blood Received.</li>
          <li class="pl-2">The request is completed.</li>
        </ol>
      </div>

      <!-- Our Goal -->
      <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center justify-center hover:shadow-md transition">
        <div class="w-16 h-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center text-2xl mb-6">
          <i class="fas fa-hands-helping"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Goal</h2>
        <p class="text-gray-600 text-lg">
          To make blood donation coordination more convenient and help connect suitable donors with people in need.
        </p>
      </div>
    </div>

  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
