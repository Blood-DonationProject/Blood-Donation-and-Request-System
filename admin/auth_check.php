<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../user/login.php');
    exit;
}

// Check if user has Admin role
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    // Non-admin user trying to access admin area
    $_SESSION['access_denied'] = 'Access Denied. You do not have administrator privileges.';
    header('Location: ../user/login.php?access_denied=1');
    exit;
}

