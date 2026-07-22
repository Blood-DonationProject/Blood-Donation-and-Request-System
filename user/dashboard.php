<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['user_role'] ?? '';
if ($role === 'Admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$username = htmlspecialchars($_SESSION['username'] ?? '');
$userIdForQuery = $_SESSION['user_id'] ?? 0;

$isAlreadyDonor = false;
$donorId = 0;
$donorBloodGroup = '';
$stmt = $conn->prepare("SELECT id, blood_groups FROM donor WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userIdForQuery);
$stmt->execute();
$donorResult = $stmt->get_result();
if ($donorResult && $donorResult->num_rows > 0) {
    $donorRow = $donorResult->fetch_assoc();
    $isAlreadyDonor = true;
    $donorId = $donorRow['id'];
    $donorBloodGroup = htmlspecialchars($donorRow['blood_groups'] ?? '');
}
$stmt->close();

$totalDonors = 0;
$totalRequests = 0;
$totalDonations = 0;
$totalUsers = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM donor");
if ($r) $totalDonors = $r->fetch_assoc()['c'] ?? 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM blood_request");
if ($r) $totalRequests = $r->fetch_assoc()['c'] ?? 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM donation_history");
if ($r) $totalDonations = $r->fetch_assoc()['c'] ?? 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($r) $totalUsers = $r->fetch_assoc()['c'] ?? 0;
$livesSaved = $totalDonations * 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - BloodLife</title>
  <script>(function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();</script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    @keyframes fadeInDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeInRight{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
    @keyframes pulse-ring{0%{transform:scale(.9);opacity:1}100%{transform:scale(1.4);opacity:0}}
    @keyframes heartbeat{0%,100%{transform:scale(1)}15%{transform:scale(1.15)}30%{transform:scale(1)}45%{transform:scale(1.1)}}
    .animate-fade-down{animation:fadeInDown .6s ease-out both}
    .animate-fade-up{animation:fadeInUp .6s ease-out both}
    .animate-fade-right{animation:fadeInRight .6s ease-out both}
    .float-anim{animation:float 3s ease-in-out infinite}
    .pulse-ring{animation:pulse-ring 2s ease-out infinite}
    .heartbeat{animation:heartbeat 1.5s ease-in-out infinite}
    .hero-bg{background:linear-gradient(135deg,#dc2626 0%,#991b1b 40%,#be123c 70%,#e11d48 100%);position:relative;overflow:hidden}
    .hero-bg::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle at 30% 50%,rgba(255,255,255,.08) 0%,transparent 50%),radial-gradient(circle at 70% 80%,rgba(255,255,255,.05) 0%,transparent 40%);animation:float 8s ease-in-out infinite}
    .section-pink{background:linear-gradient(180deg,#fff1f2 0%,#ffffff 100%)}
    .section-white{background:#ffffff}
    .card-hover{transition:all .3s cubic-bezier(.4,0,.2,1)}
    .card-hover:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(220,38,38,.15)}
    .faq-answer{max-height:0;overflow:hidden;transition:max-height .4s ease,padding .4s ease}
    .faq-answer.open{max-height:500px;padding-top:1rem;padding-bottom:.5rem}
    .faq-icon{transition:transform .3s ease}
    .faq-icon.rotated{transform:rotate(180deg)}
    .emergency-banner{background:linear-gradient(135deg,#dc2626 0%,#b91c1c 50%,#991b1b 100%);position:relative;overflow:hidden}
    .emergency-banner::before{content:'';position:absolute;top:-100%;left:-100%;width:300%;height:300%;background:repeating-linear-gradient(45deg,transparent,transparent 20px,rgba(255,255,255,.02) 20px,rgba(255,255,255,.02) 40px)}
    .testimonial-card{background:linear-gradient(135deg,#ffffff 0%,#fff1f2 100%)}
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body{background-color:#fff!important;background-image:none!important}
    html:not(.dark) .bg-gray-50{background-color:#fff!important}
    html:not(.dark) .bg-gray-100{background-color:#fff!important}
    html.dark body{background-color:#111827!important;background-image:none!important;color:#e5e7eb}
    html.dark nav.bg-white,html.dark nav.bg-white.shadow-lg{background-color:#1f2937!important}
    html.dark .bg-white{background-color:#1f2937!important}
    html.dark .text-gray-900,html.dark .text-gray-800{color:#f3f4f6!important}
    html.dark .text-gray-700{color:#d1d5db!important}
    html.dark .text-gray-600{color:#9ca3af!important}
    html.dark .text-gray-500{color:#9ca3af!important}
    html.dark input,html.dark select,html.dark textarea{background-color:#374151!important;border-color:#4b5563!important;color:#e5e7eb!important}
    html.dark label{color:#d1d5db!important}
    html.dark .bg-gray-50,html.dark .bg-gray-100{background-color:#374151!important}
    html.dark .border-gray-200{border-color:#4b5563!important}
    html.dark .border-t{border-color:#374151!important}
    html.dark .bg-red-50{background-color:rgba(220,38,38,.15)!important}
    html.dark .bg-pink-50{background-color:rgba(236,72,153,.1)!important}
    html.dark .bg-green-50{background-color:rgba(34,197,94,.15)!important}
    html.dark tbody tr{border-color:#374151!important}
    html.dark tbody tr:hover{background-color:#374151!important}
    html.dark .section-pink{background:linear-gradient(180deg,rgba(236,72,153,.08) 0%,#1f2937 100%)!important}
    html.dark .testimonial-card{background:linear-gradient(135deg,#1f2937 0%,rgba(220,38,38,.08) 100%)!important}
  </style>
</head>
<body class="bg-white min-h-screen" style="font-family:'Pyidaungsu',Noto Sans Myanmar,sans-serif">

  <!-- NAVBAR (same as other pages) -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <a href="donordashboard.php" class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩹</span>
          <div><h1 class="font-bold text-xl text-red-700">BloodLife</h1><p class="text-xs text-gray-500" data-i18n="save_lives_together">Save Lives Together</p></div>
        </a>
        <div class="hidden md:flex items-center space-x-8">
          <a href="index.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
          <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <div class="relative" id="userMenu">
            <div class="flex items-center gap-2 cursor-pointer" onclick="toggleUserDropdown()">
              <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700"><?= strtoupper(substr($username, 0, 1)) ?></div>
              <span class="font-medium text-gray-700"><?= $username ?></span>
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
              <div class="p-4 border-b border-gray-100"><p class="font-semibold text-gray-800"><?= $username ?></p><p class="text-sm text-gray-500">Logged in</p></div>
              <div class="p-2">
                <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"><i class="fas fa-user"></i> <span data-i18n="profile">Profile</span></a>
                <a href="donordashboard.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition"><i class="fas fa-gauge-high"></i> <span>Dashboard</span></a>
                <a href="#" onclick="bloodlifeLogout(); return false;" class="flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition"><i class="fas fa-right-from-bracket"></i> <span data-i18n="logout">Logout</span></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- 1. HERO BANNER -->
  <section class="hero-bg text-white py-16 sm:py-24 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      <div class="grid md:grid-cols-2 gap-12 items-center">
        <div class="animate-fade-up">
          <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white px-5 py-2 rounded-full text-sm font-semibold mb-6">
            <i class="fas fa-heart-pulse"></i> <span>Welcome, <?= $username ?></span>
          </div>
          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6">Become a<br><span class="text-pink-200">Blood Donor</span></h1>
          <p class="text-lg sm:text-xl text-red-100 mb-8 leading-relaxed max-w-lg">Every drop of blood is a gift of life. Your donation can save someone's life today.</p>
          <?php if ($isAlreadyDonor): ?>
            <div class="bg-white/15 backdrop-blur-sm rounded-2xl p-6 border border-white/20 mb-6">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center"><i class="fas fa-check text-white"></i></div>
                <p class="font-bold text-white text-lg">You are already registered as a donor.</p>
              </div>
              <?php if ($donorBloodGroup): ?>
              <p class="text-red-100 text-sm ml-13 mb-3">Blood Type: <span class="font-bold text-white"><?= $donorBloodGroup ?></span></p>
              <?php endif; ?>
              <a href="donateform.php?edit=<?= $donorId ?>" class="inline-flex items-center gap-2 bg-white text-red-700 px-8 py-3 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105"><i class="fas fa-user-pen"></i> View Donor Profile</a>
            </div>
          <?php else: ?>
            <div class="flex flex-col sm:flex-row gap-4">
              <a href="donateform.php" class="bg-white text-red-700 px-8 py-4 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105 text-center"><i class="fas fa-hand-holding-heart mr-2"></i> <span data-i18n="register_as_donor">Register as Donor</span></a>
              <a href="#process" class="border-2 border-white/50 text-white px-8 py-4 rounded-xl font-bold hover:bg-white/10 transition text-center"><i class="fas fa-circle-info mr-2"></i> <span data-i18n="learn_more">Learn More</span></a>
            </div>
          <?php endif; ?>
        </div>
        <div class="hidden md:flex justify-center animate-fade-right" style="animation-delay:0.2s">
          <div class="relative">
            <div class="absolute inset-0 flex items-center justify-center"><div class="w-72 h-72 rounded-full border-2 border-white/10 pulse-ring"></div><div class="absolute w-56 h-56 rounded-full border-2 border-white/10 pulse-ring" style="animation-delay:.5s"></div></div>
            <div class="relative w-72 h-72 bg-white/10 backdrop-blur-sm rounded-full flex items-center justify-center float-anim">
              <div class="text-center"><i class="fas fa-droplet text-8xl text-pink-200 mb-4 drop-shadow-lg heartbeat"></i><p class="text-2xl font-bold text-white">Give Blood</p><p class="text-pink-200 text-sm mt-1">Save a Life</p></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- 2. STATISTICS CARDS -->
  <section class="section-white py-16 -mt-12 relative z-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl shadow-xl p-6 text-center card-hover border border-red-50">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4 shadow-lg shadow-red-200"><i class="fas fa-users"></i></div>
          <h3 class="text-3xl font-extrabold text-red-600 mb-1"><?= $totalDonors ?></h3>
          <p class="text-gray-500 text-sm font-medium">Registered Donors</p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 text-center card-hover border border-red-50">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4 shadow-lg shadow-pink-200"><i class="fas fa-clipboard-list"></i></div>
          <h3 class="text-3xl font-extrabold text-red-600 mb-1"><?= $totalRequests ?></h3>
          <p class="text-gray-500 text-sm font-medium">Blood Requests</p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 text-center card-hover border border-red-50">
          <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4 shadow-lg shadow-rose-200"><i class="fas fa-heart-pulse"></i></div>
          <h3 class="text-3xl font-extrabold text-red-600 mb-1"><?= $livesSaved ?>+</h3>
          <p class="text-gray-500 text-sm font-medium">Lives Saved</p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 text-center card-hover border border-red-50">
          <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4 shadow-lg shadow-red-100"><i class="fas fa-droplet"></i></div>
          <h3 class="text-3xl font-extrabold text-red-600 mb-1"><?= $totalDonations ?></h3>
          <p class="text-gray-500 text-sm font-medium">Successful Donations</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 3. WHY DONATE BLOOD -->
  <section class="section-pink py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-heart mr-1"></i> Make a Difference</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Why Your Donation Matters</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Every day, patients in hospitals need blood for surgeries, trauma care, and life-threatening conditions. Your one donation can save up to 3 lives.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-200"><i class="fas fa-heart-pulse"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Save Lives</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Each pint of blood you donate can save up to 3 lives - patients fighting cancer, surviving accidents, or undergoing surgery.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200"><i class="fas fa-truck-medical"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Be There in Emergencies</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Every second counts when someone is in an accident or emergency. A ready blood supply gives them a fighting chance.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-rose-200"><i class="fas fa-users"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Strengthen Community</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Local hospitals depend on community donors like you to keep their blood supply ready for anyone in need.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-100"><i class="fas fa-rotate"></i></div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Build a Lifesaving Habit</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Blood is always needed. By donating regularly every 3 months, you help maintain a stable supply for your community.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 4. DONOR ELIGIBILITY -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-clipboard-check mr-1"></i> Check Your Eligibility</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Donor Eligibility</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Before you donate, make sure you meet these basic requirements to ensure your safety and the safety of recipients.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-red-200"><i class="fas fa-cake-candles"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Age 18-65 years</h4>
          <p class="text-gray-500 text-sm">You must be between 18 and 65 years old to donate blood.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-pink-200"><i class="fas fa-weight-scale"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Weight at least 100 lb</h4>
          <p class="text-gray-500 text-sm">You must weigh at least 100 lbs to safely donate blood.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-rose-200"><i class="fas fa-heart"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Good Health Condition</h4>
          <p class="text-gray-500 text-sm">You should be in good health, free from fever or infectious diseases.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-red-100"><i class="fas fa-clock"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Last Donation 3 Months Ago</h4>
          <p class="text-gray-500 text-sm">You must wait at least 3 months between whole blood donations.</p>
        </div>
      </div>
      <div class="mt-12 max-w-3xl mx-auto">
        <div class="bg-pink-50 rounded-2xl p-8 border border-pink-100">
          <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2"><i class="fas fa-triangle-exclamation text-pink-500"></i> Who Should Not Donate?</h3>
          <ul class="space-y-4">
            <li class="flex items-center gap-3 text-gray-700"><i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i> <span>Pregnant or recently gave birth</span></li>
            <li class="flex items-center gap-3 text-gray-700"><i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i> <span>Low hemoglobin or anemia</span></li>
            <li class="flex items-center gap-3 text-gray-700"><i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i> <span>HIV, Hepatitis B, or Hepatitis C</span></li>
            <li class="flex items-center gap-3 text-gray-700"><i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i> <span>Recent surgery, tattoo, or piercing</span></li>
            <li class="flex items-center gap-3 text-gray-700"><i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i> <span>Currently sick or having fever</span></li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- 5. BLOOD DONATION PROCESS -->
  <section id="process" class="section-pink py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-list-ol mr-1"></i> Simple Steps</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Blood Donation Process</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Donating blood is quick, easy, and safe. Here is what to expect during your visit.</p>
      </div>
      <!-- Horizontal Steps (Desktop) -->
      <div class="hidden lg:flex items-start justify-between max-w-5xl mx-auto">
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-200 hover:scale-110 transition-transform"><i class="fas fa-user-plus"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Register</h4>
          <p class="text-gray-500 text-sm px-2">Fill out the registration form with your basic details.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-red-300 to-pink-300 hidden lg:block"></div>
        </div>
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-pink-500 to-rose-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-pink-200 hover:scale-110 transition-transform"><i class="fas fa-clipboard-check"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Admin Review</h4>
          <p class="text-gray-500 text-sm px-2">Our team reviews your registration and verifies your eligibility.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-pink-300 to-rose-300 hidden lg:block"></div>
        </div>
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-rose-500 to-red-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-rose-200 hover:scale-110 transition-transform"><i class="fas fa-link"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Assigned</h4>
          <p class="text-gray-500 text-sm px-2">You are matched with a patient request based on your blood type.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-rose-300 to-red-300 hidden lg:block"></div>
        </div>
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-red-400 to-pink-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-100 hover:scale-110 transition-transform"><i class="fas fa-droplet"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Donate</h4>
          <p class="text-gray-500 text-sm px-2">Relax while about 450ml of blood is collected. Takes 8-10 minutes.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-red-300 to-pink-300 hidden lg:block"></div>
        </div>
        <div class="flex flex-col items-center text-center flex-1">
          <div class="w-20 h-20 bg-gradient-to-br from-red-600 to-rose-600 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-200 hover:scale-110 transition-transform"><i class="fas fa-certificate"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Certificate</h4>
          <p class="text-gray-500 text-sm px-2">Get your donation certificate and know you have saved lives.</p>
        </div>
      </div>
      <!-- Vertical Steps (Mobile) -->
      <div class="lg:hidden space-y-6 max-w-md mx-auto">
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm"><div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md"><i class="fas fa-user-plus"></i></div><div><h4 class="font-bold text-gray-900">Register</h4><p class="text-gray-500 text-sm mt-1">Fill out the registration form with your basic details.</p></div></div>
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm"><div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-rose-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md"><i class="fas fa-clipboard-check"></i></div><div><h4 class="font-bold text-gray-900">Admin Review</h4><p class="text-gray-500 text-sm mt-1">Our team reviews your registration and verifies your eligibility.</p></div></div>
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm"><div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-red-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md"><i class="fas fa-link"></i></div><div><h4 class="font-bold text-gray-900">Assigned</h4><p class="text-gray-500 text-sm mt-1">You are matched with a patient request based on your blood type.</p></div></div>
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm"><div class="w-12 h-12 bg-gradient-to-br from-red-400 to-pink-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md"><i class="fas fa-droplet"></i></div><div><h4 class="font-bold text-gray-900">Donate</h4><p class="text-gray-500 text-sm mt-1">Relax while about 450ml of blood is collected. Takes 8-10 minutes.</p></div></div>
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm"><div class="w-12 h-12 bg-gradient-to-br from-red-600 to-rose-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md"><i class="fas fa-certificate"></i></div><div><h4 class="font-bold text-gray-900">Certificate</h4><p class="text-gray-500 text-sm mt-1">Get your donation certificate and know you have saved lives.</p></div></div>
      </div>
    </div>
  </section>

  <!-- 6. BENEFITS -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-gift mr-1"></i> What You Get</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Benefits of Blood Donation</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Donating blood is not just good for others - it is good for you too.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-red-200"><i class="fas fa-certificate"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Donation Certificate</h4>
          <p class="text-gray-500 text-sm">Receive an official certificate of your generous contribution.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-rose-200"><i class="fas fa-heart-pulse"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Free Health Check</h4>
          <p class="text-gray-500 text-sm">Get a free mini health screening including blood pressure and hemoglobin check.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-400 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-pink-200"><i class="fas fa-fire"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Calorie Burn</h4>
          <p class="text-gray-500 text-sm">Your body burns about 650 calories per donation as it replenishes blood cells.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-red-100"><i class="fas fa-face-smile-beam"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Emotional Well-being</h4>
          <p class="text-gray-500 text-sm">Experience the joy and fulfillment of knowing you have helped save a life.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-red-200"><i class="fas fa-droplet"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Reduces Iron Levels</h4>
          <p class="text-gray-500 text-sm">Regular donation helps maintain healthy iron levels, reducing disease risk.</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-pink-200"><i class="fas fa-shield-heart"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Priority Access</h4>
          <p class="text-gray-500 text-sm">Registered donors receive priority notifications for urgent blood requests.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 7. FAQ -->
  <section class="section-pink py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-circle-question mr-1"></i> Got Questions?</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Frequently Asked Questions</h2>
      </div>
      <div class="space-y-4">
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)"><span class="font-semibold text-gray-900">Is donating blood painful?</span><i class="fas fa-chevron-down text-red-400 faq-icon"></i></button>
          <div class="faq-answer px-6 text-gray-600"><p>You may feel a brief pinch when the needle is inserted, but the actual donation process is generally painless. Most donors describe it as a minor discomfort that passes quickly.</p></div>
        </div>
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)"><span class="font-semibold text-gray-900">How long does the donation take?</span><i class="fas fa-chevron-down text-red-400 faq-icon"></i></button>
          <div class="faq-answer px-6 text-gray-600"><p>The actual blood collection takes about 8-10 minutes. The entire visit, including registration, screening, and recovery, typically takes about 30-45 minutes.</p></div>
        </div>
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)"><span class="font-semibold text-gray-900">How often can I donate blood?</span><i class="fas fa-chevron-down text-red-400 faq-icon"></i></button>
          <div class="faq-answer px-6 text-gray-600"><p>You can donate whole blood once every 56 days (8 weeks). This waiting period allows your body to fully replenish the donated blood cells.</p></div>
        </div>
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)"><span class="font-semibold text-gray-900">Is it safe to donate blood?</span><i class="fas fa-chevron-down text-red-400 faq-icon"></i></button>
          <div class="faq-answer px-6 text-gray-600"><p>Yes, absolutely. All donation equipment is sterile and used only once. The process is supervised by trained medical professionals, and your safety is our top priority.</p></div>
        </div>
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)"><span class="font-semibold text-gray-900">Will I feel weak after donating?</span><i class="fas fa-chevron-down text-red-400 faq-icon"></i></button>
          <div class="faq-answer px-6 text-gray-600"><p>Most people feel fine after donating. We recommend resting for a few minutes and enjoying refreshments. Drink plenty of fluids and avoid heavy lifting for the rest of the day.</p></div>
        </div>
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)"><span class="font-semibold text-gray-900">What blood types are needed most?</span><i class="fas fa-chevron-down text-red-400 faq-icon"></i></button>
          <div class="faq-answer px-6 text-gray-600"><p>All blood types are needed, but O-negative (universal donor) and B-negative are often in highest demand. However, the best blood type to donate is the one you have - every type saves lives!</p></div>
        </div>
      </div>
    </div>
  </section>

  <!-- 8. EMERGENCY BLOOD REQUEST BANNER -->
  <section class="emergency-banner py-20 relative overflow-hidden">
    <div class="absolute top-0 left-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-pink-500/10 rounded-full blur-3xl translate-x-1/3 translate-y-1/3"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
      <div class="inline-flex items-center justify-center w-20 h-20 bg-white/15 rounded-full mb-6"><i class="fas fa-truck-medical text-4xl heartbeat"></i></div>
      <h2 class="text-3xl sm:text-4xl font-extrabold mb-4">Emergency Blood Needed</h2>
      <p class="text-lg text-red-100 mb-8 max-w-xl mx-auto">Someone in your community needs blood right now. Your donation can be the difference between life and death. Act today.</p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="bloodrequest.php" class="bg-white text-red-600 px-8 py-4 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105"><i class="fas fa-hand-holding-medical mr-2"></i> View Blood Requests</a>
        <a href="requestblood.php" class="border-2 border-white/50 text-white px-8 py-4 rounded-xl font-bold hover:bg-white/10 transition"><i class="fas fa-plus-circle mr-2"></i> Submit a Request</a>
      </div>
    </div>
  </section>

  <!-- 9. TESTIMONIALS -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-star mr-1"></i> Donor Stories</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">What Our Donors Say</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Hear from real donors who have experienced the joy of saving lives.</p>
      </div>
      <div class="grid md:grid-cols-3 gap-8">
        <div class="testimonial-card rounded-2xl p-8 border border-pink-100 shadow-sm card-hover">
          <div class="flex items-center gap-1 text-yellow-400 mb-4"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"Donating blood through BloodLife was the most rewarding experience. The process was smooth, the staff was friendly, and knowing I helped save lives makes it all worth it."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center text-white font-bold">AR</div>
            <div><p class="font-bold text-gray-900 text-sm">Ahmed Raza</p><p class="text-gray-500 text-xs">Donated 5 times</p></div>
          </div>
        </div>
        <div class="testimonial-card rounded-2xl p-8 border border-pink-100 shadow-sm card-hover">
          <div class="flex items-center gap-1 text-yellow-400 mb-4"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"I was nervous at first, but the team made me feel comfortable. Now I donate every 3 months. It is incredible to know my blood is saving patients in need."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-rose-500 rounded-full flex items-center justify-center text-white font-bold">SK</div>
            <div><p class="font-bold text-gray-900 text-sm">Sara Khan</p><p class="text-gray-500 text-xs">Donated 3 times</p></div>
          </div>
        </div>
        <div class="testimonial-card rounded-2xl p-8 border border-pink-100 shadow-sm card-hover">
          <div class="flex items-center gap-1 text-yellow-400 mb-4"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p class="text-gray-600 text-sm leading-relaxed mb-6">"BloodLife made the entire process easy and professional. I received my certificate, got a free health check, and most importantly, I know I am making a difference."</p>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-red-500 rounded-full flex items-center justify-center text-white font-bold">MH</div>
            <div><p class="font-bold text-gray-900 text-sm">Muhammad Hassan</p><p class="text-gray-500 text-xs">Donated 8 times</p></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 10. CONTACT SECTION -->
  <section class="section-pink py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4"><i class="fas fa-envelope mr-1"></i> Get in Touch</span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Contact Information</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">Have questions? Reach out to us anytime. We are here to help you become a lifesaver.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-200"><i class="fas fa-phone"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Phone</h4>
          <p class="text-gray-500 text-sm">1-800-BLOOD-999</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200"><i class="fas fa-envelope"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Email</h4>
          <p class="text-gray-500 text-sm">info@bloodlife.com</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-rose-200"><i class="fas fa-location-dot"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Address</h4>
          <p class="text-gray-500 text-sm">123 Health Street, City</p>
        </div>
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-100"><i class="fas fa-clock"></i></div>
          <h4 class="font-bold text-gray-900 mb-2">Office Hours</h4>
          <p class="text-gray-500 text-sm">Mon - Sat: 8AM - 6PM</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 11. FOOTER -->
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
            <li>info@bloodlife.com</li>
            <li>1-800-BLOOD-999</li>
            <li>123 Health Street, City</li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4">Follow Us</h4>
          <div class="flex space-x-4 text-sm">
            <a href="#" class="hover:text-red-400 transition"><i class="fab fa-facebook-f"></i> Facebook</a>
            <a href="#" class="hover:text-red-400 transition"><i class="fab fa-twitter"></i> Twitter</a>
            <a href="#" class="hover:text-red-400 transition"><i class="fab fa-instagram"></i> Instagram</a>
          </div>
        </div>
      </div>
      <div class="border-t border-gray-700 pt-8 text-center text-sm">
        <p>&copy; 2026 BloodLife. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <!-- SCRIPTS -->
  <script>
    function bloodlifeLogout() {
      if (!confirm('Are you sure you want to logout?')) return;
      window.location.href = 'logout.php';
    }
    function toggleUserDropdown() {
      document.getElementById('userDropdown').classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
      var menu = document.getElementById('userMenu');
      var dropdown = document.getElementById('userDropdown');
      if (menu && dropdown && !menu.contains(e.target)) dropdown.classList.add('hidden');
    });
    function toggleFaq(btn) {
      var answer = btn.nextElementSibling;
      var icon = btn.querySelector('.faq-icon');
      var isOpen = answer.classList.contains('open');
      document.querySelectorAll('.faq-answer').forEach(function(el) { el.classList.remove('open'); });
      document.querySelectorAll('.faq-icon').forEach(function(el) { el.classList.remove('rotated'); });
      if (!isOpen) { answer.classList.add('open'); icon.classList.add('rotated'); }
    }
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) { entry.target.style.opacity = '1'; entry.target.style.transform = 'translateY(0)'; }
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('.card-hover').forEach(function(el) {
      el.style.opacity = '0'; el.style.transform = 'translateY(20px)'; el.style.transition = 'all 0.6s ease-out';
      observer.observe(el);
    });
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
