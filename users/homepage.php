<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donation System - Save Lives</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
</head>

<body class="bg-gradient-to-b from-gray-50 to-gray-100">

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
                    <div class="text-3xl pulse-glow">🩸</div>
                    <div>
                        <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
                        <p class="text-xs text-gray-500">Save Lives Together</p>
                    </div>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="text-gray-700 hover:text-red-600 font-medium transition">Home</a>
                    <a href="#donors" class="text-gray-700 hover:text-red-600 font-medium transition">Donors</a>
                    <a href="#hospitals" class="text-gray-700 hover:text-red-600 font-medium transition">Hospitals</a>
                    <a href="#requests" class="text-gray-700 hover:text-red-600 font-medium transition">Requests</a>

                    <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition">
                        Login
                    </a>
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
                    <div class="inline-block bg-red-100 text-red-700 px-4 py-2 rounded-full text-sm font-semibold mb-4">
                        ✨ Help Save Lives Today
                    </div>

                    <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold leading-tight mb-6">
                        <span class="text-gray-900">Donate </span>
                        <span class="bg-gradient-to-r from-red-600 to-red-800 bg-clip-text text-transparent">Blood</span>
                        <span class="text-gray-900">,</span>
                        <br>
                        <span class="text-red-600">Save Lives</span>
                    </h1>

                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        Join our community of generous donors and help patients in critical need. Every donation can save up to 3 lives. Be the hero someone needs today.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="register.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-4 rounded-xl font-bold hover:shadow-xl hover:from-red-700 hover:to-red-800 transition transform hover:scale-105 text-center">
                            Become a Donor
                        </a>
                        <a href="requests.php" class="border-2 border-red-600 text-red-600 px-8 py-4 rounded-xl font-bold hover:bg-red-50 transition text-center">
                            Search Blood Type
                        </a>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-12">
                        <div class="text-center">
                            <h3 class="text-3xl font-bold text-red-600">250+</h3>
                            <p class="text-gray-600 text-sm">Active Donors</p>
                        </div>
                        <div class="text-center">
                            <h3 class="text-3xl font-bold text-red-600">120+</h3>
                            <p class="text-gray-600 text-sm">Lives Saved</p>
                        </div>
                        <div class="text-center">
                            <h3 class="text-3xl font-bold text-red-600">35+</h3>
                            <p class="text-gray-600 text-sm">Hospitals</p>
                        </div>
                    </div>
                </div>

                <!-- Right - Blood Type Card -->
                <div class="animate-fade-up" style="animation-delay: 0.2s;">
                    <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10">
                        <div class="text-center mb-10">
                            <div class="text-8xl mb-4 inline-block bg-red-100 p-6 rounded-full">🩸</div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-2">Blood Availability</h2>
                            <p class="text-gray-600">Real-time inventory status</p>
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

                        <button class="w-full bg-red-600 text-white font-bold py-3 rounded-xl hover:bg-red-700 transition">
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
            <h2 class="text-4xl font-bold text-center mb-12">Why Choose BloodLife?</h2>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🎯</div>
                    <h3 class="text-xl font-bold mb-3">Quick & Easy</h3>
                    <p class="text-gray-600">Simple registration process. Start donating in minutes with our user-friendly platform.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🏥</div>
                    <h3 class="text-xl font-bold mb-3">Network of Hospitals</h3>
                    <p class="text-gray-600">Connect with over 35+ hospitals nationwide to ensure your donation reaches those in need.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🛡️</div>
                    <h3 class="text-xl font-bold mb-3">Safe & Secure</h3>
                    <p class="text-gray-600">Your health and data are our priority. All donations follow strict medical guidelines.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">📊</div>
                    <h3 class="text-xl font-bold mb-3">Track Impact</h3>
                    <p class="text-gray-600">See how many lives your donations have saved with our transparent tracking system.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🎁</div>
                    <h3 class="text-xl font-bold mb-3">Rewards</h3>
                    <p class="text-gray-600">Earn certificates and rewards for your generous contributions to the community.</p>
                </div>

                <div class="p-8 border-2 border-gray-200 rounded-2xl hover:border-red-600 hover:shadow-xl transition transform hover:-translate-y-2">
                    <div class="text-4xl mb-4">🌍</div>
                    <h3 class="text-xl font-bold mb-3">Global Community</h3>
                    <p class="text-gray-600">Join thousands of donors making a difference in their communities every day.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics -->

<section class="py-10">

    <div class="max-w-7xl mx-auto px-6">

        <div class="grid md:grid-cols-4 gap-6">

            <div class="bg-white p-8 rounded-2xl shadow">
                <h2 class="text-4xl font-bold text-red-700">150+</h2>
                <p>Active Donors</p>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow">
                <h2 class="text-4xl font-bold text-red-700">50+</h2>
                <p>Hospitals</p>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow">
                <h2 class="text-4xl font-bold text-red-700">300+</h2>
                <p>Blood Requests</p>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow">
                <h2 class="text-4xl font-bold text-red-700">100%</h2>
                <p>Lives Saved</p>
            </div>

        </div>

    </div>

</section>


    <!-- CTA Section -->
    <section class="py-16 bg-gradient-to-r from-red-600 to-red-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold mb-6">Ready to Make a Difference?</h2>
            <p class="text-xl mb-8 opacity-90">Join our community of lifesavers. Your donation today could save someone's tomorrow.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="register.php" class="bg-white text-red-600 px-8 py-4 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105">
                    Start Donating Now
                </a>
                <a href="#" class="border-2 border-white text-white px-8 py-4 rounded-xl font-bold hover:bg-white hover:bg-opacity-10 transition">
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
                    <p class="text-sm">Connecting donors with those who need help. Save lives today.</p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-red-600 transition">About Us</a></li>
                        <li><a href="#" class="hover:text-red-600 transition">Donors</a></li>
                        <li><a href="#" class="hover:text-red-600 transition">Hospitals</a></li>
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
                        <a href="#" class="hover:text-red-600 transition">Facebook</a>
                        <a href="#" class="hover:text-red-600 transition">Twitter</a>
                        <a href="#" class="hover:text-red-600 transition">Instagram</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 pt-8 text-center text-sm">
                <p>&copy; 2024 BloodLife. All rights reserved. | Privacy Policy | Terms of Service</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
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

</body>

</html>

</section>


</body>

</html>