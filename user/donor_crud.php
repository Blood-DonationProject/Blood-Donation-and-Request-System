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
        // Check if user already has a donor record (prevent duplicate registration)
        if ($updateId <= 0) {
            $checkStmt = $conn->prepare("SELECT id, available_status, last_donation_date FROM donor WHERE user_id = ? LIMIT 1");
            $checkStmt->bind_param("i", $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult && $checkResult->num_rows > 0) {
                $existingDonor = $checkResult->fetch_assoc();
                $checkStmt->close();

                // Auto-update status based on 3-month cooldown
                if ($existingDonor['last_donation_date']) {
                    $lastDonated = new DateTime($existingDonor['last_donation_date']);
                    $threeMonthsLater = (clone $lastDonated)->modify('+3 months');
                    if ($threeMonthsLater <= new DateTime()) {
                        // Cooldown passed, auto-set to Available
                        $updateAvail = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
                        $updateAvail->bind_param("i", $existingDonor['id']);
                        $updateAvail->execute();
                        $updateAvail->close();
                    } else {
                        $remainingDays = (new DateTime())->diff($threeMonthsLater)->days;
                        $message = "You are currently Unavailable. You can register again in {$remainingDays} days (after 3 months from your last donation).";
                        $messageType = 'error';
                        $redirect = 'donateform.php?msg=error&text=' . urlencode($message);
                        header('Location: ' . $redirect);
                        exit;
                    }
                } else {
                    $message = 'You already have a donor record. You can edit your existing record instead.';
                    $messageType = 'error';
                    $redirect = 'donateform.php?msg=error&text=' . urlencode($message);
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $checkStmt->close();
            }
        }

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
