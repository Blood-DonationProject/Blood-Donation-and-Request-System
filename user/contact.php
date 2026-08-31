<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact Us – BloodLife</title>
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
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
      animation: fadeInDown 0.6s ease-out both;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out both;
    }

    .section-white {
      background: #ffffff;
    }

    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px rgba(220, 38, 38, 0.15);
    }
  </style>
  <style id="dark-mode-styles">
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

    html.dark .text-gray-900 {
      color: #f3f4f6 !important;
    }

    html.dark .text-gray-600,
    html.dark .text-gray-500 {
      color: #9ca3af !important;
    }

    html.dark .border-pink-100 {
      border-color: rgba(236, 72, 153, 0.2) !important;
    }
  </style>
</head>

<body class="bg-white min-h-screen flex flex-col">

  <!-- Navbar -->
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Main Content -->
  <main class="flex-grow">
    <!-- ═══════════════════════════════════════════════════ -->
    <!-- CONTACT INFORMATION -->
    <!-- ═══════════════════════════════════════════════════ -->
    <section class="section-white py-20 animate-fade-up">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Contact Information</h2>
          <p class="text-gray-600 max-w-2xl mx-auto text-lg">
            Have questions? Reach out to us anytime. We're here to help you become a lifesaver.
          </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 max-w-4xl mx-auto">
          <!-- Email -->
          <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-200">
              <i class="fas fa-envelope"></i>
            </div>
            <h4 class="font-bold text-gray-900 mb-2">Email</h4>
            <p class="text-gray-500 text-sm">bloodcommunication12@gmail.com</p>
          </div>

          <!-- Phone -->
          <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
            <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200">
              <i class="fas fa-phone"></i>
            </div>
            <h4 class="font-bold text-gray-900 mb-2">Phone</h4>
            <p class="text-gray-500 text-sm">09-258111622</p>
          </div>

          <!-- Address -->
          <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center sm:col-span-2 lg:col-span-1">
            <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-rose-200">
              <i class="fas fa-location-dot"></i>
            </div>
            <h4 class="font-bold text-gray-900 mb-2">Address</h4>
            <p class="text-gray-500 text-sm"> Health Street, Loilem Township</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    // Notification Dropdown Toggle
    function toggleNotifDropdown() {
      var el = document.getElementById('notifDropdown');
      if (el) el.classList.toggle('hidden');
    }
    // User Dropdown Toggle
    function toggleUserDropdown() {
      var el = document.getElementById('userDropdown');
      if (el) el.classList.toggle('hidden');
    }
  </script>
</body>

</html>