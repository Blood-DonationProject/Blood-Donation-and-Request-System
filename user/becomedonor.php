<?php
session_start();
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Blood Donor</title>

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
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 min-h-screen font-family: 'Pyidaungsu', Noto Sans Myanmar, sans-serif;">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <a href="index.php" class="flex items-center space-x-3">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </a>
        <div class="hidden md:flex items-center space-x-8">
          <a href="index.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
          <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
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
            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition text-sm">Logout</a>
          <?php else: ?>
            <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition cursor-pointer">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-red-700 to-red-500 text-white">
        <div class="max-w-7xl mx-auto px-6 py-24 text-center">

            <h1 class="text-5xl font-bold mb-6" data-i18n="donate_blood_save_lives">
                Donate Blood, Save Lives ❤️
            </h1>

            <p class="text-xl max-w-3xl mx-auto leading-8" data-i18n="becomedonor_desc">
                Your blood donation can save patients in emergencies,
                surgeries, cancer treatments, and many other life-threatening situations.
            </p>

            <a href="donateform.php" class="mt-10 inline-block bg-white text-red-600 font-semibold px-8 py-4 rounded-full shadow-lg hover:bg-red-100 duration-300" data-i18n="become_a_donor">
                Become a Donor
            </a>

        </div>
    </section>

    <!-- Eligibility -->
    <section class="py-20">

        <div class="max-w-6xl mx-auto px-6">

            <div class="text-center mb-14">
                <h2 class="text-4xl font-bold text-red-600" data-i18n="who_can_donate">
                    Who Can Donate Blood?
                </h2>

                <p class="text-gray-600 mt-4" data-i18n="who_can_donate_desc">
                    Please make sure you meet the following requirements.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">

                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="text-5xl mb-4">🎂</div>
                    <h3 class="font-bold text-xl mb-2" data-i18n="age">Age</h3>
                    <p class="text-gray-600" data-i18n="age_desc">
                        Between 18 and 65 years old.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="text-5xl mb-4">⚖️</div>
                    <h3 class="font-bold text-xl mb-2" data-i18n="weight">Weight</h3>
                    <p class="text-gray-600" data-i18n="weight_desc">
                        At least 45–50 kg.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="text-5xl mb-4">❤️</div>
                    <h3 class="font-bold text-xl mb-2" data-i18n="healthy">Healthy</h3>
                    <p class="text-gray-600" data-i18n="healthy_desc">
                        Free from fever or infectious illnesses.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="text-5xl mb-4">🩸</div>
                    <h3 class="font-bold text-xl mb-2" data-i18n="hemoglobin">Hemoglobin</h3>
                    <p class="text-gray-600" data-i18n="hemoglobin_desc">
                        Healthy hemoglobin level is required.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="text-5xl mb-4">🍽️</div>
                    <h3 class="font-bold text-xl mb-2" data-i18n="before_donation">Before Donation</h3>
                    <p class="text-gray-600" data-i18n="before_donation_desc">
                        Eat well, stay hydrated and sleep enough.
                    </p>
                </div>

                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="text-5xl mb-4">📅</div>
                    <h3 class="font-bold text-xl mb-2" data-i18n="donation_interval">Donation Interval</h3>
                    <p class="text-gray-600" data-i18n="donation_interval_desc">
                        Wait at least 3 months between donations.
                    </p>
                </div>

            </div>

        </div>

    </section>

    <!-- Not Eligible -->

    <section class="bg-red-50 py-20">

        <div class="max-w-6xl mx-auto px-6">

            <div class="text-center mb-12">

                <h2 class="text-4xl font-bold text-red-600" data-i18n="who_should_not_donate">
                    Who Should Not Donate?
                </h2>

            </div>

            <div class="grid md:grid-cols-2 gap-8">

                <div class="bg-white p-8 rounded-2xl shadow">

                    <ul class="space-y-4 text-gray-700">

                        <li>❌ <span data-i18n="pregnant">Pregnant or recently gave birth</span></li>

                        <li>❌ <span data-i18n="low_hemoglobin">Low hemoglobin or anemia</span></li>

                        <li>❌ <span data-i18n="hiv_hepatitis">HIV / Hepatitis B / Hepatitis C</span></li>

                        <li>❌ <span data-i18n="recent_surgery_tattoo">Recent surgery or tattoo</span></li>

                        <li>❌ <span data-i18n="currently_sick">Currently sick or having fever</span></li>

                    </ul>

                </div>

                <div class="flex items-center">

                    <p class="text-lg leading-8 text-gray-700" data-i18n="donor_screening_note">

                        Every donor will undergo a simple health screening
                        before donating blood. This helps ensure the safety of
                        both the donor and the recipient.

                    </p>

                </div>

            </div>

        </div>

    </section>

    <!-- Preparation -->

    <section class="py-20">

        <div class="max-w-6xl mx-auto px-6">

            <div class="text-center mb-12">

                <h2 class="text-4xl font-bold text-red-600" data-i18n="before_you_donate">
                    Before You Donate
                </h2>

            </div>

            <div class="grid md:grid-cols-4 gap-8">

                <div class="bg-white rounded-xl shadow p-8 text-center">

                    <div class="text-5xl">😴</div>

                    <h3 class="font-semibold mt-4" data-i18n="sleep_well">
                        Sleep Well
                    </h3>

                </div>

                <div class="bg-white rounded-xl shadow p-8 text-center">

                    <div class="text-5xl">🥗</div>

                    <h3 class="font-semibold mt-4" data-i18n="eat_healthy">
                        Eat Healthy
                    </h3>

                </div>

                <div class="bg-white rounded-xl shadow p-8 text-center">

                    <div class="text-5xl">💧</div>

                    <h3 class="font-semibold mt-4" data-i18n="drink_water">
                        Drink Water
                    </h3>

                </div>

                <div class="bg-white rounded-xl shadow p-8 text-center">

                    <div class="text-5xl">🪪</div>

                    <h3 class="font-semibold mt-4" data-i18n="bring_your_id">
                        Bring Your ID
                    </h3>

                </div>

            </div>

        </div>

    </section>

    <!-- CTA -->

    <section class="bg-red-600 text-white py-20">

        <div class="max-w-5xl mx-auto px-6 text-center">

            <h2 class="text-4xl font-bold mb-6" data-i18n="be_someones_hero">
                Be Someone's Hero Today
            </h2>

            <p class="text-xl leading-8 mb-8" data-i18n="be_hero_desc">

                A single blood donation can save multiple lives.
                Join our community of voluntary blood donors.

            </p>

            <a href="donateform.php" class="bg-white text-red-600 px-10 py-4 rounded-full font-bold hover:bg-red-100 duration-300" data-i18n="register_as_a_donor">

                Register as a Donor

            </a>

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
            <li><a href="index.php" class="hover:text-red-400 transition">Home</a></li>
            <li><a href="donor.php" class="hover:text-red-400 transition">Donors</a></li>
            <li><a href="bloodrequest.php" class="hover:text-red-400 transition">Requests</a></li>
            <li><a href="becomedonor.php" class="hover:text-red-400 transition">Become a Donor</a></li>
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
        <p>&copy; BloodLife. All rights reserved. | <a href="#" class="hover:text-red-400">Privacy Policy</a> | <a href="#" class="hover:text-red-400">Terms of Service</a></p>
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