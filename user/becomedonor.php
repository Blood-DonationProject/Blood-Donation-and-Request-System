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
        html.dark .bg-white { background-color: #1f2937 !important; }
        html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
        html.dark .text-gray-700 { color: #d1d5db !important; }
        html.dark .text-gray-600 { color: #9ca3af !important; }
        html.dark .text-gray-500 { color: #9ca3af !important; }
        html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #374151 !important; }
        html.dark .border-gray-200, html.dark .border-2.border-gray-200 { border-color: #4b5563 !important; }
        html.dark .border-t { border-color: #374151 !important; }
        html.dark .bg-red-50 { background-color: rgba(220,38,38,0.15) !important; }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 font-family: 'Pyidaungsu', Noto Sans Myanmar, sans-serif;">

    <!-- Language Toggle -->
    <div class="fixed top-4 right-4 z-50 flex gap-2">
        <select class="theme-toggle-select" aria-label="Theme">
            <option value="light">Light</option>
            <option value="dark">Dark</option>
        </select>
        <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
        </select>
    </div>

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