<?php
session_start();
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errorMessage = 'Invalid , try again';
    } else {
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = '';
        $dbName = 'blood_donation';

        $conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn) {
            mysqli_set_charset($conn, 'utf8mb4');
            $stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE email = ? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $storedPassword);
                if (mysqli_stmt_fetch($stmt)) {
                    $isValid = password_verify($password, $storedPassword) || $password === $storedPassword;
                    if ($isValid) {
                        $_SESSION['user_email'] = $email;
                        mysqli_stmt_close($stmt);
                        mysqli_close($conn);
                        header('Location: dashboard.php');
                        exit;
                    }
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_close($conn);
        }
        if (empty($errorMessage)) {
            $errorMessage = 'Invalid , try again';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Blood Donation System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up { animation: slideInUp 0.6s ease-out; }
        input:focus { outline: none; }
        .input-focus { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-gradient-to-br from-red-50 to-red-100 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <span class="text-3xl">🩸</span>
                <span class="font-bold text-xl text-red-700">Blood Donation</span>
            </div>
            <a href="homepage.php" class="text-gray-600 hover:text-red-700 transition">Back to Home</a>
        </div>
    </nav>

    <!-- Login Container -->
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md animate-slide-up">
            
            <!-- Login Card -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden backdrop-blur-lg">
                
                <!-- Header Section -->
                <div class="bg-gradient-to-r from-red-600 to-red-800 px-8 py-12 text-center">
                    <div class="text-6xl mb-4">🩸</div>
                    <h1 class="text-3xl font-bold text-white">Welcome Back</h1>
                    <p class="text-red-100 mt-2">Sign in to your account</p>
                </div>

                <!-- Form Section -->
                <div class="p-8">
                    <form method="POST" id="loginForm" class="space-y-5">
                        <!-- Error Message -->
                        <div id="errorMessage" class="<?php echo $errorMessage ? '' : 'hidden'; ?> bg-red-50 border-l-2 border-red-500 p-4 rounded">
                            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($errorMessage); ?></p>
                        </div>
                        
                        <!-- Email Field -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400">📧</span>
                                <input 
                                    type="email" 
                                    name="email" 
                                    id="email"
                                    placeholder="your.email@example.com"
                                    required
                                    class="input-focus w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition placeholder-gray-400"
                                >
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400">🔒</span>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password"
                                    placeholder="••••••••"
                                    required
                                    class="input-focus w-full pl-10 pr-12 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 transition"
                                >
                                <button 
                                    type="button" 
                                    id="togglePassword"
                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 transition"
                                >
                                    👁️
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="remember" class="w-4 h-4 text-red-600 rounded cursor-pointer">
                                <span class="text-sm text-gray-600">Remember me</span>
                            </label>
                            <a href="#" class="text-sm text-red-600 hover:text-red-700 font-semibold">Forgot password?</a>
                        </div>

                        <!-- Login Button -->
                        <button type="submit"
                            class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-bold py-3 rounded-xl hover:shadow-lg hover:from-red-700 hover:to-red-800 transition duration-300 transform hover:scale-105 flex items-center justify-center space-x-2"
                        >
                            <span>Sign In</span>
                            <span>→</span>
                        </button>

                        
                    </form>

                    <!-- Divider -->
                    <div class="relative my-6">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Or continue with</span>
                        </div>
                    </div>

                    

                    <!-- Sign Up Link -->
                    <p class="text-center text-gray-600 mt-6">
                        Don't have an account? 
                        <a href="signup.php" class="text-red-600 font-semibold hover:text-red-700">Sign up</a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-gray-600 text-sm mt-6">
                🩸 Every drop counts. Help save lives today.
            </p>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                togglePassword.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                togglePassword.textContent = '👁️';
            }
        });

        // Form Validation
        const loginForm = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');

        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            if (!email.includes('@')) {
                e.preventDefault();
                showError('Please enter a valid email address');
            } else if (password.length < 6) {
                e.preventDefault();
                showError('Password must be at least 6 characters');
            }
        });

        function showError(message) {
            errorMessage.classList.remove('hidden');
            errorMessage.querySelector('p').textContent = message;
            setTimeout(() => errorMessage.classList.add('hidden'), 5000);
        }

        // Input Animation
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.querySelector('label').classList.add('text-red-600');
            });
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.querySelector('label').classList.remove('text-red-600');
            });
        });
    </script>

</body>
</html>