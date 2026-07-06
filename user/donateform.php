<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Donate Blood – BloodLife</title>
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
      box-shadow: 0 8px 20px rgba(220,38,38,0.3);
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
        <a href="donordashboard.php" class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </a>
        <div class="hidden md:flex items-center space-x-8">
          <a href="donordashboard.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="dashboard">Dashboard</a>
          <a href="donor.php"         class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="hospital.php"       class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="hospitals">Hospitals</a>
          <a href="bloodrequest.php"    class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
          <select class="theme-toggle-select" aria-label="Theme">
            <option value="light">Light</option>
            <option value="dark">Dark</option>
          </select>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="profile.php" class="flex items-center gap-2 hover:text-red-600 transition">
            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">A</div>
            <span class="font-medium text-gray-700" id="navName">Ahmed</span>
          </a>
          <a href="#" id="navAuthBtn" onclick="bloodlifeLogout(); return false;"
            class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition text-sm">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-12">
    <div class="max-w-3xl mx-auto px-4 text-center animate-fade-up">
      <div class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-semibold mb-3">🩸 <span data-i18n="ready_to_save">Ready to Save a Life?</span></div>
      <h1 class="text-4xl font-bold mb-2" data-i18n="donate_blood_form">Donate Blood</h1>
      <p class="text-lg opacity-90" data-i18n="donate_blood_desc">Fill in the form below so we can prepare your donation appointment and verify your eligibility.</p>
    </div>
  </section>

  <section class="py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6 animate-fade-up">

      <!-- Medical Details -->
      <div class="bg-white rounded-2xl shadow p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🩺</div>
          <h2 class="text-xl font-bold text-gray-900" data-i18n="medical_details">Medical Details</h2>
        </div>

        <!-- Blood Type -->
        <p class="text-sm font-semibold text-gray-700 mb-3" data-i18n="blood_type">Blood Type <span class="text-red-500">*</span></p>
        <div class="grid grid-cols-4 gap-3 mb-6">
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_ap" value="A+"/><label for="bt_ap" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">A+</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_an" value="A-"/><label for="bt_an" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">A-</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_bp" value="B+"/><label for="bt_bp" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">B+</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_bn" value="B-"/><label for="bt_bn" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">B-</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_abp" value="AB+"/><label for="bt_abp" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">AB+</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_abn" value="AB-"/><label for="bt_abn" class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">AB-</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_op"  value="O+"/><label for="bt_op"  class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">O+</label></div>
          <div class="blood-type-btn"><input type="radio" name="blood" id="bt_on"  value="O-"/><label for="bt_on"  class="block border-2 border-gray-200 rounded-xl py-4 text-center font-bold text-lg text-gray-700 cursor-pointer transition transform hover:scale-105 hover:border-red-400">O-</label></div>
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="weight_kg">Weight (kg) <span class="text-red-500">*</span></label>
            <input id="d_weight" type="number" min="50" placeholder="e.g. 65"
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
            <p class="text-xs text-gray-400 mt-1" data-i18n="min_50kg">Minimum 50 kg to donate</p>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="height_cm">Height (cm)</label>
            <input id="d_height" type="number" placeholder="e.g. 170"
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="age_field">Age <span class="text-red-500">*</span></label>
            <input id="d_age" type="number" min="18" max="65" placeholder="e.g. 28"
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
            <p class="text-xs text-gray-400 mt-1" data-i18n="must_18_65">Must be 18–65 years old</p>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="blood_pressure">Blood Pressure <span class="text-red-500">*</span></label>
            <select id="d_bp" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="">Select</option>
              <option value="normal" data-i18n="normal_bp">Normal (90/60 – 120/80)</option>
              <option value="high" data-i18n="high_bp">High (above 120/80)</option>
              <option value="low" data-i18n="low_bp">Low (below 90/60)</option>
              <option value="unknown" data-i18n="dont_know">I don't know</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="chronic_illness">Any Chronic Illness? <span class="text-red-500">*</span></label>
            <select id="d_illness" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="none" data-i18n="none">None</option>
              <option value="diabetes" data-i18n="diabetes">Diabetes</option>
              <option value="heart" data-i18n="heart_condition">Heart condition</option>
              <option value="hepatitis" data-i18n="hepatitis_bc">Hepatitis B/C</option>
              <option value="hiv" data-i18n="hiv_aids">HIV / AIDS</option>
              <option value="other" data-i18n="other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="on_medication">Currently on Medication? <span class="text-red-500">*</span></label>
            <select id="d_meds" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="no" data-i18n="no">No</option>
              <option value="yes" data-i18n="yes">Yes</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="smoker">Smoker?</label>
            <select id="d_smoker" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="no" data-i18n="no">No</option>
              <option value="yes" data-i18n="yes">Yes</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="recent_surgery_field">Had surgery in last 6 months?</label>
            <select id="d_surgery" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="no" data-i18n="no">No</option>
              <option value="yes" data-i18n="yes">Yes</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Donation Appointment -->
      <div class="bg-white rounded-2xl shadow p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📅</div>
          <h2 class="text-xl font-bold text-gray-900" data-i18n="donation_appointment">Donation Appointment</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="preferred_date">Preferred Date <span class="text-red-500">*</span></label>
            <input id="d_date" type="date"
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="preferred_time">Preferred Time <span class="text-red-500">*</span></label>
            <select id="d_time" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="" data-i18n="select_time_slot">Select time slot</option>
              <option>9:00 AM – 10:00 AM</option>
              <option>10:00 AM – 11:00 AM</option>
              <option>11:00 AM – 12:00 PM</option>
              <option>1:00 PM – 2:00 PM</option>
              <option>2:00 PM – 3:00 PM</option>
              <option>3:00 PM – 4:00 PM</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="preferred_hospital">Preferred Hospital / Donation Center <span class="text-red-500">*</span></label>
            <select id="d_hospital" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm bg-white">
              <option value="" data-i18n="select_hospital">Select hospital</option>
              <option>Aga Khan University Hospital, Karachi</option>
              <option>Mayo Hospital, Lahore</option>
              <option>PIMS Hospital, Islamabad</option>
              <option>Civil Hospital, Karachi</option>
              <option>Services Hospital, Lahore</option>
              <option>Nishtar Hospital, Multan</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="full_address">Full Address <span class="text-red-500">*</span></label>
            <input id="d_address" type="text" data-i18n-placeholder="enter_address" placeholder="Your current home address"
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
            <p class="text-xs text-gray-400 mt-1" data-i18n="address_help">So the hospital can contact you if needed</p>
          </div>
        </div>
      </div>

      <!-- Declaration -->
      <div class="bg-white rounded-2xl shadow p-8">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">✅</div>
          <h2 class="text-xl font-bold text-gray-900" data-i18n="pre_donation_declaration">Pre-Donation Declaration</h2>
        </div>
        <div class="space-y-3 bg-gray-50 rounded-xl p-5">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" id="decl1" class="accent-red-600 w-4 h-4 mt-0.5 flex-shrink-0" />
            <span class="text-sm text-gray-600" data-i18n="decl_good_health">I am currently in good health — no fever, cold, or active infection.</span>
          </label>
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" id="decl2" class="accent-red-600 w-4 h-4 mt-0.5 flex-shrink-0" />
            <span class="text-sm text-gray-600" data-i18n="decl_not_donated">I have not donated blood in the last 4 months (120 days).</span>
          </label>
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" id="decl3" class="accent-red-600 w-4 h-4 mt-0.5 flex-shrink-0" />
            <span class="text-sm text-gray-600" data-i18n="decl_no_medication">I am not taking blood-thinning medications (e.g. aspirin, warfarin).</span>
          </label>
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" id="decl4" class="accent-red-600 w-4 h-4 mt-0.5 flex-shrink-0" />
            <span class="text-sm text-gray-600" data-i18n="decl_truthful">All information I have provided is truthful and accurate.</span>
          </label>
        </div>

        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-4 mt-4 flex items-start gap-3">
          <span class="text-xl flex-shrink-0">⚠️</span>
          <p class="text-sm text-yellow-800" data-i18n="warning_hiv">If you have HIV, Hepatitis B/C, or any blood-borne illness, you are not eligible to donate. Providing false information may put patients at risk.</p>
        </div>

        <div class="flex gap-4 mt-6">
          <a href="donordashboard.php" class="border-2 border-gray-300 text-gray-600 px-8 py-4 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition text-center text-sm" data-i18n="cancel">Cancel</a>
          <button onclick="handleSubmit()" class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl transition transform hover:scale-105 text-sm" data-i18n="submit_donation_request">
            Submit Donation Request 🩸
          </button>
        </div>
      </div>

    </div>
  </section>

  <!-- Success Modal -->
  <div id="successModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl p-10 max-w-md mx-4 text-center">
      <div class="text-7xl mb-4">🎉</div>
      <h2 class="text-2xl font-bold text-gray-900 mb-2" data-i18n="donation_request_submitted">Donation Request Submitted!</h2>
      <p class="text-gray-600 text-sm mb-2" data-i18n="donation_request_desc">Your appointment request has been sent to the hospital.</p>
      <p class="text-gray-500 text-sm mb-6" data-i18n="donation_request_desc2">You will receive a confirmation call within 24 hours. After your donation, the admin will issue your receipt.</p>
      <div class="flex gap-3">
        <a href="donordashboard.php" class="flex-1 border-2 border-red-600 text-red-600 py-3 rounded-xl font-bold hover:bg-red-50 transition text-center text-sm" data-i18n="dashboard_link">Dashboard</a>
        <a href="profile.php"         class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white py-3 rounded-xl font-bold hover:shadow-lg transition text-center text-sm" data-i18n="my_profile">My Profile</a>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-12 mt-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-4 gap-8 mb-8">
        <div><h3 class="text-white font-bold text-lg mb-4">BloodLife</h3><p class="text-sm" data-i18n="save_lives_together">Connecting donors with those who need help.</p></div>
        <div>
          <h4 class="text-white font-bold mb-4" data-i18n="quick_links">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="index.php" class="hover:text-red-400 transition" data-i18n="home">Home</a></li>
            <li><a href="donor.php"   class="hover:text-red-400 transition" data-i18n="donors">Donors</a></li>
            <li><a href="hospital.php" class="hover:text-red-400 transition" data-i18n="hospitals">Hospitals</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4" data-i18n="contact">Contact</h4>
          <ul class="space-y-2 text-sm">
            <li>📧 info@bloodlife.com</li>
            <li>📱 1-800-BLOOD-999</li>
            <li>📍 123 Health Street, City</li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4" data-i18n="follow_us">Follow Us</h4>
          <div class="flex space-x-4 text-sm">
            <a href="#" class="hover:text-red-400 transition">Facebook</a>
            <a href="#" class="hover:text-red-400 transition">Twitter</a>
            <a href="#" class="hover:text-red-400 transition">Instagram</a>
          </div>
        </div>
      </div>
      <div class="border-t border-gray-700 pt-8 text-center text-sm">
        <p>&copy; BloodLife. <span data-i18n="all_rights_reserved">All rights reserved.</span></p>
      </div>
    </div>
  </footer>

  <script>
    function bloodlifeLogout() {
      localStorage.removeItem('bloodlife_logged_in');
      localStorage.removeItem('bloodlife_user_name');
      window.location.href = 'logout.php';
    }

    // Show username in navbar
    const name = localStorage.getItem('bloodlife_user_name');
    if (name) document.getElementById('navName').textContent = name;

    function handleSubmit() {
      const blood   = document.querySelector('input[name="blood"]:checked');
      const weight  = document.getElementById('d_weight').value;
      const age     = document.getElementById('d_age').value;
      const bp      = document.getElementById('d_bp').value;
      const date    = document.getElementById('d_date').value;
      const time    = document.getElementById('d_time').value;
      const hospital= document.getElementById('d_hospital').value;
      const address = document.getElementById('d_address').value;
      const allDecl = ['decl1','decl2','decl3','decl4'].every(id => document.getElementById(id).checked);

      if (!blood)    { alert('Please select your blood type.'); return; }
      if (!weight || weight < 50) { alert('Minimum weight is 50 kg to donate.'); return; }
      if (!age || age < 18 || age > 65) { alert('Age must be between 18 and 65.'); return; }
      if (!bp)       { alert('Please select your blood pressure.'); return; }
      if (!date)     { alert('Please select a preferred donation date.'); return; }
      if (!time)     { alert('Please select a time slot.'); return; }
      if (!hospital) { alert('Please select a hospital.'); return; }
      if (!address)  { alert('Please enter your address.'); return; }
      if (!allDecl)  { alert('Please confirm all declarations before submitting.'); return; }

      // Check illness disqualifiers
      const illness = document.getElementById('d_illness').value;
      if (['hiv','hepatitis'].includes(illness)) {
        alert('Unfortunately, donors with HIV or Hepatitis B/C are not eligible to donate blood. Thank you for your honesty.');
        return;
      }

      const modal = document.getElementById('successModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
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