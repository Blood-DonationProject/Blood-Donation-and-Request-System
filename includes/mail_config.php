<?php
/**
 * Mail Configuration Helper
 * Provides a clean interface to retrieve mail settings.
 */

if (!function_exists('get_mail_configuration')) {
    function get_mail_configuration() {
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
}

return get_mail_configuration();
