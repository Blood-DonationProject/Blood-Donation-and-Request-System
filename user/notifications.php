<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filterQuery = "";
if ($filter === 'unread') {
    $filterQuery = " AND is_read = 0";
} elseif ($filter === 'read') {
    $filterQuery = " AND is_read = 1";
}

// Total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?" . $filterQuery);
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$totalNotifs = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($totalNotifs / $limit);

// Fetch notifications
$stmt = $conn->prepare("SELECT id, request_id, assignment_id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ?" . $filterQuery . " ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $userId, $limit, $offset);
$stmt->execute();
$notificationsList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Notifications - BloodLife</title>
  <script>(function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();</script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/css/myanmar-font.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* Dark mode body & nav */
    html.dark body { background-color: #111827 !important; color: #e5e7eb; }
    html.dark nav.bg-white { background-color: #1f2937 !important; }
    html.dark .bg-white { background-color: #1f2937 !important; }
    html.dark .text-gray-900, html.dark .text-gray-800 { color: #f3f4f6 !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark .text-gray-600 { color: #9ca3af !important; }
    html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark .border-gray-100, html.dark .border-gray-200 { border-color: #374151 !important; }
    html.dark .bg-gray-50 { background-color: #374151 !important; }
    html.dark .hover\:bg-gray-50:hover { background-color: #374151 !important; }
    html.dark .bg-red-50 { background-color: rgba(220,38,38,0.1) !important; }
    html.dark .hover\:bg-red-100:hover { background-color: rgba(220,38,38,0.2) !important; }
    
    .pulse-dot {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: .7; transform: scale(1.1); }
    }
  </style>
</head>
<body class="bg-gray-50 transition-colors duration-300">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="max-w-4xl mx-auto px-4 py-8 min-h-[calc(100vh-140px)]">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h2 class="text-2xl font-bold text-gray-800">Your Notifications</h2>
      
      <div class="flex items-center gap-3">
        <!-- Filters -->
        <div class="flex bg-white rounded-lg shadow-sm border p-1 border-gray-200">
            <a href="?filter=all" class="px-4 py-1.5 text-sm font-medium rounded-md transition <?= $filter === 'all' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">All</a>
            <a href="?filter=unread" class="px-4 py-1.5 text-sm font-medium rounded-md transition <?= $filter === 'unread' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">Unread</a>
            <a href="?filter=read" class="px-4 py-1.5 text-sm font-medium rounded-md transition <?= $filter === 'read' ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">Read</a>
        </div>
        
        <?php if ($totalNotifs > 0): ?>
          <a href="?read_notifs=1" class="text-sm font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-lg transition shadow-sm border border-blue-100">
              <i class="fas fa-check-double mr-1"></i> Mark all as read
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <?php if (count($notificationsList) > 0): ?>
        <div class="divide-y divide-gray-100">
          <?php foreach ($notificationsList as $n): 
            $is_read = $n['is_read'] == 1;
            $bgClass = $is_read ? 'bg-white hover:bg-gray-50' : 'bg-red-50 hover:bg-red-100/50';
            
            // Fetch extra contact info for successful assignments
            $contactHtml = '';
            $nTitle = $n['title'] ?? '';
            if (!empty($n['assignment_id']) && (strpos($nTitle, 'Assignment') !== false || strpos($nTitle, 'Assigned') !== false)) {
                $detailQuery = "SELECT br.users_id AS requester_user_id, br.hospital, br.required_date, bg.blood_gp_name, ru.username AS requester_name, ru.email AS requester_email, rd.phone AS requester_phone, du.username AS donor_name, du.email AS donor_email, d.phone AS donor_phone FROM donor_assignments da JOIN blood_request br ON da.request_id = br.id LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id JOIN donor d ON da.donor_id = d.id JOIN users du ON d.user_id = du.id JOIN users ru ON br.users_id = ru.id LEFT JOIN donor rd ON ru.id = rd.user_id WHERE da.id = ?";
                $stmtDetail = $conn->prepare($detailQuery);
                if ($stmtDetail) {
                    $stmtDetail->bind_param("i", $n['assignment_id']);
                    $stmtDetail->execute();
                    $detailRes = $stmtDetail->get_result();
                    if ($detail = $detailRes->fetch_assoc()) {
                        if ($nTitle == 'New Assignment' || strpos($nTitle, 'Assignment') !== false) {
                            $n['title'] = 'Donor Assignment';
                            $contactHtml = '<div class="mt-3 p-3 bg-red-50 rounded-xl text-sm space-y-1.5 text-gray-700 border border-red-100 shadow-sm">';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Blood Group:</span> ' . htmlspecialchars($detail['blood_gp_name'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Hospital:</span> ' . htmlspecialchars($detail['hospital'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Required Date:</span> ' . htmlspecialchars($detail['required_date'] ?? '') . '</p>';
                            $contactHtml .= '<div class="h-px bg-red-200 my-2"></div>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Requester Name:</span> ' . htmlspecialchars($detail['requester_name'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Requester Phone:</span> ' . htmlspecialchars($detail['requester_phone'] ?? 'N/A') . '</p>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Requester Email:</span> ' . htmlspecialchars($detail['requester_email'] ?? '') . '</p>';
                            $contactHtml .= '</div>';
                        } elseif ($nTitle == 'Donor Assigned') {
                            $contactHtml = '<div class="mt-3 p-3 bg-blue-50 rounded-xl text-sm space-y-1.5 text-gray-700 border border-blue-100 shadow-sm">';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Blood Group:</span> ' . htmlspecialchars($detail['blood_gp_name'] ?? '') . '</p>';
                            $contactHtml .= '<div class="h-px bg-blue-200 my-2"></div>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Donor Name:</span> ' . htmlspecialchars($detail['donor_name'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Donor Phone:</span> ' . htmlspecialchars($detail['donor_phone'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-bold text-gray-900">Donor Email:</span> ' . htmlspecialchars($detail['donor_email'] ?? '') . '</p>';
                            $contactHtml .= '</div>';
                        }
                    }
                    $stmtDetail->close();
                }
            }

            // Determine notification link dynamically based on type and title
            $link = "profile.php"; // default
            
            $nType = strtolower($n['type'] ?? '');
            $nTitle2 = strtolower($n['title'] ?? '');
            
            if ($n['title'] === 'Donor Assigned') {
                $link = "bloodrequest.php";
            } elseif (in_array($nType, ['assignment', 'donation']) || strpos($nTitle2, 'assign') !== false || strpos($nTitle2, 'donat') !== false) {
                $link = "donor.php";
            } elseif (in_array($nType, ['statusupdate', 'system']) || strpos($nTitle2, 'request') !== false || strpos($nTitle2, 'receive') !== false || strpos($nTitle2, 'status') !== false) {
                $link = "bloodrequest.php";
            } elseif (isset($_SESSION['role']) && strtolower($_SESSION['role']) == 'donor') {
                $link = "donor.php";
            }

            if (!empty($n['request_id']) && in_array($link, ['donor.php', 'bloodrequest.php'])) {
                $link .= "#req-" . $n['request_id'];
            }

            $read_link = "?mark_read=" . $n['id'] . "&redirect=" . urlencode($link);
          ?>
            <div class="p-5 transition <?= $bgClass ?>">
              <div class="flex items-start gap-4">
                <div class="mt-1">
                  <?php if (!$is_read): ?>
                    <div class="w-3 h-3 rounded-full bg-red-600 shadow-sm shadow-red-200"></div>
                  <?php else: ?>
                    <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                  <?php endif; ?>
                </div>
                
                <div class="flex-1">
                  <div class="flex justify-between items-start gap-2">
                    <h3 class="text-base font-bold text-gray-900"><?= htmlspecialchars($n['title']) ?></h3>
                    <span class="text-xs font-semibold text-gray-400 whitespace-nowrap"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></span>
                  </div>
                  <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($n['message']) ?></p>
                  <?= $contactHtml ?>
                  
                  <div class="mt-3">
                    <?php if (!$is_read): ?>
                      <a href="<?= htmlspecialchars($read_link) ?>" class="text-sm font-semibold text-red-600 hover:text-red-800 transition">
                        View Details &rarr;
                      </a>
                    <?php else: ?>
                      <a href="<?= htmlspecialchars($link) ?>" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition">
                        View Details &rarr;
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="p-12 text-center">
          <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-bell-slash text-gray-300 text-2xl"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">No notifications found</h3>
          <p class="text-gray-500 mt-1">You don't have any <?= $filter !== 'all' ? htmlspecialchars($filter) . ' ' : '' ?>notifications at the moment.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="mt-8 flex justify-center">
        <nav class="flex items-center gap-1 bg-white p-1 rounded-xl shadow-sm border border-gray-200">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&filter=<?= urlencode($filter) ?>" class="px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition"><i class="fas fa-chevron-left text-sm"></i></a>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&filter=<?= urlencode($filter) ?>" class="px-4 py-2 rounded-lg font-medium transition <?= $i === $page ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&filter=<?= urlencode($filter) ?>" class="px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition"><i class="fas fa-chevron-right text-sm"></i></a>
          <?php endif; ?>
        </nav>
      </div>
    <?php endif; ?>

  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    function toggleTheme() {
        var t = localStorage.getItem('bloodlife-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('bloodlife-theme', t);
        if (t === 'dark') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        document.querySelectorAll('.theme-icon-sun').forEach(e => e.style.display = t === 'dark' ? 'none' : 'inline');
        document.querySelectorAll('.theme-icon-moon').forEach(e => e.style.display = t === 'dark' ? 'inline' : 'none');
    }
    function toggleNotifDropdown() {
        document.getElementById('notifDropdown').classList.toggle('hidden');
    }
    function toggleUserDropdown() {
        document.getElementById('userDropdown').classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        const nMenu = document.getElementById('notifMenu');
        const nDrop = document.getElementById('notifDropdown');
        if (nMenu && nDrop && !nMenu.contains(e.target)) nDrop.classList.add('hidden');
        
        const uMenu = document.getElementById('userMenu');
        const uDrop = document.getElementById('userDropdown');
        if (uMenu && uDrop && !uMenu.contains(e.target)) uDrop.classList.add('hidden');
    });
  </script>
</body>
</html>
