<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];

if (isset($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];
    $stmt_del = $conn->prepare("UPDATE user_notifications SET is_deleted = 1 WHERE notification_id = ? AND user_id = ?");
    if ($stmt_del) {
        $stmt_del->bind_param('ii', $notif_id, $userId);
        $stmt_del->execute();
        $stmt_del->close();
    }
    $cleanQuery = $_GET;
    unset($cleanQuery['delete']);
    $queryString = http_build_query($cleanQuery);
    header('Location: notifications.php' . ($queryString ? '?' . $queryString : ''));
    exit;
}

if (isset($_GET['clear_all'])) {
    $stmt_del = $conn->prepare("UPDATE user_notifications SET is_deleted = 1 WHERE user_id = ?");
    if ($stmt_del) {
        $stmt_del->bind_param('i', $userId);
        $stmt_del->execute();
        $stmt_del->close();
    }
    header('Location: notifications.php');
    exit;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filterQuery = "";
if ($filter === 'unread') {
    $filterQuery = " AND nr.is_read = 0";
} elseif ($filter === 'read') {
    $filterQuery = " AND nr.is_read = 1";
}

// Total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications n JOIN user_notifications nr ON n.id = nr.notification_id WHERE nr.user_id = ? AND nr.is_deleted = 0" . $filterQuery);
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$totalNotifs = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($totalNotifs / $limit);

// Fetch notifications
$stmt = $conn->prepare("SELECT n.id, n.request_id, n.assignment_id, n.type, n.title, n.message, nr.is_read, n.created_at FROM notifications n JOIN user_notifications nr ON n.id = nr.notification_id WHERE nr.user_id = ? AND nr.is_deleted = 0" . $filterQuery . " ORDER BY n.created_at DESC LIMIT ? OFFSET ?");
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
    html.dark nav.bg-slate-100 { background-color: #1f2937 !important; }
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
          <a href="?clear_all=1" onclick="return confirm('Are you sure you want to clear all notifications?');" class="text-sm font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg transition shadow-sm border border-red-100">
              <i class="fas fa-trash-alt mr-1"></i> Clear all
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
            // Fetch extra contact info for successful assignments or requests
            $contactHtml = '';
            $nTitle = $n['title'] ?? '';
            
            if (!empty($n['assignment_id'])) {
                // Fetch assignment specific info
                $detailQuery = "SELECT br.status AS req_status, da.status AS assign_status, br.hospital, br.required_date, bg.blood_gp_name, ru.username AS requester_name, ru.email AS requester_email, rd.phone AS requester_phone, du.username AS donor_name, du.email AS donor_email, d.phone AS donor_phone FROM donor_assignments da JOIN blood_request br ON da.request_id = br.id LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id JOIN donor d ON da.donor_id = d.id JOIN users du ON d.user_id = du.id JOIN users ru ON br.users_id = ru.id LEFT JOIN donor rd ON ru.id = rd.user_id WHERE da.id = ?";
                $stmtDetail = $conn->prepare($detailQuery);
                if ($stmtDetail) {
                    $stmtDetail->bind_param("i", $n['assignment_id']);
                    $stmtDetail->execute();
                    $detailRes = $stmtDetail->get_result();
                    if ($detail = $detailRes->fetch_assoc()) {
                        $contactHtml = '<div class="mt-4 p-4 bg-gray-50 rounded-xl text-sm space-y-2 text-gray-700 border border-gray-200">';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-tint text-red-500 w-5"></i> Blood Group:</span> ' . htmlspecialchars($detail['blood_gp_name'] ?? '') . '</p>';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-hospital text-blue-500 w-5"></i> Hospital:</span> ' . htmlspecialchars($detail['hospital'] ?? '') . '</p>';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-calendar-alt text-green-500 w-5"></i> Required Date:</span> ' . htmlspecialchars($detail['required_date'] ?? '') . '</p>';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-info-circle text-purple-500 w-5"></i> Assignment Status:</span> ' . htmlspecialchars($detail['assign_status'] ?? '') . '</p>';
                        $contactHtml .= '<div class="h-px bg-gray-200 my-3"></div>';
                        
                        if (isset($_SESSION['role']) && strtolower($_SESSION['role']) == 'donor' || $nTitle == 'New Assignment' || strpos($nTitle, 'Assignment') !== false) {
                            $contactHtml .= '<p class="font-bold text-gray-900 mb-1">Requester Information</p>';
                            $contactHtml .= '<p><span class="font-semibold text-gray-600">Name:</span> ' . htmlspecialchars($detail['requester_name'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-semibold text-gray-600">Phone:</span> ' . htmlspecialchars($detail['requester_phone'] ?? 'N/A') . '</p>';
                            $contactHtml .= '<p><span class="font-semibold text-gray-600">Email:</span> ' . htmlspecialchars($detail['requester_email'] ?? '') . '</p>';
                        } else {
                            $contactHtml .= '<p class="font-bold text-gray-900 mb-1">Donor Information</p>';
                            $contactHtml .= '<p><span class="font-semibold text-gray-600">Name:</span> ' . htmlspecialchars($detail['donor_name'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-semibold text-gray-600">Phone:</span> ' . htmlspecialchars($detail['donor_phone'] ?? '') . '</p>';
                            $contactHtml .= '<p><span class="font-semibold text-gray-600">Email:</span> ' . htmlspecialchars($detail['donor_email'] ?? '') . '</p>';
                        }
                        $contactHtml .= '</div>';
                    }
                    $stmtDetail->close();
                }
            } elseif (!empty($n['request_id'])) {
                // Fetch request specific info
                $reqQuery = "SELECT br.status AS req_status, br.hospital, br.required_date, bg.blood_gp_name FROM blood_request br LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.id = ?";
                $stmtReq = $conn->prepare($reqQuery);
                if ($stmtReq) {
                    $stmtReq->bind_param("i", $n['request_id']);
                    $stmtReq->execute();
                    $reqRes = $stmtReq->get_result();
                    if ($reqDetail = $reqRes->fetch_assoc()) {
                        $contactHtml = '<div class="mt-4 p-4 bg-gray-50 rounded-xl text-sm space-y-2 text-gray-700 border border-gray-200">';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-tint text-red-500 w-5"></i> Blood Group:</span> ' . htmlspecialchars($reqDetail['blood_gp_name'] ?? '') . '</p>';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-hospital text-blue-500 w-5"></i> Hospital:</span> ' . htmlspecialchars($reqDetail['hospital'] ?? '') . '</p>';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-calendar-alt text-green-500 w-5"></i> Required Date:</span> ' . htmlspecialchars($reqDetail['required_date'] ?? '') . '</p>';
                        $contactHtml .= '<p><span class="font-bold text-gray-900"><i class="fas fa-info-circle text-purple-500 w-5"></i> Request Status:</span> ' . htmlspecialchars($reqDetail['req_status'] ?? '') . '</p>';
                        $contactHtml .= '</div>';
                    }
                    $stmtReq->close();
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
            <div id="notif-row-<?= $n['id'] ?>" class="p-5 transition <?= $bgClass ?>">
              <div class="flex items-start gap-4">
                <div class="mt-1">
                  <?php if (!$is_read): ?>
                    <div id="notif-dot-<?= $n['id'] ?>" class="w-3 h-3 rounded-full bg-red-600 shadow-sm shadow-red-200"></div>
                  <?php else: ?>
                    <div id="notif-dot-<?= $n['id'] ?>" class="w-3 h-3 rounded-full bg-gray-300"></div>
                  <?php endif; ?>
                </div>
                
                <div class="flex-1">
                  <div class="flex justify-between items-start gap-2">
                    <h3 class="text-base font-bold text-gray-900"><?= htmlspecialchars($n['title']) ?></h3>
                    <span class="text-xs font-semibold text-gray-400 whitespace-nowrap"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></span>
                  </div>
                  <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($n['message']) ?></p>
                  
                  <div class="mt-3 flex items-center justify-between">
                    <?php if (!$is_read): ?>
                      <button id="notif-btn-<?= $n['id'] ?>" type="button" onclick="openNotifModal(<?= $n['id'] ?>, false)" class="text-sm font-semibold text-red-600 hover:text-red-800 transition">
                        View Details &rarr;
                      </button>
                    <?php else: ?>
                      <button id="notif-btn-<?= $n['id'] ?>" type="button" onclick="openNotifModal(<?= $n['id'] ?>, true)" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition">
                        View Details &rarr;
                      </button>
                    <?php endif; ?>
                      <a href="?delete=<?= $n['id'] ?>" onclick="return confirm('Are you sure you want to delete this notification?');" class="text-xs text-gray-400 hover:text-red-600 transition" title="Delete Notification">
                        <i class="fas fa-trash-alt"></i> Delete
                      </a>
                  </div>
                </div>
              </div>
            </div>

            <!-- Hidden Template for Modal Content -->
            <template id="notif-content-<?= $n['id'] ?>">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($n['title']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></p>
                </div>
                <div class="text-gray-700 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                    <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                </div>
                <?= $contactHtml ?>
            </template>

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

  <!-- Notification Modal -->
  <div id="notifModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity opacity-0" id="notifModalBackdrop" onclick="closeNotifModal()"></div>
      <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg relative z-10 transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh]" id="notifModalPanel">
          <!-- Close button -->
          <button onclick="closeNotifModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 hover:bg-gray-100 w-8 h-8 flex items-center justify-center rounded-full transition z-20">
              <i class="fas fa-times"></i>
          </button>
          
          <!-- Modal Body (Scrollable) -->
          <div class="p-6 md:p-8 overflow-y-auto" id="notifModalBody">
              <!-- Populated via JS -->
          </div>
          
          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-3xl flex justify-end shrink-0">
              <button onclick="closeNotifModal()" class="px-6 py-2 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-50 shadow-sm transition">
                  Close
              </button>
          </div>
      </div>
  </div>

  <script>
    function openNotifModal(id, isRead) {
        const modal = document.getElementById('notifModal');
        const backdrop = document.getElementById('notifModalBackdrop');
        const panel = document.getElementById('notifModalPanel');
        const body = document.getElementById('notifModalBody');
        const template = document.getElementById('notif-content-' + id);
        
        if (template) {
            body.innerHTML = template.innerHTML;
        }
        
        // Show modal
        modal.classList.remove('hidden');
        // Animate in
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);

        // Mark as read via AJAX if not read
        if (!isRead) {
            fetch('?mark_read=' + id).then(() => {
                // Update UI dot
                const dot = document.getElementById('notif-dot-' + id);
                if (dot) dot.className = 'w-3 h-3 rounded-full bg-gray-300';
                
                // Update row background
                const row = document.getElementById('notif-row-' + id);
                if (row) row.className = 'p-5 transition bg-white hover:bg-gray-50';
                
                // Update view details button state
                const btn = document.getElementById('notif-btn-' + id);
                if (btn) {
                    btn.className = 'text-sm font-medium text-gray-500 hover:text-gray-700 transition';
                    btn.setAttribute('onclick', `openNotifModal(${id}, true)`);
                }
            }).catch(err => console.error("Error marking read:", err));
        }
    }

    function closeNotifModal() {
        const modal = document.getElementById('notifModal');
        const backdrop = document.getElementById('notifModalBackdrop');
        const panel = document.getElementById('notifModalPanel');
        
        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function toggleTheme() {
        var t = localStorage.getItem('bloodlife-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('bloodlife-theme', t);
        if (t === 'dark') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        document.querySelectorAll('.theme-icon-sun').forEach(e => e.style.display = t === 'dark' ? 'none' : 'inline');
        document.querySelectorAll('.theme-icon-moon').forEach(e => e.style.display = t === 'dark' ? 'inline' : 'none');
    }
    function toggleNotifDropdown() {
        var nd = document.getElementById('notifDropdown');
        if (nd) nd.classList.toggle('hidden');
        var ud = document.getElementById('userDropdown');
        if (ud && !ud.classList.contains('hidden')) ud.classList.add('hidden');
    }
    function toggleUserDropdown() {
        var ud = document.getElementById('userDropdown');
        if (ud) ud.classList.toggle('hidden');
        var nd = document.getElementById('notifDropdown');
        if (nd && !nd.classList.contains('hidden')) nd.classList.add('hidden');
    }
    document.addEventListener('click', function(e) {
        var notifMenu = document.getElementById('notifMenu');
        var notifDropdown = document.getElementById('notifDropdown');
        if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
            notifDropdown.classList.add('hidden');
        }

        var userMenu = document.getElementById('userMenu');
        var userDropdown = document.getElementById('userDropdown');
        if (userMenu && userDropdown && !userMenu.contains(e.target)) {
            userDropdown.classList.add('hidden');
        }
    });
  </script>
</body>
</html>
