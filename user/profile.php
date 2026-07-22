<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? '';
$username = htmlspecialchars($_SESSION['username'] ?? '');

$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newEmail = trim($_POST['email'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    $newAddress = trim($_POST['address'] ?? '');

    if ($newEmail === '') {
        $message = 'Email is required.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $newEmail, $userId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_email'] = $newEmail;

        // Update donor table if record exists
        $stmt2 = $conn->prepare("UPDATE donor SET phone = ?, address = ? WHERE user_id = ?");
        $stmt2->bind_param("ssi", $newPhone, $newAddress, $userId);
        $stmt2->execute();
        $stmt2->close();

        $message = 'Profile updated successfully.';
        $messageType = 'success';
    }
}

// Handle donor registration/update from profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donor_submit'])) {
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $blood_groups = trim($_POST['blood_groups'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $weight = (float)($_POST['weight'] ?? 0);
    $last_donation_date = $_POST['last_donation_date'] ?: null;
    $available_status = $_POST['available_status'] ?? 'Available';

    if ($gender === '' || $blood_groups === '' || $phone === '' || $address === '' || $weight <= 0) {
        $message = 'Please fill in all required donor fields.';
        $messageType = 'error';
    } else {
        $check = $conn->prepare("SELECT id FROM donor WHERE user_id = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE donor SET gender=?, date_of_birth=?, age=?, blood_groups=?, phone=?, address=?, weight=?, last_donation_date=?, available_status=? WHERE user_id=?");
            $stmt->bind_param("sssisssssi", $gender, $date_of_birth, $age, $blood_groups, $phone, $address, $weight, $last_donation_date, $available_status, $userId);
        } else {
            $stmt = $conn->prepare("INSERT INTO donor (user_id, gender, date_of_birth, age, blood_groups, phone, address, weight, last_donation_date, available_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssisssds", $userId, $gender, $date_of_birth, $age, $blood_groups, $phone, $address, $weight, $last_donation_date, $available_status);
        }

        if ($stmt->execute()) {
            $donorIdForHistory = $existing ? $existing['id'] : $conn->insert_id;
            $bgStmt = $conn->prepare("SELECT id FROM blood_groups WHERE blood_gp_name = ?");
            $bgStmt->bind_param("s", $blood_groups);
            $bgStmt->execute();
            $bgResult = $bgStmt->get_result()->fetch_assoc();
            $bgStmt->close();
            $blood_groups_id = $bgResult ? $bgResult['id'] : 0;
            $donationDate = $last_donation_date ?: date('Y-m-d');
            $dhStmt = $conn->prepare("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, donation_date, units, status) VALUES (?, ?, 0, ?, ?, 1, 'Completed')");
            $dhStmt->bind_param("iiis", $donorIdForHistory, $userId, $blood_groups_id, $donationDate);
            $dhStmt->execute();
            $dhStmt->close();
            $message = $existing ? 'Donor information updated successfully.' : 'Donor registration successful.';
            $messageType = 'success';
        } else {
            $message = 'Error saving donor info: ' . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Handle blood request submission from profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_submit'])) {
    $blood_groups_id = (int)($_POST['blood_groups_id'] ?? 0);
    $units = max(1, (int)($_POST['units'] ?? 1));
    $hospital = trim($_POST['hospital'] ?? '');
    $required_date = $_POST['required_date'] ?? date('Y-m-d');

    if ($blood_groups_id < 1) {
        $message = 'Please select a blood type.';
        $messageType = 'error';
    } elseif ($hospital === '') {
        $message = 'Please enter the hospital name.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO blood_request (users_id, requester_name, blood_groups_id, units, hospital, required_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiisss", $userId, $username, $blood_groups_id, $units, $hospital, $required_date, $status = 'Pending');
        if ($stmt->execute()) {
            $message = 'Blood request submitted successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error submitting request: ' . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Fetch user data
$userData = [];
$stmt = $conn->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch donor data if donor
$donorData = [];
$donorId = 0;
$donations = [];
$donationCount = 0;
$totalUnits = 0;
$livesSaved = 0;
$daysSinceLast = '-';
$bloodGroup = '-';

// Fetch donor record for any user (Donor or Requester)
$stmt = $conn->prepare("SELECT * FROM donor WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$donorData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($donorData) {
    $donorId = (int)$donorData['id'];
    $bloodGroup = htmlspecialchars($donorData['blood_groups'] ?? '-');
    $userData['phone'] = $donorData['phone'] ?? '';
    $userData['address'] = $donorData['address'] ?? '';
}

// Fetch donation history by donor_id or users_id
$stmt = $conn->prepare("SELECT dh.*, bg.blood_gp_name, br.hospital
                        FROM donation_history dh
                        LEFT JOIN blood_groups bg ON bg.id = dh.blood_groups_id
                        LEFT JOIN blood_request br ON br.id = dh.request_id AND dh.request_id > 0
                        WHERE " . ($donorId > 0 ? "dh.donor_id = ?" : "dh.users_id = ?") . "
                        ORDER BY dh.donation_date DESC");
$param = $donorId > 0 ? $donorId : $userId;
$stmt->bind_param("i", $param);
$stmt->execute();
$donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$donationCount = count($donations);
foreach ($donations as $d) {
    $totalUnits += (int)($d['units'] ?? 1);
}
$livesSaved = $totalUnits * 3;

if ($donationCount > 0 && !empty($donations[0]['donation_date'])) {
    $lastDate = new DateTime($donations[0]['donation_date']);
    $now = new DateTime();
    $daysSinceLast = $now->diff($lastDate)->days;
}

// Fetch blood request history for any user
$bloodRequests = [];
$stmt = $conn->prepare("SELECT br.*, bg.blood_gp_name
                        FROM blood_request br
                        LEFT JOIN blood_groups bg ON bg.id = br.blood_groups_id
                        WHERE br.users_id = ?
                        ORDER BY br.required_date DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$bloodRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile – BloodLife</title>
  <script>
    (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
  </script>
  <script>
    tailwind.config = { darkMode: 'class' }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <style>
    @keyframes fadeInDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeInUp   { from { opacity:0; transform:translateY( 20px); } to { opacity:1; transform:translateY(0); } }
    .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
    .animate-fade-up   { animation: fadeInUp   0.6s ease-out; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
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
    html.dark .bg-red-50 { background-color: rgba(220,38,38,0.15) !important; }
    html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
    html.dark .bg-yellow-50 { background-color: rgba(234,179,8,0.15) !important; }
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
  </style>
</head>
<body class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
 <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Cover Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 h-40 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full"></div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <?php if ($message): ?>
      <div class="mb-6 rounded-xl border px-4 py-3 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="relative -mt-16 mb-8 animate-fade-up">
      <div class="bg-white rounded-2xl shadow p-6 sm:p-8 flex flex-col sm:flex-row items-center sm:items-end gap-6">
        <div class="w-28 h-28 bg-red-100 rounded-full border-4 border-white shadow-lg flex items-center justify-center text-5xl flex-shrink-0 -mt-2">👤</div>
        <div class="flex-1 text-center sm:text-left">
          <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 justify-center sm:justify-start">
            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($donorData['full_name'] ?? $userData['username'] ?? '') ?></h1>
            <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-0.5 rounded-full text-sm w-fit mx-auto sm:mx-0"><?= $bloodGroup ?></span>
          </div>

        </div>
        <a href="donordashboard.php" onclick="toggleEdit()" id="editToggleBtn" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition whitespace-nowrap">
          Back
        </a>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8 pb-16">

      <!-- Left: Stats + Badges -->
      <div class="space-y-6 animate-fade-up">

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-gray-900 mb-4" data-i18n="donation_stats">Donation Stats</h2>
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600"><?= $donationCount ?></p>
              <p class="text-xs text-gray-500 mt-1" data-i18n="donations">Donations</p>
            </div>
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600"><?= $livesSaved ?></p>
              <p class="text-xs text-gray-500 mt-1" data-i18n="lives_saved_stat">Lives Saved</p>
            </div>
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600"><?= $daysSinceLast ?></p>
              <p class="text-xs text-gray-500 mt-1" data-i18n="days_since_last_stat">Days Since Last</p>
            </div>
            <div class="text-center bg-red-50 rounded-xl p-4">
              <p class="text-3xl font-bold text-red-600"><?= number_format($totalUnits * 0.5, 1) ?>L</p>
              <p class="text-xs text-gray-500 mt-1" data-i18n="blood_donated">Blood Donated</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-gray-900 mb-4" data-i18n="badges_earned_profile">Badges Earned</h2>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🥇</div>
              <p class="text-xs font-bold text-yellow-700" data-i18n="first_donation">First Donation</p>
            </div>
            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🔥</div>
              <p class="text-xs font-bold text-red-700" data-i18n="five_donations">5 Donations</p>
            </div>
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">⚡</div>
              <p class="text-xs font-bold text-blue-700" data-i18n="quick_responder">Quick Responder</p>
            </div>
            <div class="bg-purple-50 border-2 border-purple-200 rounded-xl p-3 text-center">
              <div class="text-3xl mb-1">🌟</div>
              <p class="text-xs font-bold text-purple-700" data-i18n="life_saver">Life Saver</p>
            </div>
            <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-3 text-center opacity-40">
              <div class="text-3xl mb-1">🔒</div>
              <p class="text-xs font-bold text-gray-500" data-i18n="ten_donations">10 Donations</p>
            </div>
            <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-3 text-center opacity-40">
              <div class="text-3xl mb-1">🔒</div>
              <p class="text-xs font-bold text-gray-500" data-i18n="one_year_member">1 Year Member</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <h2 class="font-bold text-gray-900 mb-3" data-i18n="account">Account</h2>
          <div class="space-y-2">
            <button class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 transition text-sm font-medium text-gray-700 flex items-center justify-between">
              <span data-i18n="change_password">Change Password</span> <span>›</span>
            </button>
            <button class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 transition text-sm font-medium text-gray-700 flex items-center justify-between">
              <span data-i18n="notification_settings">Notification Settings</span> <span>›</span>
            </button>
            <button class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 transition text-sm font-medium text-red-600 flex items-center justify-between">
              <span data-i18n="delete_account">Delete Account</span> <span>›</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Tabs -->
      <div class="lg:col-span-2 animate-fade-up">
        <div class="bg-white rounded-2xl shadow overflow-hidden">

          <!-- Tabs -->
          <div class="flex border-b border-gray-100 overflow-x-auto">
            <button onclick="setTab('info')" id="tabbtn-info" class="flex-1 py-4 font-semibold text-sm text-red-600 border-b-2 border-red-600 transition whitespace-nowrap px-2" data-i18n="personal_info">Personal Info</button>
            <button onclick="setTab('history')" id="tabbtn-history" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2" data-i18n="donation_history">Donation History</button>
            <button onclick="setTab('requests')" id="tabbtn-requests" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2" data-i18n="blood_requests_tab">Blood Requests</button>
            <button onclick="setTab('health')" id="tabbtn-health" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2" data-i18n="health_info">Health Info</button>
            <button onclick="setTab('receipts')" id="tabbtn-receipts" class="flex-1 py-4 font-semibold text-sm text-gray-500 hover:text-gray-700 transition whitespace-nowrap px-2" data-i18n="receipts_tab">🧾 Receipts</button>
          </div>

          <div class="p-6 sm:p-8">

            <!-- Personal Info Tab -->
            <form method="POST" id="profileForm">
              <div id="tab-info" class="tab-panel active space-y-5">
                <div class="grid sm:grid-cols-2 gap-5">
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="username">Username</label>
                    <input type="text" value="<?= htmlspecialchars($userData['username'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="email_address">Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="phone_number">Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="member_since">Member Since</label>
                    <input type="text" value="<?= !empty($userData['created_at']) ? date('F j, Y', strtotime($userData['created_at'])) : '' ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="address">Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($userData['address'] ?? '') ?>" disabled class="profile-input w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600 disabled:cursor-not-allowed" />
                  </div>
                </div>
              </div>
            </form>
            <!-- Save Bar -->
            <div id="saveBar" class="hidden px-6 sm:px-8 pb-6 pt-2 border-t border-gray-100 flex items-center justify-between">
              <p class="text-sm text-gray-500">You have unsaved changes.</p>
              <div class="flex gap-3">
                <button type="button" onclick="toggleEdit()" class="border-2 border-gray-300 text-gray-600 px-5 py-2 rounded-xl font-semibold hover:border-red-400 hover:text-red-600 transition text-sm" data-i18n="cancel">Cancel</button>
                <button type="button" onclick="saveProfile()" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-xl font-bold hover:shadow-lg transition text-sm" data-i18n="save_changes">Save Changes</button>
              </div>
            </div>

            <!-- Donation History Tab -->
            <div id="tab-history" class="tab-panel">
              <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                  <span class="text-xl">📋</span>
                  <h3 class="font-bold text-gray-900" data-i18n="donation_history">Donation History</h3>
                  <?php if ($donationCount > 0): ?>
                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full ml-2"><?= $donationCount ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (count($donations) > 0): ?>
                <div class="overflow-x-auto rounded-xl border border-gray-100">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left text-gray-500 font-semibold px-4 py-3" data-i18n="date">Date</th>
                        <th class="text-left text-gray-500 font-semibold px-4 py-3" data-i18n="blood_type">Blood Type</th>
                        <th class="text-left text-gray-500 font-semibold px-4 py-3" data-i18n="units">Units</th>
                        <th class="text-left text-gray-500 font-semibold px-4 py-3" data-i18n="hospital_col">Hospital</th>
                        <th class="text-left text-gray-500 font-semibold px-4 py-3" data-i18n="status">Status</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                      <?php foreach ($donations as $d): ?>
                        <tr class="hover:bg-gray-50 transition">
                          <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap"><?= date('M j, Y', strtotime($d['donation_date'])) ?></td>
                          <td class="px-4 py-3">
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($d['blood_gp_name'] ?? '-') ?></span>
                          </td>
                          <td class="px-4 py-3 text-gray-600"><?= (int)($d['units'] ?? 1) ?> <?= (int)($d['units'] ?? 1) > 1 ? 'units' : 'unit' ?></td>
                          <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($d['hospital'] ?? '-') ?></td>
                          <td class="px-4 py-3">
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full"><?= htmlspecialchars($d['status'] ?? 'Completed') ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div class="mt-4 bg-gray-50 rounded-xl px-5 py-3 flex flex-wrap items-center justify-between text-sm">
                  <span class="text-gray-500"><span data-i18n="donation_history_total">Total</span>: <strong class="text-gray-900"><?= number_format($totalUnits) ?></strong> <?= $totalUnits > 1 ? 'units' : 'unit' ?> <?= !empty($bloodGroup) && $bloodGroup !== '-' ? "($bloodGroup)" : '' ?></span>
                  <span class="text-green-600 font-semibold">🩸 <span data-i18n="lives_saved_stat">Lives Saved</span>: <?= $livesSaved ?></span>
                </div>
              <?php else: ?>
                <div class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                  <div class="text-5xl mb-4">🩸</div>
                  <p class="text-gray-500 font-semibold" data-i18n="no_donation_history">No donation history found.</p>
                  <p class="text-gray-400 text-sm mt-1" data-i18n="donation_history_empty_desc">When you make your first donation, your history will appear here.</p>
                </div>
              <?php endif; ?>
            </div>

            <!-- Blood Requests Tab -->
            <div id="tab-requests" class="tab-panel">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-gray-100">
                      <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="date">Date</th>
                      <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="blood_type">Blood Type</th>
                      <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="units">Units</th>
                      <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="hospital_col">Hospital</th>
                      <th class="text-left text-gray-500 font-semibold pb-3" data-i18n="status">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50">
                    <?php if (count($bloodRequests) > 0): ?>
                      <?php foreach ($bloodRequests as $br): ?>
                        <tr class="hover:bg-gray-50">
                          <td class="py-3 text-gray-700 font-medium"><?= date('M j, Y', strtotime($br['required_date'])) ?></td>
                          <td class="py-3"><span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($br['blood_gp_name'] ?? '-') ?></span></td>
                          <td class="py-3 text-gray-600"><?= (int)($br['units'] ?? 1) ?> unit</td>
                          <td class="py-3 text-gray-600"><?= htmlspecialchars($br['hospital'] ?? '-') ?></td>
                          <td class="py-3">
                            <?php
                              $status = htmlspecialchars($br['status'] ?? 'Pending');
                              $statusColors = [
                                  'Pending'   => 'bg-yellow-100 text-yellow-700',
                                  'Approved'  => 'bg-blue-100 text-blue-700',
                                  'Completed' => 'bg-green-100 text-green-700',
                                  'Rejected'  => 'bg-red-100 text-red-700',
                              ];
                              $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="<?= $color ?> text-xs font-bold px-2 py-1 rounded-full" data-i18n="<?= strtolower($status) ?>"><?= $status ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="5" class="py-8 text-center text-gray-500" data-i18n="no_blood_request_history">No blood request history found.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Health Info Tab -->
            <div id="tab-health" class="tab-panel space-y-5">
              <div class="grid sm:grid-cols-2 gap-5">
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="blood_type">Blood Type</label>
                  <input type="text" value="<?= $bloodGroup ?>" disabled class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1" data-i18n="weight_field">Weight (kg)</label>
                  <input type="text" value="<?= htmlspecialchars($donorData['weight'] ?? '-') ?>" disabled class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-600" />
                </div>
              </div>
              <div class="bg-red-50 border-2 border-red-100 rounded-xl p-5">
                <h3 class="font-bold text-red-700 mb-2 flex items-center gap-2">⚠️ <span data-i18n="medical_conditions">Medical Conditions</span></h3>
                <p class="text-sm text-gray-600" data-i18n="medical_conditions_desc">None reported. Please update this section if your health status changes, as it affects your donation eligibility.</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-5">
                <h3 class="font-bold text-gray-800 mb-2" data-i18n="eligibility_checklist">Eligibility Checklist</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                  <li class="flex items-center gap-2">✅ <span data-i18n="no_fever_2weeks">No fever or infection in past 2 weeks</span></li>
                  <li class="flex items-center gap-2">✅ <span data-i18n="last_donation_4months">Last donation over 4 months ago</span></li>
                  <li class="flex items-center gap-2">✅ <span data-i18n="not_on_medication">Not on blood-thinning medication</span></li>
                  <li class="flex items-center gap-2">✅ <span data-i18n="weight_above_min">Weight above minimum requirement</span></li>
                </ul>
              </div>
            </div>

            <!-- Receipts Tab -->
            <div id="tab-receipts" class="tab-panel space-y-5">

              <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-gray-500" data-i18n="receipts_desc">Receipts issued to you by BloodLife admins after each donation.</p>
              </div>

              <!-- Receipt Card 1 -->
              <div class="border-2 border-gray-100 hover:border-red-200 rounded-2xl p-5 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧾</div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-gray-900">Receipt #BL-2026-4821</span>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ <span data-i18n="verified">Verified</span></span>
                      </div>
                      <p class="text-sm text-gray-500">📅 Donated: <span class="font-semibold text-gray-700">April 28, 2026</span></p>
                      <p class="text-sm text-gray-500">🏥 Hospital: <span class="font-semibold text-gray-700">Aga Khan University Hospital, Karachi</span></p>
                    </div>
                  </div>
                  <button onclick="showReceipt(0)" class="border-2 border-red-600 text-red-600 px-5 py-2 rounded-xl font-semibold hover:bg-red-50 transition text-sm whitespace-nowrap" data-i18n="view_receipt">View Receipt</button>
                </div>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="blood_type_label">Blood Type</p>
                    <p class="font-bold text-red-600">A+</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="units_label">Units</p>
                    <p class="font-bold text-gray-700">1 unit</p>
                  </div>
                  <div class="bg-green-50 rounded-xl p-2">
                    <p class="text-green-600 mb-0.5">Re-donate From</p>
                    <p class="font-bold text-green-700">Aug 26, 2026</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="issued_by">Issued By</p>
                    <p class="font-bold text-gray-700">Dr. Kamran</p>
                  </div>
                </div>
                <div class="mt-3 bg-gray-50 rounded-xl p-3">
                  <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Remark:</span> Donor was in excellent health. No complications observed during donation. Vitals stable throughout.</p>
                </div>
              </div>

              <!-- Receipt Card 2 -->
              <div class="border-2 border-gray-100 hover:border-red-200 rounded-2xl p-5 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧾</div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-gray-900">Receipt #BL-2026-1193</span>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ <span data-i18n="verified">Verified</span></span>
                      </div>
                      <p class="text-sm text-gray-500">📅 Donated: <span class="font-semibold text-gray-700">January 10, 2026</span></p>
                      <p class="text-sm text-gray-500">🏥 Hospital: <span class="font-semibold text-gray-700">Civil Hospital, Karachi</span></p>
                    </div>
                  </div>
                  <button onclick="showReceipt(1)" class="border-2 border-red-600 text-red-600 px-5 py-2 rounded-xl font-semibold hover:bg-red-50 transition text-sm whitespace-nowrap">View Receipt</button>
                </div>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="blood_type_label">Blood Type</p>
                    <p class="font-bold text-red-600">A+</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="units_label">Units</p>
                    <p class="font-bold text-gray-700">1 unit</p>
                  </div>
                  <div class="bg-green-50 rounded-xl p-2">
                    <p class="text-green-600 mb-0.5">Re-donate From</p>
                    <p class="font-bold text-green-700">May 10, 2026</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="issued_by">Issued By</p>
                    <p class="font-bold text-gray-700">Dr. Saira</p>
                  </div>
                </div>
                <div class="mt-3 bg-gray-50 rounded-xl p-3">
                  <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Remark:</span> Successful donation. Donor advised to rest and stay hydrated for 24 hours.</p>
                </div>
              </div>

              <!-- Receipt Card 3 -->
              <div class="border-2 border-gray-100 hover:border-red-200 rounded-2xl p-5 transition">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🧾</div>
                    <div>
                      <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-gray-900">Receipt #BL-2025-7734</span>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ <span data-i18n="verified">Verified</span></span>
                      </div>
                      <p class="text-sm text-gray-500">📅 Donated: <span class="font-semibold text-gray-700">September 3, 2025</span></p>
                      <p class="text-sm text-gray-500">🏥 Hospital: <span class="font-semibold text-gray-700">Aga Khan University Hospital, Karachi</span></p>
                    </div>
                  </div>
                  <button onclick="showReceipt(2)" class="border-2 border-red-600 text-red-600 px-5 py-2 rounded-xl font-semibold hover:bg-red-50 transition text-sm whitespace-nowrap">View Receipt</button>
                </div>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="blood_type_label">Blood Type</p>
                    <p class="font-bold text-red-600">A+</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="units_label">Units</p>
                    <p class="font-bold text-gray-700">1 unit</p>
                  </div>
                  <div class="bg-green-50 rounded-xl p-2">
                    <p class="text-green-600 mb-0.5">Re-donate From</p>
                    <p class="font-bold text-green-700">Jan 1, 2026</p>
                  </div>
                  <div class="bg-gray-50 rounded-xl p-2">
                    <p class="text-gray-400 mb-0.5" data-i18n="issued_by">Issued By</p>
                    <p class="font-bold text-gray-700">Dr. Kamran</p>
                  </div>
                </div>
                <div class="mt-3 bg-gray-50 rounded-xl p-3">
                  <p class="text-xs text-gray-500"><span class="font-semibold text-gray-700">Remark:</span> No issues reported. Donor is a regular contributor and eligible for the 5-donation badge.</p>
                </div>
              </div>

            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Receipt Modal -->
  <div id="receiptModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden">

      <!-- Modal Header -->
      <div class="bg-gradient-to-r from-red-600 to-red-800 text-white px-8 py-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-3xl bg-white/20 p-2 rounded-full">🩸</span>
            <div>
              <h2 class="font-bold text-xl">BloodLife</h2>
              <p class="text-red-200 text-xs">Official Donation Receipt</p>
            </div>
          </div>
          <div class="text-right">
            <p id="modal_receipt_no" class="font-bold text-lg tracking-wider">BL-0000-0000</p>
            <p id="modal_issued_on" class="text-red-200 text-xs">Issued: —</p>
          </div>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="px-8 py-6 space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">DONOR NAME</p>
            <p class="font-bold text-gray-900">Ahmed Raza</p>
          </div>
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">BLOOD TYPE</p>
            <span class="inline-block bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-0.5 rounded-full">A+</span>
          </div>
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">DONATION DATE</p>
            <p id="modal_donate_date" class="font-bold text-gray-900">—</p>
          </div>
          <div>
            <p class="text-gray-400 text-xs font-semibold mb-1">UNITS DONATED</p>
            <p class="font-bold text-gray-900">1 unit</p>
          </div>
          <div class="col-span-2">
            <p class="text-gray-400 text-xs font-semibold mb-1">HOSPITAL</p>
            <p id="modal_hospital" class="font-bold text-gray-900">—</p>
          </div>
          <div class="col-span-2 bg-green-50 border-2 border-green-200 rounded-xl p-3">
            <p class="text-green-600 text-xs font-semibold mb-1">🔄 RE-DONATION ELIGIBLE FROM</p>
            <p id="modal_redonate" class="font-bold text-green-700 text-lg">—</p>
          </div>
          <div class="col-span-2">
            <p class="text-gray-400 text-xs font-semibold mb-1">REMARKS</p>
            <p id="modal_remark" class="text-gray-600 text-sm bg-gray-50 rounded-xl p-3">—</p>
          </div>
          <div class="col-span-2 flex items-center justify-between pt-2 border-t border-gray-100">
            <div>
              <p class="text-gray-400 text-xs font-semibold mb-0.5">ISSUED BY</p>
              <p id="modal_admin" class="font-bold text-gray-700 text-sm">—</p>
            </div>
            <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">✅ Verified</span>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-8 pb-6 flex gap-3">
        <button onclick="window.print()" class="flex-1 bg-gradient-to-r from-red-600 to-red-700 text-white py-3 rounded-xl font-bold hover:shadow-lg transition">🖨️ <span data-i18n="print">Print</span></button>
        <button onclick="closeReceiptModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition">Close</button>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <?php include __DIR__ . '/../includes/footer.php'; ?>

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

    function setTab(tab) {
      ['info','history','requests','health','receipts'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (el) el.classList.remove('active');
        const btn = document.getElementById('tabbtn-' + t);
        if (btn) {
          btn.classList.remove('text-red-600','border-b-2','border-red-600');
          btn.classList.add('text-gray-500');
        }
      });
      document.getElementById('tab-' + tab).classList.add('active');
      const activeBtn = document.getElementById('tabbtn-' + tab);
      activeBtn.classList.add('text-red-600','border-b-2','border-red-600');
      activeBtn.classList.remove('text-gray-500');
    }

    const receipts = [
      {
        no: 'BL-2026-4821', issued: 'April 28, 2026',
        donateDate: 'April 28, 2026', hospital: 'Aga Khan University Hospital, Karachi',
        redonate: 'August 26, 2026', admin: 'Dr. Kamran',
        remark: 'Donor was in excellent health. No complications observed during donation. Vitals stable throughout.'
      },
      {
        no: 'BL-2026-1193', issued: 'January 10, 2026',
        donateDate: 'January 10, 2026', hospital: 'Civil Hospital, Karachi',
        redonate: 'May 10, 2026', admin: 'Dr. Saira',
        remark: 'Successful donation. Donor advised to rest and stay hydrated for 24 hours.'
      },
      {
        no: 'BL-2025-7734', issued: 'September 3, 2025',
        donateDate: 'September 3, 2025', hospital: 'Aga Khan University Hospital, Karachi',
        redonate: 'January 1, 2026', admin: 'Dr. Kamran',
        remark: 'No issues reported. Donor is a regular contributor and eligible for the 5-donation badge.'
      },
    ];

    function showReceipt(index) {
      const r = receipts[index];
      document.getElementById('modal_receipt_no').textContent  = '#' + r.no;
      document.getElementById('modal_issued_on').textContent   = 'Issued: ' + r.issued;
      document.getElementById('modal_donate_date').textContent = r.donateDate;
      document.getElementById('modal_hospital').textContent    = r.hospital;
      document.getElementById('modal_redonate').textContent    = r.redonate;
      document.getElementById('modal_admin').textContent       = r.admin;
      document.getElementById('modal_remark').textContent      = r.remark;
      const modal = document.getElementById('receiptModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeReceiptModal() {
      const modal = document.getElementById('receiptModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // Close modal on backdrop click
    document.getElementById('receiptModal').addEventListener('click', function(e) {
      if (e.target === this) closeReceiptModal();
    });

    let editing = false;
    function toggleEdit() {
      editing = !editing;
      document.querySelectorAll('.profile-input').forEach(el => {
        el.disabled = !editing;
        if (editing) {
          el.classList.remove('bg-gray-50','text-gray-600');
          el.classList.add('bg-white','text-gray-900','focus:outline-none','focus:border-red-500');
        } else {
          el.classList.add('bg-gray-50','text-gray-600');
          el.classList.remove('bg-white','text-gray-900');
        }
      });
      document.getElementById('saveBar').classList.toggle('hidden', !editing);
      document.getElementById('editToggleBtn').textContent = editing ? '✕ Cancel' : '✏️ Edit Profile';
    }

    function saveProfile() {
      document.getElementById('profileForm').submit();
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