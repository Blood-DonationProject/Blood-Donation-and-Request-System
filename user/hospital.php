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
  <title>Hospitals – BloodLife</title>
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
<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center animate-fade-up">
      <div class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-semibold mb-4">🏥 <span data-i18n="partner_network">Partner Network</span></div>
      <h1 class="text-5xl font-bold mb-4" data-i18n="hospital_network_title">Our Hospital Network</h1>
      <p class="text-xl opacity-90 max-w-2xl mx-auto">We partner with leading hospitals across the country to ensure donated blood reaches patients who need it most — fast.</p>
      <div class="grid grid-cols-3 gap-6 mt-12 max-w-lg mx-auto text-center">
        <div><p class="text-4xl font-bold">35+</p><p class="text-sm opacity-80">Partner Hospitals</p></div>
        <div><p class="text-4xl font-bold">12</p><p class="text-sm opacity-80">Cities Covered</p></div>
        <div><p class="text-4xl font-bold">24/7</p><p class="text-sm opacity-80">Emergency Ready</p></div>
      </div>
    </div>
  </section>

  <!-- Search & Filter -->
  <section class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-2xl shadow p-6 flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Search Hospital</label>
          <input type="text" placeholder="Search by name or city…" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">City</label>
          <select class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
            <option value="">All Cities</option>
            <option>Karachi</option><option>Lahore</option><option>Islamabad</option>
            <option>Rawalpindi</option><option>Faisalabad</option><option>Multan</option>
            <option>Peshawar</option><option>Quetta</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
          <select class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
            <option>All</option>
            <option>Accepting Donations</option>
            <option>Emergency Only</option>
          </select>
        </div>
        <button class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transition">Search</button>
      </div>
    </div>
  </section>

  <!-- Hospital Cards -->
  <section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <div class="bg-gradient-to-r from-red-500 to-red-600 h-3"></div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
              <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Accepting</span>
            </div>
            <h3 class="font-bold text-gray-900 text-xl mb-1">Aga Khan University Hospital</h3>
            <p class="text-red-600 font-semibold text-sm mb-3">Karachi, Sindh</p>
            <p class="text-gray-500 text-sm mb-4">A world-class tertiary care hospital with a 24/7 blood bank facility and advanced transfusion services.</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-lg">A+  A-</span>
              <span class="bg-blue-50 text-blue-600 text-xs font-semibold px-2 py-1 rounded-lg">B+  B-</span>
              <span class="bg-purple-50 text-purple-600 text-xs font-semibold px-2 py-1 rounded-lg">O+  O-</span>
              <span class="bg-yellow-50 text-yellow-600 text-xs font-semibold px-2 py-1 rounded-lg">AB+  AB-</span>
            </div>
            <div class="flex gap-2 text-sm text-gray-500 mb-5">
              <span>📞 021-3486-1234</span>
            </div>
            <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">View Details</button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <div class="bg-gradient-to-r from-red-500 to-red-600 h-3"></div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
              <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Accepting</span>
            </div>
            <h3 class="font-bold text-gray-900 text-xl mb-1">Mayo Hospital</h3>
            <p class="text-red-600 font-semibold text-sm mb-3">Lahore, Punjab</p>
            <p class="text-gray-500 text-sm mb-4">One of Pakistan's oldest and largest public hospitals with extensive blood bank infrastructure serving thousands daily.</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-lg">A+  A-</span>
              <span class="bg-blue-50 text-blue-600 text-xs font-semibold px-2 py-1 rounded-lg">B+  B-</span>
              <span class="bg-purple-50 text-purple-600 text-xs font-semibold px-2 py-1 rounded-lg">O+</span>
            </div>
            <div class="flex gap-2 text-sm text-gray-500 mb-5">
              <span>📞 042-9920-5951</span>
            </div>
            <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">View Details</button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <div class="bg-gradient-to-r from-orange-400 to-orange-500 h-3"></div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
              <span class="bg-orange-100 text-orange-700 text-xs font-bold px-3 py-1 rounded-full">🚨 Emergency Only</span>
            </div>
            <h3 class="font-bold text-gray-900 text-xl mb-1">PIMS Hospital</h3>
            <p class="text-red-600 font-semibold text-sm mb-3">Islamabad, Federal</p>
            <p class="text-gray-500 text-sm mb-4">Pakistan Institute of Medical Sciences — a premier federal hospital handling critical and emergency blood requirements.</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <span class="bg-purple-50 text-purple-600 text-xs font-semibold px-2 py-1 rounded-lg">O-  O+</span>
              <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-lg">A+</span>
            </div>
            <div class="flex gap-2 text-sm text-gray-500 mb-5">
              <span>📞 051-9261-170</span>
            </div>
            <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">View Details</button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <div class="bg-gradient-to-r from-red-500 to-red-600 h-3"></div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
              <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Accepting</span>
            </div>
            <h3 class="font-bold text-gray-900 text-xl mb-1">Civil Hospital</h3>
            <p class="text-red-600 font-semibold text-sm mb-3">Karachi, Sindh</p>
            <p class="text-gray-500 text-sm mb-4">The largest public sector hospital in Karachi with a high-capacity blood bank serving the city's underprivileged population.</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-lg">A+</span>
              <span class="bg-blue-50 text-blue-600 text-xs font-semibold px-2 py-1 rounded-lg">B+  B-</span>
              <span class="bg-yellow-50 text-yellow-600 text-xs font-semibold px-2 py-1 rounded-lg">AB+</span>
            </div>
            <div class="flex gap-2 text-sm text-gray-500 mb-5">
              <span>📞 021-9921-5740</span>
            </div>
            <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">View Details</button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <div class="bg-gradient-to-r from-red-500 to-red-600 h-3"></div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
              <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Accepting</span>
            </div>
            <h3 class="font-bold text-gray-900 text-xl mb-1">Services Hospital</h3>
            <p class="text-red-600 font-semibold text-sm mb-3">Lahore, Punjab</p>
            <p class="text-gray-500 text-sm mb-4">A leading teaching hospital connected to King Edward Medical University with full-service blood bank operations.</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-lg">A+  A-</span>
              <span class="bg-purple-50 text-purple-600 text-xs font-semibold px-2 py-1 rounded-lg">O+  O-</span>
              <span class="bg-yellow-50 text-yellow-600 text-xs font-semibold px-2 py-1 rounded-lg">AB-</span>
            </div>
            <div class="flex gap-2 text-sm text-gray-500 mb-5">
              <span>📞 042-9921-2401</span>
            </div>
            <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">View Details</button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <div class="bg-gradient-to-r from-red-500 to-red-600 h-3"></div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-4">
              <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center text-3xl">🏥</div>
              <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Accepting</span>
            </div>
            <h3 class="font-bold text-gray-900 text-xl mb-1">Nishtar Hospital</h3>
            <p class="text-red-600 font-semibold text-sm mb-3">Multan, Punjab</p>
            <p class="text-gray-500 text-sm mb-4">South Punjab's largest hospital with an active blood donation drive and 24/7 trauma blood services.</p>
            <div class="flex flex-wrap gap-2 mb-5">
              <span class="bg-blue-50 text-blue-600 text-xs font-semibold px-2 py-1 rounded-lg">B+</span>
              <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-lg">A+  A-</span>
              <span class="bg-purple-50 text-purple-600 text-xs font-semibold px-2 py-1 rounded-lg">O+</span>
            </div>
            <div class="flex gap-2 text-sm text-gray-500 mb-5">
              <span>📞 061-9200-301</span>
            </div>
            <button class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition">View Details</button>
          </div>
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

  <!-- CTA -->
  <section class="py-16 bg-gradient-to-r from-red-600 to-red-800 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <h2 class="text-4xl font-bold mb-4">Is Your Hospital Not Listed?</h2>
      <p class="text-xl opacity-90 mb-8">We're always expanding our network. Partner with BloodLife to connect with thousands of donors.</p>
      <a href="mailto:info@bloodlife.com" class="bg-white text-red-600 px-8 py-4 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105 inline-block">
        Partner With Us
      </a>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-4 gap-8 mb-8">
        <div><h3 class="text-white font-bold text-lg mb-4">BloodLife</h3><p class="text-sm">Connecting donors with those who need help. Save lives today.</p></div>
        <div>
          <h4 class="text-white font-bold mb-4">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-red-400 transition">About Us</a></li>
            <li><a href="donor.php" class="hover:text-red-400 transition">Donors</a></li>
            <li><a href="hospital.php" class="hover:text-red-400 transition">Hospitals</a></li>
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
        <p>&copy; BloodLife. All rights reserved. | Privacy Policy | Terms of Service</p>
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