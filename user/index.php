<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

$totalDonors   = $conn->query("SELECT COUNT(*) AS c FROM donor")->fetch_assoc()['c'] ?? 0;
$totalRequests = $conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0;

$totalUsers    = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System - Save Lives</title>
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
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 20px rgba(220, 38, 38, 0.5);
            }

            50% {
                box-shadow: 0 0 30px rgba(220, 38, 38, 0.8);
            }
        }

        .animate-fade-down {
            animation: fadeInDown 0.6s ease-out;
        }

        .animate-fade-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .pulse-glow {
            animation: pulse-glow 2s infinite;
        }

        .blood-drop {
            position: relative;
            display: inline-block;
        }
    </style>
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        html.dark nav.bg-white, html.dark nav.bg-white.shadow-lg, html.dark .w-64.bg-white { background-color: #1f2937 !important; }
        html.dark .bg-white { background-color: #1f2937 !important; }
        html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
        html.dark .text-gray-700 { color: #d1d5db !important; }
        html.dark .text-gray-600 { color: #9ca3af !important; }
        html.dark .text-gray-500 { color: #9ca3af !important; }
        html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        html.dark label { color: #d1d5db !important; }
        html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #374151 !important; }
        html.dark thead.bg-gray-50 { background-color: #111827 !important; }
        html.dark .border-gray-200, html.dark .border-2.border-gray-200 { border-color: #4b5563 !important; }
        html.dark .border-t { border-color: #374151 !important; }
        html.dark .bg-red-50 { background-color: rgba(220,38,38,0.15) !important; }
        html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
        html.dark .bg-yellow-50 { background-color: rgba(234,179,8,0.15) !important; }
        html.dark .bg-blue-50 { background-color: rgba(59,130,246,0.15) !important; }
        html.dark .bg-purple-50 { background-color: rgba(168,85,247,0.15) !important; }
        html.dark .bg-orange-50 { background-color: rgba(249,115,22,0.15) !important; }
        html.dark .bg-white.rounded-xl.shadow-xl { background-color: #1f2937 !important; border-color: #374151 !important; }
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
        html.dark ::-webkit-scrollbar { width: 8px; }
        html.dark ::-webkit-scrollbar-track { background: #1f2937; }
        html.dark ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
    </style>
</head>

<body class="bg-gradient-to-b from-gray-50 to-gray-100 font-family: 'Pyidaungsu', Noto Sans Myanmar, sans-serif;">

    <!-- Mobile Menu Toggle -->
    <div id="mobileMenuToggle" class="fixed top-4 right-4 z-50 md:hidden bg-red-600 text-white p-2 rounded-lg cursor-pointer">
        ☰
    </div>

    <!-- Navbar -->
    <nav class="bg-white shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-3 animate-fade-down">
                    <span class="text-2xl bg-red-200 p-1 rounded-full shadow-black/35 shadow-md">🩸</span>
                    <div>
                        <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
                        <p class="text-xs text-gray-500" data-i18n="save_lives_together">Save Lives Together</p>
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
                        <!-- Bell Icon -->
                        <div class="relative" id="notifMenu">
                            <button onclick="toggleNotifDropdown()" class="relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 hover:bg-red-50 transition">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                                </svg>
                                <span id="notifBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-600 text-white text-xs font-bold rounded-full flex items-center justify-center hidden">0</span>
                            </button>
                            <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                                    <p class="font-semibold text-gray-800">Notifications</p>
                                    <span class="text-xs text-gray-400">0 new</span>
                                </div>
                                <div class="p-4 text-center text-gray-400 text-sm">
                                    No new notifications
                                </div>
                            </div>
                        </div>

                        <div class="relative" id="userMenu">
                            <div class="flex items-center gap-2 cursor-pointer" onclick="toggleUserDropdown()">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">
                                    <?= strtoupper(substr($username, 0, 1)) ?>
                                </div>
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
                                    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                                        <span>🚪</span> <span data-i18n="logout">Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition" data-i18n="login">
                            Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="py-16 sm:py-24 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-10 lg:gap-16 items-center">

                <!-- Left Content -->
                <div class="animate-fade-up">
                    <div class="inline-block bg-red-100 text-red-700 px-4 py-2 rounded-full text-sm font-semibold mb-4" data-i18n="help_save_lives_today">
                        ✨ Help Save Lives Today
                    </div>
                    <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold leading-tight mb-6">
                        <span class="text-gray-900" data-i18n="donate">Donate </span>
                        <span class="bg-gradient-to-r from-red-600 to-red-800 bg-clip-text text-transparent" data-i18n="blood">Blood</span>
                        <span class="text-gray-900">,</span>
                        <br>
                        <span class="text-red-600" data-i18n="save_lives">Save Lives</span>
                    </h1>

                    <p class="text-lg text-gray-600 mb-8 leading-relaxed" data-i18n="hero_desc">
                        Join our community of generous donors and help patients in critical need. Every donation can save up to 3 lives. Be the hero someone needs today.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="becomedonor.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl hover:from-red-700 hover:to-red-800 transition transform hover:scale-105 text-center" data-i18n="become_a_donor">
                            Become a Donor
                        </a>
                        <a href="requestblood.php" class="border-2 border-red-600 text-red-600 px-8 py-4 rounded-xl font-bold hover:bg-red-50 transition text-center" data-i18n="search_blood_type">
                            Search Blood Type
                        </a>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-12">
                        <div class="text-center bg-red-600 text-white rounded-2xl p-4">
                            <h3 class="text-3xl font-bold"><?= $totalDonors ?>+</h3>
                            <p class="text-sm opacity-90" data-i18n="active_donors">Active Donors</p>
                        </div>
                        <div class="text-center bg-red-600 text-white rounded-2xl p-4">
                            <h3 class="text-3xl font-bold"><?= $totalRequests ?>+</h3>
                            <p class="text-sm opacity-90" data-i18n="lives_saved">Lives Saved</p>
                        </div>
                        <div class="text-center bg-red-600 text-white rounded-2xl p-4">
                            <h3 class="text-3xl font-bold"><?= $totalUsers ?>+</h3>
                            <p class="text-sm opacity-90" data-i18n="total_users">Total Users</p>
                        </div>
                    </div>
                </div>

                <!-- Right - Blood Type Card -->
                <div class="animate-fade-up" style="animation-delay: 0.2s;">
                    <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10">
                        <div class="text-center mb-10">
                            <div class="text-8xl mb-4 inline-block bg-red-100 p-6 rounded-full">🩸</div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-2" data-i18n="blood_availability">Blood Availability</h2>
                            <p class="text-gray-600" data-i18n="realtime_inventory">Real-time inventory status</p>
                        </div>

                        <!-- Blood Type Grid -->
                        <div class="grid grid-cols-4 gap-3 mb-6">
                            <div class="bg-gradient-to-br from-red-100 to-red-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-red-700 text-lg">A+</p>
                            </div>
                            <div class="bg-gradient-to-br from-blue-100 to-blue-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-blue-700 text-lg">B+</p>
                            </div>
                            <div class="bg-gradient-to-br from-yellow-100 to-yellow-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-yellow-700 text-lg">AB+</p>
                            </div>
                            <div class="bg-gradient-to-br from-purple-100 to-purple-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-purple-700 text-lg">O+</p>
                            </div>
                            <div class="bg-gradient-to-br from-red-100 to-red-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-red-700 text-lg">A-</p>
                            </div>
                            <div class="bg-gradient-to-br from-blue-100 to-blue-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-blue-700 text-lg">B-</p>
                            </div>
                            <div class="bg-gradient-to-br from-yellow-100 to-yellow-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-yellow-700 text-lg">AB-</p>
                            </div>
                            <div class="bg-gradient-to-br from-purple-100 to-purple-200 p-4 rounded-xl text-center hover:shadow-lg transition transform hover:scale-110">
                                <p class="font-bold text-purple-700 text-lg">O-</p>
                            </div>
                        </div>

                        <button class="w-full bg-red-600 text-white font-bold py-3 rounded-xl hover:bg-red-700 transition" data-i18n="view_full_inventory">
                            View Full Inventory
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center mb-12" data-i18n="why_choose_bloodlife">Why Choose BloodLife?</h2>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🎯</div>
                    <h3 class="text-xl font-bold mb-3" data-i18n="quick_easy">Quick & Easy</h3>
                    <p class="text-gray-600" data-i18n="quick_easy_desc">Simple registration process. Start donating in minutes with our user-friendly platform.</p>
                </div>

                

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🛡</div>
                    <h3 class="text-xl font-bold mb-3" data-i18n="safe_secure">Safe & Secure</h3>
                    <p class="text-gray-600" data-i18n="safe_secure_desc">Your health and data are our priority. All donations follow strict medical guidelines.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">📊</div>
                    <h3 class="text-xl font-bold mb-3" data-i18n="track_impact">Track Impact</h3>
                    <p class="text-gray-600" data-i18n="track_impact_desc">See how many lives your donations have saved with our transparent tracking system.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🎁</div>
                    <h3 class="text-xl font-bold mb-3" data-i18n="rewards">Rewards</h3>
                    <p class="text-gray-600" data-i18n="rewards_desc">Earn certificates and rewards for your generous contributions to the community.</p>
                </div>
<div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🌍</div>
                    <h3 class="text-xl font-bold mb-3" data-i18n="global_community">Global Community</h3>
                    <p class="text-gray-600" data-i18n="global_community_desc">Join thousands of donors making a difference in their communities every day.</p>
                </div>
            </div>
        </div>
    </section>

   
    <!-- CTA Section -->
    <section class="py-16 bg-gradient-to-r from-red-600 to-red-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold mb-6" data-i18n="ready_make_difference">Ready to Make a Difference?</h2>
            <p class="text-xl mb-8 opacity-90" data-i18n="ready_make_difference_desc">Join our community of lifesavers. Your donation today could save someone's tomorrow.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="register.php" class="bg-white text-red-600 px-8 py-4 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105" data-i18n="start_donating_now">
                    Start Donating Now
                </a>
                <a href="#" class="border-2 border-white text-white px-8 py-4 rounded-xl font-bold hover:bg-white hover:bg-opacity-10 transition" data-i18n="learn_more">
                    Learn More
                </a>
            </div>
        </div>
    </section>
 <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <h3 class="text-white font-bold text-lg mb-4">BloodLife</h3>
                    <p class="text-sm" data-i18n="save_lives_together">Connecting donors with those who need help. Save lives today.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4" data-i18n="quick_links">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-red-600 transition" data-i18n="about_us">About Us</a></li>
                        <li><a href="donor.php" class="hover:text-red-600 transition" data-i18n="donors">Donors</a></li>
                        
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
                    <div class="flex space-x-4">
                        <a href="#" class="hover:text-red-600 transition">Facebook</a>
                        <a href="#" class="hover:text-red-600 transition">Twitter</a>
                        <a href="#" class="hover:text-red-600 transition">Instagram</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 pt-8 text-center text-sm">
                <p>&copy; BloodLife. All rights reserved. | Privacy Policy | Terms of Service</p>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md mx-4 animate-fade-up relative">
            <!-- Close button -->
            <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>

            <div class="p-8">
                <div class="text-center mb-6">
                    <span class="text-4xl mb-2 block">🩸</span>
                    <h2 class="text-2xl font-bold text-gray-900" data-i18n="welcome_back">Welcome Back</h2>
                    <p class="text-gray-500 text-sm" data-i18n="signin_bloodlife">Sign in to your BloodLife account</p>
                </div>

                <form id="loginForm" class="space-y-4">
                    <div id="loginError" class="bg-red-50 border-l-2 border-red-500 p-4 rounded hidden">
                        <p class="text-red-700 text-sm" id="loginErrorText"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="username">Username</label>
                        <input type="text" name="username" id="loginUsername" data-i18n-placeholder="enter_username" placeholder="Enter your username" required
                               class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="password">Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="loginPassword" data-i18n-placeholder="enter_password" placeholder="Enter your password" required
                                   class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition" />
                            <button type="button" onclick="toggleModalPassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700" id="modalEyeBtn">
                                <svg id="modalEyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                <svg id="modalEyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                            <input type="checkbox" class="accent-red-600 w-4 h-4" />
                            <span data-i18n="remember_me">Remember me</span>
                        </label>
                        <a href="#" class="text-red-600 font-semibold hover:underline" data-i18n="forgot_password">Forgot password?</a>
                    </div>

                    <button type="submit" id="loginSubmitBtn" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-xl font-bold hover:shadow-xl hover:from-red-700 hover:to-red-800 transition transform hover:scale-[1.02] text-lg mt-2" data-i18n="sign_in">
                        Sign In →
                    </button>
                </form>

                <p class="text-center text-sm text-gray-500 mt-6">
                    <span data-i18n="new_to_bloodlife">New to BloodLife?</span> <a href="register.php" class="text-red-600 font-bold hover:underline" data-i18n="create_account">Create an account</a>
                </p>
            </div>
        </div>
    </div>

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
            const notifMenu = document.getElementById('notifMenu');
            const notifDropdown = document.getElementById('notifDropdown');
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');

            if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
                notifDropdown.classList.add('hidden');
            }
            if (userMenu && userDropdown && !userMenu.contains(e.target)) {
                userDropdown.classList.add('hidden');
            }
        });

        // Login Modal Password Toggle
        function toggleModalPassword() {
            const f = document.getElementById('loginPassword');
            const open = document.getElementById('modalEyeOpen');
            const closed = document.getElementById('modalEyeClosed');
            if (f.type === 'password') {
                f.type = 'text';
                open.classList.add('hidden');
                closed.classList.remove('hidden');
            } else {
                f.type = 'password';
                closed.classList.add('hidden');
                open.classList.remove('hidden');
            }
        }

        // Login Modal
        function openLoginModal() {
            document.getElementById('loginModal').classList.remove('hidden');
            document.getElementById('loginModal').classList.add('flex');
            document.getElementById('loginError').classList.add('hidden');
            document.getElementById('loginForm').reset();
        }

        function closeLoginModal() {
            document.getElementById('loginModal').classList.add('hidden');
            document.getElementById('loginModal').classList.remove('flex');
        }

        // Close modal on backdrop click
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) closeLoginModal();
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLoginModal();
        });

        // AJAX Login
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const username = document.getElementById('loginUsername').value.trim();
            const password = document.getElementById('loginPassword').value;
            const errorDiv = document.getElementById('loginError');
            const errorText = document.getElementById('loginErrorText');
            const submitBtn = document.getElementById('loginSubmitBtn');

            if (!username || !password) {
                errorDiv.classList.remove('hidden');
                errorText.textContent = 'Please enter both username and password.';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';

            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password) + '&ajax=1'
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorDiv.classList.remove('hidden');
                    errorText.textContent = data.message || 'Invalid username or password.';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Sign In →';
                }
            })
            .catch(function() {
                errorDiv.classList.remove('hidden');
                errorText.textContent = 'Connection error. Please try again.';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In →';
            });
        });

        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        mobileMenuToggle.addEventListener('click', function() {
            alert('Mobile menu placeholder - Implement your mobile menu here');
        });

        // Intersection Observer for fade-in animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });

        document.querySelectorAll('.hover\\:shadow-lg').forEach(el => {
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

</body>

</html>