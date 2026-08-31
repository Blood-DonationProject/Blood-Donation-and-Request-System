<?php
/**
 * Master Mailer & Email Notification Service with Gmail SMTP Support
 * 
 * Provides unified, decoupled email sending for all key system events:
 * - User & Donor Registrations (Welcome emails)
 * - Blood Requests (Confirmation & Admin Urgent Alerts)
 * - Donor Assignments (Detailed Call to Action & Acceptance/Rejection notices)
 * - Blood Received & Completions (Thank You & Summary receipts)
 * - Account Status Changes & Secure Password Resets
 * 
 * Guarantees fail-safe execution: email errors never rollback database transactions
 * or delete website notifications. Every attempt is logged in `email_logs`.
 */

// Load Composer autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Retrieve Mail Configuration
 * 
 * @return array
 */
function get_mail_config() {
    static $config = null;
    if ($config === null) {
        $configPath = dirname(__DIR__) . '/config/mail.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
        } else {
            $config = [
                'smtp_host'     => 'smtp.gmail.com',
                'smtp_port'     => 587,
                'smtp_secure'   => 'tls',
                'smtp_auth'     => true,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email'    => '',
                'from_name'     => 'BloodLife Donation System',
                'debug'         => false,
            ];
        }
    }
    return $config;
}

/**
 * Instantiate and configure a PHPMailer object
 * 
 * @param bool|null $debugOverride
 * @return PHPMailer
 * @throws Exception
 */
function get_mailer($debugOverride = null) {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception('PHPMailer library is not installed. Please run "composer require phpmailer/phpmailer".');
    }

    $config = get_mail_config();
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = !empty($config['smtp_host']) ? trim($config['smtp_host']) : 'smtp.gmail.com';
    $mail->SMTPAuth   = $config['smtp_auth'] !== false;
    $mail->Username   = trim($config['smtp_username'] ?? '');
    // Ensure 16-character Google App Password has no spaces or extra padding
    $mail->Password   = str_replace(' ', '', trim($config['smtp_password'] ?? ''));
    $mail->Timeout    = 15; // 15-second network timeout
    $mail->SMTPKeepAlive = false;
    
    // Encryption & Port (Gmail: 587 -> STARTTLS, 465 -> SMTPS/SSL)
    $port = (int)($config['smtp_port'] ?? 587);
    $secure = strtolower(trim($config['smtp_secure'] ?? 'tls'));
    if ($port === 465 || $secure === 'ssl' || $secure === 'smtps') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
    }

    // Windows / WAMP OpenSSL compatibility options
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];

    // Debugging configuration
    $debug = $debugOverride !== null ? $debugOverride : ($config['debug'] ?? false);
    if ($debug) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    } else {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
    }

    // Encoding & Options
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    // Sender
    $fromEmail = !empty($config['from_email']) ? trim($config['from_email']) : trim($config['smtp_username'] ?? '');
    $fromName  = !empty($config['from_name']) ? trim($config['from_name']) : 'BloodLife Donation System';
    
    if (!empty($fromEmail)) {
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
    }

    return $mail;
}

/**
 * Send an email with safe error handling and database logging
 * 
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML content
 * @param string $altBody Plain text content fallback
 * @param array $options Optional metadata (user_id, notification_id, email_type)
 * @return array ['success' => bool, 'error' => string|null, 'message_id' => string|null]
 */
function send_email($toEmail, $toName, $subject, $htmlBody, $altBody = '', array $options = []) {
    $toEmail = trim($toEmail);
    $relatedId = $options['related_id'] ?? $options['request_id'] ?? $options['assignment_id'] ?? null;

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid recipient email address.';
        log_email_record([
            'notification_id' => $options['notification_id'] ?? null,
            'user_id'         => $options['user_id'] ?? null,
            'related_id'      => $relatedId,
            'recipient_email' => $toEmail ?: 'invalid@email.com',
            'recipient_name'  => $toName,
            'subject'         => $subject,
            'email_type'      => $options['email_type'] ?? 'General',
            'status'          => 'Failed',
            'error_message'   => $err,
            'sent_at'         => date('Y-m-d H:i:s'),
        ]);
        return ['success' => false, 'error' => $err, 'message_id' => null];
    }

    $config = get_mail_config();
    if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
        $msg = 'Gmail SMTP credentials are not configured in config/mail.local.php or .env.';
        error_log("[Mailer] " . $msg);
        log_email_record([
            'notification_id' => $options['notification_id'] ?? null,
            'user_id'         => $options['user_id'] ?? null,
            'related_id'      => $relatedId,
            'recipient_email' => $toEmail,
            'recipient_name'  => $toName,
            'subject'         => $subject,
            'email_type'      => $options['email_type'] ?? 'General',
            'status'          => 'Failed',
            'error_message'   => $msg,
            'sent_at'         => date('Y-m-d H:i:s'),
        ]);
        return ['success' => false, 'error' => $msg, 'message_id' => null];
    }

    try {
        $mail = get_mailer();
        $mail->addAddress($toEmail, $toName ?: '');
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);

        $sent = $mail->send();
        $messageId = $mail->getLastMessageID();

        // Log successful send to email_logs
        log_email_record([
            'notification_id' => $options['notification_id'] ?? null,
            'user_id'         => $options['user_id'] ?? null,
            'related_id'      => $relatedId,
            'recipient_email' => $toEmail,
            'recipient_name'  => $toName,
            'subject'         => $subject,
            'email_type'      => $options['email_type'] ?? 'General',
            'status'          => 'Sent',
            'error_message'   => null,
            'sent_at'         => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'error' => null, 'message_id' => $messageId];
    } catch (Exception $e) {
        $rawError = $mail->ErrorInfo ?? $e->getMessage();
        $cleanError = sanitize_smtp_error($rawError);
        error_log("[Mailer Error] Failed sending to {$toEmail}: " . $cleanError);

        // Log failed send to email_logs
        log_email_record([
            'notification_id' => $options['notification_id'] ?? null,
            'user_id'         => $options['user_id'] ?? null,
            'related_id'      => $relatedId,
            'recipient_email' => $toEmail,
            'recipient_name'  => $toName,
            'subject'         => $subject,
            'email_type'      => $options['email_type'] ?? 'General',
            'status'          => 'Failed',
            'error_message'   => $cleanError,
            'sent_at'         => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => false,
            'error'   => $cleanError,
            'message_id' => null
        ];
    }
}

/**
 * Redact sensitive password fragments from error messages
 * 
 * @param string $error
 * @return string
 */
function sanitize_smtp_error($error) {
    $config = get_mail_config();
    if (!empty($config['smtp_password'])) {
        $error = str_replace($config['smtp_password'], '********', $error);
    }
    return $error;
}

/**
 * Record email in email_logs table if DB connection exists
 * 
 * @param array $data
 */
function log_email_record(array $data) {
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $dbPath = dirname(__DIR__) . '/config/db.php';
        if (file_exists($dbPath)) {
            include_once $dbPath;
        }
    }

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
        static $hasRelatedCol = null;
        if ($hasRelatedCol === null) {
            $chk = $conn->query("SHOW COLUMNS FROM email_logs LIKE 'related_id'");
            $hasRelatedCol = ($chk && $chk->num_rows > 0);
        }

        $notificationId = $data['notification_id'] ?? null;
        $userId         = $data['user_id'] ?? null;
        $relatedId      = $data['related_id'] ?? $data['request_id'] ?? $data['assignment_id'] ?? null;
        $recipientEmail = $data['recipient_email'] ?? '';
        $recipientName  = $data['recipient_name'] ?? null;
        $subject        = $data['subject'] ?? '';
        $emailType      = $data['email_type'] ?? 'General';
        $status         = $data['status'] ?? 'Pending';
        $errorMsg       = $data['error_message'] ?? null;
        $sentAt         = $data['sent_at'] ?? date('Y-m-d H:i:s');

        if ($hasRelatedCol) {
            $stmt = $conn->prepare("INSERT INTO email_logs 
                (notification_id, user_id, related_id, recipient_email, recipient_name, subject, email_type, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param(
                    "iiisssssss",
                    $notificationId,
                    $userId,
                    $relatedId,
                    $recipientEmail,
                    $recipientName,
                    $subject,
                    $emailType,
                    $status,
                    $errorMsg,
                    $sentAt
                );
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO email_logs 
                (notification_id, user_id, recipient_email, recipient_name, subject, email_type, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param(
                    "iisssssss",
                    $notificationId,
                    $userId,
                    $recipientEmail,
                    $recipientName,
                    $subject,
                    $emailType,
                    $status,
                    $errorMsg,
                    $sentAt
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

/**
 * Helper to construct base URL for links inside emails
 */
function get_system_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    // Normalize if running inside user or admin subfolder
    $scriptDir = preg_replace('#/(user|admin)$#', '', $scriptDir);
    return $protocol . $host . $scriptDir;
}

/**
 * Unified HTML Email Template Builder
 * 
 * @param string $headerTitle
 * @param string $greeting
 * @param string $contentHtml
 * @param array|null $button ['text' => string, 'url' => string]
 * @param string|null $badge ['text' => string, 'color' => string]
 * @param string|null $footerNote
 * @return string
 */
function build_base_email_template($headerTitle, $greeting, $contentHtml, $button = null, $badge = null, $footerNote = null) {
    $safeGreeting = htmlspecialchars($greeting);
    $safeHeader = htmlspecialchars($headerTitle);
    
    $badgeHtml = '';
    if (!empty($badge['text'])) {
        $badgeBg = $badge['bg'] ?? '#fef2f2';
        $badgeColor = $badge['color'] ?? '#dc2626';
        $badgeBorder = $badge['border'] ?? '#fecaca';
        $safeBadge = htmlspecialchars($badge['text']);
        $badgeHtml = "<div style=\"display: inline-block; background-color: {$badgeBg}; color: {$badgeColor}; border: 1px solid {$badgeBorder}; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 10px; border-radius: 9999px; margin-bottom: 12px;\">{$safeBadge}</div>";
    }

    $buttonHtml = '';
    if (!empty($button['url']) && !empty($button['text'])) {
        $safeBtnText = htmlspecialchars($button['text']);
        $safeBtnUrl = htmlspecialchars($button['url'], ENT_QUOTES, 'UTF-8');
        $btnBg = $button['bg'] ?? 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)';
        $buttonHtml = <<<HTML
        <div style="text-align: center; margin: 28px 0 16px 0;">
            <a href="{$safeBtnUrl}" target="_blank" style="display: inline-block; background: {$btnBg}; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 15px; padding: 12px 28px; border-radius: 8px; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);">
                {$safeBtnText} &rarr;
            </a>
        </div>
HTML;
    }

    $footerExtra = $footerNote ? "<p style=\"font-size: 12px; color: #64748b; margin-top: 20px; line-height: 1.5;\">{$footerNote}</p>" : '';
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$safeHeader}</title>
<style>
    body { margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .email-container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
    .email-header { background: linear-gradient(135deg, #e11d48 0%, #be123c 100%); padding: 28px 24px; text-align: center; color: #ffffff; }
    .email-header h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.3px; }
    .email-header p { margin: 6px 0 0 0; opacity: 0.9; font-size: 13px; font-weight: 500; }
    .email-body { padding: 32px 28px; line-height: 1.6; }
    .greeting { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 12px; }
    .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; background-color: #f8fafc; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
    .info-table td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #e2e8f0; }
    .info-table tr:last-child td { border-bottom: none; }
    .info-label { color: #64748b; font-weight: 600; width: 35%; }
    .info-val { color: #0f172a; font-weight: 700; }
    .email-footer { background: #f8fafc; padding: 20px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; line-height: 1.6; }
</style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>🩸 BloodLife</h1>
        <p>Blood Donation & Request Communication System</p>
    </div>
    <div class="email-body">
        {$badgeHtml}
        <div class="greeting">{$safeGreeting}</div>
        {$contentHtml}
        {$buttonHtml}
        {$footerExtra}
    </div>
    <div class="email-footer">
        &copy; {$year} BloodLife. All rights reserved.<br>
        This is an automated message. Please do not reply directly to this email.
    </div>
</div>
</body>
</html>
HTML;
}

// ============================================================================
// DEDICATED EMAIL NOTIFICATION DISPATCHERS
// ============================================================================

/**
 * 1. Send Welcome Email upon User Registration
 */
function send_welcome_user_email($userId, $username, $email) {
    $baseUrl = get_system_base_url();
    $loginUrl = $baseUrl . '/user/login.php';
    
    $subject = "Welcome to BloodLife — Account Created Successfully";
    $greeting = "Hello, " . htmlspecialchars($username) . "!";
    
    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">Thank you for registering an account on <strong>BloodLife</strong>. You can now request blood in emergencies, register as a volunteer donor, and track active requests in real time.</p>
    
    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 14px 16px; border-radius: 6px; margin: 18px 0; font-size: 13px; color: #166534;">
        <strong>✓ Your Account is Active:</strong> You can log in anytime using your registered email (<strong>{$email}</strong>).
    </div>
HTML;

    $button = ['text' => 'Log In to Dashboard', 'url' => $loginUrl];
    $badge = ['text' => 'Registration Successful', 'bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'];
    
    $html = build_base_email_template("Welcome to BloodLife", $greeting, $content, $button, $badge);
    $alt = "Hello {$username},\n\nWelcome to BloodLife! Your account has been registered successfully.\nLog in at: {$loginUrl}\n\nBloodLife Team";

    return send_email($email, $username, $subject, $html, $alt, [
        'user_id'    => $userId,
        'related_id' => $userId,
        'email_type' => 'Welcome_User'
    ]);
}

/**
 * 2. Send Welcome & Eligibility Confirmation upon Donor Registration
 */
function send_welcome_donor_email($userId, $donorUsername, $bloodGroup, $email) {
    $baseUrl = get_system_base_url();
    $donorUrl = $baseUrl . '/user/donor.php';
    
    $subject = "Thank You for Becoming a BloodLife Donor!";
    $greeting = "Welcome, Hero " . htmlspecialchars($donorUsername) . "!";
    $safeGroup = htmlspecialchars($bloodGroup);
    
    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">Thank you for registering as a voluntary blood donor. Your willingness to donate blood gives hope and saves lives in our community.</p>
    
    <table class="info-table" style="width: 100%; border-collapse: collapse; margin: 18px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600; width: 40%;">Donor Profile:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$donorUsername}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Blood Group:</td><td style="padding: 9px 12px; color: #dc2626; font-weight: 800;">🩸 {$safeGroup}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Status:</td><td style="padding: 9px 12px; color: #16a34a; font-weight: 700;">Available for Matches</td></tr>
    </table>

    <p style="font-size: 13px; color: #475569;">When a patient in your area requires <strong>{$safeGroup}</strong> blood, you will receive an assignment alert via email and on your donor dashboard.</p>
HTML;

    $button = ['text' => 'View Donor Dashboard', 'url' => $donorUrl];
    $badge = ['text' => 'Hero Donor Registered', 'bg' => '#ecfdf5', 'color' => '#059669', 'border' => '#a7f3d0'];

    $html = build_base_email_template("Donor Registration", $greeting, $content, $button, $badge);
    $alt = "Welcome {$donorUsername}!\n\nThank you for registering as a blood donor ({$bloodGroup}). You will be notified when matches occur.\nDashboard: {$donorUrl}\n\nBloodLife Team";

    return send_email($email, $donorUsername, $subject, $html, $alt, [
        'user_id'    => $userId,
        'related_id' => $userId,
        'email_type' => 'Donor_Welcome'
    ]);
}

/**
 * 3. Send Blood Request Confirmation to Requester
 */
function send_blood_request_confirmation_email($requesterUserId, array $requestData, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $requesterUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $baseUrl = get_system_base_url();
    $reqId = (int)($requestData['id'] ?? 0);
    $trackUrl = $baseUrl . '/user/bloodrequest.php';
    
    $bg = htmlspecialchars($requestData['blood_group'] ?? 'Unknown');
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'General Hospital');
    $units = (int)($requestData['units'] ?? 1);
    $reqDate = htmlspecialchars($requestData['required_date'] ?? date('Y-m-d'));
    $urgency = htmlspecialchars($requestData['urgency'] ?? 'Normal');

    $subject = "Blood Request #{$reqId} Received — BloodLife";
    $greeting = "Hello, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">Your request for blood has been submitted successfully and logged in our system. Our administrators and volunteer donors are being notified.</p>
    
    <table class="info-table" style="width: 100%; border-collapse: collapse; margin: 18px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600; width: 40%;">Request ID:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">#{$reqId}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Blood Group:</td><td style="padding: 9px 12px; color: #dc2626; font-weight: 800;">🩸 {$bg}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Units Required:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$units} Unit(s)</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Hospital:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$hosp}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Required Date:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$reqDate}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Urgency:</td><td style="padding: 9px 12px; color: #b91c1c; font-weight: 800;">{$urgency}</td></tr>
    </table>
HTML;

    $button = ['text' => 'Track Request Status', 'url' => $trackUrl];
    $badge = ['text' => 'Request Received', 'bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'];

    $html = build_base_email_template("Blood Request Confirmation", $greeting, $content, $button, $badge);
    $alt = "Hello {$u['username']},\n\nYour blood request #{$reqId} for {$bg} at {$hosp} has been received.\nTrack at: {$trackUrl}\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $requesterUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Request_Confirmation'
    ]);
}

/**
 * 4. Send Urgent Alert Email to Admins for Urgent/Critical Requests
 */
function send_admin_urgent_request_email(array $requestData, $notificationId = null) {
    global $conn;
    $res = $conn->query("SELECT id, email, username FROM users WHERE role = 'Admin' AND status = 'Active'");
    if (!$res || $res->num_rows === 0) return ['success' => false, 'error' => 'No active admins'];

    $reqId = (int)($requestData['id'] ?? 0);
    $bg = htmlspecialchars($requestData['blood_group'] ?? 'Unknown');
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');
    $urgency = htmlspecialchars($requestData['urgency'] ?? 'Urgent');
    $reqName = htmlspecialchars($requestData['requester_name'] ?? 'Patient');

    $baseUrl = get_system_base_url();
    $assignUrl = $baseUrl . '/admin/assignments.php?request_id=' . $reqId;

    $subject = "🚨 URGENT: Blood Request #{$reqId} Needs Donor ({$bg})";
    $content = <<<HTML
    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 13px; color: #991b1b;">
        <strong>⚠️ Immediate Action Required:</strong> An urgent blood request has been submitted by <strong>{$reqName}</strong> and requires fast donor matching.
    </div>

    <table class="info-table" style="width: 100%; border-collapse: collapse; margin: 18px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600; width: 40%;">Request ID:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">#{$reqId}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Blood Group:</td><td style="padding: 9px 12px; color: #dc2626; font-weight: 800;">🩸 {$bg}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Hospital:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$hosp}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Urgency:</td><td style="padding: 9px 12px; color: #b91c1c; font-weight: 800;">{$urgency}</td></tr>
    </table>
HTML;

    $button = ['text' => 'Assign Donor Now', 'url' => $assignUrl, 'bg' => 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)'];
    $badge = ['text' => 'Urgent Priority', 'bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'];

    $lastRes = ['success' => true];
    while ($admin = $res->fetch_assoc()) {
        $html = build_base_email_template("Urgent Blood Request", "Admin Alert", $content, $button, $badge);
        $alt = "Urgent Blood Request #{$reqId} ({$bg} at {$hosp}). Assign at: {$assignUrl}";
        $lastRes = send_email($admin['email'], $admin['username'], $subject, $html, $alt, [
            'user_id'         => (int)$admin['id'],
            'related_id'      => $reqId,
            'notification_id' => $notificationId,
            'email_type'      => 'Admin_Urgent_Request'
        ]);
    }
    return $lastRes;
}

/**
 * 5. Send Urgent Donor Assignment Email to Donor
 */
function send_donor_assignment_email($donorUserId, array $requestData, $assignmentId = null, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $donorUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $baseUrl = get_system_base_url();
    $donorPortalUrl = $baseUrl . '/user/donor.php';
    
    $reqId = (int)($requestData['id'] ?? 0);
    $bg = htmlspecialchars($requestData['blood_group'] ?? 'Blood');
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');
    $units = (int)($requestData['units'] ?? 1);
    $reqDate = htmlspecialchars($requestData['required_date'] ?? date('Y-m-d'));
    $patientName = htmlspecialchars($requestData['requester_name'] ?? 'A Patient in need');

    $subject = "Urgent: You Have Been Assigned to Blood Request #{$reqId} ({$bg})";
    $greeting = "Hello, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">You have been matched and assigned by the BloodLife Admin to a blood donation request. A patient urgently requires your blood type.</p>
    
    <table class="info-table" style="width: 100%; border-collapse: collapse; margin: 18px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600; width: 40%;">Blood Group Needed:</td><td style="padding: 9px 12px; color: #dc2626; font-weight: 800;">🩸 {$bg}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Units:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$units} Unit(s)</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Hospital Location:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">🏥 {$hosp}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Required Date:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">📅 {$reqDate}</td></tr>
        <tr><td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Patient / Requester:</td><td style="padding: 9px 12px; color: #0f172a; font-weight: 700;">{$patientName}</td></tr>
    </table>

    <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 6px; margin: 18px 0; font-size: 13px; color: #92400e;">
        <strong>⚡ Next Step:</strong> Please log in to your Donor Dashboard to <strong>Accept</strong> or <strong>Reject</strong> this assignment so we can coordinate hospital arrival.
    </div>
HTML;

    $button = ['text' => 'Review & Accept Assignment', 'url' => $donorPortalUrl];
    $badge = ['text' => 'Donor Assignment', 'bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'];

    $html = build_base_email_template("Blood Request Assignment", $greeting, $content, $button, $badge);
    $alt = "Hello {$u['username']},\n\nYou have been assigned to blood request #{$reqId} ({$bg} at {$hosp}). Please log in to respond:\n{$donorPortalUrl}\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $donorUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Donor_Assignment'
    ]);
}

/**
 * 6. Send Donor Assigned Notice to Requester
 */
function send_requester_donor_assigned_email($requesterUserId, array $requestData, $donorName, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $requesterUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $baseUrl = get_system_base_url();
    $trackUrl = $baseUrl . '/user/bloodrequest.php';
    $reqId = (int)($requestData['id'] ?? 0);
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');
    $safeDonor = htmlspecialchars($donorName);

    $subject = "Donor Assigned to Your Blood Request #{$reqId}";
    $greeting = "Good news, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">A volunteer donor (<strong>{$safeDonor}</strong>) has been matched and assigned to your blood request <strong>#{$reqId}</strong> for {$hosp}.</p>
    <p style="font-size: 13px; color: #475569;">The donor has been notified to review and accept the assignment. You will receive an immediate update once the donor confirms.</p>
HTML;

    $button = ['text' => 'View Request Details', 'url' => $trackUrl];
    $badge = ['text' => 'Donor Matched', 'bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'];

    $html = build_base_email_template("Donor Matched", $greeting, $content, $button, $badge);
    $alt = "Good news {$u['username']}!\n\nA donor ({$donorName}) has been assigned to your blood request #{$reqId}.\nTrack at: {$trackUrl}\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $requesterUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Requester_Donor_Assigned'
    ]);
}

/**
 * 7. Send Notice to Requester when Donor Accepts
 */
function send_donor_accepted_email($requesterUserId, array $requestData, array $donorData, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $requesterUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $baseUrl = get_system_base_url();
    $trackUrl = $baseUrl . '/user/bloodrequest.php';
    $reqId = (int)($requestData['id'] ?? 0);
    $donorName = htmlspecialchars($donorData['username'] ?? 'Donor');
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');

    $subject = "Donor {$donorName} Has Accepted Your Blood Request #{$reqId}!";
    $greeting = "Hello, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 13px; color: #14532d;">
        <strong>✓ Assignment Accepted:</strong> Donor <strong>{$donorName}</strong> has confirmed and accepted your blood request for <strong>{$hosp}</strong>.
    </div>
    <p style="font-size: 13px; color: #475569;">Please log in to your Blood Request page to check real-time status and prepare for blood transfer at the hospital.</p>
HTML;

    $button = ['text' => 'View Blood Request', 'url' => $trackUrl];
    $badge = ['text' => 'Donor Accepted', 'bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'];

    $html = build_base_email_template("Donor Accepted Request", $greeting, $content, $button, $badge);
    $alt = "Hello {$u['username']}!\n\nDonor {$donorName} has accepted blood request #{$reqId} at {$hosp}.\nDetails: {$trackUrl}\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $requesterUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Assignment_Accepted'
    ]);
}

/**
 * 8. Send Notice to Admin when Donor Rejects Assignment
 */
function send_admin_donor_rejected_email(array $requestData, array $donorData, $notificationId = null) {
    global $conn;
    $res = $conn->query("SELECT id, email, username FROM users WHERE role = 'Admin' AND status = 'Active'");
    if (!$res || $res->num_rows === 0) return ['success' => false, 'error' => 'No active admins'];

    $reqId = (int)($requestData['id'] ?? 0);
    $donorName = htmlspecialchars($donorData['username'] ?? 'Donor');
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');
    $bg = htmlspecialchars($requestData['blood_group'] ?? 'Blood');

    $baseUrl = get_system_base_url();
    $assignUrl = $baseUrl . '/admin/assignments.php?request_id=' . $reqId;

    $subject = "Donor Declined: Request #{$reqId} Needs Reassignment";
    $content = <<<HTML
    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 13px; color: #991b1b;">
        <strong>⚠️ Reassignment Needed:</strong> Donor <strong>{$donorName}</strong> has declined the blood request #{$reqId} ({$bg} at {$hosp}).
    </div>
    <p style="font-size: 13px; color: #475569;">The request status has been reverted to Pending. Please assign an alternate available donor.</p>
HTML;

    $button = ['text' => 'Reassign Donor', 'url' => $assignUrl];
    $badge = ['text' => 'Reassignment Required', 'bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'];

    $lastRes = ['success' => true];
    while ($admin = $res->fetch_assoc()) {
        $html = build_base_email_template("Donor Declined Request", "Admin Alert", $content, $button, $badge);
        $alt = "Donor {$donorName} rejected request #{$reqId}. Reassign at: {$assignUrl}";
        $lastRes = send_email($admin['email'], $admin['username'], $subject, $html, $alt, [
            'user_id'         => (int)$admin['id'],
            'related_id'      => $reqId,
            'notification_id' => $notificationId,
            'email_type'      => 'Admin_Donor_Rejected'
        ]);
    }
    return $lastRes;
}

/**
 * 9. Send Notice to Requester when Donor Rejects
 */
function send_requester_donor_rejected_email($requesterUserId, array $requestData, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $requesterUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $baseUrl = get_system_base_url();
    $trackUrl = $baseUrl . '/user/bloodrequest.php';
    $reqId = (int)($requestData['id'] ?? 0);

    $subject = "Update on Your Blood Request #{$reqId}";
    $greeting = "Hello, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">The assigned donor was unfortunately unable to fulfill blood request <strong>#{$reqId}</strong>. Our administrators are currently matching an alternate volunteer donor for you.</p>
    <p style="font-size: 13px; color: #475569;">You will be notified immediately once a new donor is assigned.</p>
HTML;

    $button = ['text' => 'View Request Status', 'url' => $trackUrl];
    $badge = ['text' => 'Reassignment in Progress', 'bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fde68a'];

    $html = build_base_email_template("Blood Request Update", $greeting, $content, $button, $badge);
    $alt = "Hello {$u['username']},\n\nYour blood request #{$reqId} is being reassigned to another donor.\nTrack: {$trackUrl}\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $requesterUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Requester_Assignment_Rejected'
    ]);
}

/**
 * 10. Send Thank You Email to Donor after Blood Received Confirmation
 */
function send_blood_received_thankyou_email($donorUserId, array $requestData, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $donorUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $baseUrl = get_system_base_url();
    $historyUrl = $baseUrl . '/user/donor.php';
    $reqId = (int)($requestData['id'] ?? 0);
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');
    $bg = htmlspecialchars($requestData['blood_group'] ?? 'Blood');

    $subject = "❤️ Thank You for Saving a Life! — BloodLife";
    $greeting = "Dear Hero, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; color: #14532d; line-height: 1.6;">
        <strong>🎉 Blood Received Confirmed:</strong> The patient has confirmed receipt of your blood donation for Request #{$reqId} at {$hosp}. Your selfless contribution has directly saved a life!
    </div>
    
    <p style="font-size: 14px; color: #334155;">This donation has been officially recorded in your <strong>Donation History</strong>. You are truly making our community stronger and healthier.</p>
HTML;

    $button = ['text' => 'View Donation History', 'url' => $historyUrl];
    $badge = ['text' => 'Life Saver', 'bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'];

    $html = build_base_email_template("Donation Completed", $greeting, $content, $button, $badge);
    $alt = "Dear Hero {$u['username']}!\n\nYour blood donation for Request #{$reqId} has been confirmed received. Thank you for saving a life!\nHistory: {$historyUrl}\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $donorUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Blood_Received_ThankYou'
    ]);
}

/**
 * 11. Send Completion Summary Email to Requester
 */
function send_request_completed_email($requesterUserId, array $requestData, $notificationId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $requesterUserId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $reqId = (int)($requestData['id'] ?? 0);
    $hosp = htmlspecialchars($requestData['hospital'] ?? 'Hospital');
    $bg = htmlspecialchars($requestData['blood_group'] ?? 'Blood');

    $subject = "Blood Request #{$reqId} Completed Successfully";
    $greeting = "Hello, " . htmlspecialchars($u['username']) . "!";

    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">Your blood request <strong>#{$reqId}</strong> for <strong>{$bg}</strong> blood at <strong>{$hosp}</strong> has been marked as <strong>Completed</strong>.</p>
    <p style="font-size: 13px; color: #475569;">Thank you for using BloodLife. We wish you and your loved ones good health!</p>
HTML;

    $badge = ['text' => 'Request Completed', 'bg' => '#f0fdf4', 'color' => '#16a34a', 'border' => '#bbf7d0'];

    $html = build_base_email_template("Request Completed", $greeting, $content, null, $badge);
    $alt = "Hello {$u['username']},\n\nYour blood request #{$reqId} has been completed successfully.\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'         => $requesterUserId,
        'related_id'      => $reqId,
        'notification_id' => $notificationId,
        'email_type'      => 'Request_Completed'
    ]);
}

/**
 * 12. Send Account Status Change Email
 */
function send_account_status_email($userId, $newStatus) {
    global $conn;
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'User lookup error'];
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u || empty($u['email'])) return ['success' => false, 'error' => 'No email found'];

    $safeStatus = htmlspecialchars($newStatus);
    $subject = "BloodLife Account Status Update: {$safeStatus}";
    $greeting = "Hello, " . htmlspecialchars($u['username']) . "!";

    $statusColor = $newStatus === 'Active' ? '#16a34a' : '#dc2626';
    $statusBg = $newStatus === 'Active' ? '#f0fdf4' : '#fef2f2';
    $statusBorder = $newStatus === 'Active' ? '#bbf7d0' : '#fecaca';

    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">Your BloodLife account status has been updated by an administrator to: <strong style="color: {$statusColor};">{$safeStatus}</strong>.</p>
HTML;

    $badge = ['text' => "Status: {$safeStatus}", 'bg' => $statusBg, 'color' => $statusColor, 'border' => $statusBorder];

    $html = build_base_email_template("Account Status Update", $greeting, $content, null, $badge);
    $alt = "Hello {$u['username']},\n\nYour BloodLife account status is now: {$newStatus}.\n\nBloodLife Team";

    return send_email($u['email'], $u['username'], $subject, $html, $alt, [
        'user_id'    => $userId,
        'related_id' => $userId,
        'email_type' => 'Account_Status_Change'
    ]);
}

/**
 * 13. Send Password Reset Email
 */
function send_password_reset_email($toEmail, $recipientName, $resetLink) {
    $displayName = !empty($recipientName) ? htmlspecialchars($recipientName) : 'User';
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $subject = "Reset Your BloodLife Password";

    $greeting = "Hello, {$displayName}!";
    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">We received a request to reset the password for your BloodLife account. Click the button below to choose a new password:</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #991b1b; margin: 20px 0;">
        <strong>⏱️ Important:</strong> This password reset link is valid for <strong>1 hour</strong> and can only be used once.
    </div>

    <p style="font-size: 13px; color: #64748b;">If you did not request a password reset, you can safely ignore this email. Your current password will remain completely secure.</p>
HTML;

    $button = ['text' => 'Reset Password', 'url' => $resetLink];
    $badge = ['text' => 'Security Alert', 'bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'];
    $footerNote = "If the button above does not work, copy and paste this link into your browser:<br><a href=\"{$safeLink}\" style=\"color: #dc2626; word-break: break-all;\">{$safeLink}</a>";

    $html = build_base_email_template("Password Reset Request", $greeting, $content, $button, $badge, $footerNote);
    $alt = "Hello {$displayName},\n\nReset your BloodLife password using this link (valid 1 hour):\n{$resetLink}\n\nBloodLife Team";

    return send_email($toEmail, $recipientName, $subject, $html, $alt, [
        'email_type' => 'Password_Reset'
    ]);
}

/**
 * 14. Send Security Alert Email when Password is Changed/Reset
 */
function send_password_changed_security_email($userId, $toEmail, $recipientName) {
    $displayName = !empty($recipientName) ? htmlspecialchars($recipientName) : 'User';
    $subject = "Security Alert: Your BloodLife Password Was Changed";

    $greeting = "Hello, {$displayName}!";
    $dateStr = date('M j, Y · g:i A');
    $content = <<<HTML
    <p style="font-size: 14px; color: #334155;">The password for your BloodLife account was successfully changed on <strong>{$dateStr}</strong>.</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #991b1b; margin: 20px 0;">
        <strong>⚠️ If you did not perform this change:</strong> Please contact the system administrator immediately to secure your account.
    </div>
HTML;

    $badge = ['text' => 'Password Changed', 'bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fecaca'];

    $html = build_base_email_template("Password Changed", $greeting, $content, null, $badge);
    $alt = "Hello {$displayName},\n\nYour BloodLife account password was recently changed. If this wasn't you, please contact support.\n\nBloodLife Team";

    return send_email($toEmail, $recipientName, $subject, $html, $alt, [
        'user_id'    => $userId,
        'related_id' => $userId,
        'email_type' => 'Password_Changed'
    ]);
}

/**
 * 15. Generic Notification Email Fallback (Legacy support)
 */
function send_notification_email($userId, $title, $message, $type = 'Notification', $requestId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return ['success' => false, 'error' => 'Database error'];
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['email'])) return ['success' => false, 'error' => 'User does not have an email.'];

    $subject = "[BloodLife] " . $title;
    $greeting = "Hello, " . htmlspecialchars($user['username']) . "!";
    $safeMsg = nl2br(htmlspecialchars($message));
    $content = "<div style=\"background: #f8fafc; border-left: 4px solid #dc2626; padding: 16px; border-radius: 6px; margin: 16px 0; font-size: 14px; color: #334155;\">{$safeMsg}</div>";

    $html = build_base_email_template($title, $greeting, $content);
    $alt = "Hello {$user['username']},\n\n{$title}\n\n{$message}\n\nBloodLife Team";

    return send_email($user['email'], $user['username'], $subject, $html, $alt, [
        'user_id'    => $userId,
        'related_id' => $requestId,
        'email_type' => $type
    ]);
}
