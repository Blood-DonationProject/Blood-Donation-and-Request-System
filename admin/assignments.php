<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/notification_helper.php';
$error = '';
$success = '';



// Assign donor action
if (isset($_POST['assign_donor'])) {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

    function respond_assignment($status, $message, $is_ajax)
    {
        if ($is_ajax) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => $status, 'message' => $message]);
            exit;
        } else {
            if ($status === 'error') {
                $_SESSION['error'] = $message;
            } else {
                $_SESSION['success'] = $message;
            }
            header('Location: assignments.php');
            exit;
        }
    }

    $request_id = (int)$_POST['request_id'];
    $donor_id = (int)$_POST['donor_id'];

    if ($request_id > 0 && $donor_id > 0) {
        // Verify the request exists and is in a valid state
        $check = $conn->prepare("SELECT br.id, br.users_id, br.status, br.assigned_donor_id, br.hospital, br.required_date, bg.blood_gp_name AS blood_group_name, u.email AS requester_email, u.username AS requester_name FROM blood_request br LEFT JOIN users u ON br.users_id = u.id LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id WHERE br.id = ?");
        $check->bind_param("i", $request_id);
        $check->execute();
        $result = $check->get_result();
        if ($result && $result->num_rows > 0) {
            $req = $result->fetch_assoc();
            if ($req['status'] === 'Expired' || strtotime($req['required_date']) < strtotime('today')) {
                respond_assignment('error', 'Cannot assign donor: this blood request has expired.', $is_ajax);
            } else if (in_array($req['status'], ['Pending', 'Approved', 'Assigned', 'Accepted', 'Rejected'])) {
                // Verify donor exists and is available
                $donor_check = $conn->prepare("SELECT id, available_status, blood_groups, user_id FROM donor WHERE id = ?");
                $donor_check->bind_param("i", $donor_id);
                $donor_check->execute();
                $donor_result = $donor_check->get_result();
                if ($donor_result && $donor_result->num_rows > 0) {
                    $donor = $donor_result->fetch_assoc();

                    // Server-side validation for exact blood group match
                    if ($donor['blood_groups'] !== $req['blood_group_name']) {
                        respond_assignment('error', 'Mismatched blood type. Assignment aborted.', $is_ajax);
                    }
                    // Server-side validation for self-assignment
                    else if (isset($req['users_id']) && isset($donor['user_id']) && $req['users_id'] == $donor['user_id']) {
                        respond_assignment('error', 'The requester cannot be assigned as a donor for their own blood request.', $is_ajax);
                    } else if ($donor['available_status'] === 'Available') {
                        // Check if this donor already has an active assignment anywhere
                        $activeDonorCheck = $conn->prepare("SELECT COUNT(*) FROM donor_assignments WHERE donor_id = ? AND status IN ('Assigned', 'Accepted')");
                        $activeDonorCheck->bind_param("i", $donor_id);
                        $activeDonorCheck->execute();
                        $hasActiveAssignment = $activeDonorCheck->get_result()->fetch_row()[0] > 0;
                        $activeDonorCheck->close();

                        // Check for duplicate active assignment for this request
                        $dupAssignCheck = $conn->prepare("SELECT COUNT(*) FROM donor_assignments WHERE request_id = ? AND donor_id = ? AND status NOT IN ('Cancelled', 'Rejected')");
                        $dupAssignCheck->bind_param("ii", $request_id, $donor_id);
                        $dupAssignCheck->execute();
                        $isDuplicate = $dupAssignCheck->get_result()->fetch_row()[0] > 0;
                        $dupAssignCheck->close();

                        $assignedCheck = $conn->prepare("SELECT COUNT(*) FROM donor_assignments WHERE request_id = ? AND status NOT IN ('Cancelled', 'Rejected')");
                        $assignedCheck->bind_param("i", $request_id);
                        $assignedCheck->execute();
                        $assignedCount = $assignedCheck->get_result()->fetch_row()[0];
                        $assignedCheck->close();

                        if ($hasActiveAssignment) {
                            respond_assignment('error', 'This donor already has an active assignment for another request.', $is_ajax);
                        } else if ($isDuplicate) {
                            respond_assignment('error', 'This donor is already assigned to this request.', $is_ajax);
                        } else if ($assignedCount >= 1) {
                            respond_assignment('error', 'This request already has a donor assigned.', $is_ajax);
                        } else {
                            // Assign donor and update status to Assigned
                            $assign = $conn->prepare("UPDATE blood_request SET assigned_donor_id = ?, status = 'Assigned' WHERE id = ?");
                            $assign->bind_param("ii", $donor_id, $request_id);
                            if ($assign->execute()) {
                                // Mark donor as Unavailable after assignment
                                $donorUpdate = $conn->prepare("UPDATE donor SET available_status = 'Unavailable' WHERE id = ?");
                                $donorUpdate->bind_param("i", $donor_id);
                                $donorUpdate->execute();
                                $donorUpdate->close();

                                // Create donor_assignments record
                                $assign_admin_id = $_SESSION['user_id'] ?? 1; // get admin user id
                                $assignStmt = $conn->prepare("INSERT INTO donor_assignments (request_id, donor_id, assigned_by, status) VALUES (?, ?, ?, 'Assigned')");
                                $assignStmt->bind_param("iii", $request_id, $donor_id, $assign_admin_id);
                                $assignStmt->execute();
                                $assignment_id = $conn->insert_id;
                                $assignStmt->close();

                                // Get donor's user_id and email for notification
                                $donorUser = $conn->prepare("SELECT d.user_id, u.email AS donor_email, u.username FROM donor d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
                                $donorUser->bind_param("i", $donor_id);
                                $donorUser->execute();
                                $donorUserRow = $donorUser->get_result()->fetch_assoc();
                                $donorUser->close();

                                $donorEmailRes = null;
                                if ($donorUserRow) {
                                    $notifMsg = "You have been assigned as a donor for a blood request. Blood Group: " . $req['blood_group_name'] . " | Requester/Hospital: " . $req['hospital'] . " | Required Date: " . $req['required_date'];
                                    $notifType = 'Assignment';
                                    $notifTitle = 'New Blood Request Assignment';
                                    $donorNotifId = create_notification($conn, $donorUserRow['user_id'], $notifType, $notifTitle, $notifMsg, $request_id, $assignment_id, $donor_id);

                                    // Fail-safe decoupled email notification to donor
                                    require_once __DIR__ . '/../includes/mailer.php';
                                    $donorEmailRes = send_donor_assignment_email($donorUserRow['user_id'], [
                                        'id'             => $request_id,
                                        'blood_group'    => $req['blood_group_name'],
                                        'hospital'       => $req['hospital'],
                                        'units'          => $req['units'] ?? 1,
                                        'required_date'  => $req['required_date'],
                                        'requester_name' => $req['requester_name'] ?? 'Patient'
                                    ], $assignment_id, $donorNotifId);
                                }

                                // Send notification to Requester
                                $reqEmailRes = null;
                                if (!empty($req['users_id'])) {
                                    $reqNotifMsg = "Good news! A donor (" . ($donorUserRow['username'] ?? 'matched volunteer') . ") has been assigned to your blood request #" . $request_id . ".";
                                    $reqNotifType = 'Assignment';
                                    $reqNotifTitle = 'Donor Assigned';
                                    $reqNotifId = create_notification($conn, $req['users_id'], $reqNotifType, $reqNotifTitle, $reqNotifMsg, $request_id, $assignment_id, $donor_id);

                                    // Fail-safe decoupled email notification to requester
                                    require_once __DIR__ . '/../includes/mailer.php';
                                    $reqEmailRes = send_requester_donor_assigned_email($req['users_id'], [
                                        'id'       => $request_id,
                                        'hospital' => $req['hospital']
                                    ], $donorUserRow['username'] ?? 'Volunteer Donor', $reqNotifId);
                                }

                                // Create confirmation notification for Admin
                                require_once __DIR__ . '/../includes/notification_helper.php';
                                $donorName = $donorUserRow['username'] ?? 'Donor #' . $donor_id;
                                $adminConfirmMsg = "Donor \"{$donorName}\" was successfully assigned to Request #{$request_id} ({$req['hospital']}).";
                                notify_admins($conn, 'Assignment', 'Donor assigned successfully', $adminConfirmMsg, $request_id, $assignment_id, $donor_id);

                                $emailSuccess = (!empty($donorEmailRes['success']) && ($reqEmailRes === null || !empty($reqEmailRes['success'])));
                                $feedbackMsg = format_action_feedback('Donor assigned', $emailSuccess);

                                respond_assignment('success', $feedbackMsg, $is_ajax);
                            } else {
                                respond_assignment('error', 'Error assigning donor: ' . $conn->error, $is_ajax);
                            }
                            $assign->close();
                        }
                    } else {
                        respond_assignment('error', 'Selected donor is not available.', $is_ajax);
                    }
                } else {
                    respond_assignment('error', 'Donor not found.', $is_ajax);
                }
                $donor_check->close();
            } else {
                respond_assignment('error', 'Request is already assigned or cannot be assigned (status: ' . htmlspecialchars($req['status']) . ').', $is_ajax);
            }
        } else {
            respond_assignment('error', 'Blood request not found.', $is_ajax);
        }
        $check->close();
    } else {
        respond_assignment('error', 'Please select both a blood request and a donor.', $is_ajax);
    }
}


// Unassign donor action
if (isset($_GET['unassign'])) {
    $assignment_id = (int)$_GET['unassign'];

    // Get donor_id, request_id, and status from assignment
    $getDonor = $conn->prepare("SELECT donor_id, request_id, status FROM donor_assignments WHERE id = ?");
    $getDonor->bind_param("i", $assignment_id);
    $getDonor->execute();
    $donorRow = $getDonor->get_result()->fetch_assoc();
    $getDonor->close();

    // Allow Unassign if the assignment status is active ('Assigned', 'Accepted', 'Pending')
    if ($donorRow && in_array($donorRow['status'], ['Assigned', 'Accepted', 'Pending'])) {
        $donor_id = $donorRow['donor_id'];
        $req_id = $donorRow['request_id'];

        $stmt = $conn->prepare("UPDATE donor_assignments SET status = 'Cancelled' WHERE id = ?");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $stmt->close();

        // Restore donor availability
        $restoreDonor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
        $restoreDonor->bind_param("i", $donor_id);
        $restoreDonor->execute();
        $restoreDonor->close();

        // Clear assigned_donor_id from blood_request and set back to Pending
        $clearAssig = $conn->prepare("UPDATE blood_request SET assigned_donor_id = NULL, status = 'Pending' WHERE id = ?");
        $clearAssig->bind_param("i", $req_id);
        $clearAssig->execute();
        $clearAssig->close();
    }

    header('Location: assignments.php');
    exit;
}

// Fetch assignable requests (Pending or Approved without donor and not expired)
$assignable_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.users_id, r.requester_name, bg.blood_gp_name AS blood_group, bg.id AS blood_groups_id,
               r.units, r.hospital, r.required_date, r.status, r.assigned_donor_id, r.urgency,
               0 as assigned_units,
               (SELECT GROUP_CONCAT(DISTINCT donor_id) FROM donor_assignments WHERE request_id = r.id AND status = 'Rejected') AS rejected_donors,
               (SELECT GROUP_CONCAT(DISTINCT donor_id) FROM donor_assignments WHERE request_id = r.id AND status = 'Cancelled') AS unassigned_donors
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        WHERE r.status IN ('Pending', 'Approved') AND r.assigned_donor_id IS NULL AND r.required_date >= CURDATE()
        ORDER BY CASE WHEN r.urgency = 'Urgent' THEN 1 ELSE 2 END ASC, r.required_date ASC, r.id ASC
    ");
    if ($result && $result->num_rows > 0) {
        $assignable_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}


// Fetch available donors
$available_donors = [];
try {
    $result = $conn->query("
        SELECT d.id, d.user_id, d.blood_groups, d.phone, d.weight, d.age, d.available_status, d.address,
               d.last_donation_date, u.username
        FROM donor d
        JOIN users u ON d.user_id = u.id
        WHERE d.available_status = 'Available'
          AND u.status = 'Active'
          AND (d.last_donation_date IS NULL OR DATEDIFF(CURRENT_DATE, d.last_donation_date) >= 90)
          AND NOT EXISTS (
              SELECT 1 FROM donor_assignments da 
              WHERE da.donor_id = d.id AND da.status IN ('Assigned', 'Accepted')
          )
        ORDER BY d.blood_groups ASC, u.username ASC
    ");
    if ($result && $result->num_rows > 0) {
        $available_donors = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}


// Fetch already assigned requests for display (non-expired)
$assigned_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.id AS request_id, r.requester_name, bg.blood_gp_name AS blood_group, r.units,
               r.hospital, r.required_date, r.status AS request_status,
               u.username AS donor_name, d.blood_groups AS donor_blood_group, d.phone AS donor_phone,
               da.status AS assignment_status, da.id AS assignment_id, 
               da.created_at AS assigned_date, da.responded_at, da.completed_at
        FROM donor_assignments da
        JOIN (
            SELECT MAX(id) AS max_id
            FROM donor_assignments
            WHERE status IN ('Assigned', 'Accepted', 'Received', 'Pending')
            GROUP BY request_id
        ) latest_da ON da.id = latest_da.max_id
        JOIN blood_request r ON da.request_id = r.id
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        JOIN donor d ON da.donor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
        ORDER BY da.created_at DESC
    ");
    if ($result && $result->num_rows > 0) {
        $assigned_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}



// Handle mark as completed
if (isset($_GET['complete_assignment'])) {
    $id = (int)$_GET['complete_assignment'];

    // Verify status is Received
    $check = $conn->prepare("SELECT request_id, donor_id, status FROM donor_assignments WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $resCheck = $check->get_result();
    if ($resCheck && $resCheck->num_rows > 0) {
        $row = $resCheck->fetch_assoc();
        if ($row['status'] === 'Received') {
            $req_id = $row['request_id'];

            // Update assignment status
            $stmt = $conn->prepare("UPDATE donor_assignments SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Assignment marked as completed.";
                // Update blood request status
                $conn->query("UPDATE blood_request SET status = 'Completed' WHERE id = $req_id");

                // Update donor available_status
                $donor_id = $row['donor_id'];
                $conn->query("UPDATE donor SET available_status = 'Available' WHERE id = $donor_id");

                // Add to donation history
                $bg_query = $conn->query("SELECT blood_groups_id FROM blood_request WHERE id = $req_id");
                if ($bg_query && $bg_query->num_rows > 0) {
                    $bg_row = $bg_query->fetch_assoc();
                    $bg_id = $bg_row['blood_groups_id'];
                    $admin_id = $_SESSION['user_id'] ?? 0;
                    $conn->query("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, units, donation_date, status) VALUES ($donor_id, $admin_id, $req_id, $bg_id, 1, CURRENT_DATE(), 'Completed')");
                }
            } else {
                $error = "Failed to update assignment: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Only assignments in 'Received' status can be marked as completed.";
        }
    }
    $check->close();

    // Redirect to clear URL
    header("Location: assignments.php");
    exit;
}

// Stats
$stats = ['active' => 0, 'accepted' => 0, 'rejected' => 0, 'completed' => 0];
$stats_query = $conn->query("
    SELECT da.status, COUNT(*) as count 
    FROM donor_assignments da
    JOIN (
        SELECT MAX(id) AS max_id
        FROM donor_assignments
        GROUP BY request_id
    ) latest_da ON da.id = latest_da.max_id
    GROUP BY da.status
");
if ($stats_query) {
    while ($row = $stats_query->fetch_assoc()) {
        $st = $row['status'];
        if ($st === 'Assigned') $stats['active'] += $row['count'];
        else if ($st === 'Accepted') $stats['accepted'] += $row['count'];
        else if ($st === 'Rejected') $stats['rejected'] += $row['count'];
        else if ($st === 'Completed' || $st === 'Received') $stats['completed'] += $row['count'];
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Assignments - BloodLife Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#dc2626',
                        secondary: '#991b1b'
                    }
                }
            }
        }
    </script>
    <style>
        .timeline-line {
            position: absolute;
            left: 15px;
            top: 30px;
            bottom: 0;
            width: 2px;
            background-color: #e5e7eb;
            z-index: 0;
        }

        html.dark .timeline-line {
            background-color: #374151;
        }

        .timeline-dot {
            position: relative;
            z-index: 1;
        }

        .assign-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-center: center;
            z-index: 50;
        }

        .assign-modal-overlay.active {
            display: flex;
        }

        .assign-modal {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 1.5rem;
        }

        .assign-modal-donor {
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
        }

        .assign-modal-donor:hover {
            border-color: #3b82f6;
        }

        .assign-modal-donor.selected {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .assign-modal-donor.best-match {
            border-color: #22c55e;
        }

        .assign-modal-donor.best-match.selected {
            border-color: #16a34a;
            background: #f0fdf4;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Navbar -->
            <?php include __DIR__ . '/../includes/navbar.php'; ?>
            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto p-6">

                <!-- Notifications -->
                <?php if ($success): ?>
                    <div class="mb-6 p-4 rounded-lg bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800">
                        <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>



                <!-- Donor Assignment Section -->
                <div class="mb-8">


                    <?php if (count($assignable_requests) > 0): ?>
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Requests List -->
                            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                                <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                                    <span class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-lg flex items-center justify-center mr-2">
                                        <i class="fas fa-file-medical text-sm"></i>
                                    </span>
                                    Requests Awaiting Assignment
                                    <span class="ml-auto bg-yellow-100 text-yellow-700 text-xs font-bold px-2.5 py-1 rounded-full"><?= count($assignable_requests) ?></span>
                                </h4>
                                <div class="space-y-3 max-h-96 overflow-y-auto" id="requestList">
                                    <?php foreach ($assignable_requests as $ar): ?>
                                        <div class="request-item p-4 rounded-xl border-2 border-gray-200 transition group"
                                            data-id="<?= $ar['id'] ?>"
                                            data-users-id="<?= $ar['users_id'] ?>"
                                            data-blood-group="<?= htmlspecialchars($ar['blood_group']) ?>"
                                            data-blood-groups-id="<?= (int)$ar['blood_groups_id'] ?>"
                                            data-units="<?= (int)$ar['units'] ?>"
                                            data-assigned-units="<?= (int)$ar['assigned_units'] ?>"
                                            data-hospital="<?= htmlspecialchars($ar['hospital']) ?>"
                                            data-rejected-donors="<?= htmlspecialchars($ar['rejected_donors'] ?? '') ?>"
                                            data-unassigned-donors="<?= htmlspecialchars($ar['unassigned_donors'] ?? '') ?>">
                                            <div class="flex items-start justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="relative w-11 h-11 bg-red-600 text-white rounded-xl flex items-center justify-center font-bold text-sm">
                                                        <?= strtoupper(substr($ar['blood_group'], 0, 2)) ?>
                                                        <?php if (($ar['urgency'] ?? '') === 'Urgent'): ?>
                                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 border-2 border-white rounded-full animate-pulse"></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($ar['blood_group']) ?> - 1 Unit</p>
                                                            <?php if (($ar['urgency'] ?? '') === 'Urgent'): ?>
                                                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-red-200">URGENT</span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <p class="text-[10px] text-gray-400 mt-0.5">Request #<?= $ar['id'] ?> - <?= htmlspecialchars($ar['requester_name'] ?? 'Unknown') ?></p>
                                                    </div>
                                                </div>
                                                <div class="flex flex-col items-end space-y-2">
                                                    <span class="text-xs font-semibold <?= $ar['status'] === 'Pending' ? 'text-yellow-600 bg-yellow-50' : 'text-blue-600 bg-blue-50' ?> px-2.5 py-1 rounded-full">
                                                        <?= htmlspecialchars($ar['status']) ?>
                                                    </span>
                                                    <div class="flex items-center space-x-2">
                                                        <a href="blood_requests_crud.php?view=<?= $ar['id'] ?>" class="text-gray-400 hover:text-blue-500 transition px-2 py-1" title="View Request">
                                                            <i class="fas fa-eye text-sm"></i> View
                                                        </a>
                                                        <button type="button" onclick="openAssignModal(<?= $ar['id'] ?>)" class="px-3 py-1 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-xs font-semibold transition border border-blue-200" title="Assign Donor">
                                                            Assign Donor
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex items-center text-xs text-gray-500 space-x-4">
                                                <span><i class="fas fa-hospital mr-1"></i><?= htmlspecialchars($ar['hospital']) ?></span>
                                                <span><i class="fas fa-calendar mr-1"></i><?= htmlspecialchars($ar['required_date']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                            </div>
                            <p class="text-gray-600 font-semibold">All requests have been assigned</p>
                            <p class="text-gray-400 text-sm mt-1">No pending requests awaiting donor assignment</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Donors Summary -->
                <?php if (count($assigned_requests) > 0): ?>
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    <i class="fas fa-clipboard-check text-green-500 mr-2"></i>Active Assignments
                                </h3>
                                <p class="text-sm text-gray-400 mt-1">Requests with assigned donors</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-100">
                                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Request</th>
                                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Blood Type</th>
                                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Hospital</th>
                                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Assigned Donor</th>
                                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Donor Phone</th>
                                            <th class="px-5 py-3 text-left font-semibold text-gray-600">Status</th>
                                            <th class="px-5 py-3 text-center font-semibold text-gray-600">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assigned_requests as $asr): ?>
                                            <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                                                <td class="px-5 py-3">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="font-bold text-gray-900 hidden">#<?= $asr['id'] ?></span>
                                                        <span class="text-gray-400 hidden">-</span>
                                                        <span class="text-gray-600"><?= htmlspecialchars($asr['requester_name'] ?? 'Unknown') ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-3">
                                                    <span class="bg-red-100 text-red-700 font-bold px-2.5 py-1 rounded-full text-xs"><?= htmlspecialchars($asr['blood_group']) ?></span>
                                                </td>
                                                <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($asr['hospital']) ?></td>
                                                <td class="px-5 py-3">
                                                    <div class="flex items-center space-x-2">
                                                        <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center font-bold text-xs">
                                                            <?= strtoupper(substr($asr['donor_name'] ?? 'U', 0, 2)) ?>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($asr['donor_name'] ?? '-') ?></p>
                                                            <p class="text-xs text-gray-400"><?= htmlspecialchars($asr['donor_blood_group'] ?? '') ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($asr['donor_phone'] ?? '-') ?></td>
                                                <td class="px-5 py-3">
                                                    <?php
                                                    $badgeColor = 'bg-gray-100 text-gray-600';
                                                    $statusDisplay = $asr['assignment_status'] ?? $asr['request_status'] ?? 'Unknown';
                                                    if ($statusDisplay === 'Accepted') $badgeColor = 'bg-blue-100 text-blue-700';
                                                    elseif ($statusDisplay === 'Rejected') $badgeColor = 'bg-red-100 text-red-700';
                                                    elseif ($statusDisplay === 'Received') $badgeColor = 'bg-yellow-100 text-yellow-700';
                                                    elseif ($statusDisplay === 'Completed') $badgeColor = 'bg-green-100 text-green-700';
                                                    elseif ($statusDisplay === 'Assigned') $badgeColor = 'bg-blue-100 text-blue-700';
                                                    elseif ($statusDisplay === 'Expired') $badgeColor = 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                                    ?>
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $badgeColor ?>"><?= htmlspecialchars($statusDisplay) ?></span>
                                                </td>
                                                <td class="px-5 py-3 text-center space-x-3">
                                                    <!-- View Button -->
                                                    <button onclick='openTimelineModal(<?= json_encode($asr, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-3 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 rounded-lg transition shadow-sm" title="View Timeline">
                                                        View
                                                    </button>

                                                    <?php if ($statusDisplay === 'Assigned'): ?>
                                                        <a href="assignments.php?unassign=<?= $asr['assignment_id'] ?>" onclick="return confirm('Remove this donor assignment?')" class="text-red-500 hover:text-red-700 transition" title="Unassign">
                                                            <i class="fas fa-user-minus"></i>
                                                        </a>
                                                    <?php elseif ($statusDisplay === 'Received'): ?>
                                                        <!-- Mark as Completed -->
                                                        <a href="assignments.php?complete_assignment=<?= $asr['assignment_id'] ?>" onclick="return confirm('Mark this assignment as completed?')" class="text-emerald-500 hover:text-emerald-700 transition" title="Mark Completed">
                                                            <i class="fas fa-check-double"></i>
                                                        </a>
                                                    <?php elseif ($statusDisplay === 'Pending'): ?>
                                                        <button onclick="openAssignModal(<?= $asr['request_id'] ?? $asr['id'] ?>, false, '<?= $asr['blood_group'] ?>', <?= $asr['units'] ?? 1 ?>, '<?= addslashes($asr['hospital']) ?>')" class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition shadow-sm" title="Assign Donor">
                                                            Assign Donor
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>


            </main>
        </div>
    </div>

    <!-- Assign Donor Modal -->
    <div id="assignModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4" onclick="if(event.target===this)closeAssignModal()">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden animate-fade-up">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-lg">Assign Donors</h3>
                        <p class="text-xs text-gray-500" id="modalRequestInfo"></p>
                    </div>
                </div>
                <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600 p-2 transition"><i class="fas fa-times text-lg"></i></button>
            </div>
            <div class="p-5 bg-gray-50/30 max-h-[500px] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-bold text-gray-700 text-sm">Recommended Donors</h4>
                </div>
                <!-- Donor Info Block -->
                <div id="modalBestDonorDetails" class="hidden space-y-3">
                    <!-- Injected by JS -->
                </div>

                <div id="modalNoDonors" class="hidden text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 text-2xl mx-auto mb-3"><i class="fas fa-user-slash"></i></div>
                    <p class="text-gray-500 font-medium">No suitable donor available.</p>
                </div>
            </div>

            <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 rounded-b-3xl">
                <button onclick="closeAssignModal()" class="px-5 py-2.5 rounded-xl font-bold text-gray-600 border border-gray-200 bg-white hover:bg-gray-100 transition">Close</button>
            </div>
        </div>
    </div>


    <div id="assignConfirmModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
            <div class="p-8 text-center space-y-6">
                <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 mb-2" id="assignConfirmTitle">Assign Donor</h2>
                    <p class="text-gray-500" id="assignConfirmMessage">Are you sure you want to assign this donor to this request?</p>
                </div>
            </div>
            <div class="px-8 pb-8 flex gap-3">
                <button onclick="closeAssignConfirmModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
                <button onclick="executeModalAssign()" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition text-center shadow-md">Confirm Assignment</button>
            </div>
        </div>
    </div>


    <!-- Timeline Modal -->
    <div id="timelineModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md mx-4 overflow-hidden shadow-2xl transform transition-all">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Assignment Timeline</h3>
                <button onclick="closeTimelineModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 relative">
                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Requester: <span id="tm-requester" class="font-semibold text-gray-900 dark:text-white"></span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Donor: <span id="tm-donor" class="font-semibold text-gray-900 dark:text-white"></span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Request ID: <span id="tm-reqid" class="font-semibold text-gray-900 dark:text-white"></span></p>
                </div>

                <div class="relative pl-8 space-y-6" id="timeline-container">
                    <div class="timeline-line"></div>
                    <div class="relative">
                        <div class="timeline-dot absolute -left-8 w-6 h-6 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center text-xs border-2 border-white dark:border-gray-800"><i class="fas fa-check"></i></div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">Assigned</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400" id="tm-date-assigned"></p>
                    </div>
                    <div class="relative" id="step-response">
                        <div class="timeline-dot absolute -left-8 w-6 h-6 bg-gray-100 text-gray-400 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs border-2 border-white dark:border-gray-800" id="dot-response"><i class="fas fa-circle" style="font-size: 8px;"></i></div>
                        <h4 class="text-sm font-bold text-gray-500 dark:text-gray-400" id="title-response">Pending Response</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400" id="tm-date-response"></p>
                    </div>
                    <div class="relative" id="step-received">
                        <div class="timeline-dot absolute -left-8 w-6 h-6 bg-gray-100 text-gray-400 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs border-2 border-white dark:border-gray-800" id="dot-received"><i class="fas fa-circle" style="font-size: 8px;"></i></div>
                        <h4 class="text-sm font-bold text-gray-500 dark:text-gray-400" id="title-received">Blood Received</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400" id="tm-date-received"></p>
                    </div>
                    <div class="relative" id="step-completed">
                        <div class="timeline-dot absolute -left-8 w-6 h-6 bg-gray-100 text-gray-400 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs border-2 border-white dark:border-gray-800" id="dot-completed"><i class="fas fa-circle" style="font-size: 8px;"></i></div>
                        <h4 class="text-sm font-bold text-gray-500 dark:text-gray-400" id="title-completed">Completed</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400" id="tm-date-completed"></p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 text-right rounded-b-2xl">
                <button onclick="closeTimelineModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-800 dark:text-white rounded-lg text-sm font-semibold transition">Close</button>
            </div>
        </div>
    </div>

    <script>
        const themeToggleBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.getItem('bloodlife-theme') === 'dark' || (!('bloodlife-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        }
        themeToggleBtn.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.setItem('bloodlife-theme', html.classList.contains('dark') ? 'dark' : 'light');
        });

        function formatDate(dateString) {
            if (!dateString) return '';
            const d = new Date(dateString);
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function setStepActive(dotId, titleId, titleText, colorClass, textClass, iconClass) {
            const dot = document.getElementById(dotId);
            const title = document.getElementById(titleId);
            dot.className = `timeline-dot absolute -left-8 w-6 h-6 ${colorClass} ${textClass} rounded-full flex items-center justify-center text-xs border-2 border-white dark:border-gray-800`;
            dot.innerHTML = `<i class="${iconClass}"></i>`;
            title.className = `text-sm font-bold text-gray-900 dark:text-white`;
            title.innerText = titleText;
        }

        function setStepInactive(dotId, titleId, titleText) {
            const dot = document.getElementById(dotId);
            const title = document.getElementById(titleId);
            dot.className = `timeline-dot absolute -left-8 w-6 h-6 bg-gray-100 text-gray-400 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs border-2 border-white dark:border-gray-800`;
            dot.innerHTML = `<i class="fas fa-circle" style="font-size: 8px;"></i>`;
            title.className = `text-sm font-bold text-gray-500 dark:text-gray-400`;
            title.innerText = titleText;
        }

        function openTimelineModal(assignment) {
            document.getElementById('tm-requester').innerText = assignment.requester_name || 'N/A';
            document.getElementById('tm-donor').innerText = assignment.donor_name || 'N/A';
            document.getElementById('tm-reqid').innerText = '#' + assignment.request_id;

            document.getElementById('tm-date-assigned').innerText = formatDate(assignment.assigned_date);

            setStepInactive('dot-response', 'title-response', 'Pending Response');
            document.getElementById('tm-date-response').innerText = '';
            setStepInactive('dot-received', 'title-received', 'Blood Received');
            document.getElementById('tm-date-received').innerText = '';
            setStepInactive('dot-completed', 'title-completed', 'Completed');
            document.getElementById('tm-date-completed').innerText = '';

            const status = assignment.assignment_status;
            if (status !== 'Assigned') {
                if (status === 'Rejected') {
                    setStepActive('dot-response', 'title-response', 'Rejected', 'bg-red-100 dark:bg-red-900/30', 'text-red-500', 'fas fa-times');
                    document.getElementById('tm-date-response').innerText = formatDate(assignment.responded_at);
                } else {
                    setStepActive('dot-response', 'title-response', 'Accepted', 'bg-blue-100 dark:bg-blue-900/30', 'text-blue-500', 'fas fa-check');
                    document.getElementById('tm-date-response').innerText = formatDate(assignment.responded_at);
                }
            }

            if (status === 'Received' || status === 'Completed') {
                setStepActive('dot-received', 'title-received', 'Blood Received', 'bg-yellow-100 dark:bg-yellow-900/30', 'text-yellow-600', 'fas fa-check');
            }

            if (status === 'Completed') {
                setStepActive('dot-completed', 'title-completed', 'Completed', 'bg-emerald-100 dark:bg-emerald-900/30', 'text-emerald-500', 'fas fa-check-double');
                document.getElementById('tm-date-completed').innerText = formatDate(assignment.completed_at);
            }

            document.getElementById('timelineModal').classList.remove('hidden');
        }

        function closeTimelineModal() {
            document.getElementById('timelineModal').classList.add('hidden');
        }
    </script>
    <script>
        var modalRequestId = null;
        var modalBloodGroup = null;
        var modalRequestHospital = '';
        var modalRequiredUnits = 1;
        var modalAssignedUnits = 0;
        var isReassignMode = false;
        var modalRejectedDonors = [];
        var modalUnassignedDonors = [];
        var allDonors = <?= json_encode($available_donors) ?>;

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openAssignModal(requestId, reassign = false, bGroup = null, bUnits = null, hospital = null) {
            isReassignMode = reassign;
            modalRequestId = requestId;

            var reqInfo = null;
            document.querySelectorAll('.request-item').forEach(function(item) {
                if (item.getAttribute('data-id') == requestId) {
                    reqInfo = {
                        users_id: parseInt(item.getAttribute('data-users-id')) || 0,
                        blood_group: item.getAttribute('data-blood-group'),
                        units: parseInt(item.getAttribute('data-units')) || 1,
                        assigned_units: parseInt(item.getAttribute('data-assigned-units')) || 0,
                        hospital: item.getAttribute('data-hospital'),
                        rejected_donors: item.getAttribute('data-rejected-donors') || '',
                        unassigned_donors: item.getAttribute('data-unassigned-donors') || '',
                        requester_name: item.querySelector('p.font-bold') ? item.querySelector('p.font-bold').innerText : 'Unknown'
                    };
                }
            });

            if (!reqInfo) {
                reqInfo = {
                    users_id: 0,
                    blood_group: bGroup || 'Unknown',
                    units: parseInt(bUnits) || 1,
                    assigned_units: 0,
                    hospital: hospital || '',
                    rejected_donors: '',
                    unassigned_donors: ''
                };
            }
            modalBloodGroup = reqInfo.blood_group;
            modalRequestHospital = reqInfo.hospital;
            modalRequiredUnits = reqInfo.units;
            modalAssignedUnits = reqInfo.assigned_units;
            modalRequesterUserId = reqInfo.users_id;

            document.getElementById('modalRequestInfo').textContent = 'Request #' + requestId;

            modalRejectedDonors = reqInfo.rejected_donors ? reqInfo.rejected_donors.split(',').map(Number) : [];
            modalUnassignedDonors = reqInfo.unassigned_donors ? reqInfo.unassigned_donors.split(',').map(Number) : [];

            renderModalDonors(reqInfo.blood_group);

            document.getElementById('assignModal').classList.add('active');
            document.getElementById('assignModal').classList.remove('hidden');
            document.getElementById('assignModal').classList.add('flex');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
            document.getElementById('assignModal').classList.add('hidden');
            document.getElementById('assignModal').classList.remove('flex');
            modalRequestId = null;

            // Reload page if any donors were assigned to reflect changes in main lists
            if (modalAssignedUnits > 0) {
                window.location.reload();
            }
        }

        var bloodCompatibility = {
            'O-': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
            'O+': ['O+', 'A+', 'B+', 'AB+'],
            'A-': ['A-', 'A+', 'AB-', 'AB+'],
            'A+': ['A+', 'AB+'],
            'B-': ['B-', 'B+', 'AB-', 'AB+'],
            'B+': ['B+', 'AB+'],
            'AB-': ['AB-', 'AB+'],
            'AB+': ['AB+']
        };

        function calculateMatchScore(donor, requestBloodGroup) {
            var score = 0;
            var reasons = [];
            if (donor.blood_groups === requestBloodGroup) {
                score += 40;
                reasons.push('Exact match');
            } else {
                score += 20;
                reasons.push('Compatible');
            }

            var canDonate = true;
            var daysSinceLastDonation = 999;
            if (donor.last_donation_date) {
                var lastDate = new Date(donor.last_donation_date);
                var now = new Date();
                daysSinceLastDonation = Math.floor((now - lastDate) / (1000 * 60 * 60 * 24));
                canDonate = daysSinceLastDonation >= 90;
            }
            if (canDonate) {
                score += 20;
                reasons.push('Ready');
            }
            var timeBonus = Math.min(10, Math.floor(daysSinceLastDonation / 14));
            score += timeBonus;

            if (modalRequestHospital && donor.address) {
                var hospitalLower = modalRequestHospital.toLowerCase();
                var addressLower = donor.address.toLowerCase();
                var words = hospitalLower.split(/[\s,]+/);
                var locationMatched = false;
                for (var i = 0; i < words.length; i++) {
                    if (words[i].length > 3 && addressLower.indexOf(words[i]) !== -1) {
                        locationMatched = true;
                        break;
                    }
                }
                if (locationMatched) {
                    score += 15;
                    reasons.push('Location Match');
                }
            }

            return {
                score: score,
                reasons: reasons,
                canDonate: canDonate,
                daysSince: daysSinceLastDonation
            };
        }

        function assignDonorAjax(donorId, btnElement) {
            if (modalAssignedUnits >= modalRequiredUnits) {
                alert('This request already has the required number of donors assigned.');
                return;
            }

            var originalText = btnElement.innerHTML;
            btnElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
            btnElement.disabled = true;

            var formData = new FormData();
            formData.append('assign_donor', '1');
            formData.append('request_id', modalRequestId);
            formData.append('donor_id', donorId);

            fetch('assignments.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        modalAssignedUnits++;

                        // Remove donor from available donors list
                        allDonors = allDonors.filter(d => d.id !== donorId);

                        if (modalAssignedUnits >= modalRequiredUnits) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 800);
                        } else {
                            renderModalDonors(modalBloodGroup);
                        }
                    } else {
                        alert(data.message || 'Error assigning donor');
                        btnElement.innerHTML = originalText;
                        btnElement.disabled = false;
                    }
                })
                .catch(err => {
                    alert('Error connecting to server');
                    btnElement.innerHTML = originalText;
                    btnElement.disabled = false;
                });
        }

        function renderModalDonors(bloodGroup) {
            var bestDonorDetails = document.getElementById('modalBestDonorDetails');
            var noDonors = document.getElementById('modalNoDonors');

            var remaining = modalRequiredUnits - modalAssignedUnits;

            if (remaining <= 0) {
                bestDonorDetails.innerHTML = '';
                bestDonorDetails.classList.add('hidden');
                noDonors.classList.remove('hidden');
                noDonors.innerHTML = '<div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center text-green-500 text-2xl mx-auto mb-3"><i class="fas fa-check-circle"></i></div><p class="text-green-600 font-bold">Required units fully assigned!</p>';
                return;
            }

            var freshDonors = [];
            var unassignedDonorsList = [];

            allDonors.forEach(function(d) {
                var donorIdNum = parseInt(d.id);
                if (modalRejectedDonors.includes(donorIdNum)) return;
                if (d.blood_groups !== bloodGroup) return;
                if (modalRequesterUserId && d.user_id == modalRequesterUserId) return;
                var match = calculateMatchScore(d, bloodGroup);

                if (modalUnassignedDonors.includes(donorIdNum)) {
                    unassignedDonorsList.push({
                        donor: d,
                        match: match,
                        isPreviouslyUnassigned: true
                    });
                } else {
                    freshDonors.push({
                        donor: d,
                        match: match,
                        isPreviouslyUnassigned: false
                    });
                }
            });

            freshDonors.sort(function(a, b) {
                return b.match.score - a.match.score;
            });

            unassignedDonorsList.sort(function(a, b) {
                return b.match.score - a.match.score;
            });

            // Prioritize fresh suitable donors over previously unassigned donors
            var scored = freshDonors.concat(unassignedDonorsList);

            if (scored.length === 0) {
                bestDonorDetails.innerHTML = '';
                bestDonorDetails.classList.add('hidden');
                noDonors.classList.remove('hidden');
                noDonors.innerHTML = '<div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 text-2xl mx-auto mb-3"><i class="fas fa-user-slash"></i></div><p class="text-gray-500 font-medium">No suitable donor with the required blood type is available.</p>';
                return;
            }

            noDonors.classList.add('hidden');
            bestDonorDetails.classList.remove('hidden');

            var html = '';
            scored.forEach(function(item, index) {
                var d = item.donor;
                var m = item.match;
                var elText = m.canDonate ? 'Eligible' : 'Ineligible (' + (90 - m.daysSince) + 'd)';
                var elColor = m.canDonate ? 'text-green-600' : 'text-red-500';
                
                // Only mark as Recommended if it's the top fresh donor (never if previously unassigned)
                var isRecommended = (index === 0 && !item.isPreviouslyUnassigned);

                var borderClass = isRecommended 
                    ? 'border-green-500 bg-green-50/20' 
                    : (item.isPreviouslyUnassigned ? 'border-amber-200 bg-amber-50/10' : 'border-gray-200 bg-white');

                html += '<div class="p-4 rounded-xl border-2 ' + borderClass + ' transition-all hover:border-blue-300 mb-3">';
                html += '  <div class="flex items-start justify-between mb-3">';
                html += '    <div class="flex items-center space-x-3">';
                html += '      <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-lg shadow-sm">' + escapeHtml(d.username).substring(0, 2).toUpperCase() + '</div>';
                html += '      <div>';
                html += '        <p class="font-bold text-gray-900 leading-tight">' + escapeHtml(d.username) + '</p>';
                html += '        <p class="text-xs font-bold text-red-500 mt-0.5">Blood Group: ' + escapeHtml(d.blood_groups) + '</p>';
                html += '      </div>';
                html += '    </div>';
                html += '    <div class="text-right">';
                if (isRecommended) {
                    html += '      <span class="text-[10px] font-bold text-green-700 bg-green-200 px-2 py-1 rounded-full uppercase tracking-wider"><i class="fas fa-star mr-1"></i>Recommended</span>';
                } else if (item.isPreviouslyUnassigned) {
                    html += '      <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-1 rounded-full uppercase tracking-wider"><i class="fas fa-undo mr-1"></i>Previously Unassigned</span>';
                }
                html += '    </div>';
                html += '  </div>';

                html += '  <div class="grid grid-cols-2 gap-2 text-[11px] font-medium text-gray-600 mb-4 bg-gray-50/50 p-2 rounded-lg">';
                html += '    <p class="flex items-center"><i class="fas fa-phone w-4 text-gray-400"></i>' + escapeHtml(d.phone) + '</p>';
                html += '    <p class="flex items-center truncate" title="' + escapeHtml(d.address || 'Unknown') + '"><i class="fas fa-map-marker-alt w-4 text-gray-400"></i>' + escapeHtml(d.address || 'Unknown') + '</p>';
                html += '    <p class="flex items-center"><i class="fas fa-calendar-alt w-4 text-gray-400"></i>' + (d.last_donation_date ? escapeHtml(d.last_donation_date) : 'Never') + '</p>';
                html += '    <p class="flex items-center"><i class="fas fa-heartbeat w-4 text-gray-400"></i><span class="' + elColor + ' ml-1">' + elText + '</span></p>';
                html += '  </div>';

                html += '  <button onclick="assignDonorAjax(' + d.id + ', this)" class="w-full py-2 ' + (item.isPreviouslyUnassigned ? 'bg-gray-600 hover:bg-gray-700' : 'bg-blue-600 hover:bg-blue-700') + ' text-white text-sm font-bold rounded-lg transition shadow-sm flex items-center justify-center gap-2">';
                html += '    <i class="fas fa-user-plus"></i> Assign Donor';
                html += '  </button>';
                html += '</div>';
            });

            bestDonorDetails.innerHTML = html;
        }

        // Delete Modal Logic
        function openDeleteModal(url) {
            document.getElementById('confirmDeleteBtn').href = url;
            document.getElementById('deleteConfirmModal').classList.remove('hidden');
            document.getElementById('deleteConfirmModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').classList.remove('flex');
            document.getElementById('deleteConfirmModal').classList.add('hidden');
        }
    </script>
</body>

</html>