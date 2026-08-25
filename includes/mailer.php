<?php
/**
 * PHPMailer Helper with Gmail SMTP Support
 * 
 * Provides unified email sending, password reset emails,
 * system notifications, and secure error handling/logging.
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
    $mail->Host       = $config['smtp_host'] ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = $config['smtp_auth'] !== false;
    $mail->Username   = $config['smtp_username'] ?? '';
    $mail->Password   = $config['smtp_password'] ?? '';
    
    // Encryption & Port
    $secure = strtolower($config['smtp_secure'] ?? 'tls');
    if ($secure === 'ssl' || $config['smtp_port'] == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port       = (int)($config['smtp_port'] ?: 587);

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
    $fromEmail = !empty($config['from_email']) ? $config['from_email'] : $config['smtp_username'];
    $fromName  = !empty($config['from_name']) ? $config['from_name'] : 'BloodLife Donation System';
    
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
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid recipient email address.', 'message_id' => null];
    }

    $config = get_mail_config();
    if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
        $msg = 'Gmail SMTP credentials are not configured. Please set up your Gmail address and App Password in config/mail.local.php or .env.';
        error_log("[Mailer] " . $msg);
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

        // Log successful send to email_logs if database connection is available
        log_email_record([
            'notification_id' => $options['notification_id'] ?? null,
            'user_id'         => $options['user_id'] ?? null,
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
        $cleanError = sanitize_smtp_error($mail->ErrorInfo ?? $e->getMessage());
        error_log("[Mailer Error] Failed sending to {$toEmail}: " . $cleanError);

        // Log failed send to email_logs
        log_email_record([
            'notification_id' => $options['notification_id'] ?? null,
            'user_id'         => $options['user_id'] ?? null,
            'recipient_email' => $toEmail,
            'recipient_name'  => $toName,
            'subject'         => $subject,
            'email_type'      => $options['email_type'] ?? 'General',
            'status'          => 'Failed',
            'error_message'   => $cleanError,
            'sent_at'         => null,
        ]);

        return [
            'success' => false,
            'error'   => $cleanError,
            'message_id' => null
        ];
    }
}

/**
 * Remove any sensitive password fragments from error messages
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
        // Attempt to load db connection if not available in current scope
        $dbPath = dirname(__DIR__) . '/config/db.php';
        if (file_exists($dbPath)) {
            include_once $dbPath;
        }
    }

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
        $stmt = $conn->prepare("INSERT INTO email_logs 
            (notification_id, user_id, recipient_email, recipient_name, subject, email_type, status, error_message, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $notificationId = $data['notification_id'] ?? null;
            $userId         = $data['user_id'] ?? null;
            $recipientEmail = $data['recipient_email'] ?? '';
            $recipientName  = $data['recipient_name'] ?? null;
            $subject        = $data['subject'] ?? '';
            $emailType      = $data['email_type'] ?? 'General';
            $status         = $data['status'] ?? 'Pending';
            $errorMsg       = $data['error_message'] ?? null;
            $sentAt         = $data['sent_at'] ?? null;

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

/**
 * Build and send a styled Password Reset Email
 * 
 * @param string $toEmail
 * @param string $recipientName
 * @param string $resetLink
 * @return array
 */
function send_password_reset_email($toEmail, $recipientName, $resetLink) {
    $displayName = !empty($recipientName) ? htmlspecialchars($recipientName) : 'User';
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    $subject = "Reset Your BloodLife Password";

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Reset</title>
<style>
    body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #333333; }
    .email-container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
    .email-header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 32px 24px; text-align: center; color: #ffffff; }
    .email-header h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
    .email-header p { margin: 8px 0 0; opacity: 0.9; font-size: 14px; }
    .email-body { padding: 32px 28px; line-height: 1.6; }
    .greeting { font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px; }
    .button-container { text-align: center; margin: 32px 0; }
    .reset-button { display: inline-block; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 16px; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3); }
    .notice { background: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #991b1b; margin: 24px 0; }
    .fallback-url { font-size: 12px; color: #6b7280; word-break: break-all; margin-top: 24px; }
    .fallback-url a { color: #dc2626; }
    .email-footer { background: #f9fafb; padding: 20px 24px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
</style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>🩸 BloodLife</h1>
        <p>Blood Donation & Request Communication System</p>
    </div>
    <div class="email-body">
        <div class="greeting">Hello, {$displayName}!</div>
        <p>We received a request to reset the password for your BloodLife account. Click the button below to choose a new password:</p>
        
        <div class="button-container">
            <a href="{$safeLink}" class="reset-button" target="_blank">Reset Password &rarr;</a>
        </div>

        <div class="notice">
            <strong>⏱️ Important:</strong> This password reset link is valid for <strong>1 hour</strong>. For security purposes, it can only be used once.
        </div>

        <p style="font-size: 14px; color: #4b5563;">If you did not request a password reset, you can safely ignore this email. Your current password will remain completely secure and unchanged.</p>

        <div class="fallback-url">
            <p>If you have trouble clicking the button above, copy and paste the following URL into your web browser:</p>
            <a href="{$safeLink}">{$safeLink}</a>
        </div>
    </div>
    <div class="email-footer">
        &copy; " . date('Y') . " BloodLife. All rights reserved.<br>
        This is an automated system notification. Please do not reply directly to this email.
    </div>
</div>
</body>
</html>
HTML;

    $altBody = "Hello {$displayName},\n\n"
             . "We received a request to reset your password for your BloodLife account.\n"
             . "Please visit the following link to choose a new password:\n\n"
             . "{$resetLink}\n\n"
             . "This link is valid for 1 hour. If you did not make this request, you can safely ignore this email.\n\n"
             . "BloodLife Team";

    return send_email($toEmail, $recipientName, $subject, $htmlBody, $altBody, [
        'email_type' => 'PasswordReset'
    ]);
}

/**
 * Send a notification email to a registered user
 * 
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string $type
 * @param int|null $requestId
 * @return array
 */
function send_notification_email($userId, $title, $message, $type = 'Notification', $requestId = null) {
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $dbPath = dirname(__DIR__) . '/config/db.php';
        if (file_exists($dbPath)) {
            include_once $dbPath;
        }
    }

    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        return ['success' => false, 'error' => 'Database connection unavailable.'];
    }

    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'error' => 'Failed to prepare user lookup.'];
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['email'])) {
        return ['success' => false, 'error' => 'User does not have a registered email address.'];
    }

    $displayName = htmlspecialchars($user['username'] ?? 'User');
    $safeTitle = htmlspecialchars($title);
    $safeMessage = nl2br(htmlspecialchars($message));
    $subject = "[BloodLife] " . $title;

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$safeTitle}</title>
<style>
    body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #333333; }
    .email-container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
    .email-header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 24px 20px; text-align: center; color: #ffffff; }
    .email-header h1 { margin: 0; font-size: 22px; font-weight: 700; }
    .email-body { padding: 28px 24px; line-height: 1.6; }
    .notif-card { background: #f8fafc; border-left: 4px solid #dc2626; padding: 18px; border-radius: 6px; margin: 20px 0; }
    .notif-title { font-size: 17px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
    .notif-body { font-size: 14px; color: #475569; line-height: 1.5; }
    .email-footer { background: #f9fafb; padding: 16px 20px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
</style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>🩸 BloodLife Notification</h1>
    </div>
    <div class="email-body">
        <p>Hello <strong>{$displayName}</strong>,</p>
        <div class="notif-card">
            <div class="notif-title">{$safeTitle}</div>
            <div class="notif-body">{$safeMessage}</div>
        </div>
        <p style="font-size: 14px; color: #64748b;">Please log in to your BloodLife dashboard to view more details or take action.</p>
    </div>
    <div class="email-footer">
        &copy; " . date('Y') . " BloodLife. All rights reserved.
    </div>
</div>
</body>
</html>
HTML;

    $altBody = "Hello {$user['username']},\n\n"
             . "{$title}\n\n"
             . "{$message}\n\n"
             . "Please log in to your BloodLife account for details.\n\n"
             . "BloodLife Team";

    return send_email($user['email'], $user['username'], $subject, $htmlBody, $altBody, [
        'user_id'    => $userId,
        'email_type' => $type
    ]);
}
