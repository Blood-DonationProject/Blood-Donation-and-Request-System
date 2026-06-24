<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Blood Donation System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes countUp {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .animate-slide-in { animation: slideIn 0.6s ease-out; }
        .animate-count-up { animation: countUp 0.8s ease-out; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-50 to-red-50">

    <!-- Sidebar Navigation -->
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg hidden md:flex flex-col sticky top-0 self-start h-screen overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <span class="text-3xl">🩸</span>
                    <div>
                        <h1 class="font-bold text-lg text-red-700">BloodLife</h1>
                        <p class="text-xs text-gray-500">Dashboard</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-red-50 text-red-700 rounded-lg font-semibold">
                    <span>📊</span>
                    <span>Overview</span>
                </a>

                <a href="donor.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>👥</span>
                    <span>Donors</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>🏥</span>
                    <span>Hospitals</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>📋</span>
                    <span>Blood Requests</span>
                </a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <span>🎓</span>
                    <span>Certificates</span>
                </a>
            </nav>

            <div class="p-4 border-t border-gray-200">
                <a href="homepage.php" class="w-full bg-red-600 text-white flex justify-center py-2 rounded-lg font-semibold hover:bg-red-700 transition">
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Navigation -->
            <nav class="bg-white shadow-md sticky top-0 z-40">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="text-2xl font-bold text-gray-800">
                        Welcome back! 👋
                    </div>
                    <div class="flex items-center space-x-6">
                        <button class="relative">
                            <span class="text-2xl">🔔</span>
                            <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                        </button>
                        <div class="flex items-center space-x-3">
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">John Donor</p>
                                <p class="text-xs text-gray-500">Active Donor</p>
                            </div>
                            <div class="w-10 h-10 bg-gradient-to-br from-red-400 to-red-600 text-white rounded-full flex items-center justify-center font-bold">
                                JD
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <div class="p-4 md:p-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

                    <!-- Total Donors Card -->
                    <div class="stat-card bg-gradient-to-br from-red-600 to-red-800 text-white p-8 rounded-2xl shadow-lg animate-slide-in">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-red-100 text-sm font-medium">Total Donors</p>
                                <h2 class="text-5xl font-bold mt-2 animate-count-up">250</h2>
                            </div>
                            <span class="text-4xl opacity-30">👥</span>
                        </div>
                        <div class="flex items-center text-red-100 text-sm">
                            <span class="text-green-300">↑</span>
                            <span>12% increase this month</span>
                        </div>
                    </div>

                    <!-- Blood Requests Card -->
                    <div class="stat-card bg-white shadow-lg p-8 rounded-2xl animate-slide-in" style="animation-delay: 0.1s;">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Blood Requests</p>
                                <h2 class="text-5xl font-bold mt-2 text-red-700 animate-count-up">120</h2>
                            </div>
                            <span class="text-4xl">🩸</span>
                        </div>
                        <div class="flex items-center text-gray-500 text-sm">
                            <span class="text-orange-500">→</span>
                            <span>5 pending approvals</span>
                        </div>
                    </div>

                    <!-- Hospitals Card -->
                    <div class="stat-card bg-white shadow-lg p-8 rounded-2xl animate-slide-in" style="animation-delay: 0.2s;">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Partner Hospitals</p>
                                <h2 class="text-5xl font-bold mt-2 text-blue-700 animate-count-up">35</h2>
                            </div>
                            <span class="text-4xl">🏥</span>
                        </div>
                        <div class="flex items-center text-gray-500 text-sm">
                            <span class="text-green-500">✓</span>
                            <span>All operational</span>
                        </div>
                    </div>

                    <!-- Certificates Card -->
                    <div class="stat-card bg-white shadow-lg p-8 rounded-2xl animate-slide-in" style="animation-delay: 0.3s;">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Certificates Issued</p>
                                <h2 class="text-5xl font-bold mt-2 text-purple-700 animate-count-up">180</h2>
                            </div>
                            <span class="text-4xl">🎓</span>
                        </div>
                        <div class="flex items-center text-gray-500 text-sm">
                            <span class="text-blue-500">✓</span>
                            <span>Life-changing impact</span>
                        </div>
                    </div>

                </div>

                <!-- Charts and Activities Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Recent Activities -->
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Recent Donation Activity</h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-xl border-l-4 border-green-500">
                                <div>
                                    <p class="font-semibold text-gray-800">Blood Type A+ Collected</p>
                                    <p class="text-sm text-gray-600">From John Donor · 2 hours ago</p>
                                </div>
                                <span class="text-3xl">✓</span>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl border-l-4 border-blue-500">
                                <div>
                                    <p class="font-semibold text-gray-800">Blood Type B- Used</p>
                                    <p class="text-sm text-gray-600">Emergency transfusion · General Hospital</p>
                                </div>
                                <span class="text-3xl">→</span>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl border-l-4 border-purple-500">
                                <div>
                                    <p class="font-semibold text-gray-800">Certificate Issued</p>
                                    <p class="text-sm text-gray-600">Donation milestone reached · 50 donations</p>
                                </div>
                                <span class="text-3xl">🎓</span>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-xl border-l-4 border-orange-500">
                                <div>
                                    <p class="font-semibold text-gray-800">Request Accepted</p>
                                    <p class="text-sm text-gray-600">Request ID #5234 · Type O+ · City Hospital</p>
                                </div>
                                <span class="text-3xl">👍</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="space-y-4">
                        <!-- Blood Availability -->
                        <div class="bg-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Blood Availability</h3>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">A+</span>
                                        <span class="font-semibold text-red-600">45 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: 75%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">B+</span>
                                        <span class="font-semibold text-blue-600">38 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: 63%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">O+</span>
                                        <span class="font-semibold text-purple-600">52 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-purple-500 h-2 rounded-full" style="width: 87%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">AB+</span>
                                        <span class="font-semibold text-pink-600">25 Units</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-pink-500 h-2 rounded-full" style="width: 42%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="bg-gradient-to-br from-red-600 to-red-800 text-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-lg font-bold mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <button class="w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-2 rounded-lg font-semibold transition">
                                    Schedule Donation
                                </button>
                                <button class="w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white py-2 rounded-lg font-semibold transition">
                                    View Requests
                                </button>
                                <button class="w-full bg-white text-red-700 py-2 rounded-lg font-semibold hover:bg-gray-100 transition">
                                    My Profile
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Animate count-up numbers
        function animateCounter(element, target, duration = 1000) {
            let current = 0;
            const increment = target / (duration / 30);
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 30);
        }

        // Start counter animations when page loads
        document.querySelectorAll('.animate-count-up').forEach(counter => {
            const target = parseInt(counter.textContent);
            animateCounter(counter, target);
        });

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarItems = document.querySelectorAll('nav a');
            sidebarItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (!this.href.includes('#')) {
                        e.preventDefault();
                    }
                    sidebarItems.forEach(i => i.classList.remove('bg-red-50', 'text-red-700'));
                    this.classList.add('bg-red-50', 'text-red-700');
                });
            });
        });
    </script>

</body>
</html>