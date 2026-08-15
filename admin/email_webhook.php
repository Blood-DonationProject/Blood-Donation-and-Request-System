<?php
// admin/email_webhook.php
// Simulated webhook endpoint to handle Delivered/Bounced statuses.
// A real email provider (SendGrid, Mailgun) would POST data here.
require_once __DIR__ . '/../config/db.php';

// Assuming a simple JSON payload: {"log_id": 123, "event": "delivered"}
// or {"log_id": 123, "event": "bounced", "error": "Recipient mailbox full"}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (isset($data['log_id']) && isset($data['event'])) {
    $logId = (int)$data['log_id'];
    $event = strtolower($data['event']);
    
    $now = date('Y-m-d H:i:s');
    
    if ($event === 'delivered') {
        $stmt = $conn->prepare("UPDATE email_logs SET status = 'Delivered', delivered_at = ? WHERE id = ?");
        $stmt->bind_param("si", $now, $logId);
        $stmt->execute();
        $stmt->close();
    } elseif ($event === 'bounced') {
        $errorMsg = $data['error'] ?? 'Unknown bounce error';
        $stmt = $conn->prepare("UPDATE email_logs SET status = 'Bounced', error_message = ? WHERE id = ?");
        $stmt->bind_param("si", $errorMsg, $logId);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
}
