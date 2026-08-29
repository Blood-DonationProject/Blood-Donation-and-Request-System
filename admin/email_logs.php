<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

// Handle single log deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delStmt = $conn->prepare("DELETE FROM email_logs WHERE id = ?");
    if ($delStmt) {
        $delStmt->bind_param("i", $id);
        if ($delStmt->execute()) {
            $success = "Email log #{$id} deleted successfully.";
        } else {
            $error = "Failed to delete email log: " . $conn->error;
        }
        $delStmt->close();
    }
    header("Location: email_logs.php");
    exit;
}

// Handle clearing all logs
if (isset($_POST['clear_all_logs'])) {
    if ($conn->query("TRUNCATE TABLE email_logs")) {
        $success = "All email logs have been cleared.";
    } else {
        $error = "Failed to clear email logs: " . $conn->error;
    }
    header("Location: email_logs.php");
    exit;
}

// Fetch distinct email types for filter
$emailTypes = [];
try {
    $typesRes = $conn->query("SELECT DISTINCT email_type FROM email_logs WHERE email_type IS NOT NULL AND email_type != '' ORDER BY email_type ASC");
    if ($typesRes) {
        while ($tRow = $typesRes->fetch_assoc()) {
            $emailTypes[] = $tRow['email_type'];
        }
    }
} catch (Exception $e) {}

// Fetch email logs
$logs = [];
try {
    $logsRes = $conn->query("SELECT * FROM email_logs ORDER BY id DESC LIMIT 500");
    if ($logsRes && $logsRes->num_rows > 0) {
        $logs = $logsRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error loading email logs: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Logs - BloodLife Admin</title>
    <script>
        (function(){ var t = localStorage.getItem('bloodlife-theme'); if (t === 'dark') document.documentElement.classList.add('dark'); })();
    </script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @keyframes fadeInDown { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeInUp   { from { opacity:0; transform:translateY( 20px); } to { opacity:1; transform:translateY(0); } }
        .animate-fade-down { animation: fadeInDown 0.6s ease-out; }
        .animate-fade-up   { animation: fadeInUp   0.6s ease-out; }
    </style>
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50:not(.sidebar):not(nav):not(nav *) { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #ffffff !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        
        html.dark .bg-white:not(.sidebar):not(nav) { background-color: #1f2937 !important; }
        html.dark .text-gray-900:not(.sidebar *):not(nav *), html.dark .text-gray-800:not(.sidebar *):not(nav *) { color: #f3f4f6 !important; }
        html.dark .text-gray-700:not(.sidebar *):not(nav *) { color: #d1d5db !important; }
        html.dark .text-gray-600:not(.sidebar *):not(nav *) { color: #9ca3af !important; }
        html.dark .text-gray-500:not(.sidebar *):not(nav *) { color: #9ca3af !important; }
        html.dark input, html.dark select, html.dark textarea { background-color: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        html.dark label { color: #d1d5db !important; }
        html.dark .bg-gray-50:not(.sidebar *):not(nav *), html.dark .bg-gray-100:not(.sidebar *):not(nav *) { background-color: #374151 !important; }
        html.dark thead.bg-gray-50 { background-color: #111827 !important; }
        html.dark .border-gray-200:not(.sidebar):not(nav), html.dark .border-2.border-gray-200:not(.sidebar):not(nav), html.dark .border:not(.sidebar):not(nav) { border-color: #4b5563 !important; }
        html.dark .border-t:not(.sidebar *) { border-color: #374151 !important; }
        html.dark .bg-red-50:not(.sidebar *) { background-color: rgba(220,38,38,0.15) !important; }
        html.dark tbody tr { border-color: #374151 !important; }
        html.dark tbody tr:hover { background-color: #374151 !important; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="p-8">

            <?php if ($error): ?>
                <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded mb-6"><p class="text-red-700"><?= htmlspecialchars($error) ?></p></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded mb-6"><p class="text-green-700"><?= htmlspecialchars($success) ?></p></div>
            <?php endif; ?>

            <!-- Header & Search and Filter -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        <input id="searchInput" type="text" placeholder="Search by recipient email, subject, type, or error..." class="w-full border-2 border-gray-200 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:border-red-500 transition">
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <select id="statusFilter" class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                        <option value="">All Status</option>
                        <option value="Sent">Sent (Success)</option>
                        <option value="Failed">Failed (Error)</option>
                    </select>

                    <select id="typeFilter" class="border-2 border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-red-500 transition">
                        <option value="">All Email Types</option>
                        <?php foreach ($emailTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (count($logs) > 0): ?>
                    <button onclick="openClearLogsModal()" class="px-4 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition flex items-center gap-2">
                        <i class="fas fa-trash-alt text-sm"></i>
                        <span>Clear Logs</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Data Table Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Email Transmission History</h3>
                        <p class="text-sm text-gray-500">Live records of all outgoing system emails.</p>
                    </div>
                    <span class="text-sm text-gray-500 font-medium">Total: <span id="filteredCount"><?= count($logs) ?></span></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-slate-600">
                                <th class="p-3">ID</th>
                                <th class="p-3">Recipient</th>
                                <th class="p-3">Subject</th>
                                <th class="p-3">Email Type</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 whitespace-nowrap">Sent At</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $isSent = ($log['status'] === 'Sent');
                                    $isFailed = ($log['status'] === 'Failed');
                                    
                                    // Status Badge styling
                                    if ($isSent) {
                                        $statusBadge = 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 border border-green-300';
                                        $statusIcon = 'fa-check-circle';
                                    } elseif ($isFailed) {
                                        $statusBadge = 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 border border-red-300';
                                        $statusIcon = 'fa-times-circle';
                                    } else {
                                        $statusBadge = 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400 border border-yellow-300';
                                        $statusIcon = 'fa-clock';
                                    }

                                    // Email Type Badge styling
                                    $typeClass = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                    if ($log['email_type'] === 'Password Reset') {
                                        $typeClass = 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400';
                                    } elseif ($log['email_type'] === 'Donor Assignment') {
                                        $typeClass = 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-400';
                                    } elseif ($log['email_type'] === 'Blood Request') {
                                        $typeClass = 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400';
                                    }

                                    $recipientDisplay = $log['recipient_email'];
                                    $sentTimestamp = $log['sent_at'] ? date('M d, Y · h:i A', strtotime($log['sent_at'])) : date('M d, Y · h:i A', strtotime($log['created_at']));
                                    ?>
                                    <tr class="log-row border-t border-slate-200 hover:bg-gray-50" 
                                        data-status="<?= htmlspecialchars($log['status']) ?>" 
                                        data-type="<?= htmlspecialchars($log['email_type']) ?>">
                                        <td class="p-3 font-semibold text-gray-500">#<?= $log['id'] ?></td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $isSent ? 'bg-green-100 text-green-700' : ($isFailed ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') ?>">
                                                    <i class="fas <?= $isSent ? 'fa-envelope' : ($isFailed ? 'fa-envelope-open' : 'fa-paper-plane') ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($log['recipient_email']) ?></div>
                                                    <?php if (!empty($log['recipient_name'])): ?>
                                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($log['recipient_name']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 font-medium text-gray-800 max-w-xs truncate" title="<?= htmlspecialchars($log['subject']) ?>">
                                            <?= htmlspecialchars($log['subject']) ?>
                                        </td>
                                        <td class="p-3">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $typeClass ?>">
                                                <?= htmlspecialchars($log['email_type']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge ?>">
                                                <i class="fas <?= $statusIcon ?>"></i>
                                                <?= htmlspecialchars($log['status']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3 text-gray-500 text-xs whitespace-nowrap">
                                            <?= $sentTimestamp ?>
                                        </td>
                                        <td class="p-3 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" onclick="openDeleteModal('email_logs.php?delete=<?= $log['id'] ?>')" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 font-semibold text-xs rounded-lg transition" title="Delete Log">
                                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-gray-500">
                                        <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <p class="font-medium text-base">No email logs recorded yet.</p>
                                        <p class="text-xs text-gray-400 mt-1">System email delivery attempts will automatically appear here.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Log Details Modal -->
<div id="logDetailsModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-fade-up">
        <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div id="modalStatusIconBg" class="w-10 h-10 rounded-xl flex items-center justify-center text-lg">
                    <i id="modalStatusIcon" class="fas fa-envelope"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">Email Log Details</h3>
                    <p id="modalLogId" class="text-xs text-gray-400 font-medium"></p>
                </div>
            </div>
            <button onclick="closeLogModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Recipient</span>
                <p id="modalRecipient" class="font-bold text-gray-900 dark:text-gray-100 text-base"></p>
                <p id="modalRecipientName" class="text-sm text-gray-500"></p>
            </div>

            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Subject</span>
                <p id="modalSubject" class="font-medium text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-700/50 p-3 rounded-xl border border-gray-100 dark:border-gray-600"></p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Email Type</span>
                    <span id="modalEmailType" class="inline-block font-semibold text-sm"></span>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Delivery Status</span>
                    <span id="modalStatusBadge" class="inline-block font-semibold text-sm"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Related Record ID</span>
                    <p id="modalRelatedId" class="font-medium text-gray-700 dark:text-gray-300 text-sm">-</p>
                </div>
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Web Notification ID</span>
                    <p id="modalNotifId" class="font-medium text-gray-700 dark:text-gray-300 text-sm">-</p>
                </div>
            </div>

            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 block mb-1">Attempt Date & Time</span>
                <p id="modalSentAt" class="font-medium text-gray-700 dark:text-gray-300 text-sm"></p>
            </div>

            <!-- Error Message Section -->
            <div id="modalErrorSection" class="hidden">
                <span class="text-xs font-semibold uppercase tracking-wider text-red-500 block mb-1">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Error Details
                </span>
                <div id="modalErrorMessage" class="bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 p-4 rounded-xl text-xs font-mono border border-red-200 dark:border-red-800 overflow-x-auto whitespace-pre-wrap leading-relaxed"></div>
            </div>

            <!-- Success Note Section -->
            <div id="modalSuccessSection" class="hidden">
                <div class="bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 p-4 rounded-xl text-xs border border-green-200 dark:border-green-800 flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-600 text-base"></i>
                    <span>Email was accepted by the SMTP server and queued for delivery.</span>
                </div>
            </div>
        </div>
        <div class="p-6 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
            <button onclick="closeLogModal()" class="px-5 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition">Close</button>
        </div>
    </div>
</div>

<!-- Delete Single Log Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
        <div class="p-8 text-center space-y-6">
            <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div>
                <h2 class="font-bold text-2xl text-gray-900 mb-2">Delete Email Log</h2>
                <p class="text-gray-500">Are you sure you want to delete this log record? This action cannot be undone.</p>
            </div>
        </div>
        <div class="px-8 pb-8 flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
            <a href="#" id="confirmDeleteBtn" onclick="this.classList.add('opacity-50', 'pointer-events-none');" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition text-center shadow-md flex items-center justify-center">Delete</a>
        </div>
    </div>
</div>

<!-- Clear All Logs Modal -->
<div id="clearLogsModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
        <div class="p-8 text-center space-y-6">
            <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
                <i class="fas fa-radiation-alt"></i>
            </div>
            <div>
                <h2 class="font-bold text-2xl text-gray-900 mb-2">Clear All Email Logs</h2>
                <p class="text-gray-500">Are you sure you want to delete all email log records? This cannot be undone.</p>
            </div>
        </div>
        <form method="POST" class="px-8 pb-8 flex gap-3">
            <button type="button" onclick="closeClearLogsModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
            <button type="submit" name="clear_all_logs" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition shadow-md">Clear All</button>
        </form>
    </div>
</div>

<script>
// Search and Filter Functionality
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const typeFilter = document.getElementById('typeFilter');
const rows = document.querySelectorAll('.log-row');
const filteredCount = document.getElementById('filteredCount');

function applyFilters() {
    const q = searchInput.value.toLowerCase();
    const status = statusFilter.value;
    const type = typeFilter.value;
    let count = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowStatus = row.getAttribute('data-status');
        const rowType = row.getAttribute('data-type');

        const matchesSearch = text.includes(q);
        const matchesStatus = !status || rowStatus === status;
        const matchesType = !type || rowType === type;

        if (matchesSearch && matchesStatus && matchesType) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });

    if (filteredCount) filteredCount.textContent = count;
}

searchInput?.addEventListener('keyup', applyFilters);
statusFilter?.addEventListener('change', applyFilters);
typeFilter?.addEventListener('change', applyFilters);

// View Log Modal
function viewLogDetails(log) {
    document.getElementById('modalLogId').textContent = 'Log #' + log.id;
    document.getElementById('modalRecipient').textContent = log.recipient_email || '-';
    document.getElementById('modalRecipientName').textContent = log.recipient_name ? ('(' + log.recipient_name + ')') : '';
    document.getElementById('modalSubject').textContent = log.subject || '(No Subject)';
    document.getElementById('modalRelatedId').textContent = (log.related_id !== null && log.related_id !== undefined && log.related_id !== '') ? ('#' + log.related_id) : '-';
    document.getElementById('modalNotifId').textContent = (log.notification_id !== null && log.notification_id !== undefined && log.notification_id !== '') ? ('#' + log.notification_id) : '-';
    
    // Email Type Badge
    const modalType = document.getElementById('modalEmailType');
    modalType.textContent = log.email_type || 'General';
    modalType.className = 'inline-block px-3 py-1 rounded-full text-xs font-semibold ' + 
        (log.email_type === 'Password Reset' ? 'bg-blue-100 text-blue-700' : 
        (log.email_type === 'Donor Assignment' ? 'bg-pink-100 text-pink-700' : 'bg-gray-100 text-gray-700'));

    // Status Badge & Icon
    const modalStatusBadge = document.getElementById('modalStatusBadge');
    const modalIconBg = document.getElementById('modalStatusIconBg');
    const modalIcon = document.getElementById('modalStatusIcon');
    const errorSection = document.getElementById('modalErrorSection');
    const successSection = document.getElementById('modalSuccessSection');

    if (log.status === 'Sent') {
        modalStatusBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Sent';
        modalStatusBadge.className = 'inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-300';
        modalIconBg.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-lg bg-green-100 text-green-600';
        modalIcon.className = 'fas fa-check-circle';
        errorSection.classList.add('hidden');
        successSection.classList.remove('hidden');
    } else if (log.status === 'Failed') {
        modalStatusBadge.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Failed';
        modalStatusBadge.className = 'inline-block px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-300';
        modalIconBg.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-lg bg-red-100 text-red-600';
        modalIcon.className = 'fas fa-times-circle';
        errorSection.classList.remove('hidden');
        successSection.classList.add('hidden');
        document.getElementById('modalErrorMessage').textContent = log.error_message || 'Unknown SMTP error occurred.';
    } else {
        modalStatusBadge.innerHTML = '<i class="fas fa-clock mr-1"></i> ' + (log.status || 'Pending');
        modalStatusBadge.className = 'inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-300';
        modalIconBg.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-lg bg-yellow-100 text-yellow-600';
        modalIcon.className = 'fas fa-clock';
        errorSection.classList.add('hidden');
        successSection.classList.add('hidden');
    }

    const dateStr = log.sent_at || log.created_at;
    if (dateStr) {
        const d = new Date(dateStr);
        document.getElementById('modalSentAt').textContent = d.toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
    } else {
        document.getElementById('modalSentAt').textContent = '-';
    }

    document.getElementById('logDetailsModal').classList.remove('hidden');
}

function closeLogModal() {
    document.getElementById('logDetailsModal').classList.add('hidden');
}

// Single Delete Modal
function openDeleteModal(url) {
    document.getElementById('confirmDeleteBtn').href = url;
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
    document.getElementById('deleteConfirmModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.remove('flex');
    document.getElementById('deleteConfirmModal').classList.add('hidden');
}

// Clear All Logs Modal
function openClearLogsModal() {
    document.getElementById('clearLogsModal').classList.remove('hidden');
    document.getElementById('clearLogsModal').classList.add('flex');
}

function closeClearLogsModal() {
    document.getElementById('clearLogsModal').classList.remove('flex');
    document.getElementById('clearLogsModal').classList.add('hidden');
}

// Close modals on backdrop click
document.getElementById('logDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeLogModal();
});
document.getElementById('deleteConfirmModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
document.getElementById('clearLogsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeClearLogsModal();
});
</script>

</body>
</html>
