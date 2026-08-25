<?php
/**
 * Master Mail Configuration
 * 
 * Centralized mail settings loaded by the PHPMailer helper.
 * Reads settings from:
 * 1. `config/mail.local.php` (if exists, highest precedence for local development)
 * 2. Environment variables (.env file or system environment)
 * 3. Default Gmail SMTP fallback configuration
 */

// Simple helper to load .env file if present
if (!function_exists('load_custom_env_file')) {
    function load_custom_env_file($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!isset($_ENV[$key]) && getenv($key) === false) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
}

// Attempt to load .env from project root
$envPath = dirname(__DIR__) . '/.env';
load_custom_env_file($envPath);

// Default configuration with Gmail SMTP settings
$mailConfig = [
    'smtp_host'     => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'smtp_port'     => (int)(getenv('MAIL_PORT') ?: 587),
    'smtp_secure'   => strtolower(getenv('MAIL_ENCRYPTION') ?: 'tls'), // 'tls' (STARTTLS) or 'ssl'
    'smtp_auth'     => filter_var(getenv('MAIL_AUTH') ?: true, FILTER_VALIDATE_BOOLEAN),
    'smtp_username' => getenv('MAIL_USERNAME') ?: '',
    'smtp_password' => getenv('MAIL_PASSWORD') ?: '',
    'from_email'    => getenv('MAIL_FROM_ADDRESS') ?: (getenv('MAIL_USERNAME') ?: 'bloodcommunicationsystem@gmail.com'),
    'from_name'     => getenv('MAIL_FROM_NAME') ?: 'BloodLife Donation System',
    'debug'         => filter_var(getenv('MAIL_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
];

// Merge local overrides if config/mail.local.php exists
$localConfigPath = __DIR__ . '/mail.local.php';
if (file_exists($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $mailConfig = array_merge($mailConfig, $localConfig);
    }
}

return $mailConfig;
