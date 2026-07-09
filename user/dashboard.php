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

if ($role === 'Donor') {
    header('Location: donordashboard.php');
    exit;
}

if ($role === 'Requester') {
    header('Location: requester.php');
    exit;
}

header('Location: donordashboard.php');
exit;
