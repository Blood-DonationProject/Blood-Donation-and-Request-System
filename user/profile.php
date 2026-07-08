<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$userId = $_SESSION['user_id'] ?? 0;

$userData = [];
if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT id, username, email, phone, address, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile – BloodLife</title>
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
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
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
    html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
    html.dark .bg-yellow-50 { background-color: rgba(234,179,8,0.15) !important; }
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
          <a href="donordashboard.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="dashboard">Dashboard</a>
          <a href="donor.php"      class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="hospital.php"    class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="hospitals">Hospitals</a>
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
<button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="profile.php" class="flex items-center gap-2 text-red-600">
            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">A</div>
            <span class="font-semibold">Ahmed</span>
          </a>
          <a href="#" onclick="bloodlifeLogout(); return false;" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition text-sm" data-i18n="logout">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Cover Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 h-40 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full"></div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Profile Header -->
    <div class="relative -mt-16 mb-8 animate-fade-up">
      <div class="bg-white rounded-2xl shadow p-6 sm:p-8 flex flex-col sm:flex-row items-center sm:items-end gap-6">
        <div class="w-28 h-28 bg-red-100 rounded-full border-4 border-white shadow-lg flex items-center justify-center text-5xl flex-shrink-0 -mt-2">👤</div>
        <div class="flex-1 text-center sm:text-left">
          <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 justify-center sm:justify-start">
            <h1 class="text-2xl font-bold text-gray-900">Ahmed Raza</h1>
            <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-0.5 rounded-full text-sm w-fit mx-auto sm:mx-0">A+</span>
          </div>
          <p class="text-gray-500 text-sm mt-1">📍 <?= htmlspecialchars($userData['address'] ?? 'Not set') ?> &nbsp;·&nbsp; Member since <?= $userData['created_at'] ? date('F Y', strtotime($userData['created_at'])) : 'N/A' ?></p>
        </div>
        <button onclick="toggleEdit()" id="editToggleBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition whitespace-nowrap">
          ✏️ Edit Profile
        </button>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8 pb-16">

      <!-- Left: Stats + Badges -->
      <div class="space-y-6 animate-fade-up">

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-gray-900 mb-4">Donation Stats</h2>
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600">7</p>
              <p class="text-xs text-gray-500 mt-1">Donations</p>
            </div>
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600">21</p>
              <p class="text-xs text-gray-500 mt-1">Lives Saved</p>
            </div>
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600">62</p>
              <p class="text-xs text-gray-500 mt-1">Days Since Last</p>
            </div>
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600">3.5L</p>
              <p class="text-xs text-gray-500 mt-1">Blood Donated</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-gray-900 mb-4">Badges Earned</h2>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🥇</div>
              <p class="text-xs font-bold text-yellow-700">First Donation</p>
            </div>
            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🔥</div>
              <p class="text-xs font-bold text-red-700">5 Donations</p>
            </div>
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">⚡</div>
              <p class="text-xs font-bold text-blue-700">Quick Responder</p>
            </div>
            <div class="bg-purple-50 border-2 border-purple-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🌟</div>
              <p class="text-xs font-bold text-purple-700">Life Saver</p>
            </div>
            <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-3 text-center opacity-40">
              <div class="text-3xl mb-1">🔒</div>
              <p class="text-xs font-bold text-gray-500">10 Donations</p>
            </div>
            <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-3 text-center opacity-40">
              <div class="text-3xl mb-1">🔒</div>
              <p class="text-xs font-bold text-gray-500">1 Year Member</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-gray-900 mb-3">Account</h2>
          <div class="space-y-2">
            <button class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 transition text-sm font-medium text-gray-700 flex items-center justify-between">
              Change Password <span>›</span>
            </button>
            <button class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 transition text-sm font-medium text-gray-700 flex items-center justify-between">
              Notification Settings <span>›</span>
            </button>
            <button class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 transition text-sm font-medium text-red-600 flex items-center justify-between">
              Delete Account <span>›</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Tabs -->
      <div class="lg:col-span-2 animate-fade-up">
        <div class="bg-white rounded-2xl shadow overflow-hidden">

          <!-- Tabs -->
          <div class="flex border-b border-gray-100 overflow-x-auto">
            <button onclick="setTab('info')" id="tabbtn-info" class="flex-1 py-4 font-semibold text-sm text-red-600 border-b-2 border-red-600 transition whitespace-nowrap px-2">Personal Info</button>
            <button onclick="setTab('history')" id="tabbtn-history" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2">Donation History</button>
            <button onclick="setTab('health')" id="tabbtn-health" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2">Health Info</button>
            <button onclick="setTab('receipts')" id="tabbtn-receipts" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2">🧾 Receipts</button>
          </div>

          <div class="p-6 sm:p-8">

            <!-- Personal Info Tab -->
            <div id="tab-info" class="tab-panel active space-y-5">
              <div class="grid sm:grid-cols-2 gap-5">
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                  <input type="text" value="<?= htmlspecialchars($userData['username'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Email Address</label>
                  <input type="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                  <input type="tel" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Member Since</label>
                  <input type="text" value="<?= $userData['created_at'] ? date('F j, Y', strtotime($userData['created_at'])) : '' ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                  <input type="text" value="<?= htmlspecialchars($userData['address'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                </div>
              </div>
            </div>

            <!-- Donation History Tab -->
            <div id="tab-history" class="tab-panel">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-gray-100">
                      <th class="text-left text-gray-500 font-semibold pb-3">Date</th>
                      <th class="text-left text-gray-500 font-semibold pb-3">Hospital</th>
                      <th class="text-left text-gray-500 font-semibold pb-3">Units</th>
                      <th class="text-left text-gray-500 font-semibold pb-3">Status</th>
                      <th class="text-left text-gray-500 font-semibold pb-3">Certificate</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50">
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium">Apr 28, 2026</td>
                      <td class="py-3 text-gray-600">Aga Khan Hospital</td>
                      <td class="py-3 text-gray-600">1 unit</td>
                      <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Completed</span></td>
                      <td class="py-3"><a href="#" class="text-red-600 font-semibold hover:underline">Download</a></td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium">Jan 10, 2026</td>
                      <td class="py-3 text-gray-600">Civil Hospital</td>
                      <td class="py-3 text-gray-600">1 unit</td>
                      <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Completed</span></td>
                      <td class="py-3"><a href="#" class="text-red-600 font-semibold hover:underline">Download</a></td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium">Sep 3, 2025</td>
                      <td class="py-3 text-gray-600">Aga Khan Hospital</td>
                      <td class="py-3 text-gray-600">1 unit</td>
                      <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Completed</span></td>
                      <td class="py-3"><a href="#" class="text-red-600 font-semibold hover:underline">Download</a></td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium">May 20, 2025</td>
                      <td class="py-3 text-gray-600">Mayo Hospital</td>
                      <td class="py-3 text-gray-600">1 unit</td>
                      <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Completed</span></td>
                      <td class="py-3"><a href="#" class="text-red-600 font-semibold hover:underline">Download</a></td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                      <td class="py-3 text-gray-700 font-medium">Dec 2, 2024</td>
                      <td class="py-3 text-gray-600">Services Hospital</td>
                      <td class="py-3 text-gray-600">1 unit</td>
                      <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Completed</span></td>
                      <td class="py-3"><a href="#" class="text-red-600 font-semibold hover:underline">Download</a></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Health Info Tab -->
            <div id="tab-health" class="tab-panel space-y-5">
              <div class="grid sm:grid-cols-2 gap-5">
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type</label>
                  <input type="text" value="A+" disabled class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Weight (kg)</label>
                  <input type="text" value="68" disabled class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600" />
                </div>
              </div>
              <div class="bg-red-50 border-2 border-red-100 rounded-xl p-5">
                <h3 class="font-bold text-red-700 mb-2 flex items-center gap-2">⚠️ Medical Conditions</h3>
                <p class="text-sm text-gray-600">None reported. Please update this section if your health status changes, as it affects your donation eligibility.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-5">
                <h3 class="font-bold text-gray-800 mb-2">Eligibility Checklist</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                  <li class="flex items-center gap-2">✅ No fever or infection in past 2 weeks</li>
                  <li class="flex items-center gap-2">✅ Last donation over 4 months ago</li>
                  <li class="flex items-center gap-2">✅ Not on blood-thinning medication</li>
                  <li class="flex items-center gap-2">✅ Weight above minimum requirement</li>
                </ul>
              </div>
            </div>

            <!-- Receipts Tab -->
            <div id="tab-receipts" class="tab-panel space-y-5">

              <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-gray-500">Receipts issued to you by BloodLife admins after each donation.</p>
              </div>

              <!-- Receipt Card 1 -->
              <div class="border-2 border-gray-100 hover:border-red-200 rounded-2xl p-5 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧾</div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-gray-900">Receipt #BL-2026-4821</span>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ Verified</span>
                      </div>
                      <p class="text-sm text-gray-500">📅 Donated: <span class="font-semibold text-gray-700">April 28, 2026</span></p>
                      <p class="text-sm text-gray-500">🏥 Hospital: <span class="font-semibold text-gray-700">Aga Khan University Hospital, Karachi</span></p>
                    </div>
                  </div>
                  <button onclick="showReceipt(0)" class="border-2 border-red-600 text-red-600 px-5 py-2 rounded-xl font-semibold hover:bg-red-50 transition text-sm whitespace-nowrap">View Receipt</button>
                </div>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Blood Type</p>
                    <p class="font-bold text-red-600">A+</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Units</p>
                    <p class="font-bold text-gray-700">1 unit</p>
                  </div>
                  <div class="bg-green-50 rounded-xl p-2">
                    <p class="text-green-600 mb-0.5">Re-donate From</p>
                    <p class="font-bold text-green-700">Aug 26, 2026</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Issued By</p>
                    <p class="font-bold text-gray-700">Dr. Kamran</p>
                  </div>
                </div>
                <div class="mt-3 bg-gray-50 rounded-xl p-3">
                  <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Remark:</span> Donor was in excellent health. No complications observed during donation. Vitals stable throughout.</p>
                </div>
              </div>

              <!-- Receipt Card 2 -->
              <div class="border-2 border-gray-100 hover:border-red-200 rounded-2xl p-5 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧾</div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-gray-900">Receipt #BL-2026-1193</span>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ Verified</span>
                      </div>
                      <p class="text-sm text-gray-500">📅 Donated: <span class="font-semibold text-gray-700">January 10, 2026</span></p>
                      <p class="text-sm text-gray-500">🏥 Hospital: <span class="font-semibold text-gray-700">Civil Hospital, Karachi</span></p>
                    </div>
                  </div>
                  <button onclick="showReceipt(1)" class="border-2 border-red-600 text-red-600 px-5 py-2 rounded-xl font-semibold hover:bg-red-50 transition text-sm whitespace-nowrap">View Receipt</button>
                </div>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Blood Type</p>
                    <p class="font-bold text-red-600">A+</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Units</p>
                    <p class="font-bold text-gray-700">1 unit</p>
                  </div>
                  <div class="bg-green-50 rounded-xl p-2">
                    <p class="text-green-600 mb-0.5">Re-donate From</p>
                    <p class="font-bold text-green-700">May 10, 2026</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Issued By</p>
                    <p class="font-bold text-gray-700">Dr. Saira</p>
                  </div>
                </div>
                <div class="mt-3 bg-gray-50 rounded-xl p-3">
                  <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Remark:</span> Successful donation. Donor advised to rest and stay hydrated for 24 hours.</p>
                </div>
              </div>

              <!-- Receipt Card 3 -->
              <div class="border-2 border-gray-100 hover:border-red-200 rounded-2xl p-5 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧾</div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-gray-900">Receipt #BL-2025-7734</span>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ Verified</span>
                      </div>
                      <p class="text-sm text-gray-500">📅 Donated: <span class="font-semibold text-gray-700">September 3, 2025</span></p>
                      <p class="text-sm text-gray-500">🏥 Hospital: <span class="font-semibold text-gray-700">Aga Khan University Hospital, Karachi</span></p>
                    </div>
                  </div>
                  <button onclick="showReceipt(2)" class="border-2 border-red-600 text-red-600 px-5 py-2 rounded-xl font-semibold hover:bg-red-50 transition text-sm whitespace-nowrap">View Receipt</button>
                </div>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Blood Type</p>
                    <p class="font-bold text-red-600">A+</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Units</p>
                    <p class="font-bold text-gray-700">1 unit</p>
                  </div>
                  <div class="bg-green-50 rounded-xl p-2">
                    <p class="text-green-600 mb-0.5">Re-donate From</p>
                    <p class="font-bold text-green-700">Jan 1, 2026</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5">Issued By</p>
                    <p class="font-bold text-gray-700">Dr. Kamran</p>
                  </div>
                </div>
                <div class="mt-3 bg-gray-50 rounded-xl p-3">
                  <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Remark:</span> No issues reported. Donor is a regular contributor and eligible for the 5-donation badge.</p>
                </div>
              </div>

            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Receipt Modal -->
  <div id="receiptModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden">

      <!-- Modal Header -->
      <div class="bg-gradient-to-r from-red-600 to-red-800 text-white px-8 py-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-3xl bg-white/20 p-2 rounded-full">🩸</span>
            <div>
              <h2 class="font-bold text-xl">BloodLife</h2>
              <p class="text-red-200 text-xs">Official Donation Receipt</p>
            </div>
          </div>
          <div class="text-right">
            <p id="modal_receipt_no" class="font-bold text-lg tracking-wider">BL-0000-0000</p>
            <p id="modal_issued_on" class="text-red-200 text-xs">Issued: —</p>
          </div>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="px-8 py-6 space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">DONOR NAME</p>
            <p class="font-bold text-gray-900">Ahmed Raza</p>
          </div>
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">BLOOD TYPE</p>
            <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-0.5 rounded-full">A+</span>
          </div>
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">DONATION DATE</p>
            <p id="modal_donate_date" class="font-bold text-gray-900">—</p>
          </div>
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">UNITS DONATED</p>
            <p class="font-bold text-gray-900">1 unit</p>
          </div>
          <div class="col-span-2">
            <p class="text-gray-400 text-xs font-semibold mb-1">HOSPITAL</p>
            <p id="modal_hospital" class="font-bold text-gray-900">—</p>
          </div>
          <div class="col-span-2 bg-green-50 border-2 border-green-200 rounded-xl p-3">
            <p class="text-green-600 text-xs font-semibold mb-1">🔄 RE-DONATION ELIGIBLE FROM</p>
            <p id="modal_redonate" class="font-bold text-green-700 text-lg">—</p>
          </div>
          <div class="col-span-2">
            <p class="text-gray-400 text-xs font-semibold mb-1">REMARKS</p>
            <p id="modal_remark" class="text-gray-600 text-sm bg-gray-50 rounded-xl p-3">—</p>
          </div>
          <div class="col-span-2 flex items-center justify-between pt-2 border-t border-gray-100">
            <div>
              <p class="text-gray-400 text-xs font-semibold mb-0.5">ISSUED BY</p>
              <p id="modal_admin" class="font-bold text-gray-700 text-sm">—</p>
            </div>
            <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Verified</span>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-8 pb-6 flex gap-3">
        <button onclick="window.print()" class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white py-3 rounded-xl font-bold hover:shadow-lg transition">🖨️ Print</button>
        <button onclick="closeReceiptModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition">Close</button>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-4 gap-8 mb-8">
        <div><h3 class="text-white font-bold text-lg mb-4">BloodLife</h3><p class="text-sm">Connecting donors with those who need help. Save lives today.</p></div>
        <div>
          <h4 class="text-white font-bold mb-4">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="index.php" class="hover:text-red-400 transition">Home</a></li>
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
        <p>&copy; 2024 BloodLife. All rights reserved. | Privacy Policy | Terms of Service</p>
      </div>
    </div>
  </footer>

  <script>
    function bloodlifeLogout() {
      if (!confirm('Are you sure you want to logout?')) return;
      localStorage.removeItem('bloodlife_logged_in');
      localStorage.removeItem('bloodlife_user_name');
      window.location.href = 'logout.php';
    }

    function setTab(tab) {
      ['info','history','health','receipts'].forEach(t => {
        document.getElementById('tab-' + t).classList.remove('active');
        const btn = document.getElementById('tabbtn-' + t);
        btn.classList.remove('text-red-600','border-b-2','border-red-600');
        btn.classList.add('text-gray-500');
      });
      document.getElementById('tab-' + tab).classList.add('active');
      const activeBtn = document.getElementById('tabbtn-' + tab);
      activeBtn.classList.add('text-red-600','border-b-2','border-red-600');
      activeBtn.classList.remove('text-gray-500');
    }

    const receipts = [
      {
        no: 'BL-2026-4821', issued: 'April 28, 2026',
        donateDate: 'April 28, 2026', hospital: 'Aga Khan University Hospital, Karachi',
        redonate: 'August 26, 2026', admin: 'Dr. Kamran',
        remark: 'Donor was in excellent health. No complications observed during donation. Vitals stable throughout.'
      },
      {
        no: 'BL-2026-1193', issued: 'January 10, 2026',
        donateDate: 'January 10, 2026', hospital: 'Civil Hospital, Karachi',
        redonate: 'May 10, 2026', admin: 'Dr. Saira',
        remark: 'Successful donation. Donor advised to rest and stay hydrated for 24 hours.'
      },
      {
        no: 'BL-2025-7734', issued: 'September 3, 2025',
        donateDate: 'September 3, 2025', hospital: 'Aga Khan University Hospital, Karachi',
        redonate: 'January 1, 2026', admin: 'Dr. Kamran',
        remark: 'No issues reported. Donor is a regular contributor and eligible for the 5-donation badge.'
      },
    ];

    function showReceipt(index) {
      const r = receipts[index];
      document.getElementById('modal_receipt_no').textContent  = '#' + r.no;
      document.getElementById('modal_issued_on').textContent   = 'Issued: ' + r.issued;
      document.getElementById('modal_donate_date').textContent = r.donateDate;
      document.getElementById('modal_hospital').textContent    = r.hospital;
      document.getElementById('modal_redonate').textContent    = r.redonate;
      document.getElementById('modal_admin').textContent       = r.admin;
      document.getElementById('modal_remark').textContent      = r.remark;
      const modal = document.getElementById('receiptModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeReceiptModal() {
      const modal = document.getElementById('receiptModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // Close modal on backdrop click
    document.getElementById('receiptModal').addEventListener('click', function(e) {
      if (e.target === this) closeReceiptModal();
    });

    let editing = false;
    function toggleEdit() {
      editing = !editing;
      document.querySelectorAll('.profile-input').forEach(el => {
        el.disabled = !editing;
        if (editing) {
          el.classList.remove('bg-gray-50','text-gray-600');
          el.classList.add('bg-white','text-gray-900','focus:outline-none','focus:border-red-500');
        } else {
          el.classList.add('bg-gray-50','text-gray-600');
          el.classList.remove('bg-white','text-gray-900');
        }
      });
      document.getElementById('saveBar').classList.toggle('hidden', !editing);
      document.getElementById('editToggleBtn').textContent = editing ? '✕ Cancel' : '✏️ Edit Profile';
    }

    function saveProfile() {
      toggleEdit();
      alert('Profile updated successfully!');
    }
  </script>

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