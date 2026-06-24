<?php
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $fullName = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if ($password === '' || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = '';
        $dbName = 'blood_donation';

        $conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

        if (!$conn) {
            $errors[] = 'Unable to connect to the database. Please try again later.';
        } else {
            mysqli_set_charset($conn, 'utf8mb4');

            $checkStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
            mysqli_stmt_bind_param($checkStmt, 's', $email);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] = 'This email address is already registered.';
            }

            mysqli_stmt_close($checkStmt);

            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = mysqli_prepare($conn, 'INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
                mysqli_stmt_bind_param($insertStmt, 'sss', $fullName, $email, $password);

                if (mysqli_stmt_execute($insertStmt)) {
                    $success = 'Your account has been created successfully. You can now log in.';
                    $fullName = $email = '';
                } else {
                    $errors[] = 'Unable to register right now. Please try again later.';
                }

                mysqli_stmt_close($insertStmt);
            }

            mysqli_close($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Sign Up - Blood Donation</title>
    <style>
        .hero-bg {
            background-image: radial-gradient(circle at top left, rgba(220, 38, 38, 0.18), transparent 35%),
                              radial-gradient(circle at bottom right, rgba(185, 28, 28, 0.18), transparent 30%);
        }
        .pulse-red {
            animation: pulse 2.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.03); opacity: 0.85; }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-red-50 via-white to-red-100  text-gray-900">
    <main class="hero-bg  flex items-center justify-center px-4 py-8">
        <div class="max-w-5xl w-full grid lg:grid-cols-2 gap-8 items-center">
            <section class="space-y-8 p-8 bg-white/95 rounded-[32px] shadow-2xl border border-red-100">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 bg-red-600/10 text-red-700 rounded-3xl flex items-center justify-center text-3xl pulse-red">🩸</div>
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-red-600 font-semibold">Join the lifesavers</p>
                        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900">Sign up for blood donation support</h1>
                    </div>
                </div>
                <p class="text-gray-600 leading-relaxed text-base sm:text-lg">Become a registered donor, connect with hospitals, and help patients in urgent need. Your donation can save multiple lives.</p>

                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-3xl bg-red-50 border border-red-100 p-5">
                        <p class="text-3xl font-bold text-red-600">250+</p>
                        <p class="text-sm text-gray-500 mt-2">Active donors</p>
                    </div>
                    <div class="rounded-3xl bg-red-50 border border-red-100 p-5">
                        <p class="text-3xl font-bold text-red-600">120+</p>
                        <p class="text-sm text-gray-500 mt-2">Requests fulfilled</p>
                    </div>
                </div>

                <div class="rounded-3xl bg-red-700 text-white p-6 shadow-lg">
                    <p class="text-sm uppercase tracking-[0.2em] opacity-90">Why sign up?</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li class="flex items-start gap-3"><span class="mt-1">✔</span> Fast donor registration</li>
                        <li class="flex items-start gap-3"><span class="mt-1">✔</span> Donor & request management</li>
                        <li class="flex items-start gap-3"><span class="mt-1">✔</span> Safe, trusted network</li>
                    </ul>
                </div>
            </section>

            <section class="p-8 lg:p-12 bg-white rounded-[32px] shadow-2xl border border-red-100">
                <div class="mb-8 text-center">
                    <p class="text-sm text-red-600 uppercase tracking-[0.25em] font-semibold">Create account</p>
                    <h2 class="text-3xl font-bold text-gray-900 mt-3">Register as a donor</h2>
                    <p class="text-gray-500 mt-2">Quick and easy registration to become part of our blood donation community.</p>
                </div>

                <form action="signup.php" method="POST" class="space-y-5">
                    <?php if (!empty($errors)): ?>
                        <div class="rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success !== ''): ?>
                        <div class="rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-2">
                        <label for="username" class="text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="username" name="username" placeholder="Your full name" value="<?php echo htmlspecialchars($fullName ?? ''); ?>" required class="w-full rounded-3xl border border-gray-200 bg-red-50 px-4 py-3 text-sm text-gray-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100 transition" />
                    </div>
                    <div class="space-y-2">
                        <label for="email" class="text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="example@email.com" value="<?php echo htmlspecialchars($email ?? ''); ?>" required class="w-full rounded-3xl border border-gray-200 bg-red-50 px-4 py-3 text-sm text-gray-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100 transition" />
                    </div>
                    <div class="space-y-2">
                        <label for="password" class="text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" placeholder="Create a password" required class="w-full rounded-3xl border border-gray-200 bg-red-50 px-4 py-3 text-sm text-gray-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100 transition" />
                    </div>
                    <div class="space-y-2">
                        <label for="confirm_password" class="text-sm font-medium text-gray-700">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required class="w-full rounded-3xl border border-gray-200 bg-red-50 px-4 py-3 text-sm text-gray-800 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100 transition" />
                    </div>

                    <button type="submit" name="signup" class="w-full rounded-3xl bg-gradient-to-r from-red-600 to-red-700 py-3 text-sm font-semibold text-white shadow-lg hover:from-red-700 hover:to-red-800 transition">Sign Up Now</button>
                </form>

                <p class="text-center text-sm text-gray-500 mt-6">Already have an account? <a href="login.php" class="font-semibold text-red-600 hover:text-red-700">Log in</a></p>
            </section>
        </div>
    </main>

    <footer class="bg-white py-8">
        <div class="max-w-6xl mx-auto px-4 text-center text-sm text-gray-500">
            &copy; 2026 Blood Donation and Request System. All rights reserved.
        </div>
    </footer>
</body>

</html>