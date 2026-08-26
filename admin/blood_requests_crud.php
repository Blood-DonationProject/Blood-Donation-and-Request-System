<?php
include 'auth_check.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
$roleCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$hasRoleColumn = ($roleCheck && $roleCheck->num_rows > 0);
$userFilter = $hasRoleColumn ? "WHERE role = 'User'" : "WHERE username != 'admin'";
$users_list = $conn->query("SELECT id, username FROM users {$userFilter} ORDER BY username");
$blood_groups_list = $conn->query("SELECT id, blood_gp_name FROM blood_groups ORDER BY blood_gp_name");

if (isset($_POST['add'])) {
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = 1;
    $hospital = trim($_POST['hospital']);
    $required_date = $_POST['required_date'];
    $status = $_POST['status'];
    $urgency = $_POST['urgency'] ?? 'Normal';

    // Get the username for the selected user
    $requester_name = '';
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $users_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_result && $user_result->num_rows > 0) {
        $requester_name = $user_result->fetch_assoc()['username'];
    }
    $user_stmt->close();

    if ($users_id && $blood_groups_id && $units > 0 && $hospital !== '' && $required_date !== '') {
        $stmt = $conn->prepare("INSERT INTO blood_request (users_id, requester_name, blood_groups_id, units, hospital, required_date, status, urgency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiissss", $users_id, $requester_name, $blood_groups_id, $units, $hospital, $required_date, $status, $urgency);
        if ($stmt->execute()) {
            $success = 'Blood request created successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $users_id = (int)$_POST['users_id'];
    $blood_groups_id = (int)$_POST['blood_groups_id'];
    $units = 1;
    $hospital = trim($_POST['hospital']);
    $required_date = $_POST['required_date'];
    $status = $_POST['status'];
    $urgency = $_POST['urgency'] ?? 'Normal';
    // Get the username for the selected user
    $requester_name = '';
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $users_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_result && $user_result->num_rows > 0) {
        $requester_name = $user_result->fetch_assoc()['username'];
    }
    $user_stmt->close();

    if ($users_id && $blood_groups_id && $units > 0 && $hospital !== '' && $required_date !== '') {
        $stmt = $conn->prepare("UPDATE blood_request SET users_id=?, requester_name=?, blood_groups_id=?, units=?, hospital=?, required_date=?, status=? WHERE id=?");
        $stmt->bind_param("isiisssi", $users_id, $requester_name, $blood_groups_id, $units, $hospital, $required_date, $status, $id);
        if ($stmt->execute()) {
            $success = 'Blood request updated successfully.';
        } else {
            $error = 'Error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Approve action
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Approved' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: blood_requests_crud.php');
    exit;
}

// Reject action
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Rejected' WHERE id=? AND status='Pending'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: blood_requests_crud.php');
    exit;
}

// Complete action
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $stmt = $conn->prepare("UPDATE blood_request SET status='Completed' WHERE id=? AND status='Received'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt_assign = $conn->prepare("UPDATE donor_assignments SET status='Completed' WHERE request_id=? AND status='Received'");
        $stmt_assign->bind_param("i", $id);
        $stmt_assign->execute();
        $stmt_assign->close();

        // Insert into donation history
        $req_stmt = $conn->prepare("SELECT users_id, assigned_donor_id, blood_groups_id, units FROM blood_request WHERE id=?");
        $req_stmt->bind_param("i", $id);
        $req_stmt->execute();
        $req_res = $req_stmt->get_result();
        if ($req_res && $req_res->num_rows > 0) {
            $req = $req_res->fetch_assoc();
            if ($req['assigned_donor_id']) {
                $dhStmt = $conn->prepare("INSERT INTO donation_history (donor_id, users_id, request_id, blood_groups_id, units, donation_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Completed')");
                $dhDate = date('Y-m-d');
                $dhStmt->bind_param("iiiiis", $req['assigned_donor_id'], $req['users_id'], $id, $req['blood_groups_id'], $req['units'], $dhDate);
                $dhStmt->execute();
                $dhStmt->close();

                // Get assignment_id for notification
                $assignment_id = null;
                $get_assign = $conn->prepare("SELECT id FROM donor_assignments WHERE request_id = ? AND donor_id = ?");
                $get_assign->bind_param("ii", $id, $req['assigned_donor_id']);
                $get_assign->execute();
                if ($row_assign = $get_assign->get_result()->fetch_assoc()) {
                    $assignment_id = $row_assign['id'];
                }
                $get_assign->close();

                // Get donor's user_id
                $donorUser = $conn->prepare("SELECT user_id FROM donor WHERE id = ?");
                $donorUser->bind_param("i", $req['assigned_donor_id']);
                $donorUser->execute();
                $donorUserRow = $donorUser->get_result()->fetch_assoc();
                $donorUser->close();

                $notifType = 'StatusUpdate';
                $notifTitle = 'Request Completed';
                $notifMsg = "Request #" . $id . " has been successfully completed and recorded in your history.";

                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, assignment_id, type, title, message) VALUES (?, ?, ?, ?, ?, ?)");

                // Notify requester
                $notifStmt->bind_param("iiisss", $req['users_id'], $id, $assignment_id, $notifType, $notifTitle, $notifMsg);
                $notifStmt->execute();

                // Notify donor
                if ($donorUserRow) {
                    $notifStmt->bind_param("iiisss", $donorUserRow['user_id'], $id, $assignment_id, $notifType, $notifTitle, $notifMsg);
                    $notifStmt->execute();
                }
                $notifStmt->close();
            }
        }
        $req_stmt->close();
        $_SESSION['success'] = 'Request marked as completed and recorded in history.';
    }
    $stmt->close();
    header('Location: blood_requests_crud.php');
    exit;
}

// Assign donor action
if (isset($_POST['assign_donor'])) {
    $request_id = (int)$_POST['request_id'];
    $donor_id = (int)$_POST['donor_id'];

    if ($request_id > 0 && $donor_id > 0) {
        // Verify the request exists and is in a valid state
        $check = $conn->prepare("SELECT id, users_id, status, assigned_donor_id, required_date FROM blood_request WHERE id = ?");
        $check->bind_param("i", $request_id);
        $check->execute();
        $result = $check->get_result();
        if ($result && $result->num_rows > 0) {
            $req = $result->fetch_assoc();
            if ($req['status'] === 'Expired' || strtotime($req['required_date']) < strtotime('today')) {
                $_SESSION['error'] = 'Cannot assign donor: this blood request has expired.';
            } else if (in_array($req['status'], ['Pending', 'Approved']) && empty($req['assigned_donor_id'])) {
                // Verify donor exists and is available
                $donor_check = $conn->prepare("SELECT id, user_id, available_status FROM donor WHERE id = ?");
                $donor_check->bind_param("i", $donor_id);
                $donor_check->execute();
                $donor_result = $donor_check->get_result();
                if ($donor_result && $donor_result->num_rows > 0) {
                    $donor = $donor_result->fetch_assoc();
                    if (isset($req['users_id']) && isset($donor['user_id']) && $req['users_id'] == $donor['user_id']) {
                        $_SESSION['error'] = 'The requester cannot be assigned as a donor for their own blood request.';
                    } else if ($donor['available_status'] === 'Available') {
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

                            // Get donor's user_id for notification
                            $donorUser = $conn->prepare("SELECT user_id FROM donor WHERE id = ?");
                            $donorUser->bind_param("i", $donor_id);
                            $donorUser->execute();
                            $donorUserRow = $donorUser->get_result()->fetch_assoc();
                            $donorUser->close();

                            if ($donorUserRow) {
                                $notifMsg = "You have a new blood donation assignment for Request #" . $request_id . ". Please accept or decline on your dashboard.";
                                $notifType = 'Assignment';
                                $notifTitle = 'New Assignment';
                                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, assignment_id, type, title, message) VALUES (?, ?, ?, ?, ?, ?)");
                                $notifStmt->bind_param("iiisss", $donorUserRow['user_id'], $request_id, $assignment_id, $notifType, $notifTitle, $notifMsg);
                                $notifStmt->execute();
                                $notifStmt->close();
                            }

                            $_SESSION['success'] = 'Donor assigned successfully!';
                        } else {
                            $_SESSION['error'] = 'Error assigning donor: ' . $conn->error;
                        }
                        $assign->close();
                    } else {
                        $_SESSION['error'] = 'Selected donor is not available.';
                    }
                } else {
                    $_SESSION['error'] = 'Donor not found.';
                }
                $donor_check->close();
            } else {
                $_SESSION['error'] = 'Request is already assigned or cannot be assigned (status: ' . htmlspecialchars($req['status']) . ').';
            }
        } else {
            $_SESSION['error'] = 'Blood request not found.';
        }
        $check->close();
    } else {
        $_SESSION['error'] = 'Please select both a blood request and a donor.';
    }
    header('Location: blood_requests_crud.php');
    exit;
}

// Unassign donor action
if (isset($_GET['unassign'])) {
    $id = (int)$_GET['unassign'];
    // Check if there is an active assignment that is 'Assigned', 'Accepted', or 'Pending'
    $checkAssign = $conn->prepare("SELECT id, donor_id FROM donor_assignments WHERE request_id = ? AND status IN ('Assigned', 'Accepted', 'Pending') ORDER BY id DESC LIMIT 1");
    $checkAssign->bind_param("i", $id);
    $checkAssign->execute();
    $assignRow = $checkAssign->get_result()->fetch_assoc();
    $checkAssign->close();

    if ($assignRow) {
        $stmt = $conn->prepare("UPDATE blood_request SET assigned_donor_id = NULL, status = 'Pending' WHERE id = ? AND assigned_donor_id IS NOT NULL");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Cancel the donor assignment
        $cancelAssign = $conn->prepare("UPDATE donor_assignments SET status = 'Cancelled' WHERE id = ?");
        $cancelAssign->bind_param("i", $assignRow['id']);
        $cancelAssign->execute();
        $cancelAssign->close();

        // Restore donor availability to Available
        $restoreDonor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
        $restoreDonor->bind_param("i", $assignRow['donor_id']);
        $restoreDonor->execute();
        $restoreDonor->close();
    }

    header('Location: blood_requests_crud.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get the assigned donor_id before deleting
    $getDonor = $conn->prepare("SELECT assigned_donor_id FROM blood_request WHERE id = ? AND assigned_donor_id IS NOT NULL");
    $getDonor->bind_param("i", $id);
    $getDonor->execute();
    $donorRow = $getDonor->get_result()->fetch_assoc();
    $getDonor->close();

    // Restore donor availability to Available
    if ($donorRow && $donorRow['assigned_donor_id']) {
        $restoreDonor = $conn->prepare("UPDATE donor SET available_status = 'Available' WHERE id = ?");
        $restoreDonor->bind_param("i", $donorRow['assigned_donor_id']);
        $restoreDonor->execute();
        $restoreDonor->close();
    }
    
    // Cancel the donor assignment
    $cancelAssign = $conn->prepare("UPDATE donor_assignments SET status = 'Cancelled' WHERE request_id = ? AND status IN ('Assigned', 'Accepted')");
    $cancelAssign->bind_param("i", $id);
    $cancelAssign->execute();
    $cancelAssign->close();

    $conn->query("DELETE FROM blood_request WHERE id = $id");
    header('Location: blood_requests_crud.php');
    exit;
}

$stats = [
    'total'     => 0,
    'pending'   => 0,
    'approved'  => 0,
    'completed' => 0
];
try {
    $stats['total']     = (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0);
    $stats['pending']   = (int)($conn->query("
        SELECT COUNT(*) AS c 
        FROM blood_request r
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ")->fetch_assoc()['c'] ?? 0);
    $stats['approved']  = (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status IN ('Approved', 'Assigned')")->fetch_assoc()['c'] ?? 0);
    $stats['completed'] = (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0);
} catch (Exception $e) {
}

$requests = [];
$edit_row = null;

$result = $conn->query("
    SELECT br.*, bg.blood_gp_name,
           u.username as requester_username,
           d_u.username as donor_username,
           d.available_status as donor_status,
           da.status as assignment_status
    FROM blood_request br
    LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
    LEFT JOIN users u ON br.users_id = u.id
    LEFT JOIN donor d ON COALESCE(br.assigned_donor_id, br.donor_id) = d.id
    LEFT JOIN users d_u ON d.user_id = d_u.id
    LEFT JOIN (
        SELECT request_id, donor_id, status
        FROM donor_assignments
        WHERE id IN (SELECT MAX(id) FROM donor_assignments GROUP BY request_id)
    ) da ON da.request_id = br.id
    ORDER BY br.required_date DESC
");
if ($result && $result->num_rows > 0) {
    $requests = $result->fetch_all(MYSQLI_ASSOC);
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($requests as $r) {
        if ($r['id'] == $edit_id) {
            $edit_row = $r;
            break;
        }
    }
}

$view_row = null;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    $stmt = $conn->prepare("
        SELECT br.*, bg.blood_gp_name, 
               u.username as requester_username, 
               d_u.username as donor_username,
               d.available_status as donor_status,
               d.blood_groups as donor_blood_group,
               d.phone as donor_phone,
               d.weight as donor_weight,
               d.age as donor_age,
               da.status as assignment_status,
               da.created_at as assignment_date
        FROM blood_request br
        LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
        LEFT JOIN users u ON br.users_id = u.id
        LEFT JOIN donor d ON COALESCE(br.assigned_donor_id, br.donor_id) = d.id
        LEFT JOIN users d_u ON d.user_id = d_u.id
        LEFT JOIN donor_assignments da ON da.request_id = br.id AND da.donor_id = COALESCE(br.assigned_donor_id, br.donor_id)
        WHERE br.id = ?
        ORDER BY da.id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $view_result = $stmt->get_result();
    if ($view_result && $view_result->num_rows > 0) {
        $view_row = $view_result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch assignable requests (Pending or Approved without donor and not expired)
$assignable_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.users_id, r.requester_name, bg.blood_gp_name AS blood_group, bg.id AS blood_groups_id,
               r.units, r.hospital, r.required_date, r.status, r.assigned_donor_id,
               (SELECT GROUP_CONCAT(DISTINCT donor_id) FROM donor_assignments WHERE request_id = r.id AND status = 'Rejected') AS rejected_donors,
               (SELECT GROUP_CONCAT(DISTINCT donor_id) FROM donor_assignments WHERE request_id = r.id AND status = 'Cancelled') AS unassigned_donors
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        WHERE r.status IN ('Pending', 'Approved') AND r.assigned_donor_id IS NULL AND r.required_date >= CURDATE()
        ORDER BY r.required_date ASC
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
        SELECT d.id, d.user_id, d.blood_groups, d.phone, d.weight, d.age, d.available_status,
               d.last_donation_date, u.username
        FROM donor d
        JOIN users u ON d.user_id = u.id
        WHERE d.available_status = 'Available'
        ORDER BY d.blood_groups ASC, u.username ASC
    ");
    if ($result && $result->num_rows > 0) {
        $available_donors = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}

// Pending / in-progress blood requests for action cards (non-expired)
$pending_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.users_id, r.requester_name, bg.blood_gp_name AS blood_group, r.units, r.hospital, r.required_date, r.status
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
        ORDER BY r.required_date ASC
        LIMIT 10
    ");
    if ($result && $result->num_rows > 0) {
        $pending_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}


// Fetch already assigned requests for display
$assigned_requests = [];
try {
    $result = $conn->query("
        SELECT r.id, r.requester_name, bg.blood_gp_name AS blood_group, r.units,
               r.hospital, r.required_date, r.status,
               u.username AS donor_name, d.blood_groups AS donor_blood_group, d.phone AS donor_phone,
               d.available_status AS donor_status,
               da.status AS assignment_status
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN donor_assignments da ON da.request_id = r.id AND da.donor_id = COALESCE(r.assigned_donor_id, r.donor_id)
        WHERE (r.assigned_donor_id IS NOT NULL OR r.donor_id IS NOT NULL)
        ORDER BY r.required_date DESC
    ");
    if ($result && $result->num_rows > 0) {
        $assigned_requests = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}

// Latest 5 blood requests for Recent section
$recent_requests = [];
try {
    $rr_result = $conn->query("
        SELECT r.id, r.requester_name, bg.blood_gp_name AS blood_group,
               r.units, r.required_date, r.status, r.assigned_donor_id
        FROM blood_request r
        LEFT JOIN blood_groups bg ON r.blood_groups_id = bg.id
        ORDER BY r.id DESC
        LIMIT 5
    ");
    if ($rr_result && $rr_result->num_rows > 0) {
        $recent_requests = $rr_result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
}

$stats = [
    'total' => (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request")->fetch_assoc()['c'] ?? 0),
    'pending' => (int)($conn->query("
        SELECT COUNT(*) AS c 
        FROM blood_request r
        LEFT JOIN donor d ON COALESCE(r.assigned_donor_id, r.donor_id) = d.id
        LEFT JOIN users u_donor ON d.user_id = u_donor.id
        WHERE r.status NOT IN ('Completed', 'Rejected', 'Cancelled')
          AND (COALESCE(r.assigned_donor_id, r.donor_id) IS NULL OR u_donor.status = 'Active' OR u_donor.status IS NULL)
    ")->fetch_assoc()['c'] ?? 0),
    'approved' => (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Approved'")->fetch_assoc()['c'] ?? 0),
    'completed' => (int)($conn->query("SELECT COUNT(*) AS c FROM blood_request WHERE status='Completed'")->fetch_assoc()['c'] ?? 0),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Requests CRUD - BloodLife</title>
    <script>
        (function() {
            var t = localStorage.getItem('bloodlife-theme');
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/myanmar-font.css">
    <style>
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-down {
            animation: fadeInDown 0.6s ease-out;
        }

        .animate-fade-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .action-card {
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(220, 38, 38, 0.25);
        }

        .btn-approve {
            transition: all 0.2s ease;
        }

        .btn-approve:hover {
            transform: scale(1.05);
        }

        .btn-reject {
            transition: all 0.2s ease;
        }

        .btn-reject:hover {
            transform: scale(1.05);
        }

        .btn-assign {
            transition: all 0.2s ease;
        }

        .btn-assign:hover {
            transform: scale(1.05);
        }

        /* Assign Modal */
        .assign-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .assign-modal-overlay.active {
            display: flex;
        }

        .assign-modal {
            background: white;
            border-radius: 1rem;
            width: 90%;
            max-width: 520px;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.3s ease;
        }

        .assign-modal-body {
            max-height: 60vh;
            overflow-y: auto;
            padding: 1rem;
        }

        .assign-modal-donor {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }

        .assign-modal-donor:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .assign-modal-donor.selected {
            border-color: #16a34a;
            background: #f0fdf4;
        }

        .assign-modal-donor.best-match {
            border-color: #22c55e;
            background: #f0fdf4;
        }
    </style>
    <style id="dark-mode-styles">
        html:not(.dark) body {
            background-color: #ffffff !important;
            background-image: none !important;
        }

        html:not(.dark) .bg-gray-50:not(.sidebar):not(nav):not(nav *) {
            background-color: #ffffff !important;
        }

        html:not(.dark) .bg-gray-100 {
            background-color: #ffffff !important;
        }

        html.dark body {
            background-color: #111827 !important;
            background-image: none !important;
            color: #e5e7eb;
        }

        

        

        html.dark .bg-white:not(.sidebar):not(nav) {
            background-color: #1f2937 !important;
        }

        html.dark .text-gray-900,
        html.dark .text-gray-800 {
            color: #f3f4f6 !important;
        }

        html.dark .text-gray-700:not(.sidebar *):not(nav *) {
            color: #d1d5db !important;
        }

        html.dark .text-gray-600:not(.sidebar *):not(nav *) {
            color: #9ca3af !important;
        }

        html.dark .text-gray-500:not(.sidebar *):not(nav *) {
            color: #9ca3af !important;
        }

        html.dark input,
        html.dark select,
        html.dark textarea {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #e5e7eb !important;
        }

        html.dark label {
            color: #d1d5db !important;
        }

        html.dark .bg-gray-50,
        html.dark .bg-gray-100 {
            background-color: #374151 !important;
        }

        html.dark thead.bg-gray-50 {
            background-color: #111827 !important;
        }

        html.dark .border-gray-200,
        html.dark .border-2.border-gray-200,
        html.dark .border {
            border-color: #4b5563 !important;
        }

        html.dark .border-t:not(.sidebar *) {
            border-color: #374151 !important;
        }

        html.dark .bg-red-50:not(.sidebar *) {
            background-color: rgba(220, 38, 38, 0.15) !important;
        }

        html.dark tbody tr {
            border-color: #374151 !important;
        }

        html.dark tbody tr:hover {
            background-color: #374151 !important;
        }

        html.dark .stat-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900">

    <div class="flex min-h-screen">

        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 w-full min-w-0 overflow-x-hidden">

            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <div class="p-8">

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-2 border-red-500 p-4 rounded mb-6">
                        <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-2 border-green-500 p-4 rounded mb-6">
                        <p class="text-green-700"><?= htmlspecialchars($success) ?></p>
                    </div>
                <?php endif; ?>





                <!-- View Assignment Details -->
                <?php if ($view_row): ?>
                    <div id="viewDetails" class="bg-white rounded-2xl shadow-lg p-6 mb-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Assignment Details (Request #<?= $view_row['id'] ?>)</h3>
                            <a href="blood_requests_crud.php" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</a>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Requester Name</p>
                                <p class="text-gray-900 font-medium"><?= htmlspecialchars($view_row['requester_name'] ?: ($view_row['requester_username'] ?: 'Unknown')) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Donor Name</p>
                                <?php if (!empty($view_row['assigned_donor_id']) || !empty($view_row['donor_id'])): ?>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-gray-900 font-medium"><?= htmlspecialchars($view_row['donor_username'] ?: 'Donor #' . ($view_row['assigned_donor_id'] ?: $view_row['donor_id'])) ?></p>
                                        <?php
                                        $donorStatus = $view_row['donor_status'] ?? '';
                                        $donorBadgeColor = ($donorStatus === 'Available') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                        if ($donorStatus): ?>
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $donorBadgeColor ?>"><?= htmlspecialchars($donorStatus) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-400 italic">Not Assigned</p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Blood Group</p>
                                <p class="text-red-600 font-bold bg-red-50 inline-block px-3 py-1 rounded-lg"><?= htmlspecialchars($view_row['blood_gp_name'] ?? '-') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Units</p>
                                <p class="text-gray-900 font-medium">1 Unit</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Hospital</p>
                                <p class="text-gray-900 font-medium"><?= htmlspecialchars($view_row['hospital'] ?? '-') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Required Date</p>
                                <p class="text-gray-900 font-medium"><?= htmlspecialchars($view_row['required_date'] ?? '-') ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Urgency</p>
                                <?php $urgency = $view_row['Urgency'] ?? 'Normal'; ?>
                                <p class="font-medium <?= $urgency == 'Urgent' ? 'text-red-600' : 'text-green-600' ?>"><?= htmlspecialchars($urgency) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-semibold mb-1">Assignment Status</p>
                                <?php
                                $st = $view_row['status'] ?: ($view_row['assignment_status'] ?? '');
                                if (empty($st)) {
                                    $st = (!empty($view_row['assigned_donor_id']) || !empty($view_row['donor_id'])) ? 'Assigned' : 'Pending';
                                }
                                $stClasses = 'bg-gray-100 text-gray-700';
                                if ($st == 'Approved') $stClasses = 'bg-blue-100 text-blue-700';
                                elseif ($st == 'Assigned') $stClasses = 'bg-indigo-100 text-indigo-700';
                                elseif ($st == 'Accepted') $stClasses = 'bg-purple-100 text-purple-700';
                                elseif ($st == 'Received') $stClasses = 'bg-teal-100 text-teal-700';
                                elseif ($st == 'Completed') $stClasses = 'bg-green-100 text-green-700';
                                elseif ($st == 'Available') $stClasses = 'bg-green-100 text-green-700';
                                elseif ($st == 'Unavailable') $stClasses = 'bg-red-100 text-red-700';
                                elseif ($st == 'Rejected' || $st == 'Cancelled') $stClasses = 'bg-red-100 text-red-700';
                                elseif ($st == 'Pending') $stClasses = 'bg-yellow-100 text-yellow-700';
                                ?>
                                <p class="inline-block px-3 py-1 rounded-full text-xs font-bold <?= $stClasses ?>"><?= htmlspecialchars($st) ?></p>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <?php
                        $st = $view_row['status'] ?: ($view_row['assignment_status'] ?? '');
                        if (empty($st) && (!empty($view_row['assigned_donor_id']) || !empty($view_row['donor_id']))) {
                            $st = 'Assigned';
                        }
                        $ast = $view_row['assignment_status'] ?? '';

                        $steps = [
                            'Assigned' => ['status' => 'Pending', 'date' => null, 'label' => 'Assigned', 'color' => 'bg-gray-200'],
                            'Accepted' => ['status' => 'Pending', 'date' => null, 'label' => 'Accepted / Rejected', 'color' => 'bg-gray-200'],
                            'Donation' => ['status' => 'Pending', 'date' => null, 'label' => 'Blood Donation', 'color' => 'bg-gray-200'],
                            'Received' => ['status' => 'Pending', 'date' => null, 'label' => 'Blood Received', 'color' => 'bg-gray-200'],
                            'Completed' => ['status' => 'Pending', 'date' => null, 'label' => 'Completed', 'color' => 'bg-gray-200']
                        ];

                        if (in_array($st, ['Assigned', 'Accepted', 'Received', 'Completed']) || $ast) {
                            $steps['Assigned']['status'] = 'Completed';
                            $steps['Assigned']['date'] = $view_row['assigned_date'];
                            $steps['Assigned']['color'] = 'bg-indigo-500';
                        }

                        if (in_array($st, ['Accepted', 'Received', 'Completed']) || $ast == 'Accepted') {
                            $steps['Accepted']['status'] = 'Completed';
                            $steps['Accepted']['label'] = 'Accepted';
                            $steps['Accepted']['date'] = $view_row['responded_date'];
                            $steps['Accepted']['color'] = 'bg-purple-500';
                        } else if ($st == 'Rejected' || $ast == 'Rejected') {
                            $steps['Accepted']['status'] = 'Rejected';
                            $steps['Accepted']['label'] = 'Rejected';
                            $steps['Accepted']['date'] = $view_row['responded_date'];
                            $steps['Accepted']['color'] = 'bg-red-500';
                        }

                        if (in_array($st, ['Received', 'Completed'])) {
                            $steps['Donation']['status'] = 'Completed';
                            $steps['Donation']['color'] = 'bg-blue-500';

                            $steps['Received']['status'] = 'Completed';
                            $steps['Received']['color'] = 'bg-teal-500';
                            $steps['Received']['date'] = $view_row['received_at'] ?? null;
                        }

                        if ($st == 'Completed') {
                            $steps['Completed']['status'] = 'Completed';
                            $steps['Completed']['color'] = 'bg-green-500';
                        }
                        ?>
                        <div class="mt-8 border-t border-gray-100 pt-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-6">Status Timeline</h4>
                            <div class="relative pl-5 border-l-2 border-gray-100 space-y-6">
                                <?php foreach ($steps as $key => $step): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full <?= $step['color'] ?> border-4 border-white shadow-sm"></div>
                                        <p class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                            <?= htmlspecialchars($step['label']) ?>
                                            <?php if ($step['status'] == 'Completed'): ?>
                                                <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full uppercase tracking-wide">Done</span>
                                            <?php elseif ($step['status'] == 'Rejected'): ?>
                                                <span class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full uppercase tracking-wide">Rejected</span>
                                            <?php else: ?>
                                                <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full uppercase tracking-wide">Pending</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($step['date']): ?>
                                            <p class="text-xs text-gray-500 mt-1"><i class="far fa-clock mr-1"></i><?= date('M j, Y g:i A', strtotime($step['date'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-8 pt-4 border-t border-gray-100 flex justify-end">
                            <?php if ($st === 'Rejected' || $st === 'Cancelled'): ?>
                                <button type="button" onclick="openAssignModal(<?= (int)$view_row['id'] ?>, true)" class="bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-red-600 transition shadow-sm flex items-center gap-2"><i class="fas fa-user-plus"></i> Assign Another Donor</button>
                            <?php elseif ($st === 'Assigned'): ?>
                                <span class="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold bg-yellow-50 text-yellow-700 border border-yellow-200"><i class="fas fa-hourglass-half mr-2"></i> Wait for donor response</span>
                            <?php elseif ($st === 'Accepted'): ?>
                                <span class="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold bg-blue-50 text-blue-700 border border-blue-200"><i class="fas fa-hourglass-half mr-2"></i> Wait for Blood Received</span>
                            <?php elseif ($st === 'Received'): ?>
                                <button type="button" onclick="openCompleteModal(<?= (int)$view_row['id'] ?>)" class="bg-green-500 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-green-600 transition shadow-sm flex items-center gap-2"><i class="fas fa-check-circle"></i> Mark as Completed</button>
                            <?php elseif ($st === 'Completed'): ?>
                                <button disabled class="bg-gray-200 text-gray-500 px-5 py-2.5 rounded-xl font-bold cursor-not-allowed flex items-center gap-2"><i class="fas fa-check-double"></i> Completed</button>
                            <?php elseif (in_array($st, ['Pending', 'Approved']) && empty($view_row['assigned_donor_id']) && empty($view_row['donor_id'])): ?>
                                <button type="button" onclick="openAssignModal(<?= (int)$view_row['id'] ?>, false)" class="bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-red-600 transition shadow-sm flex items-center gap-2"><i class="fas fa-user-plus"></i> Assign Donor</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="crudForm" class="bg-white rounded-2xl shadow-lg p-6 mb-8 <?= $edit_row ? '' : 'hidden' ?>">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800"><?= $edit_row ? 'Edit Blood Request' : 'New Blood Request' ?></h3>
                        <button onclick="toggleForm()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                    </div>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if ($edit_row): ?>
                            <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                        <?php endif; ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">User *</label>
                            <select name="users_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                                <option value="">-- Select User --</option>
                                <?php if ($users_list): mysqli_data_seek($users_list, 0);
                                    while ($u = $users_list->fetch_assoc()): ?>
                                        <option value="<?= $u['id'] ?>" <?= (($edit_row['users_id'] ?? 0) == $u['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['username']) ?>
                                        </option>
                                <?php endwhile;
                                endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group *</label>
                            <select name="blood_groups_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                                <option value="">-- Select --</option>
                                <?php if ($blood_groups_list): mysqli_data_seek($blood_groups_list, 0);
                                    while ($bg = $blood_groups_list->fetch_assoc()): ?>
                                        <option value="<?= $bg['id'] ?>" <?= (($edit_row['blood_groups_id'] ?? 0) == $bg['id']) ? 'selected' : '' ?>><?= htmlspecialchars($bg['blood_gp_name']) ?></option>
                                <?php endwhile;
                                endif; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Hospital *</label>
                            <input type="text" name="hospital" value="<?= htmlspecialchars($edit_row['hospital'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Required Date *</label>
                            <input type="date" name="required_date" value="<?= htmlspecialchars($edit_row['required_date'] ?? '') ?>" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Status *</label>
                            <select name="status" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-red-500 focus:ring-2 focus:ring-red-200 transition outline-none">
                                <?php foreach (['Pending', 'Approved', 'Assigned', 'Accepted', 'Completed', 'Rejected', 'Expired'] as $st): ?>
                                    <option value="<?= $st ?>" <?= (($edit_row['status'] ?? 'Pending') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="<?= $edit_row ? 'update' : 'add' ?>" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold py-2.5 rounded-xl hover:shadow-lg transition">
                                <?= $edit_row ? 'Update' : 'Create' ?>
                            </button>
                            <?php if ($edit_row): ?>
                                <a href="blood_requests_crud.php" class="ml-2 w-full text-center bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl hover:bg-gray-300 transition">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>







                <!-- Filters for All Blood Requests -->
                <div class="flex flex-wrap items-end gap-4 mb-6">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
                        <input id="searchInput" type="text" placeholder="Search by name, hospital, or date..." class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                    </div>
                    <div class="w-40">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Blood Group</label>
                        <select id="filterBloodGroup" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                            <option value="">All</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                <option value="<?= $bg ?>"><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                        <select id="filterStatus" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-red-500 transition">
                            <option value="">All</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Received">Received</option>
                            <option value="Completed">Completed</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>
                    <button onclick="clearFilters()" class="px-4 py-2.5 text-sm text-gray-600 border-2 border-gray-200 rounded-xl hover:bg-gray-100 transition font-semibold whitespace-nowrap">Clear Filters</button>
                </div>

                <!-- All Blood Requests Table -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-8">
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-50">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">All Blood Requests</h3>
                            <p class="text-sm text-gray-400 mt-1">Complete list of all requests</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-600 bg-gray-100 px-3 py-1 rounded-full">Total: <?= count($requests) ?></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-slate-600">
                                    <th class="p-3 hidden">ID</th>
                                    <th class="p-3">Requester</th>
                                    <th class="p-3">Blood Group</th>
                                    <th class="p-3">Units</th>
                                    <th class="p-3">Hospital</th>
                                    <th class="p-3">Required Date</th>
                                    <th class="p-3">Urgency</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($requests) > 0): ?>
                                    <?php foreach ($requests as $r): ?>
                                        <?php
                                        $reqStatus = $r['status'] ?: ($r['assignment_status'] ?? '');
                                        if (empty($reqStatus)) {
                                            $reqStatus = (!empty($r['assigned_donor_id']) || !empty($r['donor_id'])) ? 'Assigned' : 'Pending';
                                        }

                                        $statusColors = [
                                            'Pending'   => 'bg-yellow-100 text-yellow-700',
                                            'Approved'  => 'bg-blue-100 text-blue-700',
                                            'Assigned'  => 'bg-indigo-100 text-indigo-700',
                                            'Accepted'  => 'bg-purple-100 text-purple-700',
                                            'Received'  => 'bg-teal-100 text-teal-700',
                                            'Completed' => 'bg-green-100 text-green-700',
                                            'Available' => 'bg-green-100 text-green-700',
                                            'Unavailable' => 'bg-red-100 text-red-700',
                                            'Rejected'  => 'bg-red-100 text-red-700',
                                            'Cancelled' => 'bg-red-100 text-red-700',
                                            'Expired'   => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                        ];
                                        $sc = $statusColors[$reqStatus] ?? 'bg-gray-100 text-gray-700';

                                        $urgencyColors = [
                                            'Normal' => 'bg-blue-100 text-blue-700',
                                            'Urgent' => 'bg-red-100 text-red-700',
                                            'Emergency' => 'bg-red-100 text-red-700',
                                        ];
                                        $uc = $urgencyColors[$r['urgency'] ?? 'Normal'] ?? 'bg-gray-100 text-gray-700';
                                        ?>
                                        <tr class="request-row border-t border-slate-200 hover:bg-gray-50"
                                            data-blood-group="<?= htmlspecialchars($r['blood_gp_name'] ?? '') ?>"
                                            data-status="<?= htmlspecialchars($reqStatus) ?>">
                                            <td class="p-3 font-medium hidden">#<?= $r['id'] ?></td>
                                            <td class="p-3"><?= htmlspecialchars($r['requester_name'] ?: ($r['requester_username'] ?? '-')) ?></td>
                                            <td class="p-3"><span class="bg-gradient-to-br from-red-100 to-red-200 text-red-700 font-bold px-3 py-1 rounded-full text-xs"><?= htmlspecialchars($r['blood_gp_name'] ?? '-') ?></span></td>
                                            <td class="p-3">1 Unit</td>
                                            <td class="p-3"><?= htmlspecialchars($r['hospital']) ?></td>
                                            <td class="p-3"><?= htmlspecialchars($r['required_date']) ?></td>
                                            <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $uc ?>"><?= htmlspecialchars($r['urgency'] ?? 'Normal') ?></span></td>
                                            <td class="p-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $sc ?>"><?= htmlspecialchars($reqStatus) ?></span></td>
                                            <td class="p-3">
                                                <div class="flex gap-2">
                                                    <a href="blood_requests_crud.php?view=<?= $r['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold">View</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="p-8 text-center text-gray-500">No blood requests found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function toggleAdminDropdown() {
            document.getElementById('adminDropdown').classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('adminMenu');
            const dropdown = document.getElementById('adminDropdown');
            if (menu && dropdown && !menu.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        function toggleForm() {
            document.getElementById('crudForm').classList.toggle('hidden');
        }
    </script>

    <script>
        // Donor Assignment Logic with Blood Compatibility Matching
        var allDonors = <?= json_encode($available_donors) ?>;
        var pendingRequestsData = <?= json_encode($pending_requests) ?>;
        var selectedDonorId = null;

        // Blood compatibility chart: which blood types can donate TO each recipient
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

        // Calculate match score for a donor (higher = better match)
        function calculateMatchScore(donor, requestBloodGroup) {
            var score = 0;
            var reasons = [];

            // 1. Exact blood type match (40 points)
            if (donor.blood_groups === requestBloodGroup) {
                score += 40;
                reasons.push('Exact blood type match');
            }

            // 2. Blood compatibility (30 points if compatible)
            var compatibleTypes = bloodCompatibility[donor.blood_groups] || [];
            if (compatibleTypes.indexOf(requestBloodGroup) !== -1) {
                score += 30;
                reasons.push('Compatible blood type');
            }

            // 3. Readiness / cooldown status (20 points)
            var canDonate = true;
            var daysSinceLastDonation = 999;
            if (donor.last_donation_date) {
                var lastDate = new Date(donor.last_donation_date);
                var now = new Date();
                daysSinceLastDonation = Math.floor((now - lastDate) / (1000 * 60 * 60 * 24));
                canDonate = daysSinceLastDonation >= 56;
            }
            if (canDonate) {
                score += 20;
                reasons.push('Ready to donate');
            }

            // 4. Time since last donation bonus (up to 10 points)
            // Longer gap = more time for recovery = better
            var timeBonus = Math.min(10, Math.floor(daysSinceLastDonation / 14));
            score += timeBonus;
            if (timeBonus > 5) reasons.push('Extended recovery time');

            return {
                score: score,
                reasons: reasons,
                canDonate: canDonate,
                daysSince: daysSinceLastDonation
            };
        }

        function selectRequest(el) {
            // Remove previous selection
            document.querySelectorAll('.request-item').forEach(function(item) {
                item.classList.remove('border-blue-500', 'bg-blue-50');
                item.classList.add('border-gray-200');
            });

            // Select this request
            el.classList.remove('border-gray-200');
            el.classList.add('border-blue-500', 'bg-blue-50');

            var requestId = el.getAttribute('data-id');
            var bloodGroup = el.getAttribute('data-blood-group');
            var bloodGroupsId = el.getAttribute('data-blood-groups-id');
            var units = el.getAttribute('data-units');

            document.getElementById('assignRequestId').value = requestId;
            document.getElementById('selectedBloodType').textContent = bloodGroup;

            // Show donor selection
            document.getElementById('noRequestSelected').classList.add('hidden');
            document.getElementById('donorSelection').classList.remove('hidden');

            // Filter and display matching donors
            selectedDonorId = null;
            document.getElementById('assignDonorId').value = '';
            document.getElementById('assignBtn').disabled = true;
            renderDonors(bloodGroup);
        }

        function renderDonors(bloodGroup, searchQuery) {
            var donorList = document.getElementById('donorList');
            var matchInfoBox = document.getElementById('matchInfoBox');

            // Only show donors with the exact same blood type
            var scored = [];
            allDonors.forEach(function(d) {
                if (d.blood_groups !== bloodGroup) return;
                var match = calculateMatchScore(d, bloodGroup);
                if (!searchQuery) {
                    scored.push({
                        donor: d,
                        match: match
                    });
                } else {
                    var q = searchQuery.toLowerCase();
                    if (d.username.toLowerCase().indexOf(q) !== -1 || d.phone.toLowerCase().indexOf(q) !== -1) {
                        scored.push({
                            donor: d,
                            match: match
                        });
                    }
                }
            });

            // Sort by match score descending (best match first)
            scored.sort(function(a, b) {
                return b.match.score - a.match.score;
            });

            if (scored.length === 0) {
                matchInfoBox.classList.add('hidden');
                donorList.innerHTML = '<div class="text-center py-6"><div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-2"><i class="fas fa-user-slash text-gray-300"></i></div><p class="text-gray-400 text-sm">No donors with blood type ' + escapeHtml(bloodGroup) + ' available</p><p class="text-gray-300 text-xs mt-1">Check donor availability or registration</p></div>';
                return;
            }

            // Show best match info
            var best = scored[0];
            if (best.match.score > 0) {
                matchInfoBox.classList.remove('hidden');
                var infoText = escapeHtml(best.donor.username) + ' — ' + best.match.reasons.join(', ') + ' (Score: ' + best.match.score + '/100)';
                document.getElementById('matchInfoText').textContent = infoText;
            } else {
                matchInfoBox.classList.add('hidden');
            }

            var html = '';
            scored.forEach(function(item, idx) {
                var d = item.donor;
                var m = item.match;
                var isBest = idx === 0 && m.score > 0;
                var borderColor = isBest ? 'border-green-500 bg-green-50' : 'border-gray-200';
                var bestBadge = isBest ? '<span class="ml-2 text-xs font-bold text-green-700 bg-green-200 px-2 py-0.5 rounded-full"><i class="fas fa-star mr-1"></i>Best Match</span>' : '';

                // Score bar color
                var barColor = m.score >= 70 ? 'bg-green-500' : m.score >= 40 ? 'bg-yellow-500' : 'bg-gray-300';

                html += '<div class="donor-item p-3 rounded-xl border-2 ' + borderColor + ' hover:border-green-300 cursor-pointer transition" data-donor-id="' + d.id + '" onclick="selectDonor(this, ' + d.id + ')">';
                html += '  <div class="flex items-start justify-between">';
                html += '    <div class="flex items-center space-x-3">';
                html += '      <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center font-bold text-xs">';
                html += '        ' + d.username.substring(0, 2).toUpperCase();
                html += '      </div>';
                html += '      <div>';
                html += '        <p class="font-semibold text-gray-900 text-sm">' + escapeHtml(d.username) + bestBadge + '</p>';
                html += '        <p class="text-xs text-gray-400">' + escapeHtml(d.phone) + ' | Age: ' + d.age + ' | ' + d.weight + 'kg</p>';
                html += '        <p class="text-xs text-gray-400 mt-0.5">Last donation: ' + escapeHtml(m.daysSince < 999 ? m.daysSince + ' days ago' : 'Never') + '</p>';
                html += '      </div>';
                html += '    </div>';
                html += '    <div class="text-right flex flex-col items-end">';
                if (!m.canDonate) {
                    html += '      <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded-full"><i class="fas fa-clock mr-1"></i>Cooldown</span>';
                } else {
                    html += '      <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"><i class="fas fa-check mr-1"></i>Ready</span>';
                }
                html += '      <div class="mt-1.5 flex items-center gap-1">';
                html += '        <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden"><div class="h-full ' + barColor + ' rounded-full" style="width:' + m.score + '%"></div></div>';
                html += '        <span class="text-[10px] font-bold text-gray-500">' + m.score + '</span>';
                html += '      </div>';
                html += '    </div>';
                html += '  </div>';
                html += '</div>';
            });

            donorList.innerHTML = html;

            // Auto-select the best match
            if (scored.length > 0 && scored[0].match.score > 0) {
                var bestItem = donorList.querySelector('.donor-item[data-donor-id="' + scored[0].donor.id + '"]');
                if (bestItem) {
                    selectDonor(bestItem, scored[0].donor.id);
                }
            }
        }

        function selectDonor(el, donorId) {
            document.querySelectorAll('.donor-item').forEach(function(item) {
                item.classList.remove('border-green-500', 'bg-green-50');
                item.classList.add('border-gray-200');
            });
            el.classList.remove('border-gray-200');
            el.classList.add('border-green-500', 'bg-green-50');

            selectedDonorId = donorId;
            document.getElementById('assignDonorId').value = donorId;
            document.getElementById('assignBtn').disabled = false;
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // Donor search
        var donorSearch = document.getElementById('donorSearch');
        if (donorSearch) {
            donorSearch.addEventListener('input', function() {
                var selectedRequest = document.querySelector('.request-item.border-blue-500');
                if (selectedRequest) {
                    var bloodGroup = selectedRequest.getAttribute('data-blood-group');
                    renderDonors(bloodGroup, this.value);
                }
            });
        }

        // Scroll to assignment section and pre-select request
        function scrollToAssign(requestId) {
            var target = document.querySelector('.request-item[data-id="' + requestId + '"]');
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                setTimeout(function() {
                    selectRequest(target);
                }, 400);
            }
        }

        // Modal Assign Donor Logic
        var modalSelectedDonorId = null;
        var modalRequestId = null;
        var modalBloodGroup = null;
        var modalRequesterUserId = null;

        // Find assignable request info from PHP data
        var assignableRequests = <?= json_encode($assignable_requests) ?>;
        var modalUnassignedDonors = [];
        var modalRejectedDonors = [];

        function openAssignModal(requestId, isReassign = false) {
            isReassignMode = isReassign;
            modalRequestId = requestId;
            modalSelectedDonorId = null;

            // Find request info
            var reqInfo = null;
            for (var i = 0; i < assignableRequests.length; i++) {
                if (assignableRequests[i].id == requestId) {
                    reqInfo = assignableRequests[i];
                    break;
                }
            }

            // If not in assignable list, try pending requests
            if (!reqInfo) {
                for (var j = 0; j < pendingRequestsData.length; j++) {
                    if (pendingRequestsData[j].id == requestId) {
                        reqInfo = pendingRequestsData[j];
                        break;
                    }
                }
            }

            if (!reqInfo) return;

            modalBloodGroup = reqInfo.blood_group;
            modalRequesterUserId = reqInfo.users_id;
            modalUnassignedDonors = reqInfo.unassigned_donors ? reqInfo.unassigned_donors.split(',').map(Number) : [];
            modalRejectedDonors = reqInfo.rejected_donors ? reqInfo.rejected_donors.split(',').map(Number) : [];

            document.getElementById('modalRequestInfo').textContent = 'Request #' + requestId + ' — ' + reqInfo.requester_name;
            document.getElementById('modalBloodType').textContent = reqInfo.blood_group;
            document.getElementById('modalDonorSearch').value = '';
            document.getElementById('modalAssignBtn').disabled = true;

            renderModalDonors(reqInfo.blood_group, '');

            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
            modalSelectedDonorId = null;
            modalRequestId = null;
        }

        function renderModalDonors(bloodGroup, searchQuery) {
            var donorList = document.getElementById('modalDonorList');
            var noDonors = document.getElementById('modalNoDonors');
            var bestMatchBox = document.getElementById('modalBestMatch');

            // Only show donors with the exact same blood type
            var freshDonors = [];
            var unassignedDonorsList = [];

            allDonors.forEach(function(d) {
                var donorIdNum = parseInt(d.id);
                if (modalRejectedDonors.includes(donorIdNum)) return;
                if (d.blood_groups !== bloodGroup) return;
                if (modalRequesterUserId && d.user_id == modalRequesterUserId) return;
                var match = calculateMatchScore(d, bloodGroup);

                var matchesSearch = true;
                if (searchQuery) {
                    var q = searchQuery.toLowerCase();
                    matchesSearch = (d.username.toLowerCase().indexOf(q) !== -1 || d.phone.toLowerCase().indexOf(q) !== -1);
                }

                if (matchesSearch) {
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
                }
            });

            freshDonors.sort(function(a, b) {
                return b.match.score - a.match.score;
            });

            unassignedDonorsList.sort(function(a, b) {
                return b.match.score - a.match.score;
            });

            var scored = freshDonors.concat(unassignedDonorsList);

            if (scored.length === 0) {
                donorList.innerHTML = '';
                noDonors.classList.remove('hidden');
                bestMatchBox.classList.add('hidden');
                return;
            }

            noDonors.classList.add('hidden');

            // Show best match (only if a fresh suitable donor is available)
            var best = scored[0];
            if (best.match.score > 0 && !best.isPreviouslyUnassigned) {
                bestMatchBox.classList.remove('hidden');
                document.getElementById('modalBestMatchText').textContent =
                    best.donor.username + ' — ' + best.match.reasons.join(', ') + ' (Score: ' + best.match.score + '/100)';
            } else {
                bestMatchBox.classList.add('hidden');
            }

            var html = '';
            scored.forEach(function(item, idx) {
                var d = item.donor;
                var m = item.match;
                var isBest = idx === 0 && m.score > 0 && !item.isPreviouslyUnassigned;
                var borderColor = isBest ? 'best-match' : '';
                var bestBadge = isBest ? '<span class="ml-2 text-xs font-bold text-green-700 bg-green-200 px-2 py-0.5 rounded-full"><i class="fas fa-star mr-1"></i>Best</span>' : (item.isPreviouslyUnassigned ? '<span class="ml-2 text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full"><i class="fas fa-undo mr-1"></i>Previously Unassigned</span>' : '');
                var barColor = m.score >= 70 ? 'bg-green-500' : m.score >= 40 ? 'bg-yellow-500' : 'bg-gray-300';

                html += '<div class="assign-modal-donor ' + borderColor + '" data-donor-id="' + d.id + '" onclick="selectModalDonor(this, ' + d.id + ')">';
                html += '  <div class="flex items-start justify-between">';
                html += '    <div class="flex items-center space-x-3">';
                html += '      <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center font-bold text-xs">';
                html += '        ' + d.username.substring(0, 2).toUpperCase();
                html += '      </div>';
                html += '      <div>';
                html += '        <p class="font-semibold text-gray-900 text-sm">' + escapeHtml(d.username) + bestBadge + '</p>';
                html += '        <p class="text-xs text-gray-400">' + escapeHtml(d.phone) + ' | Age: ' + d.age + ' | ' + d.weight + 'kg</p>';
                html += '        <p class="text-xs text-gray-400 mt-0.5">Last donation: ' + escapeHtml(m.daysSince < 999 ? m.daysSince + ' days ago' : 'Never') + '</p>';
                html += '      </div>';
                html += '    </div>';
                html += '    <div class="text-right flex flex-col items-end">';
                if (!m.canDonate) {
                    html += '      <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded-full"><i class="fas fa-clock mr-1"></i>Cooldown</span>';
                } else {
                    html += '      <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"><i class="fas fa-check mr-1"></i>Ready</span>';
                }
                html += '      <div class="mt-1.5 flex items-center gap-1">';
                html += '        <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden"><div class="h-full ' + barColor + ' rounded-full" style="width:' + m.score + '%"></div></div>';
                html += '        <span class="text-[10px] font-bold text-gray-500">' + m.score + '</span>';
                html += '      </div>';
                html += '    </div>';
                html += '  </div>';
                html += '</div>';
            });

            donorList.innerHTML = html;

            // Auto-select best match only if it's a fresh suitable candidate
            if (scored.length > 0 && scored[0].match.score > 0 && !scored[0].isPreviouslyUnassigned) {
                var bestItem = donorList.querySelector('.assign-modal-donor[data-donor-id="' + scored[0].donor.id + '"]');
                if (bestItem) {
                    selectModalDonor(bestItem, scored[0].donor.id);
                }
            }
        }

        function selectModalDonor(el, donorId) {
            document.querySelectorAll('.assign-modal-donor').forEach(function(item) {
                item.classList.remove('selected');
            });
            el.classList.add('selected');
            modalSelectedDonorId = donorId;
            document.getElementById('modalAssignBtn').disabled = false;
        }

        function submitModalAssign() {
            if (!modalRequestId || !modalSelectedDonorId) return;
            openAssignConfirmModal();
        }

        // Modal donor search
        var modalDonorSearch = document.getElementById('modalDonorSearch');
        if (modalDonorSearch) {
            modalDonorSearch.addEventListener('input', function() {
                if (modalBloodGroup) {
                    renderModalDonors(modalBloodGroup, this.value);
                }
            });
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
            <div class="p-8 text-center space-y-6">
                <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 mb-2">Delete Record</h2>
                    <p class="text-gray-500">Are you sure you want to delete this? This action cannot be undone.</p>
                </div>
            </div>
            <div class="px-8 pb-8 flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
                <a href="#" id="confirmDeleteBtn" onclick="this.classList.add('opacity-50', 'pointer-events-none');" class="flex-1 bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition text-center shadow-md flex items-center justify-center">Delete</a>
            </div>
        </div>
    </div>

    <!-- Assign Donor Modal -->
    <div id="assignModal" class="assign-modal-overlay" onclick="if(event.target===this)closeAssignModal()">
        <div class="assign-modal">
            <div class="flex items-center justify-between p-4 border-b border-gray-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Assign Donor</h3>
                        <p class="text-xs text-gray-400" id="modalRequestInfo"></p>
                    </div>
                </div>

                <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600 p-2"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4">
                <div class="relative mb-4">
                    <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                    <input type="text" id="modalDonorSearch" placeholder="Search matching donors..." class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl pl-11 pr-4 py-3 text-sm focus:border-blue-500 focus:bg-white transition outline-none">
                </div>

                <div class="mb-2 flex items-center justify-between text-xs font-semibold text-gray-500 px-1">
                    <span>MATCHING DONORS FOR: <span id="modalBloodType" class="text-red-600 font-bold ml-1"></span></span>
                </div>

                <div id="modalDonorList" class="space-y-3 max-h-[50vh] overflow-y-auto pr-1 custom-scrollbar">
                    <!-- Donors rendered via JS -->
                </div>

                <div id="modalNoDonors" class="hidden text-center py-8">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 text-2xl mx-auto mb-3"><i class="fas fa-user-slash"></i></div>
                    <p class="text-gray-500 font-medium">No matching donors found</p>
                    <p class="text-sm text-gray-400 mt-1">Try assigning a different blood type</p>
                </div>
            </div>

            <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button onclick="closeAssignModal()" class="px-5 py-2.5 rounded-xl font-bold text-gray-600 hover:bg-gray-200 transition">Cancel</button>
                <button id="modalAssignBtn" onclick="submitModalAssign()" disabled class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-blue-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"><i class="fas fa-check"></i> Assign Selected Donor</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modals -->
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

    <div id="completeConfirmModal" class="fixed inset-0 bg-black/60 z-[60] hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden animate-fade-up">
            <div class="p-8 text-center space-y-6">
                <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-4xl mx-auto shadow-sm">
                    ✅
                </div>
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 mb-2">Mark Completed</h2>
                    <p class="text-gray-500">Are you sure you want to mark this request as completed?</p>
                </div>
            </div>
            <div class="px-8 pb-8 flex gap-3">
                <button onclick="closeCompleteModal()" class="flex-1 border-2 border-gray-300 text-gray-600 py-3 rounded-xl font-bold hover:border-gray-400 hover:text-gray-800 transition">Cancel</button>
                <a href="#" id="confirmCompleteBtn" onclick="this.classList.add('opacity-50', 'pointer-events-none');" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 transition text-center shadow-md flex items-center justify-center">Yes, Completed</a>
            </div>
        </div>
    </div>

    <script>
        var isReassignMode = false;

        function openCompleteModal(id) {
            document.getElementById('confirmCompleteBtn').href = 'blood_requests_crud.php?complete=' + id;
            document.getElementById('completeConfirmModal').classList.remove('hidden');
            document.getElementById('completeConfirmModal').classList.add('flex');
        }

        function closeCompleteModal() {
            document.getElementById('completeConfirmModal').classList.remove('flex');
            document.getElementById('completeConfirmModal').classList.add('hidden');
        }

        function openAssignConfirmModal() {
            var title = isReassignMode ? 'Reassign Donor' : 'Assign Donor';
            var msg = isReassignMode ? 'Are you sure you want to assign another donor?' : 'Are you sure you want to assign this donor to this blood request?';
            document.getElementById('assignConfirmTitle').textContent = title;
            document.getElementById('assignConfirmMessage').textContent = msg;

            document.getElementById('assignConfirmModal').classList.remove('hidden');
            document.getElementById('assignConfirmModal').classList.add('flex');
        }

        function closeAssignConfirmModal() {
            document.getElementById('assignConfirmModal').classList.remove('flex');
            document.getElementById('assignConfirmModal').classList.add('hidden');
        }

        function executeModalAssign() {
            document.querySelector('#assignConfirmModal button:last-child').classList.add('opacity-50', 'pointer-events-none');

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'blood_requests_crud.php';

            var ridInput = document.createElement('input');
            ridInput.type = 'hidden';
            ridInput.name = 'request_id';
            ridInput.value = modalRequestId;
            form.appendChild(ridInput);

            var didInput = document.createElement('input');
            didInput.type = 'hidden';
            didInput.name = 'donor_id';
            didInput.value = modalSelectedDonorId;
            form.appendChild(didInput);

            var submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'assign_donor';
            submitInput.value = '1';
            form.appendChild(submitInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Filters JS
        const searchInput = document.getElementById('searchInput');
        const filterBloodGroup = document.getElementById('filterBloodGroup');
        const filterStatus = document.getElementById('filterStatus');
        const requestRows = document.querySelectorAll('.request-row');

        function applyFilters() {
            const q = searchInput.value.toLowerCase();
            const bg = filterBloodGroup.value;
            const status = filterStatus.value;
            
            requestRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowBg = row.getAttribute('data-blood-group');
                const rowStatus = row.getAttribute('data-status');
                
                const matchesSearch = text.includes(q);
                const matchesBg = !bg || rowBg === bg;
                const matchesStatus = !status || rowStatus === status;
                
                if (matchesSearch && matchesBg && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function clearFilters() {
            if(searchInput) searchInput.value = '';
            if(filterBloodGroup) filterBloodGroup.value = '';
            if(filterStatus) filterStatus.value = '';
            applyFilters();
        }

        if(searchInput) searchInput.addEventListener('keyup', applyFilters);
        if(filterBloodGroup) filterBloodGroup.addEventListener('change', applyFilters);
        if(filterStatus) filterStatus.addEventListener('change', applyFilters);
    </script>
</body>

</html>