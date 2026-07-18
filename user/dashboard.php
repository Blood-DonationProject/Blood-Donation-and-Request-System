<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['user_role'] ?? '';

if ($role === 'Admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

header('Location: profile.php');
exit;
