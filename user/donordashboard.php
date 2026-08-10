<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$userId = $_SESSION['user_id'] ?? 0;

// Role-based access control
$userRole = $_SESSION['user_role'] ?? '';
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Redirect Admin to admin dashboard
if ($userRole === 'Admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

// Greeting time based
$hour = date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$donorData = [];
$donationCount = 0;
$donations = [];
$urgentRequests = [];
$myRequests = [];
$myRequestsCount = 0;
$notifications = [];

if ($isLoggedIn) {
    // Donor info
    $stmt = $conn->prepare("SELECT d.id AS donor_id, d.user_id, u.username, u.email, d.address, d.blood_groups AS blood_group_name
                            FROM donor d
                            JOIN users u ON u.id = d.user_id
                            WHERE d.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $donorData = $result->fetch_assoc();
    $stmt->close();

    // Donation count & history
    if (!empty($donorData['donor_id'])) {
        $did = $donorData['donor_id'];
        $stmt2 = $conn->prepare("SELECT dh.*, bg.blood_gp_name
                                 FROM donation_history dh
                                 LEFT JOIN blood_groups bg ON bg.id = dh.blood_groups_id
                                 WHERE dh.donor_id = ?
                                 ORDER BY dh.donation_date DESC");
        $stmt2->bind_param("i", $did);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $donations = $result2->fetch_all(MYSQLI_ASSOC);
        $donationCount = count($donations);
        $stmt2->close();
    }

    // Urgent requests
    $urgent = $conn->query("SELECT r.id, r.units, r.hospital, r.required_date, r.status,
                                   bg.blood_gp_name
                            FROM blood_request r
                            LEFT JOIN blood_groups bg ON bg.id = r.blood_groups_id
                            WHERE r.status IN ('Pending','Approved')
                            ORDER BY r.required_date DESC LIMIT 5");
    if ($urgent) $urgentRequests = $urgent->fetch_all(MYSQLI_ASSOC);

    // My requests
    $stmt3 = $conn->prepare("SELECT br.*, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.users_id = ? ORDER BY br.id DESC");
    $stmt3->bind_param("i", $userId);
    $stmt3->execute();
    $myResult = $stmt3->get_result();
    $allMyRequests = [];
    if ($myResult && $myResult->num_rows > 0) {
        $allMyRequests = $myResult->fetch_all(MYSQLI_ASSOC);
    }
    $myRequestsCount = count($allMyRequests);
    $myRequests = array_slice($allMyRequests, 0, 3);
    $stmt3->close();
    
    // Notifications (Mock logic based on real data)
    if ($myRequestsCount > 0) {
        $latestReq = $myRequests[0];
        if ($latestReq['status'] == 'Pending') {
            $notifications[] = ['icon' => '⏳', 'text' => 'Your blood request is pending approval.', 'time' => 'Recently', 'type' => 'warning'];
        } elseif ($latestReq['status'] == 'Approved') {
            $notifications[] = ['icon' => '✅', 'text' => 'Your blood request has been approved.', 'time' => 'Recently', 'type' => 'success'];
        }
    }
    if ($donationCount > 0) {
        $notifications[] = ['icon' => '🎉', 'text' => 'Thank you for your recent donation.', 'time' => 'Past', 'type' => 'info'];
    }
    if (empty($notifications)) {
        $notifications[] = ['icon' => '👋', 'text' => 'Welcome to your dashboard!', 'time' => 'Just now', 'type' => 'info'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Dashboard – BloodLife</title>
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
    .glassmorphism {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    html.dark .glassmorphism {
        background: rgba(31, 41, 55, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .hover-scale { transition: transform 0.2s ease-in-out; }
    .hover-scale:hover { transform: scale(1.02); }
  </style>
  <style id="dark-mode-styles">
    html:not(.dark) body { background-color: #f8fafc !important; }
    html.dark body { background-color: #0f172a !important; color: #e2e8f0; }
    html.dark .bg-white { background-color: #1e293b !important; }
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f8fafc !important; }
    html.dark .text-gray-700 { color: #cbd5e1 !important; }
    html.dark .text-gray-600 { color: #94a3b8 !important; }
    html.dark .text-gray-500 { color: #94a3b8 !important; }
    html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: #334155 !important; }
    html.dark .border-gray-200, html.dark .border-2.border-gray-200 { border-color: #475569 !important; }
    html.dark .bg-red-50 { background-color: rgba(220,38,38,0.15) !important; }
    html.dark .bg-green-50 { background-color: rgba(34,197,94,0.15) !important; }
    html.dark tbody tr { border-color: #334155 !important; }
    html.dark tbody tr:hover { background-color: #334155 !important; }
  </style>
</head>
<body class="min-h-screen text-gray-800 transition-colors duration-300">

  <!-- Navbar -->
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <!-- Welcome Banner -->
  <section class="relative bg-gradient-to-br from-red-600 to-rose-900 text-white py-12 overflow-hidden">
    <!-- Decorative elements -->
    <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full bg-white opacity-10 blur-3xl"></div>
    <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-red-400 opacity-20 blur-3xl"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 animate-fade-down">
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="text-center md:text-left">
          <p class="text-red-200 text-sm font-semibold mb-2 uppercase tracking-wider"><?= date('l, j F Y') ?></p>
          <h1 class="text-4xl md:text-5xl font-extrabold mb-3 tracking-tight"><?= $greeting ?>, <?= $username ?>! 👋</h1>
          <p class="text-lg text-red-100 max-w-2xl">Manage your blood donations and requests all in one place. Your actions save lives.</p>
        </div>
        <div class="flex gap-4">
          <a href="requestblood.php" class="glassmorphism text-white px-6 py-3 rounded-xl font-bold hover:bg-white/20 transition shadow-lg flex items-center gap-2">
            <span>🩸</span> <span data-i18n="submit_request_btn">Request Blood</span>
          </a>
          <a href="bloodrequest.php" class="bg-white text-red-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-100 transition shadow-lg flex items-center gap-2">
            <span>❤️</span> <span>Donate Now</span>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Donor Eligibility -->
  <section class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 border-b border-emerald-100 dark:border-emerald-800 py-4 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 animate-fade-up">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-800 rounded-full flex items-center justify-center text-xl shadow-sm">✅</div>
        <p class="text-emerald-800 dark:text-emerald-200 font-medium" data-i18n="eligible_donate_today">You are eligible to donate blood today! Thank you for being a hero.</p>
      </div>
      <a href="bloodrequest.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg font-semibold transition text-sm shadow-md" data-i18n="find_a_request">Find a Request</a>
    </div>
  </section>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    
    <!-- 4 Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-up" style="animation-delay: 0.1s;">
      <div class="bg-white rounded-2xl shadow-sm p-6 hover-scale border border-gray-100">
        <div class="flex justify-between items-start mb-4">
          <div class="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center text-2xl">🩸</div>
          <span class="bg-red-50 text-red-600 text-xs font-bold px-2 py-1 rounded-full">Donor</span>
        </div>
        <h3 class="text-3xl font-extrabold text-gray-900"><?= $donationCount ?></h3>
        <p class="text-gray-500 font-medium mt-1">Total Donations</p>
      </div>
      
      <div class="bg-white rounded-2xl shadow-sm p-6 hover-scale border border-gray-100">
        <div class="flex justify-between items-start mb-4">
          <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-2xl">📋</div>
          <span class="bg-blue-50 text-blue-600 text-xs font-bold px-2 py-1 rounded-full">Requester</span>
        </div>
        <h3 class="text-3xl font-extrabold text-gray-900"><?= $myRequestsCount ?></h3>
        <p class="text-gray-500 font-medium mt-1">Blood Requests</p>
      </div>
      
      <div class="bg-white rounded-2xl shadow-sm p-6 hover-scale border border-gray-100">
        <div class="flex justify-between items-start mb-4">
          <div class="w-12 h-12 bg-yellow-100 text-yellow-600 rounded-xl flex items-center justify-center text-2xl">🏆</div>
          <span class="bg-yellow-50 text-yellow-600 text-xs font-bold px-2 py-1 rounded-full">Rewards</span>
        </div>
        <h3 class="text-3xl font-extrabold text-gray-900"><?= min(4, $donationCount) ?></h3>
        <p class="text-gray-500 font-medium mt-1">Badges Earned</p>
      </div>
      
      <div class="bg-white rounded-2xl shadow-sm p-6 hover-scale border border-gray-100">
        <div class="flex justify-between items-start mb-4">
          <div class="w-12 h-12 bg-green-100 text-green-600 rounded-xl flex items-center justify-center text-2xl">❤️</div>
          <span class="bg-green-50 text-green-600 text-xs font-bold px-2 py-1 rounded-full">Status</span>
        </div>
        <h3 class="text-2xl font-extrabold text-gray-900 mt-1"><?= $donationCount > 0 ? 'Active' : 'Ready' ?></h3>
        <p class="text-gray-500 font-medium mt-1">Donor Status</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-8">
      
      <!-- Main Content (Left, 2 columns wide) -->
      <div class="lg:col-span-2 space-y-8">
        
        <!-- Current Blood Request (Requester Side) -->
        <div class="bg-white rounded-3xl shadow-sm p-7 border border-gray-100 animate-fade-up" style="animation-delay: 0.2s;">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl shadow-inner">📣</div>
              <h2 class="text-2xl font-bold text-gray-900">My Blood Requests</h2>
            </div>
            <a href="requestblood.php" class="text-blue-600 font-semibold hover:text-blue-700 transition text-sm">Manage All →</a>
          </div>
          
          <div class="space-y-4">
            <?php if (count($myRequests) > 0): ?>
              <?php foreach ($myRequests as $req): ?>
                <?php 
                  $stColor = match($req['status']) {
                    'Pending' => 'bg-yellow-100 text-yellow-700',
                    'Approved' => 'bg-blue-100 text-blue-700',
                    'Completed' => 'bg-green-100 text-green-700',
                    'Rejected' => 'bg-red-100 text-red-700',
                    default => 'bg-gray-100 text-gray-700'
                  };
                ?>
                <div class="group flex flex-col sm:flex-row sm:items-center justify-between p-5 rounded-2xl border border-gray-100 hover:border-blue-200 hover:shadow-md transition bg-gray-50/50">
                  <div class="flex items-center gap-4 mb-3 sm:mb-0">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold text-xl flex items-center justify-center shadow-sm">
                      <?= htmlspecialchars($req['blood_gp_name'] ?? '?') ?>
                    </div>
                    <div>
                      <h4 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($req['hospital']) ?></h4>
                      <p class="text-sm text-gray-500 font-medium"><?= $req['units'] ?> unit(s) • Needed by <?= date('M j, Y', strtotime($req['required_date'])) ?></p>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="<?= $stColor ?> px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider"><?= htmlspecialchars($req['status']) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-center py-10 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                <div class="text-4xl mb-3">🛌</div>
                <h3 class="text-lg font-bold text-gray-700 mb-1">No Active Requests</h3>
                <p class="text-gray-500 text-sm mb-4">You haven't made any blood requests recently.</p>
                <a href="requestblood.php" class="inline-block bg-blue-600 text-white px-5 py-2 rounded-lg font-semibold hover:bg-blue-700 transition text-sm">Request Blood Now</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Donation Assignment / Urgent Requests (Donor Side) -->
        <div class="bg-white rounded-3xl shadow-sm p-7 border border-gray-100 animate-fade-up" style="animation-delay: 0.3s;">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl shadow-inner">🚨</div>
              <div>
                <h2 class="text-2xl font-bold text-gray-900">Donation Assignments</h2>
                <p class="text-sm text-gray-500">Urgent requests matching your profile</p>
              </div>
            </div>
            <a href="bloodrequest.php" class="text-red-600 font-semibold hover:text-red-700 transition text-sm">View Map →</a>
          </div>
          
          <div class="grid gap-4">
            <?php if (count($urgentRequests) > 0): ?>
              <?php foreach (array_slice($urgentRequests, 0, 3) as $ur): ?>
                <div class="relative overflow-hidden bg-white border border-red-100 rounded-2xl p-5 hover:border-red-300 hover:shadow-lg transition group">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-red-50 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-150 duration-500"></div>
                  <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                      <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-500 to-red-700 text-white font-extrabold text-xl flex items-center justify-center shadow-md">
                        <?= htmlspecialchars($ur['blood_gp_name'] ?? 'N/A') ?>
                      </div>
                      <div>
                        <h4 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($ur['hospital']) ?></h4>
                        <div class="flex items-center gap-2 mt-1">
                          <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-md">Urgent</span>
                          <span class="text-sm text-gray-500 font-medium"><?= htmlspecialchars($ur['units']) ?> unit(s) needed</span>
                        </div>
                      </div>
                    </div>
                    <a href="bloodrequest.php" class="w-full sm:w-auto text-center bg-red-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-red-700 transition shadow-md">Accept</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-center py-10 bg-gray-50 rounded-2xl border border-gray-100">
                <p class="text-gray-500 font-medium">No urgent assignments at this time. Relax!</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Donation History -->
        <div class="bg-white rounded-3xl shadow-sm p-7 border border-gray-100 animate-fade-up" style="animation-delay: 0.4s;">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl shadow-inner">📜</div>
              <h2 class="text-2xl font-bold text-gray-900">Donation History</h2>
            </div>
          </div>
          
          <div class="overflow-hidden rounded-xl border border-gray-100">
            <table class="w-full text-left text-sm">
              <thead class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider text-xs">
                <tr>
                  <th class="px-5 py-4">Date</th>
                  <th class="px-5 py-4">Blood Type</th>
                  <th class="px-5 py-4">Units</th>
                  <th class="px-5 py-4 text-right">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <?php if (count($donations) > 0): ?>
                  <?php foreach (array_slice($donations, 0, 5) as $d): ?>
                  <tr class="hover:bg-gray-50/50 transition">
                    <td class="px-5 py-4 font-medium text-gray-900"><?= date('M j, Y', strtotime($d['donation_date'])) ?></td>
                    <td class="px-5 py-4">
                      <span class="inline-flex items-center justify-center px-2 py-1 rounded bg-red-50 text-red-700 font-bold text-xs"><?= htmlspecialchars($d['blood_gp_name'] ?? '-') ?></span>
                    </td>
                    <td class="px-5 py-4 text-gray-600 font-medium"><?= htmlspecialchars($d['units'] ?? '1') ?></td>
                    <td class="px-5 py-4 text-right">
                      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 font-bold text-xs"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><?= htmlspecialchars($d['status'] ?? 'Completed') ?></span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="px-5 py-8 text-center text-gray-400 font-medium">No donations recorded yet.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right Column -->
      <div class="space-y-8">
        
        <!-- Notifications -->
        <div class="bg-white rounded-3xl shadow-sm p-7 border border-gray-100 animate-fade-up" style="animation-delay: 0.3s;">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl shadow-inner">🔔</div>
            <h2 class="text-xl font-bold text-gray-900">Notifications</h2>
          </div>
          
          <div class="space-y-4">
            <?php foreach ($notifications as $notif): ?>
              <?php
                $bgClasses = match($notif['type']) {
                  'warning' => 'bg-yellow-50 border-yellow-100',
                  'success' => 'bg-emerald-50 border-emerald-100',
                  default => 'bg-gray-50 border-gray-100'
                };
              ?>
              <div class="flex gap-4 p-4 rounded-2xl border <?= $bgClasses ?>">
                <div class="text-2xl flex-shrink-0"><?= $notif['icon'] ?></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900 mb-1"><?= htmlspecialchars($notif['text']) ?></p>
                  <p class="text-xs text-gray-500 font-medium"><?= $notif['time'] ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-3xl shadow-sm p-7 border border-gray-100 animate-fade-up" style="animation-delay: 0.4s;">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl shadow-inner">⚡</div>
            <h2 class="text-xl font-bold text-gray-900">Quick Actions</h2>
          </div>
          
          <div class="grid grid-cols-2 gap-3">
            <a href="requestblood.php" class="flex flex-col items-center text-center p-4 rounded-2xl border border-gray-100 hover:bg-gray-50 hover:border-gray-200 transition group">
              <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-2 group-hover:scale-110 transition-transform">📣</div>
              <span class="text-xs font-bold text-gray-700">Request Blood</span>
            </a>
            <a href="bloodrequest.php" class="flex flex-col items-center text-center p-4 rounded-2xl border border-gray-100 hover:bg-gray-50 hover:border-gray-200 transition group">
              <div class="w-12 h-12 rounded-full bg-red-50 text-red-600 flex items-center justify-center text-xl mb-2 group-hover:scale-110 transition-transform">🩸</div>
              <span class="text-xs font-bold text-gray-700">Donate Blood</span>
            </a>
            <a href="hospital.php" class="flex flex-col items-center text-center p-4 rounded-2xl border border-gray-100 hover:bg-gray-50 hover:border-gray-200 transition group">
              <div class="w-12 h-12 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center text-xl mb-2 group-hover:scale-110 transition-transform">🏥</div>
              <span class="text-xs font-bold text-gray-700">Find Hospital</span>
            </a>
            <a href="profile.php" class="flex flex-col items-center text-center p-4 rounded-2xl border border-gray-100 hover:bg-gray-50 hover:border-gray-200 transition group">
              <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-xl mb-2 group-hover:scale-110 transition-transform">👤</div>
              <span class="text-xs font-bold text-gray-700">Edit Profile</span>
            </a>
          </div>
        </div>

        <!-- Mini Profile Card -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 text-white rounded-3xl shadow-lg p-7 relative overflow-hidden animate-fade-up" style="animation-delay: 0.5s;">
          <div class="absolute top-0 right-0 -mr-10 -mt-10 w-40 h-40 bg-white opacity-5 rounded-full"></div>
          <div class="relative z-10 text-center">
            <div class="w-20 h-20 bg-gray-700 rounded-full flex items-center justify-center text-4xl mx-auto mb-4 border-4 border-gray-600">👤</div>
            <h3 class="font-bold text-xl mb-1"><?= htmlspecialchars($donorData['username'] ?? $username ?: 'User') ?></h3>
            <p class="text-gray-400 text-sm mb-4"><?= htmlspecialchars($donorData['address'] ?? 'Location not set') ?></p>
            <div class="inline-block bg-red-600 text-white font-black px-6 py-2 rounded-xl text-lg shadow-md mb-4">
              <?= htmlspecialchars($donorData['blood_group_name'] ?? 'Unknown') ?>
            </div>
            <a href="profile.php" class="block w-full bg-gray-700 hover:bg-gray-600 py-3 rounded-xl font-bold transition text-sm">Account Settings</a>
          </div>
        </div>

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