<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Handle inline login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
    $username = trim($_POST['login_user'] ?? '');
    $password = $_POST['login_pass'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = 'Please enter both username and password.';
    } else {
        $loginSuccess = false;

        if ($username === 'admin' && $password === 'password123') {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = 'admin';
            $_SESSION['user_email'] = 'admin@bloodlife.local';
            $loginSuccess = true;
        }

        if (!$loginSuccess && $username === 'user' && $password === '123456') {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = 'user';
            $loginSuccess = true;
        }

        if (!$loginSuccess) {
            $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $storedPassword = $row['password'];
                    if ($password === $storedPassword || password_verify($password, $storedPassword)) {
                        $_SESSION['logged_in'] = true;
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['user_id'] = $row['user_id'];
                        $loginSuccess = true;
                    }
                }
                $stmt->close();
            }
        }

        if ($loginSuccess) {
            $isLoggedIn = true;
            header('Location: donateform.php');
            exit;
        } else {
            $loginError = 'Invalid username or password.';
        }
    }
}

$message = '';
$messageType = '';
$editMode = false;
$editData = null;

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'] ?? 0;

    // DELETE
    if (isset($_GET['delete'])) {
        $deleteId = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM donor WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $deleteId, $userId);
        if ($stmt->execute()) {
            $message = 'Donor record deleted successfully.';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete record.';
            $messageType = 'error';
        }
        $stmt->close();
        header('Location: donateform.php' . (strpos($_SERVER['QUERY_STRING'], 'delete') !== false ? '' : '?msg=deleted'));
        exit;
    }

    // UPDATE - pre-fill form
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $stmt = $conn->prepare("SELECT d.*, d.blood_groups AS blood_gp_name FROM donor d WHERE d.id = ? AND d.user_id = ?");
        $stmt->bind_param("ii", $editId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editData = $result->fetch_assoc();
        $stmt->close();
        if ($editData) {
            $editMode = true;
        } else {
            $message = 'Record not found.';
            $messageType = 'error';
        }
    }

    // CREATE or UPDATE via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blood_groups'])) {
        $full_name = trim($_POST['patient_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $age = intval($_POST['age'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $blood_groups = trim($_POST['blood_groups'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);
        $last_donation_date = trim($_POST['last_donation_date'] ?? '') ?: null;
        $available_status = 'Unavailable';
        $updateId = intval($_POST['update_id'] ?? 0);

        if (!$userId) {
            $message = 'You must be logged in to register as a donor.';
            $messageType = 'error';
        } elseif ($full_name === '') {
            $message = 'Please enter the patient name.';
            $messageType = 'error';
        } elseif (empty($gender)) {
            $message = 'Please select a gender.';
            $messageType = 'error';
        } elseif ($age < 18) {
            $message = 'You must be at least 18 years old to donate.';
            $messageType = 'error';
        } elseif ($phone === '') {
            $message = 'Please enter a contact number.';
            $messageType = 'error';
        } elseif ($address === '') {
            $message = 'Please enter your address.';
            $messageType = 'error';
        } elseif (empty($blood_groups)) {
            $message = 'Please select a blood type.';
            $messageType = 'error';
        } elseif ($weight < 100) {
            $message = 'Weight must be at least 100 lb.';
            $messageType = 'error';
        } else {
            try {
                if ($updateId > 0) {
                    $stmt = $conn->prepare("UPDATE donor SET full_name=?, gender=?, date_of_birth=?, age=?, blood_groups=?, phone=?, email=?, address=?, weight=?, last_donation_date=?, available_status=? WHERE id=? AND user_id=?");
                    $stmt->bind_param("sssissssdssii", $full_name, $gender, $date_of_birth, $age, $blood_groups, $phone, $email, $address, $weight, $last_donation_date, $available_status, $updateId, $userId);
                } else {
                    $stmt = $conn->prepare("INSERT INTO donor (user_id, full_name, gender, date_of_birth, age, blood_groups, phone, email, address, weight, last_donation_date, available_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssissssdss", $userId, $full_name, $gender, $date_of_birth, $age, $blood_groups, $phone, $email, $address, $weight, $last_donation_date, $available_status);
                }

                if ($stmt->execute()) {
                    $message = $updateId > 0 ? 'Donor record updated successfully!' : 'Donor registration submitted successfully! Your status is pending approval.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to save registration. Please try again.';
                    $messageType = 'error';
                }
                $stmt->close();
            } catch (Exception $e) {
                $message = 'An error occurred. Please try again later.';
                $messageType = 'error';
            }
        }

        if ($messageType === 'success') {
            header('Location: donateform.php?msg=' . ($updateId > 0 ? 'updated' : 'created'));
            exit;
        }
        $editMode = false;
    }

    // Fetch user's donor records
    $myDonors = [];
    $stmt = $conn->prepare("SELECT d.*, d.blood_groups AS blood_gp_name, u.username FROM donor d LEFT JOIN users u ON d.user_id = u.id WHERE d.user_id = ? ORDER BY d.id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $myResult = $stmt->get_result();
    if ($myResult && $myResult->num_rows > 0) {
        $myDonors = $myResult->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();

    // URL message redirect
    if (isset($_GET['msg'])) {
        $msg = $_GET['msg'];
        if ($msg === 'created') { $message = 'Donor registration submitted successfully! Your status is pending approval.'; $messageType = 'success'; }
        elseif ($msg === 'updated') { $message = 'Donor record updated successfully!'; $messageType = 'success'; }
        elseif ($msg === 'deleted') { $message = 'Donor record deleted successfully.'; $messageType = 'success'; }
    }
}

if ($isLoggedIn):
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Donate Blood – BloodLife</title>
  <script>
    (function() {
      var t = localStorage.getItem('bloodlife-theme');
      if (t === 'dark') document.documentElement.classList.add('dark');
    })();
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class'
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="../assets/js/translations.js"></script>
  <script src="../assets/js/i18n.js"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <style>
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
    .animate-fade-up { animation: fadeInUp 0.6s ease-out; }
    .blood-type-btn input[type="radio"] { display: none; }
    .blood-type-btn input[type="radio"]:checked+label {
      background: linear-gradient(to bottom right, #dc2626, #b91c1c);
      color: white; border-color: #dc2626;
      transform: scale(1.08);
      box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
    }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
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
    html.dark .bg-red-50 { background-color: rgba(220, 38, 38, 0.15) !important; }
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
  </style>
</head>

<body class="bg-gradient-to-b from-pink-50 to-pink-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <a href="donordashboard.php" class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </a>
        <div class="hidden md:flex items-center space-x-8">
          <a href="donordashboard.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
          <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="profile.php" class="flex items-center gap-2 hover:text-red-600 transition">
            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">A</div>
            <span class="font-medium text-gray-700" id="navName">Ahmed</span>
          </a>
          <a href="#" id="navAuthBtn" onclick="bloodlifeLogout(); return false;"
            class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition text-sm">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-12">
    <div class="max-w-3xl mx-auto px-4 text-center animate-fade-up">
      <div class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-semibold mb-3">🩸 <span data-i18n="ready_to_save">Ready to Save a Life?</span></div>
      <h1 class="text-4xl font-bold mb-2" data-i18n="donate_blood_form"><?= $editMode ? 'Edit Donor Record' : 'Donate Blood' ?></h1>
      <p class="text-lg opacity-90" data-i18n="donate_blood_desc"><?= $editMode ? 'Update your donor registration details below.' : 'Fill in the form below so we can prepare your donation appointment and verify your eligibility.' ?></p>
    </div>
  </section>

  <?php if (!empty($message)): ?>
  <div class="max-w-2xl mx-auto px-4 mt-6">
    <div class="rounded-xl p-4 <?= $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Steps Bar -->
  <section class="bg-white border-b border-gray-100 py-5">
    <div class="max-w-3xl mx-auto px-4">
      <div class="flex items-center justify-between">
        <div class="flex flex-col items-center">
          <div class="w-10 h-10 rounded-full bg-red-600 text-white flex items-center justify-center font-bold text-lg">1</div>
          <p class="text-xs text-red-600 font-semibold mt-1">Patient Info</p>
        </div>
        <div class="flex-1 h-1 bg-red-200 mx-2 rounded"></div>
        <div class="flex flex-col items-center">
          <div class="w-10 h-10 rounded-full bg-red-200 text-red-600 flex items-center justify-center font-bold text-lg">2</div>
          <p class="text-xs text-gray-500 mt-1">Blood Details</p>
        </div>
        <div class="flex-1 h-1 bg-gray-200 mx-2 rounded"></div>
        <div class="flex flex-col items-center">
          <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-lg">3</div>
          <p class="text-xs text-gray-500 mt-1">Confirm</p>
        </div>
      </div>
    </div>
  </section>

  <section class="py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6 animate-fade-up">

      <form method="POST" action="" class="space-y-6">
        <?php if ($editMode && $editData): ?>
          <input type="hidden" name="update_id" value="<?= $editData['id'] ?>" />
        <?php endif; ?>

        <!-- Personal Information Card -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">👤</div>
            <h2 class="text-xl font-bold text-gray-900">Personal Information</h2>
          </div>
          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Patient Name <span class="text-red-500">*</span></label>
              <input type="text" name="patient_name" placeholder="Full name of the patient" required
                value="<?= htmlspecialchars($editData['full_name'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
              <select name="gender" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                <option value="">Select gender</option>
                <option value="Male" <?= ($editData['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($editData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($editData['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Date Of Birth</label>
              <input type="date" name="date_of_birth"
                value="<?= htmlspecialchars($editData['date_of_birth'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Age <span class="text-red-500">*</span></label>
              <input type="number" name="age" placeholder="Min. 18 years" min="18" required
                value="<?= htmlspecialchars($editData['age'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
          </div>
        </div>

        <!-- Contact Details -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📞</div>
            <h2 class="text-xl font-bold text-gray-900">Contact Details</h2>
          </div>
          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
              <input type="email" name="email" placeholder="Your email address" required
                value="<?= htmlspecialchars($editData['email'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
              <input type="text" name="contact" placeholder="+959 300 000000" required
                value="<?= htmlspecialchars($editData['phone'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
            <textarea name="address" placeholder="Your address"
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" rows="3"><?= htmlspecialchars($editData['address'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Medical Details -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🩸</div>
            <h2 class="text-xl font-bold text-gray-900">Medical Details</h2>
          </div>
          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type <span class="text-red-500">*</span></label>
              <select name="blood_groups" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                <option value="">Select blood type</option>
                <?php
                $blood_groups = $conn->query("SELECT blood_gp_name FROM blood_groups ORDER BY blood_gp_name");
                if ($blood_groups && $blood_groups->num_rows > 0):
                    while ($bg = $blood_groups->fetch_assoc()):
                ?>
                <option value="<?= htmlspecialchars($bg['blood_gp_name']) ?>" <?= ($editData['blood_groups'] ?? '') === $bg['blood_gp_name'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                <?php
                    endwhile;
                endif;
                ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Weight (lb) <span class="text-red-500">*</span></label>
              <input type="number" name="weight" placeholder="Min. 100 lb" min="100" required
                value="<?= htmlspecialchars($editData['weight'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Last Donation Date</label>
              <input type="date" name="last_donation_date"
                value="<?= htmlspecialchars($editData['last_donation_date'] ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="grid grid-cols-2 gap-4">
          <?php if ($editMode): ?>
            <a href="donateform.php" class="border-2 border-gray-300 text-gray-600 py-4 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition text-center text-sm">Cancel Edit</a>
          <?php else: ?>
            <a href="donordashboard.php" class="border-2 border-gray-300 text-gray-600 py-4 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition text-center text-sm" data-i18n="cancel">Cancel</a>
          <?php endif; ?>
          <button type="submit" class="bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-xl font-bold hover:shadow-xl transition transform hover:scale-105 text-sm">
            <?= $editMode ? 'Update Record' : 'Submit Donation Request 🩸' ?>
          </button>
        </div>
      </form>

    </div>
  </section>

  <!-- My Donor Records (CRUD Read) -->
  <section class="pb-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
      <div class="bg-white rounded-2xl shadow p-6 sm:p-8">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📋</div>
            <div>
              <h2 class="text-xl font-bold text-gray-900">My Donor Records</h2>
              <p class="text-sm text-gray-500">View, edit, or delete your registration entries.</p>
            </div>
          </div>
          <span class="text-sm text-gray-500">Total: <?= count($myDonors) ?></span>
        </div>

        <?php if (count($myDonors) > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">ID</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Name</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Blood Type</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Age</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Phone</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Status</th>
                <th class="text-left text-gray-500 font-semibold pb-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <?php foreach ($myDonors as $d): ?>
                <?php
                  $statusColor = match($d['available_status'] ?? '') {
                    'Available' => 'bg-green-100 text-green-700',
                    'Unavailable' => 'bg-red-100 text-red-700',
                    default => 'bg-gray-100 text-gray-600',
                  };
                ?>
              <tr class="hover:bg-gray-50">
                <td class="py-3 pr-4 font-medium text-gray-700">#<?= $d['id'] ?></td>
                <td class="py-3 pr-4 text-gray-800 font-medium"><?= htmlspecialchars($d['full_name']) ?></td>
                <td class="py-3 pr-4">
                  <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs">
                    <?= htmlspecialchars($d['blood_gp_name'] ?? 'N/A') ?>
                  </span>
                </td>
                <td class="py-3 pr-4 text-gray-600"><?= (int)$d['age'] ?></td>
                <td class="py-3 pr-4 text-gray-600"><?= htmlspecialchars($d['phone']) ?></td>
                <td class="py-3 pr-4">
                  <span class="<?= $statusColor ?> text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($d['available_status']) ?></span>
                </td>
                <td class="py-3">
                  <div class="flex gap-2">
                    <a href="donateform.php?edit=<?= $d['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Edit</a>
                    <a href="donateform.php?delete=<?= $d['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold text-xs" onclick="return confirm('Delete this donor record?')">Delete</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="border-2 border-dashed border-gray-200 rounded-xl p-10 text-center">
          <div class="text-4xl mb-3">🩸</div>
          <p class="text-gray-500 text-lg mb-2">No donor records yet.</p>
          <p class="text-gray-400 text-sm">Fill in the form above to register as a blood donor.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-12 mt-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-4 gap-8 mb-8">
        <div>
          <h3 class="text-white font-bold text-lg mb-4">BloodLife</h3>
          <p class="text-sm" data-i18n="save_lives_together">Connecting donors with those who need help.</p>
        </div>
        <div>
          <h4 class="text-white font-bold mb-4" data-i18n="quick_links">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="index.php" class="hover:text-red-400 transition" data-i18n="home">Home</a></li>
            <li><a href="donor.php" class="hover:text-red-400 transition" data-i18n="donors">Donors</a></li>
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
          <div class="flex space-x-4 text-sm">
            <a href="#" class="hover:text-red-400 transition">Facebook</a>
            <a href="#" class="hover:text-red-400 transition">Twitter</a>
            <a href="#" class="hover:text-red-400 transition">Instagram</a>
          </div>
        </div>
      </div>
      <div class="border-t border-gray-700 pt-8 text-center text-sm">
        <p>&copy; BloodLife. <span data-i18n="all_rights_reserved">All rights reserved.</span></p>
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
    const name = localStorage.getItem('bloodlife_user_name');
    if (name) document.getElementById('navName').textContent = name;
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
<?php else: ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login – BloodLife</title>
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
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
  </style>
</head>

<body class="bg-gradient-to-b from-pink-50 to-pink-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <a href="index.php" class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </a>
        <div class="hidden md:flex items-center space-x-8">
          <a href="index.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="home">Home</a>
          <a href="donor.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="donors">Donors</a>
          <a href="bloodrequest.php" class="text-gray-700 hover:text-red-600 font-medium transition" data-i18n="requests">Requests</a>
          <button type="button" class="theme-toggle-btn relative w-10 h-10 rounded-lg border-2 border-gray-200 bg-gray-50 flex items-center justify-center cursor-pointer hover:border-red-400 transition" aria-label="Toggle theme" onclick="toggleTheme()"><span class="theme-icon-sun">☀️</span><span class="theme-icon-moon" style="display:none">🌙</span></button>
          <select class="lang-toggle-select" aria-label="Language" style="font-size:0.8125rem;font-weight:600;border-radius:0.5rem;border:1px solid #d1d5db;background-color:#f9fafb;color:#374151;padding:6px 10px;cursor:pointer;">
            <option value="en">EN</option>
            <option value="my">MY</option>
          </select>
          <a href="register.php" class="border-2 border-red-600 text-red-600 px-6 py-2 rounded-lg font-semibold hover:bg-red-50 transition" data-i18n="register">Register</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Login Form -->
  <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md animate-fade-up">
      <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

        <!-- Header -->
        <div class="bg-gradient-to-r from-red-600 to-red-800 text-white px-8 py-8 text-center">
          <span class="text-5xl mb-3 block">🩸</span>
          <h1 class="text-2xl font-bold" data-i18n="sign_in">Sign In</h1>
          <p class="text-red-200 text-sm mt-1">Login to access the donation form</p>
        </div>

        <!-- Form -->
        <div class="px-8 py-8 space-y-5">

          <div class="bg-blue-50 border-l-2 border-blue-500 p-4 rounded">
            <p class="text-blue-700 text-sm">You must be logged in to submit a donation request. Please sign in below.</p>
          </div>

          <?php if (!empty($loginError)): ?>
            <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded">
              <p class="text-red-700 text-sm"><?= htmlspecialchars($loginError) ?></p>
            </div>
          <?php endif; ?>

          <form method="POST" class="space-y-5">
            <input type="hidden" name="login_username" value="1" />
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Username or Email <span class="text-red-500">*</span></label>
              <input type="text" name="login_user" required
                placeholder="Enter your username or email"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="password" name="login_pass" id="loginPass" required
                  placeholder="Enter your password"
                  class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:border-red-500 transition text-sm" />
                <button type="button" onclick="toggleLoginPass()" id="loginEyeBtn"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700">👁</button>
              </div>
            </div>

            <button type="submit"
              class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-3.5 rounded-xl font-bold hover:shadow-xl transition transform hover:scale-[1.02] text-sm">
              Sign In →
            </button>
          </form>

          <p class="text-center text-sm text-gray-500">
            Don't have an account? <a href="register.php" class="text-red-600 font-bold hover:underline">Sign up free</a>
          </p>
          <p class="text-center text-sm text-gray-500">
            <a href="index.php" class="text-red-600 font-semibold hover:underline">← Back to Home</a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-6">
    <div class="max-w-7xl mx-auto px-4 text-center text-sm">
      <p>&copy; BloodLife. All rights reserved.</p>
    </div>
  </footer>

  <script>
    function toggleLoginPass() {
      const f = document.getElementById('loginPass');
      const b = document.getElementById('loginEyeBtn');
      f.type = f.type === 'password' ? 'text' : 'password';
      b.textContent = f.type === 'password' ? '👁' : '🙈';
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
<?php endif; ?>
