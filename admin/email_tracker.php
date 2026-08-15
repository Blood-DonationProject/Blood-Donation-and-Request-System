<?php
require_once __DIR__ . '/../config/db.php';

if (isset($_GET['log_id'])) {
    $logId = (int)$_GET['log_id'];
    
    // Only update if not already opened
    $check = $conn->prepare("SELECT status FROM email_logs WHERE id = ?");
    $check->bind_param("i", $logId);
    $check->execute();
    $result = $check->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] !== 'Opened') {
            $now = date('Y-m-d H:i:s');
            $update = $conn->prepare("UPDATE email_logs SET status = 'Opened', opened_at = ? WHERE id = ?");
            $update->bind_param("si", $now, $logId);
            $update->execute();
            $update->close();
        }
    }
    $check->close();
}

// Output a 1x1 transparent GIF
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
exit;
