<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM email_logs");
$countStmt->execute();
$totalLogs = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($totalLogs / $limit);

// Fetch email logs
$stmt = $conn->prepare("SELECT id, recipient_email, subject, status, sent_at, delivered_at, opened_at, error_message FROM email_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$emailLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$currentPage = 'email_logs.php';
$pageData = ['title' => 'Email Logs', 'description' => 'View email delivery status and tracking logs.'];
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
    <style id="dark-mode-styles">
        html:not(.dark) body { background-color: #ffffff !important; background-image: none !important; }
        html:not(.dark) .bg-gray-50 { background-color: #ffffff !important; }
        html:not(.dark) .bg-gray-100 { background-color: #f9fafb !important; }
        html.dark body { background-color: #111827 !important; background-image: none !important; color: #e5e7eb; }
        html.dark .w-64.bg-white { background-color: #1f2937 !important; }
        html.dark nav.bg-slate-200, html.dark nav.bg-white { background-color: #1f2937 !important; border-color: #374151 !important; }
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
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 min-h-screen">
            <!-- Top Navigation Bar -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <!-- Page Content -->
            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Email Status Logs</h2>
                    <p class="text-gray-500 mt-1">Review email delivery and open tracking logs. <br><span class="text-xs text-gray-400"><i class="fas fa-info-circle mr-1"></i>Note: Email-open tracking may not be 100% accurate due to email privacy settings.</span></p>
                </div>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600 text-sm border-b border-gray-100">
                                    <th class="py-3 px-4 font-semibold">Recipient Email</th>
                                    <th class="py-3 px-4 font-semibold">Subject</th>
                                    <th class="py-3 px-4 font-semibold">Status</th>
                                    <th class="py-3 px-4 font-semibold">Sent At</th>
                                    <th class="py-3 px-4 font-semibold">Delivered At</th>
                                    <th class="py-3 px-4 font-semibold">Opened At</th>
                                    <th class="py-3 px-4 font-semibold">Error Message</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if (count($emailLogs) > 0): ?>
                                    <?php foreach ($emailLogs as $log): 
                                        $statusClass = 'bg-gray-100 text-gray-700';
                                        if ($log['status'] === 'Sent') $statusClass = 'bg-blue-100 text-blue-700';
                                        if ($log['status'] === 'Delivered') $statusClass = 'bg-green-100 text-green-700';
                                        if ($log['status'] === 'Opened') $statusClass = 'bg-purple-100 text-purple-700';
                                        if ($log['status'] === 'Failed' || $log['status'] === 'Bounced') $statusClass = 'bg-red-100 text-red-700';
                                    ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-3 px-4 text-gray-800"><?= htmlspecialchars($log['recipient_email']) ?></td>
                                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($log['subject']) ?></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>" title="<?= htmlspecialchars($log['error_message'] ?? '') ?>">
                                                <?= htmlspecialchars($log['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-gray-500 whitespace-nowrap"><?= $log['sent_at'] ? date('M j, Y g:i A', strtotime($log['sent_at'])) : '-' ?></td>
                                        <td class="py-3 px-4 text-gray-500 whitespace-nowrap"><?= $log['delivered_at'] ? date('M j, Y g:i A', strtotime($log['delivered_at'])) : '-' ?></td>
                                        <td class="py-3 px-4 text-gray-500 whitespace-nowrap"><?= $log['opened_at'] ? date('M j, Y g:i A', strtotime($log['opened_at'])) : '-' ?></td>
                                        <td class="py-3 px-4 text-red-500 text-xs"><?= $log['error_message'] ? htmlspecialchars($log['error_message']) : '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-500">No email logs found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex items-center gap-1 bg-white p-1 rounded-xl shadow-sm border border-gray-200">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition"><i class="fas fa-chevron-left text-sm"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="px-4 py-2 rounded-lg font-medium transition <?= $i === $page ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50' ?>">
                        <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition"><i class="fas fa-chevron-right text-sm"></i></a>
                    <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script>
        function toggleAdminDropdown() {
            var dropdown = document.getElementById('adminDropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const aMenu = document.getElementById('adminMenu');
            const aDrop = document.getElementById('adminDropdown');
            if (aMenu && aDrop && !aMenu.contains(e.target)) aDrop.classList.add('hidden');
        });
    </script>
</body>
</html>
