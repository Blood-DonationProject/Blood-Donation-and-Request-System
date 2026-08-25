<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer.php';

$config = get_mail_config();
$message = '';
$status = '';
$action = $_POST['action'] ?? 'send_test';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'test_connection') {
        try {
            if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
                throw new Exception('Gmail SMTP credentials are not configured. Please set your email and App Password in config/mail.local.php or .env.');
            }
            $mail = get_mailer(true);
            // Attempt SMTP connection
            if ($mail->smtpConnect()) {
                $status = 'success';
                $message = 'Successfully connected and authenticated with Gmail SMTP server (' . htmlspecialchars($config['smtp_host']) . ':' . htmlspecialchars($config['smtp_port']) . ')!';
                $mail->smtpClose();
            } else {
                $status = 'error';
                $message = 'Failed to connect to Gmail SMTP: ' . htmlspecialchars(sanitize_smtp_error($mail->ErrorInfo));
            }
        } catch (Exception $e) {
            $status = 'error';
            $message = 'SMTP Connection Error: ' . htmlspecialchars(sanitize_smtp_error($e->getMessage()));
        }
    } elseif ($action === 'send_test') {
        $to = trim($_POST['recipient_email'] ?? '');
        if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $subject = "Test Email from BloodLife Donation System";
            $htmlMessage = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Test Email</title></head>
<body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; padding: 24px;">
    <h2 style="color: #dc2626; margin-top: 0;">🩸 BloodLife System Test</h2>
    <p>Congratulations! Your <strong>PHPMailer + Gmail SMTP</strong> integration is functioning properly.</p>
    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
      <tr><td style="padding: 6px; font-weight: bold; color: #4b5563;">SMTP Host:</td><td style="padding: 6px;">{$config['smtp_host']}</td></tr>
      <tr><td style="padding: 6px; font-weight: bold; color: #4b5563;">SMTP Port:</td><td style="padding: 6px;">{$config['smtp_port']}</td></tr>
      <tr><td style="padding: 6px; font-weight: bold; color: #4b5563;">Encryption:</td><td style="padding: 6px;">STARTTLS (tls)</td></tr>
      <tr><td style="padding: 6px; font-weight: bold; color: #4b5563;">Sent At:</td><td style="padding: 6px;">" . date('Y-m-d H:i:s') . "</td></tr>
    </table>
    <p style="color: #6b7280; font-size: 12px; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 12px;">
      BloodLife Donation and Request Management System
    </p>
  </div>
</body>
</html>
HTML;

            $result = send_email($to, "BloodLife Tester", $subject, $htmlMessage, '', ['email_type' => 'Test']);

            if ($result['success']) {
                $status = 'success';
                $message = 'Test email sent successfully to ' . htmlspecialchars($to);
            } else {
                $status = 'error';
                $message = 'Failed to send email: ' . htmlspecialchars($result['error']);
            }
        } else {
            $status = 'error';
            $message = 'Please enter a valid recipient email address.';
        }
    } elseif ($action === 'test_password_reset') {
        $to = trim($_POST['recipient_email'] ?? '');
        if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $dummyToken = bin2hex(random_bytes(32));
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $dummyLink = $protocol . $host . $scriptDir . '/user/reset_password.php?token=' . urlencode($dummyToken);

            $result = send_password_reset_email($to, "Test User", $dummyLink);
            if ($result['success']) {
                $status = 'success';
                $message = 'Test Password Reset email sent successfully to ' . htmlspecialchars($to);
            } else {
                $status = 'error';
                $message = 'Failed to send reset email: ' . htmlspecialchars($result['error']);
            }
        } else {
            $status = 'error';
            $message = 'Please enter a valid recipient email address.';
        }
    }
}

$isConfigured = !empty($config['smtp_username']) && !empty($config['smtp_password']);
$maskedPassword = !empty($config['smtp_password']) ? substr($config['smtp_password'], 0, 4) . ' •••• •••• ' . substr($config['smtp_password'], -4) : '<span class="text-red-500 font-semibold">NOT SET</span>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gmail SMTP & PHPMailer Diagnostic Test – BloodLife</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen text-gray-800 p-6">

  <div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div class="flex items-center space-x-3">
        <span class="text-3xl">✉️</span>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">PHPMailer + Gmail SMTP Diagnostics</h1>
          <p class="text-sm text-gray-500">Blood Donation & Request System Email Integration</p>
        </div>
      </div>
      <a href="user/login.php" class="inline-flex items-center text-sm font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-xl transition">
        &larr; Go to User Login
      </a>
    </div>

    <!-- Alert / Status -->
    <?php if ($message): ?>
      <div class="p-4 rounded-xl border flex items-start space-x-3 <?= $status === 'success' ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' ?>">
        <span class="text-xl font-bold"><?= $status === 'success' ? '✓' : '⚠' ?></span>
        <div class="flex-1 font-medium text-sm"><?= $message ?></div>
      </div>
    <?php endif; ?>

    <!-- Config Overview Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
      <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center justify-between">
        <span>Current SMTP Configuration</span>
        <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $isConfigured ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
          <?= $isConfigured ? 'Credentials Configured' : 'Credentials Incomplete' ?>
        </span>
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100">
          <span class="text-gray-500 font-medium block">SMTP Host:</span>
          <span class="font-mono text-gray-800 font-semibold"><?= htmlspecialchars($config['smtp_host']) ?></span>
        </div>
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100">
          <span class="text-gray-500 font-medium block">SMTP Port & Encryption:</span>
          <span class="font-mono text-gray-800 font-semibold"><?= htmlspecialchars($config['smtp_port']) ?> (<?= htmlspecialchars(strtoupper($config['smtp_secure'])) ?>)</span>
        </div>
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100">
          <span class="text-gray-500 font-medium block">Gmail Account (Username):</span>
          <span class="font-mono text-gray-800 font-semibold"><?= !empty($config['smtp_username']) ? htmlspecialchars($config['smtp_username']) : '<span class="text-red-500">NOT SET</span>' ?></span>
        </div>
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100">
          <span class="text-gray-500 font-medium block">Gmail App Password:</span>
          <span class="font-mono text-gray-800"><?= $maskedPassword ?></span>
        </div>
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100">
          <span class="text-gray-500 font-medium block">Sender (From Address):</span>
          <span class="font-mono text-gray-800"><?= htmlspecialchars($config['from_email'] ?: '-') ?></span>
        </div>
        <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100">
          <span class="text-gray-500 font-medium block">Sender Name:</span>
          <span class="font-mono text-gray-800"><?= htmlspecialchars($config['from_name'] ?: '-') ?></span>
        </div>
      </div>

      <!-- Quick Test Connection -->
      <form method="POST" action="test_email.php" class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
        <input type="hidden" name="action" value="test_connection">
        <span class="text-xs text-gray-500">Test connection to Gmail SMTP without sending an email.</span>
        <button type="submit" class="bg-gray-800 hover:bg-black text-white px-4 py-2 rounded-xl text-sm font-semibold transition">
          🔌 Test SMTP Connection
        </button>
      </form>
    </div>

    <!-- Actions Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- Test 1: Standard Test Email -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col justify-between">
        <div>
          <div class="w-10 h-10 bg-red-50 text-red-600 rounded-xl flex items-center justify-center text-lg font-bold mb-3">
            ✉️
          </div>
          <h3 class="text-lg font-bold text-gray-900">Send Test Email</h3>
          <p class="text-sm text-gray-500 mt-1">Send a standard test email to verify end-to-end delivery.</p>
        </div>

        <form method="POST" action="test_email.php" class="mt-4 space-y-3">
          <input type="hidden" name="action" value="send_test">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Recipient Email</label>
            <input type="email" name="recipient_email" required placeholder="recipient@example.com"
              class="w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-red-500">
          </div>
          <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl font-bold text-sm transition">
            Send Test Email &rarr;
          </button>
        </form>
      </div>

      <!-- Test 2: Password Reset Test Email -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col justify-between">
        <div>
          <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg font-bold mb-3">
            🔑
          </div>
          <h3 class="text-lg font-bold text-gray-900">Send Password Reset Preview</h3>
          <p class="text-sm text-gray-500 mt-1">Send a test password reset email with the styled BloodLife template.</p>
        </div>

        <form method="POST" action="test_email.php" class="mt-4 space-y-3">
          <input type="hidden" name="action" value="test_password_reset">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Recipient Email</label>
            <input type="email" name="recipient_email" required placeholder="recipient@example.com"
              class="w-full border border-gray-300 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-blue-500">
          </div>
          <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl font-bold text-sm transition">
            Send Reset Template &rarr;
          </button>
        </form>
      </div>

    </div>

    <!-- How-to Guide Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
      <h3 class="text-base font-bold text-gray-900 mb-2">📋 How to configure Gmail SMTP credentials</h3>
      <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 leading-relaxed">
        <li>Create or edit <code class="bg-gray-100 px-2 py-0.5 rounded text-red-600 font-mono">config/mail.local.php</code> (or set variables in <code class="bg-gray-100 px-2 py-0.5 rounded text-red-600 font-mono">.env</code>).</li>
        <li>Enable <strong>2-Step Verification</strong> on your Google Account: <a href="https://myaccount.google.com/security" target="_blank" class="text-blue-600 hover:underline">Google Security Settings</a>.</li>
        <li>Generate a <strong>16-character App Password</strong> under <em>Security &rarr; 2-Step Verification &rarr; App Passwords</em>.</li>
        <li>Set <code class="font-mono text-gray-800 font-semibold">'smtp_username' =&gt; 'your-email@gmail.com'</code> and <code class="font-mono text-gray-800 font-semibold">'smtp_password' =&gt; 'your-16-char-app-password'</code>.</li>
        <li>Your credentials in <code class="font-mono text-gray-800">config/mail.local.php</code> and <code class="font-mono text-gray-800">.env</code> are git-ignored and never committed to public repositories.</li>
      </ol>
    </div>

  </div>

</body>
</html>