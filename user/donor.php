<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

// Check if logged-in user already has a donor record
$isAlreadyDonor = false;
$donorId = 0;
if ($isLoggedIn) {
    $userId = $_SESSION['user_id'] ?? 0;
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT id FROM donor WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $isAlreadyDonor = true;
            $donorId = $row['id'];
        }
        $stmt->close();
    }
}

// Fetch all donors with username
$donors = [];
$donorResult = $conn->query("
    SELECT d.id, d.user_id, d.gender, d.age, d.blood_groups, d.address, d.last_donation_date, d.available_status, u.username
    FROM donor d
    JOIN users u ON d.user_id = u.id
    WHERE u.status = 'Active'
    ORDER BY d.id DESC
");
if ($donorResult && $donorResult->num_rows > 0) {
    $donors = $donorResult->fetch_all(MYSQLI_ASSOC);
}

// Count total donations per donor
$donationCounts = [];
if (count($donors) > 0) {
    $donorIds = array_column($donors, 'id');
    $placeholders = implode(',', array_fill(0, count($donorIds), '?'));
    $types = str_repeat('i', count($donorIds));
    $countStmt = $conn->prepare("SELECT donor_id, COUNT(*) AS total FROM donation_history WHERE donor_id IN ($placeholders) GROUP BY donor_id");
    $countStmt->bind_param($types, ...$donorIds);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    while ($row = $countResult->fetch_assoc()) {
        $donationCounts[$row['donor_id']] = $row['total'];
    }
    $countStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Become a Blood Donor – BloodLife</title>
  <script>
    (function() {
      var t = localStorage.getItem('bloodlife-theme');
      if (t === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>
  <script>
    tailwind.config = { darkMode: 'class' }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    @keyframes fadeInDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeInUp   { from { opacity:0; transform:translateY( 20px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeInLeft  { from { opacity:0; transform:translateX(-30px); } to { opacity:1; transform:translateX(0); } }
    @keyframes fadeInRight { from { opacity:0; transform:translateX( 30px); } to { opacity:1; transform:translateX(0); } }
    @keyframes float { 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-12px); } }
    @keyframes pulse-ring { 0%{ transform:scale(.9); opacity:1; } 100%{ transform:scale(1.4); opacity:0; } }
    @keyframes heartbeat { 0%,100%{ transform:scale(1); } 15%{ transform:scale(1.15); } 30%{ transform:scale(1); } 45%{ transform:scale(1.1); } }
    .animate-fade-down  { animation: fadeInDown  0.6s ease-out both; }
    .animate-fade-up    { animation: fadeInUp    0.6s ease-out both; }
    .animate-fade-left  { animation: fadeInLeft  0.6s ease-out both; }
    .animate-fade-right { animation: fadeInRight 0.6s ease-out both; }
    .float-anim { animation: float 3s ease-in-out infinite; }
    .pulse-ring { animation: pulse-ring 2s ease-out infinite; }
    .heartbeat  { animation: heartbeat 1.5s ease-in-out infinite; }

    .hero-bg {
      background: linear-gradient(135deg, #dc2626 0%, #991b1b 40%, #be123c 70%, #e11d48 100%);
      position: relative;
      overflow: hidden;
    }
    .hero-bg::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.08) 0%, transparent 50%),
                  radial-gradient(circle at 70% 80%, rgba(255,255,255,0.05) 0%, transparent 40%);
      animation: float 8s ease-in-out infinite;
    }

    .section-pink { background: linear-gradient(180deg, #fff1f2 0%, #ffffff 100%); }
    .section-white { background: #ffffff; }

    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-hover:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px rgba(220, 38, 38, 0.15);
    }

    .step-arrow {
      position: relative;
    }
    .step-arrow::after {
      content: '';
      position: absolute;
      top: 50%;
      right: -1.25rem;
      width: 2rem;
      height: 2px;
      background: linear-gradient(90deg, #fca5a5, #dc2626);
    }
    .step-arrow:last-child::after { display: none; }

    .faq-answer {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease, padding 0.4s ease;
    }
    .faq-answer.open {
      max-height: 500px;
      padding-top: 1rem;
      padding-bottom: 0.5rem;
    }
    .faq-icon {
      transition: transform 0.3s ease;
    }
    .faq-icon.rotated {
      transform: rotate(180deg);
    }
  </style>
  <style id="dark-mode-styles">
    /* Light mode resets */
    html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
    html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
    html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }

    /* Dark mode body & nav */
    html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
    html.dark nav.bg-white, html.dark nav.bg-white.shadow-lg { background-color: #1f2937 !important; }

    /* Dark mode backgrounds */
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #374151 !important; }
    html.dark .bg-red-50 { background-color: rgba(220, 38, 38, 0.15) !important; }
    html.dark .bg-red-100 { background-color: rgba(220, 38, 38, 0.2) !important; }
    html.dark .bg-pink-50 { background-color: rgba(236, 72, 153, 0.1) !important; }
    html.dark .bg-green-50 { background-color: rgba(34, 197, 94, 0.15) !important; }
    html.dark footer { background-color: #1f2937 !important; }

    /* Dark mode sections */
    html.dark .section-pink { background: linear-gradient(180deg, rgba(236,72,153,0.08) 0%, #1f2937 100%) !important; }
    html.dark .section-white { background-color: #111827 !important; }

    /* Dark mode text */
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark .text-gray-600 { color: #9ca3af !important; }
    html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark .text-gray-400 { color: #6b7280 !important; }

    /* Dark mode forms */
    html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
    html.dark label { color: #d1d5db !important; }

    /* Dark mode borders */
    html.dark .border-gray-200, html.dark .border-2.border-gray-200 { border-color: #4b5563 !important; }
    html.dark .border-gray-300 { border-color: #4b5563 !important; }
    html.dark .border-t { border-color: #374151 !important; }
    html.dark .border-pink-100 { border-color: rgba(236, 72, 153, 0.2) !important; }
    html.dark .border-white\/50 { border-color: rgba(255, 255, 255, 0.25) !important; }
    html.dark .divide-y.divide-gray-50 > * + * { border-color: #374151 !important; }

    /* Dark mode table */
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }

    /* Dark mode FAQ */
    html.dark .faq-item { background-color: #1f2937 !important; border-color: #4b5563 !important; }

    /* Dark mode donor cards */
    html.dark .donor-card { background-color: #1f2937 !important; border-color: #4b5563 !important; }
    html.dark .donor-card:hover { box-shadow: 0 20px 40px rgba(220, 38, 38, 0.2) !important; }

    /* Dark mode donor modal */
    html.dark #donorDetailModal .bg-white { background-color: #1f2937 !important; }
    html.dark #donorDetailModal .bg-gray-100 { background-color: #374151 !important; }
  </style>
</head>
<body class="bg-white min-h-screen">

 
  <!-- Mobile Menu Toggle -->
  <div id="mobileMenuToggle" class="fixed top-4 right-4 z-50 md:hidden bg-red-600 text-white p-2 rounded-lg cursor-pointer">
    ☰
  </div>

   <!-- Navbar -->
   <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 1. HERO BANNER -->
  <!-- ══════════════════════════════════════════════════ -->
  <section class="section-pink text-white py-20 sm:py-28 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      <div class="grid md:grid-cols-2 gap-12 items-center">
        <!-- Left: Text -->
        <div class="animate-fade-up">
          <div class="inline-flex items-center gap-2 bg-white backdrop-blur-sm text-red-500 px-5 py-2 rounded-full text-sm font-semibold mb-6">
            <i class="fas fa-heart-pulse"></i> <span>Save Lives Today</span>
          </div>
          <h1 class="text-4xl text-pink-500 sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
            Become a<br>
            <span class="text-red-600" >Blood Donor</span>
          </h1>
          <p class="text-lg sm:text-xl text-gray-500 mb-8 leading-relaxed max-w-lg" data-i18n="hero_desc">
            Every drop of blood is a gift of life. Your donation can save someone's life today.
          </p>

          <?php if ($isAlreadyDonor): ?>
            <div class="bg-gray-700/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 mb-6">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                  <i class="fas fa-check text-white"></i>
                </div>
                <p class="font-bold text-red-600 text-lg">You are already registered as a donor.</p>
              </div>
              <a href="donateform.php?edit=<?= $donorId ?>" class="inline-flex items-center bg-pink-300 gap-2 text-red-700 px-8 py-3 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105">
                <i class="fas fa-user-pen"></i> View Donor Profile
              </a>
            </div>
          <?php else: ?>
            <div class="flex flex-col sm:flex-row gap-4">
              <a href="donateform.php" class="bg-white text-red-700 px-8 py-4 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105 text-center">
                <i class="fas fa-hand-holding-heart mr-2"></i> <span data-i18n="register_as_donor">Register as Donor</span>
              </a>
              <a href="#process" class="border-2 border-white/50 text-red-500 px-8 py-4 rounded-xl font-bold hover:bg-red-50/10 transition text-center">
                <i class="fas fa-circle-info mr-2"></i> <span data-i18n="learn_more">Learn More</span>
              </a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right: Illustration -->
        <div class="hidden md:flex justify-center animate-fade-right" style="animation-delay: 0.2s;">
          <div class="relative">
            <!-- Decorative rings -->
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="w-72 h-72 rounded-full border-2 border-pink-500/10 pulse-ring"></div>
              <div class="absolute w-56 h-56 rounded-full border-2 border-pink-500/10 pulse-ring" style="animation-delay: 0.5s;"></div>
            </div>
            <!-- Blood drop icon -->
            <div class="relative w-72 h-72 bg-pink-500/20 backdrop-blur-sm rounded-full flex items-center justify-center float-anim">
              <div class="text-center">
                <i class="fas fa-droplet text-8xl text-red-500 mb-4 drop-shadow-lg heartbeat"></i>
                <p class="text-2xl font-bold text-red-600">Give Blood</p>
                <p class="text-pink-400 text-sm mt-1">Save a Life</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats -->
      
      
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 2. WHY DONATE BLOOD -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-pink py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-heart mr-1"></i> <span >Make a Difference</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Why Your Donation Matters</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg" >
          Every day, patients in hospitals need blood for surgeries, trauma care, and life-threatening conditions. Your one donation can save up to 3 lives.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- Card 1 -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-200">
            <i class="fas fa-heart-pulse"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-3" >Save Lives</h3>
          <p class="text-gray-600 text-sm leading-relaxed" >
            Each pint of blood you donate can save up to 3 lives — patients fighting cancer, surviving accidents, or undergoing surgery.
          </p>
        </div>

        <!-- Card 2 -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200">
            <i class="fas fa-truck-medical"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-3">Be There in Emergencies</h3>
          <p class="text-gray-600 text-sm leading-relaxed" >
            Every second counts when someone is in an accident or emergency. A ready blood supply gives them a fighting chance.
          </p>
        </div>

        <!-- Card 3 -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-rose-200">
            <i class="fas fa-users"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-3" >Strengthen Your Community</h3>
          <p class="text-gray-600 text-sm leading-relaxed" >
            Local hospitals depend on community donors like you to keep their blood supply ready for anyone in need.
          </p>
        </div>

        <!-- Card 4 -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-100">
            <i class="fas fa-rotate"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-900 mb-3" >Build a Lifesaving Habit</h3>
          <p class="text-gray-600 text-sm leading-relaxed" >
            Blood is always needed. By donating regularly every 3 months, you help maintain a stable supply for your community.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 3. DONOR ELIGIBILITY -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-clipboard-check mr-1"></i> <span>Check Your Eligibility</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4" >Donor Eligibility</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Before you donate, make sure you meet these basic requirements to ensure your safety and the safety of recipients.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
        <!-- Age -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-red-200">
            <i class="fas fa-cake-candles"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Age 18–65 years</h4>
          <p class="text-gray-500 text-sm">You must be between 18 and 65 years old to donate blood.</p>
        </div>

        <!-- Weight -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-pink-200">
            <i class="fas fa-weight-scale"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2" >Weight at least 100 lb</h4>
          <p class="text-gray-500 text-sm">You must weigh at least 100 lbs to safely donate blood.</p>
        </div>

        <!-- Health -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-rose-200">
            <i class="fas fa-heart"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2" >Good Health Condition</h4>
          <p class="text-gray-500 text-sm" >You should be in good health, free from fever or infectious diseases.</p>
        </div>

        <!-- Interval -->
        <div class="card-hover bg-white rounded-2xl p-6 border border-pink-100 shadow-sm text-center">
          <div class="w-14 h-14 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-xl mx-auto mb-4 shadow-lg shadow-red-100">
            <i class="fas fa-clock"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Last Donation 3 Months Ago</h4>
          <p class="text-gray-500 text-sm" >You must wait at least 3 months between whole blood donations.</p>
        </div>
      </div>

      <!-- Who Should Not Donate -->
      <div class="mt-12 max-w-3xl mx-auto">
        <div class="bg-pink-50 rounded-2xl p-8 border border-pink-100">
          <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
            <i class="fas fa-triangle-exclamation text-pink-500"></i>
            <span >Who Should Not Donate?</span>
          </h3>
          <ul class="space-y-4">
            <li class="flex items-center gap-3 text-gray-700">
              <i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i>
              <span>Pregnant or recently gave birth</span>
            </li>
            <li class="flex items-center gap-3 text-gray-700">
              <i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i>
              <span>Low hemoglobin or anemia</span>
            </li>
            <li class="flex items-center gap-3 text-gray-700">
              <i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i>
              <span>HIV, Hepatitis B, or Hepatitis C</span>
            </li>
            <li class="flex items-center gap-3 text-gray-700">
              <i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i>
              <span>Recent surgery, tattoo, or piercing</span>
            </li>
            <li class="flex items-center gap-3 text-gray-700">
              <i class="fas fa-xmark-circle text-red-400 flex-shrink-0"></i>
              <span>Currently sick or having fever</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 4. BLOOD DONATION PROCESS -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section id="process" class="section-pink py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-list-ol mr-1"></i> <span>Simple Steps</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Blood Donation Process</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Donating blood is quick, easy, and safe. Here's what to expect during your visit.
        </p>
      </div>

      <!-- Horizontal Steps (Desktop) -->
      <div class="hidden lg:flex items-start justify-between max-w-5xl mx-auto">
        <!-- Step 1 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-lg shadow-red-200 hover:scale-110 transition-transform">
            <i class="fas fa-user-plus"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Register as Donor</h4>
          <p class="text-gray-500 text-sm px-2">Fill out the registration form with your basic details.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-red-300 to-pink-300 hidden lg:block"></div>
        </div>

        <!-- Step 2 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-pink-500 to-rose-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-pink-200 hover:scale-110 transition-transform">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Admin Reviews</h4>
          <p class="text-gray-500 text-sm px-2">Our team reviews your registration and verifies your eligibility.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-pink-300 to-rose-300 hidden lg:block"></div>
        </div>

        <!-- Step 3 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-rose-500 to-red-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-rose-200 hover:scale-110 transition-transform">
            <i class="fas fa-link"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Assigned to a Blood Request</h4>
          <p class="text-gray-500 text-sm px-2">You are matched with a patient request based on your blood type.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-rose-300 to-red-300 hidden lg:block"></div>
        </div>

        <!-- Step 4 -->
        <div class="flex flex-col items-center text-center flex-1 relative">
          <div class="w-20 h-20 bg-gradient-to-br from-red-400 to-pink-500 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-100 hover:scale-110 transition-transform">
            <i class="fas fa-droplet"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2" >Donate Blood</h4>
          <p class="text-gray-500 text-sm px-2">Relax while about 450ml of blood is collected. Takes 8–10 minutes.</p>
          <div class="absolute top-10 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-red-300 to-pink-300 hidden lg:block"></div>
        </div>

        <!-- Step 5 -->
        <div class="flex flex-col items-center text-center flex-1">
          <div class="w-20 h-20 bg-gradient-to-br from-red-600 to-rose-600 rounded-full flex items-center justify-center text-white text-3xl mb-4 shadow-lg shadow-red-200 hover:scale-110 transition-transform">
            <i class="fas fa-certificate"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2" >Receive Certificate</h4>
          <p class="text-gray-500 text-sm px-2" >Get your donation certificate and know you've saved lives.</p>
        </div>
      </div>

      <!-- Vertical Steps (Mobile) -->
      <div class="lg:hidden space-y-6 max-w-md mx-auto">
        <!-- Step 1 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-red-200">
            <i class="fas fa-user-plus"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Register as Donor</h4>
            <p class="text-gray-500 text-sm mt-1">Fill out the registration form with your basic details.</p>
          </div>
        </div>
        <!-- Step 2 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-rose-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-pink-200">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Admin Reviews</h4>
            <p class="text-gray-500 text-sm mt-1">Our team reviews your registration and verifies your eligibility.</p>
          </div>
        </div>
        <!-- Step 3 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-red-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-rose-200">
            <i class="fas fa-link"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Assigned to a Blood Request</h4>
            <p class="text-gray-500 text-sm mt-1">You are matched with a patient request based on your blood type.</p>
          </div>
        </div>
        <!-- Step 4 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-pink-500 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-red-100">
            <i class="fas fa-droplet"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900">Donate Blood</h4>
            <p class="text-gray-500 text-sm mt-1">Relax while about 450ml of blood is collected. Takes 8–10 minutes.</p>
          </div>
        </div>
        <!-- Step 5 -->
        <div class="flex items-start gap-4 bg-white rounded-2xl p-5 border border-pink-100 shadow-sm">
          <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-rose-600 rounded-xl flex items-center justify-center text-white flex-shrink-0 shadow-md shadow-red-200">
            <i class="fas fa-certificate"></i>
          </div>
          <div>
            <h4 class="font-bold text-gray-900" data-i18n="step_cert">Receive Certificate</h4>
            <p class="text-gray-500 text-sm mt-1" data-i18n="step_cert_desc">Get your donation certificate and know you've saved lives.</p>
          </div>
        </div>
      </div>

      <!-- Before You Donate -->
      <div class="mt-16 bg-white rounded-2xl p-8 sm:p-10 border border-pink-100 shadow-sm">
        <h3 class="text-xl font-bold text-gray-900 mb-6 text-center">
          <i class="fas fa-clipboard-list text-red-500 mr-2"></i> Before You Donate
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-bed text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Sleep Well</p>
            <p class="text-gray-500 text-xs mt-1">Get 7–8 hours</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-utensils text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Eat Healthy</p>
            <p class="text-gray-500 text-xs mt-1">Iron-rich food</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-glass-water text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Stay Hydrated</p>
            <p class="text-gray-500 text-xs mt-1">Drink plenty of water</p>
          </div>
          <div class="text-center">
            <div class="w-16 h-16 bg-pink-50 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
              <i class="fas fa-id-card text-pink-500"></i>
            </div>
            <p class="font-semibold text-gray-900 text-sm">Bring Your ID</p>
            <p class="text-gray-500 text-xs mt-1">Valid photo ID</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 5. BENEFITS OF BLOOD DONATION -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-gift mr-1"></i> <span>What You Get</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Benefits of Blood Donation</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Donating blood isn't just good for others — it's good for you too. Here's what you gain as a donor.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-red-200">
            <i class="fas fa-certificate"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Donation Certificate</h4>
          <p class="text-gray-500 text-sm">Receive an official certificate of your generous contribution.</p>
        </div>

        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-rose-200">
            <i class="fas fa-heart-pulse"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Free Health Check</h4>
          <p class="text-gray-500 text-sm">Get a free mini health screening including blood pressure and hemoglobin check.</p>
        </div>

        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-400 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-pink-200">
            <i class="fas fa-fire"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Calorie Burn</h4>
          <p class="text-gray-500 text-sm">Your body burns about 650 calories per donation as it replenishes blood cells.</p>
        </div>

        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-red-400 to-pink-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-red-100">
            <i class="fas fa-face-smile-beam"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Emotional Well-being</h4>
          <p class="text-gray-500 text-sm">Experience the joy and fulfillment of knowing you've helped save someone's life.</p>
        </div>

        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-red-200">
            <i class="fas fa-droplet"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Reduces Iron Levels</h4>
          <p class="text-gray-500 text-sm">Regular donation helps maintain healthy iron levels, reducing the risk of certain diseases.</p>
        </div>

        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm">
          <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mb-5 shadow-lg shadow-pink-200">
            <i class="fas fa-shield-heart"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Priority Access</h4>
          <p class="text-gray-500 text-sm">Registered donors receive priority notifications for urgent blood requests and community events.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 6. OUR DONORS -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-users mr-1"></i> <span>Our Heroes</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Find Blood Donors</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Browse our registered donors and find the blood type you need.
        </p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap items-end gap-4 mb-8 max-w-4xl mx-auto">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
          <input id="donorSearchInput" type="text" placeholder="Search by name or township..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
        </div>
        <div class="w-40">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group</label>
          <select id="donorFilterBloodGroup" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
            <option value="">All</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
              <option value="<?= $bg ?>"><?= $bg ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="w-40">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
          <select id="donorFilterStatus" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
            <option value="">All</option>
            <option value="Available">Available</option>
            <option value="Unavailable">Unavailable</option>
          </select>
        </div>
        <button onclick="clearDonorFilters()" class="px-4 py-2.5 text-sm text-gray-600 border-2 border-gray-200 rounded-xl hover:bg-gray-100 transition font-semibold whitespace-nowrap">Clear</button>
      </div>

      <!-- Donor Cards Grid -->
      <div id="donorCardsGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (count($donors) > 0): ?>
          <?php foreach ($donors as $d):
            $isAvailable = ($d['available_status'] ?? 'Available') === 'Available';
            $statusColor = $isAvailable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
            $statusIcon = $isAvailable ? 'fa-circle-check' : 'fa-circle-xmark';
            $avatarBg = $isAvailable ? 'from-red-500 to-rose-500' : 'from-gray-400 to-gray-500';
            $initials = strtoupper(substr($d['username'], 0, 1));
          ?>
            <div class="donor-card card-hover bg-white rounded-2xl border border-pink-100 shadow-sm overflow-hidden"
                 data-name="<?= htmlspecialchars(strtolower($d['username'])) ?>"
                 data-bloodgroup="<?= htmlspecialchars($d['blood_groups']) ?>"
                 data-status="<?= htmlspecialchars($d['available_status'] ?? 'Available') ?>"
                 data-address="<?= htmlspecialchars(strtolower($d['address'])) ?>">
              <!-- Avatar Header -->
              <div class="relative bg-gradient-to-br <?= $avatarBg ?> p-6 text-center">
                <div class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-3 border-2 border-white/30">
                  <span class="text-3xl font-bold text-white"><?= $initials ?></span>
                </div>
                <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($d['username']) ?></h3>
              </div>
              <!-- Card Body -->
              <div class="p-5 space-y-3">
                <!-- Blood Group -->
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-droplet text-red-500 text-sm"></i>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Blood Group</p>
                    <p class="font-bold text-gray-900"><?= htmlspecialchars($d['blood_groups']) ?></p>
                  </div>
                </div>
                <!-- Township -->
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 bg-pink-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-location-dot text-pink-500 text-sm"></i>
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs text-gray-500">Township</p>
                    <p class="font-semibold text-gray-900 truncate" title="<?= htmlspecialchars($d['address']) ?>"><?= htmlspecialchars($d['address']) ?></p>
                  </div>
                </div>
                <!-- Status Badge -->
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 <?= $isAvailable ? 'bg-green-50' : 'bg-red-50' ?> rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $statusIcon ?> <?= $isAvailable ? 'text-green-500' : 'text-red-500' ?> text-sm"></i>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Status</p>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusColor ?>"><?= htmlspecialchars($d['available_status']) ?></span>
                  </div>
                </div>
                <!-- Last Donation Date -->
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-calendar-check text-rose-500 text-sm"></i>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500">Last Donation</p>
                    <p class="font-semibold text-gray-900"><?= $d['last_donation_date'] ? htmlspecialchars(date('M d, Y', strtotime($d['last_donation_date']))) : 'Never donated' ?></p>
                  </div>
                </div>
              </div>
              <!-- View Details Button -->
              <div class="px-5 pb-5">
                <button type="button" onclick="openDonorModal(this)" class="block w-full text-center bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold py-2.5 rounded-xl hover:from-red-600 hover:to-red-700 hover:shadow-lg transition transform hover:scale-[1.02]"
                  data-name="<?= htmlspecialchars($d['username']) ?>"
                  data-initial="<?= $initials ?>"
                  data-bloodgroup="<?= htmlspecialchars($d['blood_groups']) ?>"
                  data-gender="<?= htmlspecialchars($d['gender']) ?>"
                  data-age="<?= (int)$d['age'] ?>"
                  data-address="<?= htmlspecialchars($d['address']) ?>"
                  data-status="<?= htmlspecialchars($d['available_status'] ?? 'Available') ?>"
                  data-lastdonation="<?= $d['last_donation_date'] ? htmlspecialchars(date('M d, Y', strtotime($d['last_donation_date']))) : 'Never donated' ?>"
                  data-donations="<?= $donationCounts[$d['id']] ?? 0 ?>"
                  data-avatarbg="<?= $avatarBg ?>">
                  <i class="fas fa-eye mr-2"></i> View Details
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Empty State -->
      <div id="donorEmptyState" class="hidden text-center py-16">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-users-slash text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">No Donors Found</h3>
        <p class="text-gray-500">Try adjusting your search filters.</p>
      </div>
      <?php if (count($donors) === 0): ?>
        <div class="text-center py-16">
          <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-users-slash text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-900 mb-2">No Donors Yet</h3>
          <p class="text-gray-500">Be the first to register as a blood donor!</p>
          <a href="donateform.php" class="inline-flex items-center gap-2 bg-red-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-red-700 transition mt-4">
            <i class="fas fa-hand-holding-heart"></i> Register as Donor
          </a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 7. FAQ -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-pink py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-circle-question mr-1"></i> <span>Got Questions?</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Frequently Asked Questions</h2>
      </div>

      <div class="space-y-4">
        <!-- FAQ 1 -->
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)">
            <span class="font-semibold text-gray-900">Is donating blood painful?</span>
            <i class="fas fa-chevron-down text-red-400 faq-icon"></i>
          </button>
          <div class="faq-answer px-6 text-gray-600">
            <p>You may feel a brief pinch when the needle is inserted, but the actual donation process is generally painless. Most donors describe it as a minor discomfort that passes quickly.</p>
          </div>
        </div>

        <!-- FAQ 2 -->
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)">
            <span class="font-semibold text-gray-900">How long does the donation take?</span>
            <i class="fas fa-chevron-down text-red-400 faq-icon"></i>
          </button>
          <div class="faq-answer px-6 text-gray-600">
            <p>The actual blood collection takes about 8–10 minutes. The entire visit, including registration, screening, and recovery, typically takes about 30–45 minutes.</p>
          </div>
        </div>

        <!-- FAQ 3 -->
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)">
            <span class="font-semibold text-gray-900">How often can I donate blood?</span>
            <i class="fas fa-chevron-down text-red-400 faq-icon"></i>
          </button>
          <div class="faq-answer px-6 text-gray-600">
            <p>You can donate whole blood once every 56 days (8 weeks). This waiting period allows your body to fully replenish the donated blood cells.</p>
          </div>
        </div>

        <!-- FAQ 4 -->
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)">
            <span class="font-semibold text-gray-900">Is it safe to donate blood?</span>
            <i class="fas fa-chevron-down text-red-400 faq-icon"></i>
          </button>
          <div class="faq-answer px-6 text-gray-600">
            <p>Yes, absolutely. All donation equipment is sterile and used only once. The process is supervised by trained medical professionals, and your safety is our top priority.</p>
          </div>
        </div>

        <!-- FAQ 5 -->
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)">
            <span class="font-semibold text-gray-900">Will I feel weak after donating?</span>
            <i class="fas fa-chevron-down text-red-400 faq-icon"></i>
          </button>
          <div class="faq-answer px-6 text-gray-600">
            <p >Most people feel fine after donating. We recommend resting for a few minutes and enjoying refreshments. Drink plenty of fluids and avoid heavy lifting for the rest of the day.</p>
          </div>
        </div>

        <!-- FAQ 6 -->
        <div class="bg-white rounded-xl border border-pink-100 shadow-sm overflow-hidden faq-item">
          <button class="w-full flex items-center justify-between p-6 text-left" onclick="toggleFaq(this)">
            <span class="font-semibold text-gray-900">What blood types are needed most?</span>
            <i class="fas fa-chevron-down text-red-400 faq-icon"></i>
          </button>
          <div class="faq-answer px-6 text-gray-600">
            <p>All blood types are needed, but O-negative (universal donor) and B-negative are often in highest demand. However, the best blood type to donate is the one you have — every type saves lives!</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 8. CONTACT INFORMATION -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="section-white py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
        <span class="inline-block bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-sm font-semibold mb-4">
          <i class="fas fa-envelope mr-1"></i> <span>Get in Touch</span>
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Contact Information</h2>
        <p class="text-gray-600 max-w-2xl mx-auto text-lg">
          Have questions? Reach out to us anytime. We're here to help you become a lifesaver.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 max-w-4xl mx-auto">
        <!-- Email -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-red-200">
            <i class="fas fa-envelope"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Email</h4>
          <p class="text-gray-500 text-sm">info@bloodlife.com</p>
        </div>

        <!-- Phone -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center">
          <div class="w-16 h-16 bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-pink-200">
            <i class="fas fa-phone"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Phone</h4>
          <p class="text-gray-500 text-sm">1-800-BLOOD-999</p>
        </div>

        <!-- Address -->
        <div class="card-hover bg-white rounded-2xl p-8 border border-pink-100 shadow-sm text-center sm:col-span-2 lg:col-span-1">
          <div class="w-16 h-16 bg-gradient-to-br from-rose-500 to-red-500 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-5 shadow-lg shadow-rose-200">
            <i class="fas fa-location-dot"></i>
          </div>
          <h4 class="font-bold text-gray-900 mb-2">Address</h4>
          <p class="text-gray-500 text-sm">123 Health Street, City</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- 9. FINAL CTA -->
  <!-- ═══════════════════════════════════════════════════ -->
  <section class="bg-gradient-to-r from-red-600 via-rose-600 to-pink-600 text-white py-20 relative overflow-hidden">
    <div class="absolute top-0 left-0 w-72 h-72 bg-white/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-pink-500/10 rounded-full blur-3xl translate-x-1/3 translate-y-1/3"></div>

    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <div class="inline-flex items-center justify-center w-20 h-20 bg-white/15 rounded-full mb-6">
        <i class="fas fa-hand-holding-heart text-4xl heartbeat"></i>
      </div>
      <h2 class="text-3xl sm:text-4xl font-extrabold mb-4">Become a Hero Today</h2>
      <p class="text-lg text-pink-100 mb-8 max-w-xl mx-auto">
        Your blood can give someone another chance to live.
      </p>

      <?php if ($isAlreadyDonor): ?>
        <div class="bg-white/15 backdrop-blur-sm rounded-2xl p-6 border border-white/20 max-w-lg mx-auto">
          <div class="flex items-center justify-center gap-3 mb-3">
            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
              <i class="fas fa-check text-white"></i>
            </div>
            <p class="font-bold text-white text-lg">You are already registered as a donor.</p>
          </div>
          <a href="donateform.php?edit=<?= $donorId ?>" class="inline-flex items-center gap-2 bg-white text-red-600 px-8 py-3 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105">
            <i class="fas fa-user-pen"></i> View Donor Profile
          </a>
        </div>
      <?php else: ?>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="donateform.php" class="bg-white text-red-600 px-10 py-4 rounded-xl font-bold hover:bg-pink-50 hover:shadow-xl transition transform hover:scale-105">
            <i class="fas fa-hand-holding-heart mr-2"></i> <span>Register as Donor</span>
          </a>
          <a href="bloodrequest.php" class="border-2 border-white/50 text-white px-10 py-4 rounded-xl font-bold hover:bg-white/10 transition">
            <i class="fas fa-search mr-2"></i> <span>Search Blood Type</span>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- FOOTER -->
  <!-- ═══════════════════════════════════════════════════ -->
 <?php include __DIR__ . '/../includes/footer.php'; ?>

  <!-- ═══════════════════════════════════════════════════ -->
  <!-- SCRIPTS -->
  <!-- ═══════════════════════════════════════════════════ -->
  <script>
    // Notification Dropdown Toggle
    function toggleNotifDropdown() {
      document.getElementById('notifDropdown').classList.toggle('hidden');
    }

    // User Dropdown Toggle
    function toggleUserDropdown() {
      document.getElementById('userDropdown').classList.toggle('hidden');
    }

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
      var notifMenu = document.getElementById('notifMenu');
      var notifDropdown = document.getElementById('notifDropdown');
      var userMenu = document.getElementById('userMenu');
      var userDropdown = document.getElementById('userDropdown');

      if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
        notifDropdown.classList.add('hidden');
      }
      if (userMenu && userDropdown && !userMenu.contains(e.target)) {
        userDropdown.classList.add('hidden');
      }
    });

    // FAQ accordion
    function toggleFaq(btn) {
      var answer = btn.nextElementSibling;
      var icon = btn.querySelector('.faq-icon');
      var isOpen = answer.classList.contains('open');

      // Close all others
      document.querySelectorAll('.faq-answer').forEach(function(el) {
        el.classList.remove('open');
      });
      document.querySelectorAll('.faq-icon').forEach(function(el) {
        el.classList.remove('rotated');
      });

      if (!isOpen) {
        answer.classList.add('open');
        icon.classList.add('rotated');
      }
    }

    // Donor card filtering
    var donorSearchInput = document.getElementById('donorSearchInput');
    var donorFilterBloodGroup = document.getElementById('donorFilterBloodGroup');
    var donorFilterStatus = document.getElementById('donorFilterStatus');
    var donorCards = document.querySelectorAll('.donor-card');
    var donorEmptyState = document.getElementById('donorEmptyState');
    var donorCardsGrid = document.getElementById('donorCardsGrid');

    function applyDonorFilters() {
      var q = donorSearchInput.value.toLowerCase();
      var bg = donorFilterBloodGroup.value;
      var status = donorFilterStatus.value;
      var visible = 0;
      donorCards.forEach(function(card) {
        var matchSearch = !q || card.dataset.name.includes(q) || card.dataset.address.includes(q);
        var matchBg = !bg || card.dataset.bloodgroup === bg;
        var matchStatus = !status || card.dataset.status === status;
        var show = matchSearch && matchBg && matchStatus;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (donorEmptyState) {
        donorEmptyState.classList.toggle('hidden', visible > 0);
      }
    }

    function clearDonorFilters() {
      donorSearchInput.value = '';
      donorFilterBloodGroup.value = '';
      donorFilterStatus.value = '';
      applyDonorFilters();
    }

    if (donorSearchInput) donorSearchInput.addEventListener('keyup', applyDonorFilters);
    if (donorFilterBloodGroup) donorFilterBloodGroup.addEventListener('change', applyDonorFilters);
    if (donorFilterStatus) donorFilterStatus.addEventListener('change', applyDonorFilters);

    // Scroll reveal animations
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, { threshold: 0.1 });

    document.querySelectorAll('.card-hover').forEach(function(el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'all 0.6s ease-out';
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

  <!-- Donor Detail Modal -->
  <div id="donorDetailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
      <div id="modalAvatar" class="p-8 text-center">
        <div class="w-24 h-24 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-4 border-2 border-white/30">
          <span id="modalInitial" class="text-4xl font-bold text-white"></span>
        </div>
        <h3 id="modalName" class="text-2xl font-bold text-white"></h3>
      </div>
      <div class="p-6 space-y-4">
        <!-- Blood Group -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-droplet text-red-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Blood Group</p>
            <p id="modalBloodGroup" class="font-bold text-gray-900"></p>
          </div>
        </div>
        <!-- Gender -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-venus-mars text-pink-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Gender</p>
            <p id="modalGender" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Age -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-cake-candles text-rose-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Age</p>
            <p id="modalAge" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Township -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-location-dot text-pink-500"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-500">Township</p>
            <p id="modalTownship" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Status -->
        <div class="flex items-center gap-3">
          <div id="modalStatusIcon" class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Available Status</p>
            <span id="modalStatus" class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"></span>
          </div>
        </div>
        <!-- Last Donation Date -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-calendar-check text-rose-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Last Donation</p>
            <p id="modalLastDonation" class="font-semibold text-gray-900"></p>
          </div>
        </div>
        <!-- Total Donations -->
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-hand-holding-heart text-red-500"></i>
          </div>
          <div class="flex-1">
            <p class="text-xs text-gray-500">Total Donations</p>
            <p id="modalDonations" class="font-bold text-gray-900 text-lg"></p>
          </div>
        </div>
      </div>
      <div class="px-6 pb-6">
        <button onclick="closeDonorModal()" class="w-full bg-gray-100 text-gray-700 font-semibold py-3 rounded-xl hover:bg-gray-200 transition">Close</button>
      </div>
    </div>
  </div>

  <script>
    function openDonorModal(btn) {
      var modal = document.getElementById('donorDetailModal');
      var avatarBg = btn.dataset.avatarbg;

      document.getElementById('modalInitial').textContent = btn.dataset.initial;
      document.getElementById('modalName').textContent = btn.dataset.name;
      document.getElementById('modalBloodGroup').textContent = btn.dataset.bloodgroup;
      document.getElementById('modalGender').textContent = btn.dataset.gender;
      document.getElementById('modalAge').textContent = btn.dataset.age + ' years';
      document.getElementById('modalTownship').textContent = btn.dataset.address;
      document.getElementById('modalLastDonation').textContent = btn.dataset.lastdonation;
      document.getElementById('modalDonations').textContent = btn.dataset.donations;

      var avatar = document.getElementById('modalAvatar');
      avatar.className = 'p-8 text-center bg-gradient-to-br ' + avatarBg;

      var isAvailable = btn.dataset.status === 'Available';
      var statusEl = document.getElementById('modalStatus');
      var statusIconEl = document.getElementById('modalStatusIcon');
      statusEl.textContent = btn.dataset.status;
      statusEl.className = 'inline-flex rounded-full px-3 py-1 text-xs font-semibold ' + (isAvailable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
      statusIconEl.className = 'w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ' + (isAvailable ? 'bg-green-50' : 'bg-red-50');
      statusIconEl.querySelector('i').className = 'fas ' + (isAvailable ? 'fa-circle-check text-green-500' : 'fa-circle-xmark text-red-500');

      modal.classList.remove('hidden');
    }

    function closeDonorModal() {
      document.getElementById('donorDetailModal').classList.add('hidden');
    }

    document.getElementById('donorDetailModal').addEventListener('click', function(e) {
      if (e.target === this) closeDonorModal();
    });
  </script>

</body>
</html>
