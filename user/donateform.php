<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$isLoggedIn) {
    header('Location: login.php?redirect_to=donateform.php');
    exit;
}

$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';

$message = '';
$messageType = '';
$editMode = false;
$editData = null;
$donorExists = false;
$donorStatus = '';
$daysRemaining = 0;

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'] ?? 0;

    // Check if user already has a donor record
    $checkStmt = $conn->prepare("SELECT id, available_status, last_donation_date FROM donor WHERE user_id = ? LIMIT 1");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult && $checkResult->num_rows > 0) {
        $existingDonor = $checkResult->fetch_assoc();
        $donorExists = true;
        $donorStatus = $existingDonor['available_status'];

        // Auto-update status based on 3-month cooldown
        if ($existingDonor['last_donation_date']) {
            $lastDonated = new DateTime($existingDonor['last_donation_date']);
            $threeMonthsLater = (clone $lastDonated)->modify('+3 months');
            if ($threeMonthsLater <= new DateTime()) {
                // Cooldown passed, auto-set to Available
                $donorStatus = 'Available';
                $updateAvail = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
                $updateAvail->bind_param("i", $existingDonor['id']);
                $updateAvail->execute();
                $updateAvail->close();
            } else {
                $daysRemaining = (new DateTime())->diff($threeMonthsLater)->days;
            }
        }
    }
    $checkStmt->close();

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
        elseif ($msg === 'error') { $message = $_GET['text'] ?? 'An error occurred.'; $messageType = 'error'; }
    }
}

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
          <?php if ($isLoggedIn): ?>
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
                                        <span><?= strtoupper(substr($username, 0, 1)) ?></span> <span data-i18n="profile">Profile</span>
                                    </a>
                                    <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                                        <span>🚪</span> <span data-i18n="logout">Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition cursor-pointer">
                            Login
                        </a>
                    <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-12">
    <div class="max-w-3xl mx-auto px-4 text-center animate-fade-up">
      <div class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-semibold mb-3">🩸 <span data-i18n="ready_to_save">Ready to Save a Life?</span></div>
      <?php if ($donorExists && !$editMode): ?>
      <h1 class="text-4xl font-bold mb-2">You Are Already a Donor</h1>
      <p class="text-lg opacity-90">You are already registered as a blood donor. You can view or edit your profile below.</p>
      <?php else: ?>
      <h1 class="text-4xl font-bold mb-2" data-i18n="donate_blood_form"><?= $editMode ? 'Edit Donor Record' : 'Donate Blood' ?></h1>
      <p class="text-lg opacity-90" data-i18n="donate_blood_desc"><?= $editMode ? 'Update your donor registration details below.' : 'Fill in the form below so we can prepare your donation appointment and verify your eligibility.' ?></p>
      <?php endif; ?>
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
          <p class="text-xs text-red-600 font-semibold mt-1">Donor Info</p>
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

  <?php if ($donorExists && $donorStatus === 'Unavailable'): ?>
  <div class="max-w-2xl mx-auto px-4 mt-6">
    <div class="rounded-xl p-6 bg-red-100 text-red-700 border border-red-200 animate-fade-up">
      <div class="flex items-start gap-3">
        <span class="text-2xl">⚠️</span>
        <div>
          <h3 class="font-bold text-lg mb-1">Donor Registration Unavailable</h3>
          <p class="text-sm mb-2">You are currently <strong>Unavailable</strong> due to a recent blood donation. You must wait <strong>3 months</strong> between donations.</p>
          <?php if ($daysRemaining > 0): ?>
          <p class="text-sm font-semibold">You can register again in <strong><?= $daysRemaining ?> days</strong>.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <section class="py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6 animate-fade-up">

      <?php if ($donorExists && !$editMode): ?>
      <!-- Already Registered Message -->
      <div class="bg-green-50 border border-green-200 rounded-2xl p-8 text-center">
        <div class="text-5xl mb-4">✅</div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">You are already registered as a donor.</h2>
        <p class="text-gray-600 mb-6">You can view your donor profile or edit your details below.</p>
        <a href="donateform.php?edit=<?= $myDonors[0]['id'] ?? 0 ?>" class="inline-block bg-gradient-to-r from-red-600 to-red-700 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transition transform hover:scale-105">
          View / Edit Donor Profile
        </a>
      </div>
      <?php else: ?>

      <?php if (!$donorExists || $donorStatus === 'Available'): ?>
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center gap-3">
        <span class="text-xl">ℹ️</span>
        <p class="text-blue-700 text-sm font-medium">Please enter all information correctly. Double-check your details before submitting.</p>
      </div>
      <?php endif; ?>

      <?php $formDisabled = ($donorExists && $donorStatus === 'Unavailable'); ?>
      <form method="POST" action="donor_crud.php" class="space-y-6" <?= $formDisabled ? 'style="pointer-events:none;opacity:0.5;"' : '' ?>>
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
              <label class="block text-sm font-semibold text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
              <select name="gender" required <?= $formDisabled ? 'disabled' : '' ?> class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                <option value="">Select gender</option>
                <option value="Male" <?= ($editData['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($editData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($editData['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Date Of Birth</label>
              <input type="date" name="date_of_birth" id="dateOfBirth"
                value="<?= htmlspecialchars($editData['date_of_birth'] ?? '') ?>"
                <?= $formDisabled ? 'disabled' : '' ?>
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Age <span class="text-red-500">*</span></label>
              <input type="number" name="age" id="ageField" placeholder="Min. 18 years" min="18" required
                value="<?= htmlspecialchars($editData['age'] ?? '') ?>"
                <?= $formDisabled ? 'disabled' : '' ?>
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" readonly />
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
              <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
              <input type="tel" name="contact" id="contactField" placeholder="Enter phone number" maxlength="15" pattern="[0-9]*" inputmode="numeric" required
                value="<?= htmlspecialchars($editData['phone'] ?? '') ?>"
                <?= $formDisabled ? 'disabled' : '' ?>
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
              <p class="text-xs text-gray-400 mt-1">Numbers only, max 15 digits</p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
            <textarea name="address" placeholder="Your address" required
              <?= $formDisabled ? 'disabled' : '' ?>
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
              <select name="blood_groups" required <?= $formDisabled ? 'disabled' : '' ?> class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
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
              <input type="number" name="weight" id="weightField" placeholder="Min. 100 lb" min="100" required
                value="<?= htmlspecialchars($editData['weight'] ?? '') ?>"
                <?= $formDisabled ? 'disabled' : '' ?>
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
              <p class="text-xs text-gray-400 mt-1">Minimum weight: 100 lb</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Last Donation Date</label>
              <input type="date" name="last_donation_date" id="lastDonationDate" max="<?= date('Y-m-d') ?>"
                value="<?= htmlspecialchars($editData['last_donation_date'] ?? '') ?>"
                <?= $formDisabled ? 'disabled' : '' ?>
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
              <p class="text-xs text-gray-400 mt-1">Cannot be a future date</p>
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
          <button type="submit" <?= $formDisabled ? 'disabled' : '' ?> class="bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-xl font-bold hover:shadow-xl transition transform hover:scale-105 text-sm <?= $formDisabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
            <?= $editMode ? 'Update Record' : 'Submit Donation Request 🩸' ?>
          </button>
        </div>
      </form>

      <?php endif; ?>
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
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Last Donation</th>
                <th class="text-left text-gray-500 font-semibold pb-3">Status</th>
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
                  // Calculate remaining cooldown days
                  $cooldownText = '';
                  if ($d['last_donation_date']) {
                      $lastDonated = new DateTime($d['last_donation_date']);
                      $threeMonthsLater = (clone $lastDonated)->modify('+3 months');
                      if ($threeMonthsLater > new DateTime()) {
                          $remain = (new DateTime())->diff($threeMonthsLater)->days;
                          $cooldownText = " ({$remain} days left)";
                      }
                  }
                ?>
              <tr class="hover:bg-gray-50">
                <td class="py-3 pr-4 font-medium text-gray-700">#<?= $d['id'] ?></td>
                <td class="py-3 pr-4 text-gray-800 font-medium"><?= htmlspecialchars($d['username'] ?? '') ?></td>
                <td class="py-3 pr-4">
                  <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs">
                    <?= htmlspecialchars($d['blood_gp_name'] ?? 'N/A') ?>
                  </span>
                </td>
                <td class="py-3 pr-4 text-gray-600"><?= (int)$d['age'] ?></td>
                <td class="py-3 pr-4 text-gray-600"><?= htmlspecialchars($d['phone']) ?></td>
                <td class="py-3 pr-4 text-gray-600"><?= htmlspecialchars($d['last_donation_date'] ?? 'N/A') ?></td>
                <td class="py-3 pr-4">
                  <span class="<?= $statusColor ?> text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($d['available_status']) ?><?= $cooldownText ?></span>
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
          <p class="text-gray-400 text-sm mb-4">You have not registered as a donor yet.</p>
          <a href="donateform.php" class="inline-block bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-xl font-bold hover:shadow-lg transition">Register as Donor</a>
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
// Phone field: numbers only, max 15 digits
    const contactField = document.getElementById('contactField');
    contactField.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 15);
    });
    contactField.addEventListener('paste', function(e) {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = pasted.replace(/[^0-9]/g, '').slice(0, 15);
      document.execCommand('insertText', false, cleaned);
    });

    // Weight field: minimum 100 lb
    const weightField = document.getElementById('weightField');
    weightField.addEventListener('input', function() {
      const val = parseFloat(this.value);
      if (this.value !== '' && val < 100) {
        this.value = 100;
      }
    });
    weightField.addEventListener('blur', function() {
      if (this.value !== '' && parseFloat(this.value) < 100) {
        this.value = 100;
      }
    });

    // Last Donation Date: no future dates
    const lastDonationDate = document.getElementById('lastDonationDate');
    const today = new Date().toISOString().split('T')[0];
    lastDonationDate.setAttribute('max', today);
    lastDonationDate.addEventListener('change', function() {
      if (this.value > today) {
        this.value = today;
      }
    });

    // Auto-calculate age from Date of Birth
    const dobInput = document.getElementById('dateOfBirth');
    const ageField = document.getElementById('ageField');

    function calculateAge(dob) {
      const birth = new Date(dob);
      const today = new Date();
      let age = today.getFullYear() - birth.getFullYear();
      const monthDiff = today.getMonth() - birth.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
      }
      return age;
    }

    dobInput.addEventListener('change', function() {
      if (this.value) {
        const age = calculateAge(this.value);
        if (age >= 0) {
          ageField.value = age;
        }
      } else {
        ageField.value = '';
      }
    });

    // Calculate age on page load if DOB is already set (edit mode)
    if (dobInput.value) {
      const age = calculateAge(dobInput.value);
      if (age >= 0) {
        ageField.value = age;
      }
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

