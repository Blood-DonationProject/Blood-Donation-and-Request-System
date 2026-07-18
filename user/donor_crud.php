<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: donateform.php');
    exit;
}

$gender = trim($_POST['gender'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$age = intval($_POST['age'] ?? 0);
$phone = trim($_POST['contact'] ?? '');
$address = trim($_POST['address'] ?? '');
$blood_groups = trim($_POST['blood_groups'] ?? '');
$weight = floatval($_POST['weight'] ?? 0);
$last_donation_date = trim($_POST['last_donation_date'] ?? '') ?: null;

// Auto-set status: Available if no last donation date or 3+ months since last donation
if ($last_donation_date) {
    $lastDonated = new DateTime($last_donation_date);
    $threeMonthsAgo = (new DateTime())->modify('-3 months');
    $available_status = ($lastDonated <= $threeMonthsAgo) ? 'Available' : 'Unavailable';
} else {
    $available_status = 'Available';
}
$updateId = intval($_POST['update_id'] ?? 0);

if (!$isLoggedIn) {
    $message = 'You must be logged in to register as a donor.';
    $messageType = 'error';
} elseif (empty($gender)) {
    $message = 'Please select a gender.';
    $messageType = 'error';
} elseif ($age < 18) {
    $message = 'You must be at least 18 years old to donate.';
    $messageType = 'error';
} elseif ($phone === '') {
    $message = 'Please enter a contact number.';
    $messageType = 'error';
} elseif ($address === '') {
    $message = 'Please enter your address.';
    $messageType = 'error';
} elseif (empty($blood_groups)) {
    $message = 'Please select a blood type.';
    $messageType = 'error';
} elseif ($weight < 100) {
    $message = 'Weight must be at least 100 lb.';
    $messageType = 'error';
} else {
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$userId) {
        $message = 'You must be logged in to register as a donor.';
        $messageType = 'error';
    } else {
        try {
            if ($updateId > 0) {
                $stmt = $conn->prepare("UPDATE donor SET gender=?, date_of_birth=?, age=?, blood_groups=?, phone=?, address=?, weight=?, last_donation_date=?, available_status=? WHERE id=? AND user_id=?");
                $stmt->bind_param("ssisssdssii", $gender, $date_of_birth, $age, $blood_groups, $phone, $address, $weight, $last_donation_date, $available_status, $updateId, $userId);
            } else {
                $stmt = $conn->prepare("INSERT INTO donor (user_id, gender, date_of_birth, age, blood_groups, phone, address, weight, last_donation_date, available_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ississsdss", $userId, $gender, $date_of_birth, $age, $blood_groups, $phone, $address, $weight, $last_donation_date, $available_status);
            }

            if ($stmt->execute()) {
                $donorId = $updateId > 0 ? $updateId : $conn->insert_id;

                // Create donation_history record
                $bgStmt = $conn->prepare("SELECT id FROM blood_groups WHERE blood_gp_name = ?");
                $bgStmt->bind_param("s", $blood_groups);
                $bgStmt->execute();
                $bgResult = $bgStmt->get_result()->fetch_assoc();
                $bgStmt->close();
                $blood_groups_id = $bgResult ? $bgResult['id'] : 0;
                $donationDate = $last_donation_date ?: date('Y-m-d');

                $dhStmt = $conn->prepare("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, donation_date, units, status) VALUES (?, ?, 0, ?, ?, 1, 'Completed')");
                $dhStmt->bind_param("iiis", $donorId, $userId, $blood_groups_id, $donationDate);
                $dhStmt->execute();
                $dhStmt->close();

                $message = $updateId > 0 ? 'Donor record updated successfully!' : 'Donor registration submitted successfully! Your status is pending approval.';
                $messageType = 'success';
            } else {
                $message = 'Failed to save registration. Please try again.';
                $messageType = 'error';
            }
            $stmt->close();
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again later.';
            $messageType = 'error';
        }
    }
}

if ($messageType === 'success') {
    $redirect = $updateId > 0 ? 'donateform.php?msg=updated' : 'donateform.php?msg=created';
} else {
    $redirect = 'donateform.php?msg=error&text=' . urlencode($message);
}
header('Location: ' . $redirect);
exit;
