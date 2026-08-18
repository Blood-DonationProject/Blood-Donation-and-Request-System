<?php
require_once __DIR__ . '/includes/mailer.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $_POST['recipient_email'] ?? '';
    if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $subject = "Test Email from Blood Donation System";
        $htmlMessage = "<h1>It Works!</h1><p>This is a test email sent via PHPMailer and Gmail SMTP.</p>";

        $result = send_email($to, "Test User", $subject, $htmlMessage);

        if ($result['success']) {
            $status = 'success';
            $message = 'Test email sent successfully to ' . htmlspecialchars($to);
        } else {
            $status = 'error';
            $message = 'Failed to send email. Error: ' . htmlspecialchars($result['error']);
        }
    } else {
        $status = 'error';
        $message = 'Please enter a valid email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Test Email Sending</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .success {
            color: green;
            margin-bottom: 10px;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        .container {
            border: 1px solid #ccc;
            padding: 20px;
            max-width: 400px;
        }

        input[type="email"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }

        button {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Test Email Configuration</h2>

        <?php if ($message): ?>
            <div class="<?php echo $status; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="recipient_email">Recipient Email Address:</label>
            <input type="email" name="recipient_email" id="recipient_email" required placeholder="Enter your email">
            <button type="submit">Send Test Email</button>
        </form>
    </div>

</body>

</html>