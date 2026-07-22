<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$userId = $_SESSION['user_id'] ?? 0;

// Role-based access control
$userRole = $_SESSION['user_role'] ?? '';
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Redirect Admin to admin dashboard
if ($userRole === 'Admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$greeting = 'Good morning';

$donorData = [];
$donationCount = 0;
$donations = [];
$urgentRequests = [];
if ($isLoggedIn) {
    // Donor info
    $stmt = $conn->prepare("SELECT d.id AS donor_id, d.user_id, u.username, u.email, d.address, d.blood_groups AS blood_group_name
                            FROM donor d
                            JOIN users u ON u.id = d.user_id
                            WHERE d.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $donorData = $result->fetch_assoc();
    $stmt->close();

    // Donation count & history
    if (!empty($donorData['donor_id'])) {
        $did = $donorData['donor_id'];
        $stmt2 = $conn->prepare("SELECT dh.*, bg.blood_gp_name
                                 FROM donation_history dh
                                 LEFT JOIN blood_groups bg ON bg.id = dh.blood_groups_id
                                 WHERE dh.donor_id = ?
                                 ORDER BY dh.donation_date DESC");
        $stmt2->bind_param("i", $did);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $donations = $result2->fetch_all(MYSQLI_ASSOC);
        $donationCount = count($donations);
        $stmt2->close();
    }

    // Urgent requests
    $urgent = $conn->query("SELECT r.id, r.units, r.hospital, r.required_date, r.status,
                                   bg.blood_gp_name
                            FROM blood_request r
                            LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                            WHERE r.status IN ('Pending','Approved')
                            ORDER BY r.required_date DESC LIMIT 5");
    if ($urgent) $urgentRequests = $urgent->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard – BloodLife</title>
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
    html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
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
            <p class="text-xs text-gray-500" data-i18n="save_lives_together">Save Lives Together</p>
          </div>
        </div>
        <div class="hidden md:flex items-center space-x-8">
          <a href="index.php"    class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php"      class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>          
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
<button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <div class="relative" id="userMenu">
            <div class="flex items-center gap-2 cursor-pointer" onclick="toggleUserDropdown()">
              <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700"><?= strtoupper(substr($username, 0, 1)) ?></div>
              <span class="font-medium text-gray-700"><?= $username ?></span>
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
              <div class="p-4 border-b border-gray-100">
                <p class="font-semibold text-gray-800"><?= $username ?></p>
                <p class="text-sm text-gray-500">Logged in</p>
              </div>
              <div class="p-2">
                <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                  <span>👤</span> <span data-i18n="profile">Profile</span>
                </a>
                <a href="#" onclick="bloodlifeLogout(); return false;" class="flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                  <span>🚪</span> <span data-i18n="logout">Logout</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- Welcome Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 animate-fade-up">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <p class="text-red-200 text-sm font-semibold mb-1"><?= date('l, j F Y') ?></p>
          <h1 class="text-4xl font-bold mb-1"><?= $greeting ?>, <?= $username ?>! 👋</h1>
          <p class="text-lg opacity-90"><span data-i18n="you_have">You have</span> <span class="font-bold text-yellow-300">2 urgent requests</span> <span data-i18n="urgent_requests_matching">matching your blood type nearby.</span></p>
        </div>
        <a href="requestblood.php" class="bg-white text-red-600 px-6 py-3 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105 whitespace-nowrap">
          <span data-i18n="submit_request_btn">+ Submit Request</span>
        </a>
      </div>
    </div>
  </section>

  <!-- Eligibility Banner -->
  <section class="bg-green-50 border-b border-green-200 py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <span class="text-2xl">✅</span>
        <p class="text-green-800 font-semibold" data-i18n="eligible_donate_today">You are eligible to donate blood today! It's been 60+ days since your last donation.</p>
      </div>
      <a href="bloodrequest.php" class="bg-green-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-green-700 transition whitespace-nowrap text-sm" data-i18n="find_a_request">Find a Request</a>
    </div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 animate-fade-up">
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">🩸</div>
        <h3 class="text-3xl font-bold text-red-600"><?= $donationCount ?></h3>
        <p class="text-gray-500 text-sm mt-1" data-i18n="total_donations">Total Donations</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">❤️</div>
        <h3 class="text-3xl font-bold text-red-600"><?= $donationCount * 3 ?></h3>
        <p class="text-gray-500 text-sm mt-1" data-i18n="lives_impacted">Lives Impacted</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">🏆</div>
        <h3 class="text-3xl font-bold text-red-600"><?= min(4, $donationCount) ?></h3>
        <p class="text-gray-500 text-sm mt-1" data-i18n="badges_earned">Badges Earned</p>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 text-center">
        <div class="text-4xl mb-2">📅</div>
        <h3 class="text-3xl font-bold text-red-600"><?= $donationCount > 0 ? 'Active' : 'N/A' ?></h3>
        <p class="text-gray-500 text-sm mt-1" data-i18n="donor_status">Donor Status</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8">

      <!-- Left Column -->
      <div class="lg:col-span-2 space-y-8">

        <!-- Urgent Requests Near You -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🚨</div>
              <h2 class="text-xl font-bold text-gray-900" data-i18n="urgent_requests_near_you">Urgent Requests Near You</h2>
            </div>
            <a href="bloodrequest.php" class="text-red-600 text-sm font-semibold hover:underline" data-i18n="view_all">View all →</a>
          </div>
          <div class="space-y-4">
            <?php if (count($urgentRequests) > 0): ?>
              <?php foreach ($urgentRequests as $ur): ?>
            <div class="border-2 border-red-100 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:border-red-400 transition">
              <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-red-100 to-red-200 flex items-center justify-center font-bold text-red-700 text-xl"><?= htmlspecialchars($ur['blood_gp_name'] ?? 'N/A') ?></div>
              <div class="flex-1">
                <div class="flex flex-wrap gap-2 mb-1">
                  <span class="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">🔴 <?= htmlspecialchars($ur['status']) ?></span>
                  <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?= htmlspecialchars($ur['hospital']) ?></span>
                </div>
                <p class="font-semibold text-gray-800"><?= htmlspecialchars($ur['blood_gp_name'] ?? '?') ?> blood needed — <?= htmlspecialchars($ur['units']) ?> unit(s)</p>
                <p class="text-xs text-gray-400 mt-0.5">Required by <?= date('M j, Y', strtotime($ur['required_date'])) ?></p>
              </div>
              <a href="bloodrequest.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-xl font-bold hover:shadow-lg transition text-sm whitespace-nowrap" data-i18n="respond">Respond</a>
            </div>
              <?php endforeach; ?>
            <?php else: ?>
            <div class="border-2 border-gray-100 rounded-xl p-8 text-center">
              <p class="text-gray-500" data-i18n="no_urgent_requests">No urgent requests at this time.</p>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Donation History -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📋</div>
              <h2 class="text-xl font-bold text-gray-900" data-i18n="donation_history">Donation History</h2>
            </div>
            <a href="profile.php" class="text-red-600 text-sm font-semibold hover:underline" data-i18n="view_all">View all →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100">
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="date">Date</th>
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="hospital_col">Hospital</th>
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="units">Units</th>
                  <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="status">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <?php if (count($donations) > 0): ?>
                  <?php foreach ($donations as $d): ?>
                <tr class="hover:bg-gray-50">
                  <td class="py-3 text-gray-700 font-medium"><?= date('M j, Y', strtotime($d['donation_date'])) ?></td>
                  <td class="py-3 text-gray-600"><?= htmlspecialchars($d['blood_gp_name'] ?? '-') ?></td>
                  <td class="py-3 text-gray-600"><?= htmlspecialchars($d['units'] ?? '1') ?> unit(s)</td>
                  <td class="py-3"><span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ <?= htmlspecialchars($d['status'] ?? 'Completed') ?></span></td>
                </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                <tr>
                  <td colspan="4" class="py-6 text-center text-gray-400" data-i18n="no_donations_yet">No donations yet.</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right Column -->
      <div class="space-y-6">

        <!-- Profile Card -->
        <div class="bg-white rounded-2xl shadow p-6 text-center animate-fade-up">
          <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center text-4xl mx-auto mb-3">👤</div>
          <h3 class="font-bold text-gray-900 text-xl"><?= htmlspecialchars($donorData['username'] ?? $username ?: 'Donor') ?></h3>
          <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($donorData['address'] ?? 'Location not set') ?></p>
          <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-5 py-1.5 rounded-full text-lg mb-4"><?= htmlspecialchars($donorData['blood_group_name'] ?? 'N/A') ?></span>
          <div class="bg-green-50 border border-green-200 rounded-xl py-2 px-3 mb-4">
            <p class="text-green-700 text-sm font-semibold">✅ <?= $donationCount > 0 ? '<span data-i18n="active_donor">Active Donor</span>' : '<span data-i18n="ready_to_donate">Ready to Donate</span>' ?></p>
          </div>
          <a href="profile.php" class="w-full border-2 border-red-600 text-red-600 py-2 rounded-xl font-semibold hover:bg-red-50 transition block text-sm" data-i18n="edit_profile">Edit Profile</a>
        </div>

        <!-- Badges -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🏆</div>
            <h2 class="text-lg font-bold text-gray-900" data-i18n="your_badges">Your Badges</h2>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🥇</div>
              <p class="text-xs font-bold text-yellow-700" data-i18n="first_donation">First Donation</p>
            </div>
            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🔥</div>
              <p class="text-xs font-bold text-red-700" data-i18n="five_donations">5 Donations</p>
            </div>
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">⚡</div>
              <p class="text-xs font-bold text-blue-700" data-i18n="quick_responder">Quick Responder</p>
            </div>
            <div class="bg-purple-50 border-2 border-purple-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🌟</div>
              <p class="text-xs font-bold text-purple-700" data-i18n="life_saver">Life Saver</p>
            </div>
          </div>
          <div class="mt-3 bg-gray-50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500"><span data-i18n="next_badge_prefix">Next badge:</span> <span class="font-bold text-red-600" data-i18n="ten_donations_hero">10 Donations Hero</span> — 3 <span data-i18n="more_to_go_suffix">more to go!</span></p>
            <div class="mt-2 bg-gray-200 rounded-full h-2">
              <div class="bg-red-500 h-2 rounded-full" style="width: 70%"></div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-2xl shadow p-6 animate-fade-up">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">⚡</div>
            <h2 class="text-lg font-bold text-gray-900" data-i18n="quick_actions">Quick Actions</h2>
          </div>
          <div class="space-y-3">
            <a href="bloodrequest.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">🚨</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="view_urgent_requests">View Urgent Requests</span>
            </a>
            <a href="requestblood.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">📋</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="submit_blood_request_link">Submit Blood Request</span>
            </a>
            <a href="hospital.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">🏥</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="find_nearby_hospitals">Find Nearby Hospitals</span>
            </a>
            <a href="profile.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 transition border-2 border-gray-100 hover:border-red-200">
              <span class="text-xl">👤</span>
              <span class="font-semibold text-gray-700 text-sm" data-i18n="update_my_profile">Update My Profile</span>
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-white text-gray-600 py-12 border-t border-gray-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-4 gap-8 mb-8">
        <div><h3 class="text-red-600 font-bold text-lg mb-4">BloodLife</h3><p class="text-sm">Connecting donors with those who need help. Save lives today.</p></div>
        <div>
          <h4 class="text-red-600 font-bold mb-4" data-i18n="quick_links">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="index.php" class="hover:text-red-400 transition">Home</a></li>
            <li><a href="donor.php" class="hover:text-red-400 transition">Donors</a></li>
            <li><a href="hospital.php" class="hover:text-red-400 transition">Hospitals</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-red-600 font-bold mb-4" data-i18n="contact">Contact</h4>
          <ul class="space-y-2 text-sm">
            <li>📧 info@bloodlife.com</li>
            <li>📱 1-800-BLOOD-999</li>
            <li>📍 123 Health Street, City</li>
          </ul>
        </div>
        <div>
          <h4 class="text-red-600 font-bold mb-4" data-i18n="follow_us">Follow Us</h4>
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
    function toggleUserDropdown() {
      document.getElementById('userDropdown').classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
      const menu = document.getElementById('userMenu');
      const dropdown = document.getElementById('userDropdown');
      if (menu && dropdown && !menu.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
    });

    function bloodlifeLogout() {
      if (!confirm('Are you sure you want to logout?')) return;
      localStorage.removeItem('bloodlife_logged_in');
      localStorage.removeItem('bloodlife_user_name');
      window.location.href = 'logout.php';
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