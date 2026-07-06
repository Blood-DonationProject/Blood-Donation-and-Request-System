<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Request Blood – BloodLife</title>
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

    .blood-type-btn input[type="radio"] { display: none; }
    .blood-type-btn input[type="radio"]:checked + label {
      background: linear-gradient(to bottom right, #dc2626, #b91c1c);
      color: white;
      border-color: #dc2626;
      transform: scale(1.08);
      box-shadow: 0 8px 20px rgba(220,38,38,0.35);
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
        <div class="hidden md:flex items-center space-x-8">
          <a href="index.php"    class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php"      class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="hospital.php"        class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="hospitals">Hospitals</a>
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
          <select class="theme-toggle-select" aria-label="Theme">
            <option value="light">Light</option>
            <option value="dark">Dark</option>
          </select>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition">Login</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center animate-fade-up">
      <div class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-semibold mb-4">🩸 <span data-i18n="emergency_help">Emergency Help</span></div>
      <h1 class="text-5xl font-bold mb-3" data-i18n="request_blood_title">Request Blood</h1>
      <p class="text-xl opacity-90 max-w-xl mx-auto">Fill in the details below and we'll immediately match you with available donors in your area.</p>
    </div>
  </section>

  <!-- Steps Bar -->
  <section class="bg-white border-b border-gray-100 py-5">
    <div class="max-w-3xl mx-auto px-4">
      <div class="flex items-center justify-between">
        <div class="flex flex-col items-center">
          <div class="w-10 h-10 rounded-full bg-red-600 text-white flex items-center justify-center font-bold text-lg">1</div>
          <p class="text-xs text-red-600 font-semibold mt-1">Patient Info</p>
        </div>
        <div class="flex-1 h-1 bg-red-200 mx-2 rounded"></div>
        <div class="flex flex-col items-center">
          <div class="w-10 h-10 rounded-full bg-red-200 text-red-600 flex items-center justify-center font-bold text-lg">2</div>
          <p class="text-xs text-gray-500 mt-1">Blood Details</p>
        </div>
        <div class="flex-1 h-1 bg-gray-200 mx-2 rounded"></div>
        <div class="flex flex-col items-center">
          <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-lg">3</div>
          <p class="text-xs text-gray-500 mt-1">Confirm</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Form -->
  <section class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 animate-fade-up">

      <!-- Patient Information Card -->
      <div class="bg-white rounded-2xl shadow p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">👤</div>
          <h2 class="text-xl font-bold text-gray-900">Patient Information</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Patient Name <span class="text-red-500">*</span></label>
            <input type="text" placeholder="Full name of the patient" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Patient Age <span class="text-red-500">*</span></label>
            <input type="number" placeholder="Age" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
            <input type="tel" placeholder="+92 300 0000000" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Relation to Patient <span class="text-red-500">*</span></label>
            <select class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
              <option value="">Select relation</option>
              <option>Self</option>
              <option>Parent</option>
              <option>Sibling</option>
              <option>Spouse</option>
              <option>Child</option>
              <option>Other</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Hospital & Location Card -->
      <div class="bg-white rounded-2xl shadow p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🏥</div>
          <h2 class="text-xl font-bold text-gray-900">Hospital & Location</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-5">
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Hospital Name <span class="text-red-500">*</span></label>
            <input type="text" placeholder="Name of the hospital" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
            <select class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
              <option value="">Select city</option>
              <option>Karachi</option>
              <option>Lahore</option>
              <option>Islamabad</option>
              <option>Rawalpindi</option>
              <option>Faisalabad</option>
              <option>Multan</option>
              <option>Peshawar</option>
              <option>Quetta</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Ward / Room No.</label>
            <input type="text" placeholder="e.g. Ward 5, Room 12" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
        </div>
      </div>

      <!-- Blood Details Card -->
      <div class="bg-white rounded-2xl shadow p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🩸</div>
          <h2 class="text-xl font-bold text-gray-900">Blood Details</h2>
        </div>

        <!-- Blood Type Selector -->
        <p class="text-sm font-semibold text-gray-700 mb-3">Select Blood Type Required <span class="text-red-500">*</span></p>
        <div class="grid grid-cols-4 gap-3 mb-6">
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_ap" value="A+" />
            <label for="bt_ap" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">A+</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_an" value="A-" />
            <label for="bt_an" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">A-</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_bp" value="B+" />
            <label for="bt_bp" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">B+</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_bn" value="B-" />
            <label for="bt_bn" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">B-</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_abp" value="AB+" />
            <label for="bt_abp" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">AB+</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_abn" value="AB-" />
            <label for="bt_abn" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">AB-</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_op" value="O+" />
            <label for="bt_op" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">O+</label>
          </div>
          <div class="blood-type-btn">
            <input type="radio" name="blood_type" id="bt_on" value="O-" />
            <label for="bt_on" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">O-</label>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Units Required <span class="text-red-500">*</span></label>
            <input type="number" min="1" max="10" placeholder="e.g. 2" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Urgency Level <span class="text-red-500">*</span></label>
            <select class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
              <option value="">Select urgency</option>
              <option value="critical">🔴 Critical — Within 2 hours</option>
              <option value="urgent">🟠 Urgent — Within 24 hours</option>
              <option value="normal">🟢 Normal — Within 3 days</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Required By <span class="text-red-500">*</span></label>
            <input type="datetime-local" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Medical Condition</label>
            <input type="text" placeholder="e.g. surgery, accident, thalassemia" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Additional Notes</label>
            <textarea rows="3" placeholder="Any additional information for donors…" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition resize-none"></textarea>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="bg-white rounded-2xl shadow p-8">
        <!-- Urgency reminder -->
        <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-6 flex items-start gap-3">
          <span class="text-2xl">⚠️</span>
          <p class="text-sm text-red-700">For life-threatening emergencies, please also call <span class="font-bold">1-800-BLOOD-999</span> directly. Our team is available 24/7.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-4">
          <a href="bloodrequest.php" class="border-2 border-gray-300 text-gray-600 px-8 py-4 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition text-center">
            ← Back to Requests
          </a>
          <button onclick="handleSubmit()" class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl hover:from-red-700 hover:to-red-800 transition transform hover:scale-105">
            Submit Blood Request 🩸
          </button>
        </div>
      </div>

    </div>
  </section>

  <!-- Success Modal (hidden by default) -->
  <div id="successModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-3xl shadow-2xl p-10 max-w-md mx-4 text-center animate-fade-up">
      <div class="text-7xl mb-4">✅</div>
      <h2 class="text-3xl font-bold text-gray-900 mb-3">Request Submitted!</h2>
      <p class="text-gray-600 mb-6">Your blood request has been posted. Matching donors in your area have been notified. You will receive a call shortly.</p>
      <div class="flex gap-3">
        <a href="bloodrequest.php" class="flex-1 border-2 border-red-600 text-red-600 py-3 rounded-xl font-bold hover:bg-red-50 transition text-center">View Requests</a>
        <a href="index.php"    class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white py-3 rounded-xl font-bold hover:shadow-lg transition text-center">Go Home</a>
      </div>
    </div>
  </div>

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
        <p>&copy; BloodLife. All rights reserved. | Privacy Policy | Terms of Service</p>
      </div>
    </div>
  </footer>

  <script>
    function handleSubmit() {
      document.getElementById('successModal').classList.remove('hidden');
      document.getElementById('successModal').classList.add('flex');
    }
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  </script>

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