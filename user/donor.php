<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Donors – BloodLife</title>
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
    html.dark .bg-red-50 { background-color: rgba(220,38,38,0.15) !important; }
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
  </style>
</head>
<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen font-family: 'Pyidaungsu', Noto Sans Myanmar, sans-serif;">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </div>


         <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
                    <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
                    <a href="hospital.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="hospitals">Hospitals</a>
                    <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>

                    <select class="theme-toggle-select" aria-label="Theme">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                    <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
                        <option value="en">EN</option>
                        <option value="my">MY</option>
                    </select>

                    <?php if ($isLoggedIn): ?>
                        <a href="donordashboard.php" class="flex items-center gap-2 hover:text-red-600 transition">
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">
                                <?= strtoupper(substr($username, 0, 1)) ?>
                            </div>
                            <span class="font-medium text-gray-700"><?= $username ?></span>
                        </a>
                        <a href="logout.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition text-sm">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition cursor-pointer">
                            Login
                        </a>
                    <?php endif; ?>
                </div>
      </div>
    </div>
  </nav>

  <!-- Hero Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 animate-fade-up">
      <div class="text-center">
        <div class="inline-block bg-white/20 text-white px-4 py-2 rounded-full text-sm font-semibold mb-4" data-i18n="our_heroes">🩸 Our Heroes</div>
        <h1 class="text-5xl font-bold mb-4" data-i18n="blood_donors_title">Blood Donors</h1>
        <p class="text-xl opacity-90 max-w-2xl mx-auto">Meet the generous individuals saving lives every day. Every drop counts — and so does every donor.</p>
      </div>

      <!-- Stats row -->
      <div class="grid grid-cols-3 gap-6 mt-12 max-w-lg mx-auto text-center">
        <div>
          <p class="text-4xl font-bold">250+</p>
          <p class="text-sm opacity-80">Active Donors</p>
        </div>
        <div>
          <p class="text-4xl font-bold">8</p>
          <p class="text-sm opacity-80">Blood Types</p>
        </div>
        <div>
          <p class="text-4xl font-bold">120+</p>
          <p class="text-sm opacity-80">Lives Saved</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Become a Donor CTA Banner -->
  <section class="bg-red-50 border-b border-red-100 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-gray-700 font-medium text-lg">Want to join the list? Become a donor today — it only takes a few minutes.</p>
        <a href="donateform.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg hover:from-red-700 hover:to-red-800 transition transform hover:scale-105 whitespace-nowrap" data-i18n="register_as_donor">
        Register as Donor
      </a>
    </div>
  </section>

  <!-- Filter & Search -->
  <section class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-2xl shadow p-6 flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Search Donor</label>
          <input type="text" placeholder="Search by name or city…" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type</label>
          <select class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
            <option value="">All Types</option>
            <option>A+</option><option>A-</option>
            <option>B+</option><option>B-</option>
            <option>AB+</option><option>AB-</option>
            <option>O+</option><option>O-</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Availability</label>
          <select class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
            <option>All</option>
            <option>Available Now</option>
            <option>Not Available</option>
          </select>
        </div>
        <button class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transition">
          Search
        </button>
      </div>
    </div>
  </section>

  <!-- Donor Cards Grid -->
  <section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

        <!-- Donor Card Template (repeated 8x with variety) -->
        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Ahmed Raza</h3>
          <p class="text-gray-500 text-sm mb-3">Karachi, Pakistan</p>
          <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-4 py-1 rounded-full text-lg mb-3">A+</span>
          <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">✅ Available</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 2 months ago</p>
          <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">Contact</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Sara Malik</h3>
          <p class="text-gray-500 text-sm mb-3">Lahore, Pakistan</p>
          <span class="bg-gradient-to-br from-blue-100 to-blue-200 text-blue-700 font-bold px-4 py-1 rounded-full text-lg mb-3">B+</span>
          <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">✅ Available</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 1 month ago</p>
          <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">Contact</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Omar Sheikh</h3>
          <p class="text-gray-500 text-sm mb-3">Islamabad, Pakistan</p>
          <span class="bg-gradient-to-br from-purple-100 to-purple-200 text-purple-700 font-bold px-4 py-1 rounded-full text-lg mb-3">O-</span>
          <span class="inline-block bg-red-100 text-red-600 text-xs font-semibold px-3 py-1 rounded-full mb-4">⏳ Cooldown</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 3 weeks ago</p>
          <button class="w-full border-2 border-gray-300 text-gray-400 py-2 rounded-xl font-semibold cursor-not-allowed">Unavailable</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-yellow-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Fatima Khan</h3>
          <p class="text-gray-500 text-sm mb-3">Peshawar, Pakistan</p>
          <span class="bg-gradient-to-br from-yellow-100 to-yellow-200 text-yellow-700 font-bold px-4 py-1 rounded-full text-lg mb-3">AB+</span>
          <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">✅ Available</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 4 months ago</p>
          <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">Contact</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Hassan Ali</h3>
          <p class="text-gray-500 text-sm mb-3">Multan, Pakistan</p>
          <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-4 py-1 rounded-full text-lg mb-3">O+</span>
          <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">✅ Available</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 6 months ago</p>
          <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">Contact</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Zainab Noor</h3>
          <p class="text-gray-500 text-sm mb-3">Karachi, Pakistan</p>
          <span class="bg-gradient-to-br from-blue-100 to-blue-200 text-blue-700 font-bold px-4 py-1 rounded-full text-lg mb-3">A-</span>
          <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">✅ Available</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 5 months ago</p>
          <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">Contact</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Bilal Hussain</h3>
          <p class="text-gray-500 text-sm mb-3">Faisalabad, Pakistan</p>
          <span class="bg-gradient-to-br from-purple-100 to-purple-200 text-purple-700 font-bold px-4 py-1 rounded-full text-lg mb-3">B-</span>
          <span class="inline-block bg-red-100 text-red-600 text-xs font-semibold px-3 py-1 rounded-full mb-4">⏳ Cooldown</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 1 month ago</p>
          <button class="w-full border-2 border-gray-300 text-gray-400 py-2 rounded-xl font-semibold cursor-not-allowed">Unavailable</button>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 p-6 flex flex-col items-center text-center">
          <div class="w-16 h-16 rounded-full bg-yellow-100 flex items-center justify-center text-3xl mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-lg">Ayesha Siddiq</h3>
          <p class="text-gray-500 text-sm mb-3">Rawalpindi, Pakistan</p>
          <span class="bg-gradient-to-br from-yellow-100 to-yellow-200 text-yellow-700 font-bold px-4 py-1 rounded-full text-lg mb-3">AB-</span>
          <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">✅ Available</span>
          <p class="text-gray-500 text-xs mb-4">Last donated: 3 months ago</p>
          <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">Contact</button>
        </div>

      </div>

      <!-- Pagination -->
      <div class="flex justify-center gap-2 mt-12">
        <button class="w-10 h-10 rounded-xl bg-red-600 text-white font-bold">1</button>
        <button class="w-10 h-10 rounded-xl border-2 border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600 transition font-bold">2</button>
        <button class="w-10 h-10 rounded-xl border-2 border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600 transition font-bold">3</button>
        <button class="w-10 h-10 rounded-xl border-2 border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600 transition font-bold">›</button>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-4 gap-8 mb-8">
        <div>
          <h3 class="text-white font-bold text-lg mb-4">BloodLife</h3>
          <p class="text-sm">Connecting donors with those who need help. Save lives today.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-red-400 transition">About Us</a></li>
            <li><a href="donor.php" class="hover:text-red-400 transition">Donors</a></li>
            <li><a href="#" class="hover:text-red-400 transition">Hospitals</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4">Contact</h4>
          <ul class="space-y-2 text-sm">
            <li>📧 info@bloodlife.com</li>
            <li>📱 1-800-BLOOD-999</li>
            <li>📍 123 Health Street, City</li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4">Follow Us</h4>
          <div class="flex space-x-4">
            <a href="#" class="hover:text-red-400 transition">Facebook</a>
            <a href="#" class="hover:text-red-400 transition">Twitter</a>
            <a href="#" class="hover:text-red-400 transition">Instagram</a>
          </div>
        </div>
      </div>
      <div class="border-t border-gray-700 pt-8 text-center text-sm">
        <p>&copy;  BloodLife. All rights reserved. | Privacy Policy | Terms of Service</p>
      </div>
    </div>
  </footer>

  <script>
  (function() {
    var KEY = 'bloodlife-theme';
    function getTheme() { return localStorage.getItem(KEY) || 'light'; }
    function apply(t) {
      if (t === 'dark') document.documentElement.classList.add('dark');
      else document.documentElement.classList.remove('dark');
      document.querySelectorAll('.theme-toggle-select').forEach(function(s){ s.value = t; });
    }
    apply(getTheme());
    document.querySelectorAll('.theme-toggle-select').forEach(function(s) {
      s.value = getTheme();
      s.addEventListener('change', function() {
        localStorage.setItem(KEY, this.value);
        apply(this.value);
      });
    });
  })();
  </script>

</body>
</html>
