<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$userId = $_SESSION['user_id'] ?? 0;

// Handle inline login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
    $loginUser = trim($_POST['login_user'] ?? '');
    $loginPass = $_POST['login_pass'] ?? '';

    if ($loginUser === '' || $loginPass === '') {
        $loginError = 'Please enter both username and password.';
    } else {
        $loginSuccess = false;

        if ($loginUser === 'admin' && $loginPass === 'password123') {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = 'admin';
            $_SESSION['user_email'] = 'admin@bloodlife.local';
            $loginSuccess = true;
        }

        if (!$loginSuccess && $loginUser === 'user' && $loginPass === '123456') {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = 'user';
            $loginSuccess = true;
        }

        if (!$loginSuccess) {
            $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $loginUser, $loginUser);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $storedPassword = $row['password'];
                    if ($loginPass === $storedPassword || password_verify($loginPass, $storedPassword)) {
                        $_SESSION['logged_in'] = true;
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['user_id'] = $row['id'];
                        $loginSuccess = true;
                    }
                }
                $stmt->close();
            }
        }

        if ($loginSuccess) {
            $isLoggedIn = true;
            $username = htmlspecialchars($_SESSION['username']);
            $userId = $_SESSION['user_id'] ?? 0;
            header('Location: requestblood.php');
            exit;
        } else {
            $loginError = 'Invalid username or password.';
        }
    }
}

$bloodGroups = [];
try {
    $bgResult = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");
    if ($bgResult) $bloodGroups = $bgResult->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {}

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
        $stmt = $conn->prepare("SELECT br.*, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.id = ? AND br.users_id = ?");
        $stmt->bind_param("ii", $editId, $userId);
        $stmt->execute();
        $editData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($editData) {
            $editMode = true;
        } else {
            $message = 'Record not found.';
            $messageType = 'error';
        }
    }

    // CREATE or UPDATE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blood_groups_id'])) {
        $blood_groups_id = (int)$_POST['blood_groups_id'];
        $units = max(1, (int)($_POST['units'] ?? 1));
        $hospital = trim($_POST['hospital'] ?? '');
        $required_date = $_POST['required_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'Pending';
        $updateId = (int)($_POST['update_id'] ?? 0);

        if ($blood_groups_id < 1) {
            $message = 'Please select a blood type.';
            $messageType = 'error';
        } elseif ($hospital === '') {
            $message = 'Please enter the hospital name.';
            $messageType = 'error';
        } else {
            if ($updateId > 0) {
                $stmt = $conn->prepare("UPDATE blood_request SET blood_groups_id=?, units=?, hospital=?, required_date=?, status=? WHERE id=? AND users_id=?");
                $stmt->bind_param("iisssii", $blood_groups_id, $units, $hospital, $required_date, $status, $updateId, $userId);
            } else {
                $stmt = $conn->prepare("INSERT INTO blood_request (users_id, blood_groups_id, units, hospital, required_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisss", $userId, $blood_groups_id, $units, $hospital, $required_date, $status);
            }
            if ($stmt->execute()) {
                $message = $updateId > 0 ? 'Blood request updated successfully!' : 'Blood request submitted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to save request. Please try again.';
                $messageType = 'error';
            }
            $stmt->close();
        }

        if ($messageType === 'success') {
            header('Location: requestblood.php?msg=' . ($updateId > 0 ? 'updated' : 'created'));
            exit;
        }
        $editMode = false;
    }

    // Fetch user's blood requests
    $myRequests = [];
    $stmt = $conn->prepare("SELECT br.*, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.users_id = ? ORDER BY br.id DESC");
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
        if ($msg === 'created') { $message = 'Blood request submitted successfully!'; $messageType = 'success'; }
        elseif ($msg === 'updated') { $message = 'Blood request updated successfully!'; $messageType = 'success'; }
        elseif ($msg === 'deleted') { $message = 'Blood request deleted successfully.'; $messageType = 'success'; }
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
    @keyframes fadeInDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeInUp   { from { opacity:0; transform:translateY( 20px); } to { opacity:1; transform:translateY(0); } }
    .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
    .animate-fade-up   { animation: fadeInUp   0.6s ease-out; }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body { background-color: #fdf2f8 !important; background-image: none !important; }
    html:not(.dark) .bg-gray-50 { background-color: #fdf2f8 !important; }
    html:not(.dark) .bg-gray-100 { background-color: #fce7f3 !important; }
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
    html.dark tbody tr { border-color: #374151 !important; }
    html.dark tbody tr:hover { background-color: #374151 !important; }
  </style>
</head>
<body class="bg-gradient-to-b from-pink-50 to-pink-100 dark:from-gray-900 dark:to-gray-900 min-h-screen">

  <!-- Navbar -->
  <nav class="bg-white shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="flex items-center space-x-3 animate-fade-down">
          <span class="text-2xl bg-red-200 p-1 rounded-full shadow-md">🩸</span>
          <div>
            <h1 class="font-bold text-xl text-red-700">BloodLife</h1>
            <p class="text-xs text-gray-500">Save Lives Together</p>
          </div>
        </div>
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
            <a href="donordashboard.php" class="flex items-center gap-2 hover:text-red-600 transition">
              <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-sm font-bold text-red-700">
                <?= strtoupper(substr($username, 0, 1)) ?>
              </div>
              <span class="font-medium text-gray-700"><?= $username ?></span>
            </a>
            <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2 rounded-lg font-semibold hover:shadow-lg transition text-sm">Logout</a>
          <?php else: ?>
            <a href="login.php" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg font-semibold hover:shadow-lg transition">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

<?php if ($isLoggedIn): ?>

  <!-- Hero Banner -->
  <section class="bg-gradient-to-r from-red-600 to-red-800 text-white py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center animate-fade-up">
      <div class="inline-block bg-white/20 px-4 py-2 rounded-full text-sm font-semibold mb-4">🩸 <span data-i18n="emergency_help">Emergency Help</span></div>
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
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 animate-fade-up">

      <form method="POST">
        <?php if ($editMode && $editData): ?>
          <input type="hidden" name="update_id" value="<?= $editData['id'] ?>" />
        <?php endif; ?>

        <!-- Blood Request Details Card -->
        <div class="bg-white rounded-2xl shadow p-8 mb-6">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-xl">🩸</div>
            <h2 class="text-xl font-bold text-gray-900">Blood Request Details</h2>
          </div>

          <div class="grid gap-5">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Type <span class="text-red-500">*</span></label>
              <select name="blood_groups_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                <option value="">Select blood type</option>
                <?php foreach ($bloodGroups as $bg): ?>
                <option value="<?= $bg['id'] ?>" <?= ($editData['blood_groups_id'] ?? '') == $bg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Units Required <span class="text-red-500">*</span></label>
              <input type="number" name="units" min="1" max="10"
                value="<?= htmlspecialchars($editData['units'] ?? '1') ?>"
                placeholder="e.g. 2" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Hospital <span class="text-red-500">*</span></label>
              <input type="text" name="hospital"
                value="<?= htmlspecialchars($editData['hospital'] ?? '') ?>"
                placeholder="e.g. City General Hospital" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Required Date <span class="text-red-500">*</span></label>
              <input type="date" name="required_date"
                value="<?= htmlspecialchars($editData['required_date'] ?? date('Y-m-d')) ?>"
                required class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition" />
            </div>
            <?php if ($editMode): ?>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
              <select name="status" class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition bg-white">
                <?php foreach (['Pending','Approved','Completed','Rejected'] as $st): ?>
                <option value="<?= $st ?>" <?= ($editData['status'] ?? 'Pending') === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Submit -->
        <div class="bg-white rounded-2xl shadow p-8">
          <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-6 flex items-start gap-3">
            <span class="text-2xl">⚠️</span>
            <p class="text-sm text-red-700">For life-threatening emergencies, please also call <span class="font-bold">1-800-BLOOD-999</span> directly. Our team is available 24/7.</p>
          </div>

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
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">ID</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Blood Type</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Units</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Hospital</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Required Date</th>
                <th class="text-left text-gray-500 font-semibold pb-3 pr-4">Status</th>
                <th class="text-left text-gray-500 font-semibold pb-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <?php foreach ($myRequests as $r): ?>
                <?php
                  $statusColor = match($r['status'] ?? '') {
                    'Pending' => 'bg-yellow-100 text-yellow-700',
                    'Approved' => 'bg-blue-100 text-blue-700',
                    'Completed' => 'bg-green-100 text-green-700',
                    'Rejected' => 'bg-red-100 text-red-600',
                    default => 'bg-gray-100 text-gray-600',
                  };
                ?>
              <tr class="hover:bg-gray-50">
                <td class="py-3 pr-4 font-medium text-gray-700">#<?= $r['id'] ?></td>
                <td class="py-3 pr-4">
                  <span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs">
                    <?= htmlspecialchars($r['blood_gp_name'] ?? 'N/A') ?>
                  </span>
                </td>
                <td class="py-3 pr-4 text-gray-600"><?= (int)$r['units'] ?> unit(s)</td>
                <td class="py-3 pr-4 text-gray-800 font-medium"><?= htmlspecialchars($r['hospital']) ?></td>
                <td class="py-3 pr-4 text-gray-600"><?= date('M j, Y', strtotime($r['required_date'])) ?></td>
                <td class="py-3 pr-4">
                  <span class="<?= $statusColor ?> text-xs font-bold px-2 py-1 rounded-full"><?= htmlspecialchars($r['status']) ?></span>
                </td>
                <td class="py-3">
                  <div class="flex gap-2">
                    <a href="requestblood.php?edit=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Edit</a>
                    <a href="requestblood.php?delete=<?= $r['id'] ?>" class="text-red-600 hover:text-red-800 font-semibold text-xs" onclick="return confirm('Delete this blood request?')">Delete</a>
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
          <p class="text-gray-500 text-lg mb-2">No blood requests yet.</p>
          <p class="text-gray-400 text-sm">Fill in the form above to submit a blood request.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php else: ?>

  <!-- Login Form (shown when not logged in) -->
  <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md animate-fade-up">
      <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

        <!-- Header -->
        <div class="bg-gradient-to-r from-red-600 to-red-800 text-white px-8 py-8 text-center">
          <span class="text-5xl mb-3 block">🩸</span>
          <h1 class="text-2xl font-bold">Sign In</h1>
          <p class="text-red-200 text-sm mt-1">Login to submit a blood request</p>
        </div>

        <!-- Form -->
        <div class="px-8 py-8 space-y-5">

          <div class="bg-blue-50 border-l-2 border-blue-500 p-4 rounded">
            <p class="text-blue-700 text-sm">You must be logged in to submit a blood request. Please sign in below.</p>
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

<?php endif; ?>

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
            <li><a href="index.php" class="hover:text-red-400 transition">Home</a></li>
            <li><a href="donor.php" class="hover:text-red-400 transition">Donors</a></li>
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
            <a href="#" class="hover:text-red-400 transition">Facebook</a>
            <a href="#" class="hover:text-red-400 transition">Twitter</a>
            <a href="#" class="hover:text-red-400 transition">Instagram</a>
          </div>
        </div>
      </div>
      <div class="border-t border-gray-700 pt-8 text-center text-sm">
        <p>&copy; BloodLife. All rights reserved.</p>
      </div>
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
