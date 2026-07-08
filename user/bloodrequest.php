<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

$requests = [];
$totalRequests = 0;
$urgentToday = 0;

try {
    $result = $conn->query("
        SELECT r.*, bg.blood_gp_name
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        ORDER BY r.id DESC
    ");
    if ($result && $result->num_rows > 0) {
        $requests = $result->fetch_all(MYSQLI_ASSOC);
    }
    $totalRequests = $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0;
    $urgentToday = $conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Pending','Approved')")->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {
    // silent
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Blood Requests – BloodLife</title>
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
    html:not(.dark) body { background-color: #fdf2f8 !important; background-image: none !important; }
    html:not(.dark) .bg-gray-50 { background-color: #fdf2f8 !important; }
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
<body class="bg-gradient-to-b from-pink-50 to-pink-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

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
      <div class="inline-block bg-white/20 text-white px-4 py-2 rounded-full text-sm font-semibold mb-4">🚨 <span data-i18n="live_requests">Live Requests</span></div>
      <h1 class="text-5xl font-bold mb-4" data-i18n="blood_requests_title">Blood Requests</h1>
      <p class="text-xl opacity-90 max-w-2xl mx-auto">Patients urgently need your help. Review open requests and respond — your one donation can save a life.</p>

      <div class="grid grid-cols-3 gap-6 mt-12 max-w-lg mx-auto text-center">
        <div><p class="text-4xl font-bold"><?= $totalRequests ?>+</p><p class="text-sm opacity-80">Total Requests</p></div>
        <div><p class="text-4xl font-bold"><?= $urgentToday ?>+</p><p class="text-sm opacity-80">Active Today</p></div>
        
      </div>
    </div>
  </section>

  <!-- New Request CTA Strip -->
  <section class="bg-red-50 border-b border-red-100 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-gray-700 font-medium text-lg">Need blood urgently? Submit a request and reach hundreds of donors instantly.</p>
      <a href="requestblood.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105 whitespace-nowrap">
        + New Blood Request
      </a>
    </div>
  </section>

  <!-- Filter Bar -->
  <section class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-2xl shadow p-6 flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
          <input type="text" placeholder="Search by patient name or hospital…" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type Needed</label>
          <select class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
            <option value="">All Types</option>
            <option>A+</option><option>A-</option>
            <option>B+</option><option>B-</option>
            <option>AB+</option><option>AB-</option>
            <option>O+</option><option>O-</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Urgency</label>
          <select class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
            <option>All</option>
            <option>Critical</option>
            <option>Urgent</option>
            <option>Normal</option>
          </select>
        </div>
        <button class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transition">
          Filter
        </button>
      </div>
    </div>
  </section>

  <!-- Request Cards -->
  <section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

      <?php if (count($requests) > 0): ?>
        <?php foreach ($requests as $req): ?>
          <?php
            $statusColors = [
              'Pending'   => ['border' => 'border-orange-400', 'bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'badge' => 'bg-orange-400', 'label' => '🟠 Pending'],
              'Approved'  => ['border' => 'border-blue-400', 'bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'badge' => 'bg-blue-500', 'label' => '🔵 Approved'],
              'Completed' => ['border' => 'border-green-400', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'badge' => 'bg-green-500', 'label' => '🟢 Completed'],
              'Rejected'  => ['border' => 'border-gray-400', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'badge' => 'bg-gray-500', 'label' => '⚪ Rejected'],
            ];
            $sc = $statusColors[$req['status']] ?? ['border' => 'border-gray-300', 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'badge' => 'bg-gray-400', 'label' => $req['status']];
          ?>
      <div class="bg-white rounded-2xl shadow hover:shadow-xl transition p-6 border-l-4 <?= $sc['border'] ?> flex flex-col sm:flex-row sm:items-center gap-6">
        <div class="flex-shrink-0 flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br <?= $sc['bg'] ?>">
          <span class="text-3xl font-bold <?= $sc['text'] ?>"><?= htmlspecialchars($req['blood_gp_name'] ?? 'N/A') ?></span>
        </div>
        <div class="flex-1">
          <div class="flex flex-wrap gap-2 mb-1">
            <span class="<?= $sc['badge'] ?> text-white text-xs font-bold px-3 py-1 rounded-full"><?= $sc['label'] ?></span>
            <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-3 py-1 rounded-full"><?= htmlspecialchars($req['hospital'] ?? '-') ?></span>
          </div>
          <h3 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($req['blood_gp_name'] ?? '?') ?> Blood Needed</h3>
          <p class="text-gray-500 text-sm"><?= htmlspecialchars($req['units'] ?? '?') ?> unit(s) of <?= htmlspecialchars($req['blood_gp_name'] ?? '?') ?> blood. Required by <?= htmlspecialchars($req['required_date'] ?? 'N/A') ?>.</p>
        </div>
        <div class="flex flex-col gap-2 sm:items-end">
          <a href="requester.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-xl font-bold hover:shadow-lg transition text-center text-sm">Respond</a>
        </div>
      </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="bg-white rounded-2xl shadow p-12 text-center">
          <p class="text-gray-500 text-lg">No blood requests found.</p>
        </div>
      <?php endif; ?>

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
            <li><a href="#" class="hover:text-red-400 transition">Donors</a></li>
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