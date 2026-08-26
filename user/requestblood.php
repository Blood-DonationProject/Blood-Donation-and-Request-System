<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$isLoggedIn) {
  header('Location: login.php?redirect_to=requestblood');
  exit;
}

$username = htmlspecialchars($_SESSION['username']);
$userId = $_SESSION['user_id'] ?? 0;
$userEmail = '';
$userPhone = '';
$userAddress = '';

// Retrieve user's email, phone, and address from users / donor profile
if ($userId > 0) {
  $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
  if ($userStmt) {
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($userRes && $userRes->num_rows > 0) {
      $userData = $userRes->fetch_assoc();
      $userEmail = $userData['email'] ?? '';
      $userPhone = $userData['phone'] ?? '';
      $userAddress = $userData['address'] ?? '';
      if (empty($username) && !empty($userData['username'])) {
        $username = htmlspecialchars($userData['username']);
      }
    }
    $userStmt->close();
  }

  // Fetch donor record for phone/address if available
  $dStmt = $conn->prepare("SELECT phone, address FROM donor WHERE user_id = ? LIMIT 1");
  if ($dStmt) {
    $dStmt->bind_param("i", $userId);
    $dStmt->execute();
    $dRes = $dStmt->get_result();
    if ($dRes && $dRes->num_rows > 0) {
      $dData = $dRes->fetch_assoc();
      if (empty($userPhone) && !empty($dData['phone'])) {
        $userPhone = $dData['phone'];
      }
      if (empty($userAddress) && !empty($dData['address'])) {
        $userAddress = $dData['address'];
      }
    }
    $dStmt->close();
  }
}

$bloodGroups = [];
try {
  $bgResult = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");
  if ($bgResult) $bloodGroups = $bgResult->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
}

$message = '';
$messageType = '';
$editMode = false;
$editData = null;

if ($isLoggedIn) {

  // DELETE
  if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM blood_request WHERE id = ? AND users_id = ?");
    $stmt->bind_param("ii", $deleteId, $userId);
    $stmt->execute();
    $stmt->close();
    header('Location: requestblood.php?msg=deleted');
    exit;
  }

  // EDIT — pre-fill form
  if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT br.*, br.Urgency AS urgency, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.id = ? AND br.users_id = ?");
    $stmt->bind_param("ii", $editId, $userId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editData) {
      if (in_array($editData['status'], ['Pending', 'Approved']) && strtotime($editData['required_date']) >= strtotime('today')) {
        $editMode = true;
      } else {
        $statusMsg = ($editData['status'] === 'Expired' || strtotime($editData['required_date']) < strtotime('today')) ? 'Expired' : htmlspecialchars($editData['status']);
        $message = 'This request cannot be edited anymore (Status: ' . $statusMsg . ').';
        $messageType = 'error';
      }
    } else {
      $message = 'Record not found.';
      $messageType = 'error';
    }
  }

  // CREATE or UPDATE
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update user profile phone & address if submitted
    $submittedPhone = trim($_POST['phone'] ?? $_POST['contact'] ?? '');
    $submittedAddress = trim($_POST['address'] ?? '');
    if (!empty($submittedPhone) || !empty($submittedAddress)) {
      // Update central users profile
      $updUser = $conn->prepare("UPDATE users SET phone = ?, address = ? WHERE id = ?");
      if ($updUser) {
        $updUser->bind_param("ssi", $submittedPhone, $submittedAddress, $userId);
        $updUser->execute();
        $updUser->close();
      }

      // Sync to donor table if record exists
      $chkDonor = $conn->prepare("SELECT id FROM donor WHERE user_id = ? LIMIT 1");
      if ($chkDonor) {
        $chkDonor->bind_param("i", $userId);
        $chkDonor->execute();
        $hasDonor = $chkDonor->get_result()->num_rows > 0;
        $chkDonor->close();
        if ($hasDonor) {
          $updDonor = $conn->prepare("UPDATE donor SET phone = ?, address = ? WHERE user_id = ?");
          if ($updDonor) {
            $updDonor->bind_param("ssi", $submittedPhone, $submittedAddress, $userId);
            $updDonor->execute();
            $updDonor->close();
          }
        }
      }
      $userPhone = $submittedPhone;
      $userAddress = $submittedAddress;
    }

    if (isset($_POST['update_id']) && (int)$_POST['update_id'] > 0) {
      // UPDATE ONLY URGENCY
      $updateId = (int)$_POST['update_id'];
      $urgency = trim($_POST['urgency'] ?? 'Normal');
      if (!in_array($urgency, ['Normal', 'Urgent'])) {
        $urgency = 'Normal';
      }
      
      $chkStmt = $conn->prepare("SELECT status FROM blood_request WHERE id=? AND users_id=?");
      $chkStmt->bind_param("ii", $updateId, $userId);
      $chkStmt->execute();
      $chkRes = $chkStmt->get_result()->fetch_assoc();
      $chkStmt->close();
      
      if ($chkRes && in_array($chkRes['status'], ['Pending', 'Approved'])) {
        $stmt = $conn->prepare("UPDATE blood_request SET Urgency=? WHERE id=? AND users_id=?");
        $stmt->bind_param("sii", $urgency, $updateId, $userId);
        if ($stmt->execute()) {
          // Notify admin of blood request update
          require_once __DIR__ . '/../includes/notification_helper.php';
          $reqDetail = $conn->query("SELECT br.*, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.id = " . (int)$updateId)->fetch_assoc();
          $hosp = $reqDetail['hospital'] ?? '';
          $bgName = $reqDetail['blood_gp_name'] ?? '';
          $notifMsg = "Requester \"{$username}\" updated blood request #{$updateId} (Hospital: {$hosp}, Blood Group: {$bgName}). Urgency set to: {$urgency}.";
          notify_admins($conn, 'Blood_Request_Update', 'Blood request updated', $notifMsg, $updateId, null, null, $userId);

          $message = 'Blood request urgency updated successfully!';
          $messageType = 'success';
        } else {
          $message = 'Failed to update request: ' . $conn->error;
          $messageType = 'error';
        }
        $stmt->close();
      } else {
        $currStatus = $chkRes ? htmlspecialchars($chkRes['status']) : 'Not Found';
        $message = 'This request cannot be edited at this stage (Status: ' . $currStatus . ').';
        $messageType = 'error';
      }
    } elseif (isset($_POST['blood_groups_id'])) {
      // INSERT NEW
      $blood_groups_id = (int)$_POST['blood_groups_id'];
      $units = 1; // Always exactly 1 Unit
      $hospital = trim($_POST['hospital'] ?? '');
      $required_date = $_POST['required_date'] ?? date('Y-m-d');
      $status = $_POST['status'] ?? 'Pending';
      $urgency = $_POST['urgency'] ?? 'Normal';

      if ($blood_groups_id < 1) {
        $message = 'Please select a blood type.';
        $messageType = 'error';
      } elseif ($hospital === '') {
        $message = 'Please enter the hospital name.';
        $messageType = 'error';
      } else {
        $stmt = $conn->prepare("SELECT id FROM blood_request WHERE users_id = ? AND status IN ('Pending', 'Approved', 'Assigned', 'Accepted', 'Blood Received') LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
          $message = 'You already have an active blood request. Please wait until it is completed.';
          $messageType = 'error';
        } else {
          $insStmt = $conn->prepare("INSERT INTO blood_request (users_id, requester_name, blood_groups_id, units, hospital, required_date, status, Urgency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          $insStmt->bind_param("isiissss", $userId, $username, $blood_groups_id, $units, $hospital, $required_date, $status, $urgency);
          if ($insStmt->execute()) {
            $newReqId = $conn->insert_id;
            
            // Notify admin of new blood request
            require_once __DIR__ . '/../includes/notification_helper.php';
            $bgName = '';
            $bgRes = $conn->query("SELECT blood_gp_name FROM blood_groups WHERE id = " . (int)$blood_groups_id);
            if ($bgRes && $bgRow = $bgRes->fetch_assoc()) $bgName = $bgRow['blood_gp_name'];
            $notifMsg = "Requester: {$username} | Blood Group: {$bgName} | Units: {$units} | Hospital: {$hospital} | Required Date: {$required_date} | Urgency: {$urgency}";
            notify_admins($conn, 'Blood_Request', 'New blood request', $notifMsg, $newReqId, null, null, $userId);

            $message = 'Blood request submitted successfully!';
            $messageType = 'success';
          } else {
            $message = 'Failed to save request: ' . $conn->error;
            $messageType = 'error';
          }
          $insStmt->close();
        }
        $stmt->close();
      }
    }

    if ($messageType === 'success') {
      $redirectUrl = (isset($_POST['update_id']) && (int)$_POST['update_id'] > 0)
        ? 'requestblood.php?edit=' . (int)$_POST['update_id'] . '&msg=updated'
        : 'requestblood.php?msg=created';
      header('Location: ' . $redirectUrl);
      exit;
    }
    $editMode = false;
  }

  // Fetch user's blood requests
  $myRequests = [];
  $stmt = $conn->prepare("SELECT br.*, br.Urgency AS urgency, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.users_id = ? ORDER BY br.id DESC");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $myResult = $stmt->get_result();
  if ($myResult && $myResult->num_rows > 0) {
    $myRequests = $myResult->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();

  // URL message redirect
  if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    if ($msg === 'created') {
      $message = 'Blood request submitted successfully!';
      $messageType = 'success';
    } elseif ($msg === 'updated') {
      $message = 'Blood request urgency updated successfully!';
      $messageType = 'success';
    } elseif ($msg === 'deleted') {
      $message = 'Blood request deleted successfully.';
      $messageType = 'success';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Request Blood – BloodLife</title>
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

    .animate-fade-down {
      animation: fadeInDown 0.6s ease-out;
    }

    .animate-fade-up {
      animation: fadeInUp 0.6s ease-out;
    }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body {
      background-color: #fdf2f8 !important;
      background-image: none !important;
    }

    html:not(.dark) .bg-gray-50 {
      background-color: #fdf2f8 !important;
    }

    html:not(.dark) .bg-gray-100 {
      background-color: #fce7f3 !important;
    }

    html.dark body {
      background-color: #111827 !important;
      background-image: none !important;
      color: #e5e7eb;
    }

    html.dark nav.bg-white,
    html.dark nav.bg-white.shadow-lg {
      background-color: #1f2937 !important;
    }

    html.dark .bg-white {
      background-color: #1f2937 !important;
    }

    html.dark .text-gray-900,
    html.dark .text-gray-800 {
      color: #f3f4f6 !important;
    }

    html.dark .text-gray-700 {
      color: #d1d5db !important;
    }

    html.dark .text-gray-600 {
      color: #9ca3af !important;
    }

    html.dark .text-gray-500 {
      color: #9ca3af !important;
    }

    html.dark input,
    html.dark select,
    html.dark textarea {
      background-color: #374151 !important;
      border-color: #4b5563 !important;
      color: #e5e7eb !important;
    }

    html.dark label {
      color: #d1d5db !important;
    }

    html.dark .bg-gray-50,
    html.dark .bg-gray-100 {
      background-color: #374151 !important;
    }

    html.dark .border-gray-200,
    html.dark .border-2.border-gray-200 {
      border-color: #4b5563 !important;
    }

    html.dark .border-t {
      border-color: #374151 !important;
    }

    html.dark .bg-red-50 {
      background-color: rgba(220, 38, 38, 0.15) !important;
    }

    html.dark tbody tr {
      border-color: #374151 !important;
    }

    html.dark tbody tr:hover {
      background-color: #374151 !important;
    }
  </style>
</head>

<body class="bg-gradient-to-b from-pink-50 to-pink-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Hero Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center animate-fade-up">

      <h1 class="text-5xl font-bold mb-3" data-i18n="request_blood_title"><?= $editMode ? 'Edit Blood Request' : 'Request Blood' ?></h1>
      <p class="text-xl opacity-90 max-w-xl mx-auto"><?= $editMode ? 'Update your blood request details below.' : "Fill in the details below and we'll immediately match you with available donors in your area." ?></p>
    </div>
  </section>

  <?php if (!empty($message)): ?>
    <div class="max-w-3xl mx-auto px-4 mt-6">
      <div class="rounded-xl p-4 <?= $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Main Form -->
  <section class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 animate-fade-up">

      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center gap-3 mb-6">
        <span class="text-xl">ℹ️</span>
        <p class="text-blue-700 text-sm font-medium">Please enter all blood request details accurately. Our system will match you with available donors.</p>
      </div>

      <form method="POST" id="requestBloodForm" class="space-y-6">
        <?php if ($editMode && $editData): ?>
          <input type="hidden" name="update_id" value="<?= $editData['id'] ?>" />
        <?php endif; ?>

        <!-- Requester Information Card -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">👤</div>
            <h2 class="text-xl font-bold text-gray-900">Requester Information</h2>
          </div>
          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Requester Name</label>
              <input type="text" value="<?= htmlspecialchars($editMode ? ($editData['requester_name'] ?? $username) : $username) ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-100 text-gray-500 cursor-not-allowed outline-none" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
              <input type="email" value="<?= htmlspecialchars($userEmail) ?>" readonly class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-100 text-gray-500 cursor-not-allowed outline-none" />
            </div>
          </div>
        </div>

        <!-- Contact Details Card -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📞</div>
            <h2 class="text-xl font-bold text-gray-900">Contact Details</h2>
          </div>
          <div class="grid sm:grid-cols-2 gap-5 mb-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
              <input type="tel" name="phone" id="contactField" placeholder="Enter phone number" maxlength="15" pattern="[0-9]*" inputmode="numeric" required
                value="<?= htmlspecialchars($userPhone ?? '') ?>"
                class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
              <p class="text-xs text-gray-400 mt-1">Numbers only, max 15 digits</p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Address / Township <span class="text-red-500">*</span></label>
            <textarea name="address" placeholder="Your address" required
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" rows="3"><?= htmlspecialchars($userAddress ?? '') ?></textarea>
          </div>
        </div>

        <!-- Blood Request Details Card -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🩸</div>
            <h2 class="text-xl font-bold text-gray-900">Blood Request Details</h2>
          </div>

          <div class="grid sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type <span class="text-red-500">*</span></label>
              <?php if ($editMode): ?>
                <input type="text" readonly value="<?= htmlspecialchars($editData['blood_gp_name'] ?? '') ?>" class="w-full border-2 border-gray-200 bg-gray-100 text-gray-500 rounded-xl px-4 py-3 focus:outline-none cursor-not-allowed">
              <?php else: ?>
                <select name="blood_groups_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                  <option value="">Select blood type</option>
                  <?php foreach ($bloodGroups as $bg): ?>
                    <option value="<?= $bg['id'] ?>"><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Hospital <span class="text-red-500">*</span></label>
              <?php if ($editMode): ?>
                <input type="text" readonly value="<?= htmlspecialchars($editData['hospital'] ?? '') ?>" class="w-full border-2 border-gray-200 bg-gray-100 text-gray-500 rounded-xl px-4 py-3 focus:outline-none cursor-not-allowed">
              <?php else: ?>
                <select name="hospital" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                  <option value="">Select Hospital</option>
                  <?php foreach (['Aung Dispensary', 'Loilem General Hospital', 'PangLong General Hospital', 'Cherry Hospital', 'NamSam General Hospital', 'Taw Win Dispensary', 'Tun Hospital', 'Khaing Hospital'] as $hosp): ?>
                    <option value="<?= htmlspecialchars($hosp) ?>"><?= htmlspecialchars($hosp) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Required Date <span class="text-red-500">*</span></label>
              <input type="date" name="required_date" id="requiredDate" <?= !$editMode ? 'min="' . date('Y-m-d') . '"' : '' ?>
                value="<?= htmlspecialchars($editData['required_date'] ?? date('Y-m-d')) ?>"
                required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition <?= $editMode ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '' ?>" <?= $editMode ? 'readonly' : '' ?> />
              <?php if (!$editMode): ?><p class="text-xs text-gray-400 mt-1">Must be today or a future date</p><?php endif; ?>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Urgency</label>
              <select name="urgency" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                <?php foreach (['Normal', 'Urgent'] as $st): ?>
                  <option value="<?= $st ?>" <?= (($editData['urgency'] ?? $editData['Urgency'] ?? 'Normal') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($editMode): ?>
              <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                <input type="text" readonly value="<?= htmlspecialchars($editData['status'] ?? 'Pending') ?>" class="w-full border-2 border-gray-200 bg-gray-100 text-gray-500 rounded-xl px-4 py-3 focus:outline-none cursor-not-allowed">
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Buttons Card -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="grid grid-cols-2 gap-4">
            <?php if ($editMode): ?>
              <a href="requestblood.php" class="border-2 border-gray-300 text-gray-600 py-4 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition text-center text-sm">Cancel Edit</a>
            <?php else: ?>
              <a href="bloodrequest.php" class="border-2 border-gray-300 text-gray-600 py-4 rounded-xl font-bold hover:border-red-400 hover:text-red-600 transition text-center text-sm">← Back to Requests</a>
            <?php endif; ?>
            <button type="submit" class="bg-gradient-to-r from-red-600 to-red-700 text-white py-4 rounded-xl font-bold hover:shadow-xl hover:from-red-700 hover:to-red-800 transition transform hover:scale-105 text-sm">
              <?= $editMode ? 'Update Request' : 'Submit Blood Request 🩸' ?>
            </button>
          </div>
        </div>
      </form>

    </div>
  </section>

  <!-- My Blood Requests (CRUD Read) -->
  <section class="pb-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
      <div class="bg-white rounded-2xl shadow p-6 sm:p-8">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">📋</div>
            <div>
              <h2 class="text-xl font-bold text-gray-900">My Blood Requests</h2>
              <p class="text-sm text-gray-500">View, edit, or delete your blood request entries.</p>
            </div>
          </div>
          <span class="text-sm text-gray-500">Total: <?= count($myRequests) ?></span>
        </div>

        <?php if (count($myRequests) > 0): ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-gray-100">
                  <th class="text-left text-gray-500 font-semibold pb-3 pr-4 hidden">ID</th>
                  <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Blood Type</th>
                  <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Units</th>
                  <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Hospital</th>
                  <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Required Date</th>
                  <th class="text-left text-gray-500 font-semibold pb-3">Status</th>
                  <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Urgency</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($myRequests as $r): ?>
                  <?php
                  $statusColor = match ($r['status'] ?? '') {
                    'Pending' => 'bg-yellow-100 text-yellow-700',
                    'Approved' => 'bg-blue-100 text-blue-700',
                    'Assigned' => 'bg-indigo-100 text-indigo-700',
                    'Accepted' => 'bg-purple-100 text-purple-700',
                    'Completed' => 'bg-green-100 text-green-700',
                    'Rejected' => 'bg-red-100 text-red-600',
                    'Expired' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                    default => 'bg-gray-100 text-gray-600',
                  };
                  $urgencyColor = match ($r['urgency'] ?? '') {
                    'Normal' => 'bg-blue-100 text-blue-700',
                    'Urgent' => 'bg-red-100 text-red-600',
                    default => 'bg-gray-100 text-gray-600',
                  };
                  ?>
                  <tr class="hover:bg-gray-50">
                    <td class="py-3 pr-4 font-medium text-gray-700 hidden">#<?= $r['id'] ?></td>
                    <td class="py-3 pr-4">
                      <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs">
                        <?= htmlspecialchars($r['blood_gp_name'] ?? 'N/A') ?>
                      </span>
                    </td>
                    <td class="py-3 pr-4 text-gray-600">1 Unit</td>
                    <td class="py-3 pr-4 text-gray-800 font-medium"><?= htmlspecialchars($r['hospital']) ?></td>
                    <td class="py-3 pr-4 text-gray-600"><?= date('M j, Y', strtotime($r['required_date'])) ?></td>
                    <td class="py-3 pr-4">
                      <span class="<?= $statusColor ?> text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($r['status']) ?></span>
                    </td>
                    <td class="py-3 pr-4">
                      <span class="<?= $urgencyColor ?> text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($r['urgency'] ?? 'Normal') ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="border-2 border-dashed border-gray-200 rounded-xl p-10 text-center">
            <div class="text-4xl mb-3">🩸</div>
            <p class="text-gray-500 text-lg mb-2">No blood requests yet.</p>
            <p class="text-gray-400 text-sm">Fill in the form above to submit a blood request.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

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

    // Phone field: numbers only, max 15 digits
    const contactField = document.getElementById('contactField');
    if (contactField) {
      contactField.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 15);
      });
      contactField.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const cleaned = pasted.replace(/[^0-9]/g, '').slice(0, 15);
        document.execCommand('insertText', false, cleaned);
      });
    }

    // Required Date: no past dates
    const requiredDate = document.getElementById('requiredDate');
    if (requiredDate) {
      const today = new Date().toISOString().split('T')[0];
      requiredDate.setAttribute('min', today);
      requiredDate.addEventListener('change', function() {
        if (this.value < today) {
          this.value = today;
        }
      });
    }

    // Phone Number Mismatch Confirmation Logic
    const savedProfilePhone = <?= json_encode((string)($userPhone ?? '')) ?>;
    const requestBloodForm = document.getElementById('requestBloodForm');
    const phoneMismatchModal = document.getElementById('phoneMismatchModal');
    const modalCurrentPhone = document.getElementById('modalCurrentPhone');
    const modalNewPhone = document.getElementById('modalNewPhone');
    const confirmUpdatePhoneBtn = document.getElementById('confirmUpdatePhoneBtn');

    let phoneMismatchConfirmed = false;

    function closePhoneMismatchModal() {
      if (phoneMismatchModal) {
        phoneMismatchModal.style.setProperty('display', 'none', 'important');
        phoneMismatchModal.classList.add('hidden');
        phoneMismatchModal.classList.remove('flex');
        document.body.style.overflow = '';
      }
    }

    function showPhoneMismatchModal(currentPhone, newPhone) {
      if (modalCurrentPhone) modalCurrentPhone.textContent = currentPhone;
      if (modalNewPhone) modalNewPhone.textContent = newPhone;
      if (phoneMismatchModal) {
        phoneMismatchModal.style.setProperty('display', 'flex', 'important');
        phoneMismatchModal.classList.remove('hidden');
        phoneMismatchModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
      }
    }

    function checkPhoneMismatch(e) {
      if (phoneMismatchConfirmed) {
        return true;
      }
      const phoneInput = document.getElementById('contactField') || (requestBloodForm ? requestBloodForm.querySelector('input[name="phone"], input[name="contact"]') : null);
      const enteredVal = phoneInput ? phoneInput.value.trim() : '';
      const cleanSaved = (savedProfilePhone || '').replace(/\D/g, '');
      const cleanEntered = enteredVal.replace(/\D/g, '');

      if (cleanSaved !== cleanEntered) {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
          if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
          }
        }
        showPhoneMismatchModal(savedProfilePhone || '(Not set)', enteredVal);
        return false;
      }
      return true;
    }

    if (requestBloodForm) {
      requestBloodForm.addEventListener('submit', function(e) {
        if (!checkPhoneMismatch(e)) {
          e.preventDefault();
        }
      }, true);
      requestBloodForm.onsubmit = function(e) {
        return checkPhoneMismatch(e);
      };

      const submitBtn = requestBloodForm.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
          if (requestBloodForm.checkValidity && !requestBloodForm.checkValidity()) {
            return;
          }
          if (!checkPhoneMismatch(e)) {
            e.preventDefault();
          }
        }, true);
      }
    }

    if (confirmUpdatePhoneBtn) {
      confirmUpdatePhoneBtn.addEventListener('click', function(e) {
        e.preventDefault();
        phoneMismatchConfirmed = true;
        closePhoneMismatchModal();
        if (requestBloodForm) {
          requestBloodForm.submit();
        }
      });
    }
  </script>

  <!-- Phone Mismatch Confirmation Modal -->
  <div id="phoneMismatchModal" class="fixed inset-0 bg-black/80 backdrop-blur-md items-center justify-center p-4 sm:p-6 transition-all duration-300" style="display: none; z-index: 999999;">
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border-2 border-amber-400 dark:border-amber-500 animate-fade-up relative transform transition-all">
      <div class="p-6 sm:p-8 text-center space-y-5">
        <!-- Warning Icon Badge -->
        <div class="w-20 h-20 bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 rounded-2xl flex items-center justify-center text-4xl mx-auto shadow-inner ring-8 ring-amber-50 dark:ring-amber-950/40">
          ⚠️
        </div>
        
        <!-- Header -->
        <div>
          <h2 class="font-extrabold text-2xl sm:text-3xl text-gray-900 dark:text-white tracking-tight mb-2">Phone Number Mismatch</h2>
          <p class="text-base font-semibold text-gray-700 dark:text-gray-200">
            The phone number you entered is different from your profile information.
          </p>
        </div>
        
        <!-- Comparison Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 my-2">
          <div class="bg-gray-50 dark:bg-gray-700/60 rounded-2xl p-4 border border-gray-200 dark:border-gray-600 text-left">
            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-1">Profile Phone</span>
            <span id="modalCurrentPhone" class="font-extrabold text-gray-900 dark:text-gray-100 text-lg tracking-wide block"></span>
          </div>
          <div class="bg-red-50 dark:bg-red-950/40 rounded-2xl p-4 border-2 border-red-300 dark:border-red-700/60 text-left shadow-sm">
            <span class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider block mb-1">New Phone Number</span>
            <span id="modalNewPhone" class="font-extrabold text-red-600 dark:text-red-400 text-lg tracking-wide block"></span>
          </div>
        </div>

        <!-- Question -->
        <p class="text-sm font-bold text-gray-800 dark:text-gray-200">
          Do you want to update your profile phone number to the new number?
        </p>
      </div>
      
      <!-- Action Buttons -->
      <div class="p-6 sm:p-8 pt-0 flex flex-col-reverse sm:flex-row gap-3">
        <button type="button" onclick="closePhoneMismatchModal()" class="w-full sm:flex-1 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 py-3.5 px-6 rounded-xl font-bold hover:bg-gray-100 dark:hover:bg-gray-700 transition text-base text-center cursor-pointer">
          Cancel
        </button>
        <button type="button" id="confirmUpdatePhoneBtn" class="w-full sm:flex-1 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white py-3.5 px-6 rounded-xl font-bold transition text-center shadow-lg hover:shadow-xl transform hover:scale-[1.02] flex items-center justify-center gap-2 text-base cursor-pointer">
          <span>Update Profile</span>
          <span>✓</span>
        </button>
      </div>
    </div>
  </div>

  <script>
    (function() {
      var KEY = 'bloodlife-theme';

      function getTheme() {
        return localStorage.getItem(KEY) || 'light';
      }

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