<?php
/**
 * Notification Helper Functions
 * 
 * Centralized notification service supporting Admin & User notifications,
 * prepared statements, dynamic admin resolution, and rich event metadata.
 */

require_once __DIR__ . '/../config/db.php';

// Ensure schema compatibility for optional relational columns
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $chkDonorCol = $conn->query("SHOW COLUMNS FROM notifications LIKE 'donor_id'");
        if ($chkDonorCol && $chkDonorCol->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN donor_id INT NULL DEFAULT NULL AFTER assignment_id");
        }
        $chkUserCol = $conn->query("SHOW COLUMNS FROM notifications LIKE 'related_user_id'");
        if ($chkUserCol && $chkUserCol->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN related_user_id INT NULL DEFAULT NULL AFTER donor_id");
        }
        
        $conn->query("
            CREATE TABLE IF NOT EXISTS `user_notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `notification_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `is_read` TINYINT(1) DEFAULT 0,
                `is_deleted` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_un_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_un_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        $chk = $conn->query("SELECT COUNT(*) as c FROM user_notifications");
        if ($chk && $chk->fetch_assoc()['c'] == 0) {
            $conn->query("
                INSERT INTO user_notifications (notification_id, user_id, is_read, created_at)
                SELECT id, user_id, is_read, created_at FROM notifications WHERE user_id IS NOT NULL
            ");
        }
        $conn->query("ALTER TABLE notifications MODIFY COLUMN user_id INT NULL");
    } catch (\Throwable $e) {
        // Silently continue if database user has restricted DDL permissions
    }
}

/**
 * Get all active Admin user IDs
 * 
 * @param mysqli $conn
 * @return array List of admin user IDs
 */
function get_admin_user_ids($conn) {
    $adminIds = [];
    $stmt = $conn->query("SELECT id FROM users WHERE role = 'Admin' AND (status = 'Active' OR status IS NULL)");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $adminIds[] = (int)$row['id'];
        }
    }
    // Fallback if no admin is active
    if (empty($adminIds)) {
        $fallbackStmt = $conn->query("SELECT id FROM users WHERE role = 'Admin' LIMIT 1");
        if ($fallbackStmt && $row = $fallbackStmt->fetch_assoc()) {
            $adminIds[] = (int)$row['id'];
        }
    }
    return $adminIds;
}

/**
 * Insert a notification for a specific user
 * 
 * @param mysqli $conn
 * @param int $userId
 * @param string $type
 * @param string $title
 * @param string $message
 * @param int|null $requestId
 * @param int|null $assignmentId
 * @param int|null $donorId
 * @param int|null $relatedUserId
 * @return int|false Notification ID or false on failure
 */
function create_base_notification($conn, $type, $title, $message, $requestId = null, $assignmentId = null, $donorId = null, $relatedUserId = null) {
    static $hasExtraCols = null;
    if ($hasExtraCols === null) {
        $chk = $conn->query("SHOW COLUMNS FROM notifications LIKE 'donor_id'");
        $hasExtraCols = ($chk && $chk->num_rows > 0);
    }

    if ($hasExtraCols) {
        $stmt = $conn->prepare("
            INSERT INTO notifications 
            (request_id, assignment_id, donor_id, related_user_id, type, title, message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param("iiiisss", $requestId, $assignmentId, $donorId, $relatedUserId, $type, $title, $message);
            $stmt->execute();
            $insertId = $stmt->insert_id;
            $stmt->close();
            return $insertId;
        }
    } else {
        $stmt = $conn->prepare("
            INSERT INTO notifications 
            (request_id, assignment_id, type, title, message, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param("iisss", $requestId, $assignmentId, $type, $title, $message);
            $stmt->execute();
            $insertId = $stmt->insert_id;
            $stmt->close();
            return $insertId;
        }
    }
    return false;
}

function add_notification_recipient($conn, $notifId, $userId) {
    if (!$notifId || !$userId) return false;
    $stmt = $conn->prepare("INSERT INTO user_notifications (notification_id, user_id, is_read, is_deleted, created_at) VALUES (?, ?, 0, 0, NOW())");
    if ($stmt) {
        $stmt->bind_param("ii", $notifId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

function create_notification($conn, $userId, $type, $title, $message, $requestId = null, $assignmentId = null, $donorId = null, $relatedUserId = null) {
    $userId = (int)$userId;
    if ($userId <= 0) return false;

    $notifId = create_base_notification($conn, $type, $title, $message, $requestId, $assignmentId, $donorId, $relatedUserId);
    if ($notifId) {
        add_notification_recipient($conn, $notifId, $userId);
        return $notifId;
    }
    return false;
}

/**
 * Send a notification to all Admin users
 * 
 * @param mysqli $conn
 * @param string $type
 * @param string $title
 * @param string $message
 * @param int|null $requestId
 * @param int|null $assignmentId
 * @param int|null $donorId
 * @param int|null $relatedUserId
 * @return int Number of notifications created
 */
function notify_admins($conn, $type, $title, $message, $requestId = null, $assignmentId = null, $donorId = null, $relatedUserId = null) {
    $adminIds = get_admin_user_ids($conn);
    $notifId = create_base_notification($conn, $type, $title, $message, $requestId, $assignmentId, $donorId, $relatedUserId);
    if (!$notifId) return 0;
    
    $count = 0;
    foreach ($adminIds as $adminId) {
        if (add_notification_recipient($conn, $notifId, $adminId)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Get unread notification count for an Admin
 * 
 * @param mysqli $conn
 * @param int $adminId
 * @return int
 */
function get_admin_unread_count($conn, $adminId) {
    $adminId = (int)$adminId;
    $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM user_notifications WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return (int)($row['unread_count'] ?? 0);
    }
    return 0;
}

/**
 * Fetch Admin notifications with optional pagination and filters
 * 
 * @param mysqli $conn
 * @param int $adminId
 * @param int $limit
 * @param int $offset
 * @param string $filter 'all' | 'unread' | 'read'
 * @param string|null $typeFilter Optional notification type filter
 * @param string|null $search Optional search keyword
 * @return array
 */
function get_admin_notifications($conn, $adminId, $limit = 10, $offset = 0, $filter = 'all', $typeFilter = null, $search = null) {
    $adminId = (int)$adminId;
    $where = ["nr.user_id = ?", "nr.is_deleted = 0"];
    $types = "i";
    $params = [$adminId];

    if ($filter === 'unread') {
        $where[] = "nr.is_read = 0";
    } elseif ($filter === 'read') {
        $where[] = "nr.is_read = 1";
    } elseif ($filter === 'important') {
        $where[] = "(n.type IN ('Blood_Request', 'Security', 'Assignment', 'Assignment_Rejected') OR n.title LIKE '%urgent%' OR n.title LIKE '%emergency%' OR n.message LIKE '%urgent%')";
    }

    if (!empty($typeFilter) && $typeFilter !== 'all') {
        $where[] = "n.type = ?";
        $types .= "s";
        $params[] = $typeFilter;
    }

    if (!empty($search)) {
        $where[] = "(n.title LIKE ? OR n.message LIKE ?)";
        $types .= "ss";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    $whereClause = implode(" AND ", $where);
    $query = "
        SELECT 
            n.*, nr.is_read,
            br.requester_name,
            br.hospital,
            br.units AS request_units,
            br.urgency AS request_urgency,
            bg.blood_gp_name AS blood_group,
            d.blood_groups AS donor_blood_group,
            d.phone AS donor_phone,
            du.username AS donor_username,
            ru.username AS requester_username,
            ru.email AS requester_email,
            reg_u.username AS registered_username,
            reg_u.email AS registered_email
        FROM notifications n
        JOIN user_notifications nr ON n.id = nr.notification_id
        LEFT JOIN blood_request br ON n.request_id = br.id
        LEFT JOIN blood_groups bg ON br.blood_groups_id = bg.id
        LEFT JOIN donor d ON n.donor_id = d.id OR br.assigned_donor_id = d.id
        LEFT JOIN users du ON d.user_id = du.id
        LEFT JOIN users ru ON br.users_id = ru.id
        LEFT JOIN users reg_u ON n.related_user_id = reg_u.id
        WHERE {$whereClause}
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT ? OFFSET ?
    ";

    $types .= "ii";
    $params[] = (int)$limit;
    $params[] = (int)$offset;

    $stmt = $conn->prepare($query);
    if (!$stmt) return [];

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $notifications;
}

/**
 * Get total notification count for pagination
 */
function get_admin_notifications_count($conn, $adminId, $filter = 'all', $typeFilter = null, $search = null) {
    $adminId = (int)$adminId;
    $where = ["nr.user_id = ?", "nr.is_deleted = 0"];
    $types = "i";
    $params = [$adminId];

    if ($filter === 'unread') {
        $where[] = "nr.is_read = 0";
    } elseif ($filter === 'read') {
        $where[] = "nr.is_read = 1";
    } elseif ($filter === 'important') {
        $where[] = "(n.type IN ('Blood_Request', 'Security', 'Assignment', 'Assignment_Rejected') OR n.title LIKE '%urgent%' OR n.title LIKE '%emergency%' OR n.message LIKE '%urgent%')";
    }

    if (!empty($typeFilter) && $typeFilter !== 'all') {
        $where[] = "type = ?";
        $types .= "s";
        $params[] = $typeFilter;
    }

    if (!empty($search)) {
        $where[] = "(n.title LIKE ? OR n.message LIKE ?)";
        $types .= "ss";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
    }

    $whereClause = implode(" AND ", $where);
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications n JOIN user_notifications nr ON n.id = nr.notification_id WHERE {$whereClause}");
    if (!$stmt) return 0;

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($res['total'] ?? 0);
}

/**
 * Mark a single notification as read
 */
function mark_notification_read($conn, $notifId, $adminId) {
    $notifId = (int)$notifId;
    $adminId = (int)$adminId;
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $notifId, $adminId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Mark a single notification as unread
 */
function mark_notification_unread($conn, $notifId, $adminId) {
    $notifId = (int)$notifId;
    $adminId = (int)$adminId;
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 0 WHERE notification_id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $notifId, $adminId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Mark all notifications as read for an admin
 */
function mark_all_notifications_read($conn, $adminId) {
    $adminId = (int)$adminId;
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND is_deleted = 0");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Delete a single notification
 */
function delete_notification($conn, $notifId, $adminId) {
    $notifId = (int)$notifId;
    $adminId = (int)$adminId;
    $stmt = $conn->prepare("UPDATE user_notifications SET is_deleted = 1 WHERE notification_id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $notifId, $adminId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Delete all read notifications for an admin
 */
function delete_all_read_notifications($conn, $adminId) {
    $adminId = (int)$adminId;
    $stmt = $conn->prepare("UPDATE user_notifications SET is_deleted = 1 WHERE user_id = ? AND is_read = 1");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Get notification type visual details (icon, color, badge, target action link)
 * 
 * @param array|string $notifOrType Notification row array or type string
 * @return array
 */
function get_notification_meta($notifOrType) {
    $type = is_array($notifOrType) ? ($notifOrType['type'] ?? '') : $notifOrType;
    $notif = is_array($notifOrType) ? $notifOrType : [];

    $requestId = (int)($notif['request_id'] ?? 0);
    $donorId = (int)($notif['donor_id'] ?? 0);
    $userId = (int)($notif['related_user_id'] ?? 0);

    $default = [
        'label'       => 'System Notification',
        'icon'        => 'fa-bell',
        'badge_bg'    => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        'icon_color'  => 'text-gray-500',
        'action_url'  => 'notifications.php',
        'action_text' => 'View Details'
    ];

    switch ($type) {
        case 'User_Registration':
            return [
                'label'       => 'New User',
                'icon'        => 'fa-user-plus',
                'badge_bg'    => 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
                'icon_color'  => 'text-blue-600',
                'action_url'  => 'users_crud.php' . ($userId > 0 ? '?search=' . $userId : ''),
                'action_text' => 'Manage Users'
            ];

        case 'Donor_Registration':
            return [
                'label'       => 'New Donor',
                'icon'        => 'fa-heart-pulse',
                'badge_bg'    => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                'icon_color'  => 'text-emerald-600',
                'action_url'  => 'donor_crud.php' . ($donorId > 0 ? '?search=' . $donorId : ''),
                'action_text' => 'Manage Donors'
            ];

        case 'Blood_Request':
            return [
                'label'       => 'Blood Request',
                'icon'        => 'fa-hand-holding-medical',
                'badge_bg'    => 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300 border border-red-200 dark:border-red-800',
                'icon_color'  => 'text-red-600',
                'action_url'  => $requestId > 0 ? 'blood_requests_crud.php?view=' . $requestId : 'blood_requests_crud.php',
                'action_text' => 'View Request'
            ];

        case 'Blood_Request_Update':
            return [
                'label'       => 'Request Update',
                'icon'        => 'fa-pen-to-square',
                'badge_bg'    => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                'icon_color'  => 'text-amber-600',
                'action_url'  => $requestId > 0 ? 'blood_requests_crud.php?view=' . $requestId : 'blood_requests_crud.php',
                'action_text' => 'View Request'
            ];

        case 'Assignment':
            return [
                'label'       => 'Donor Assignment',
                'icon'        => 'fa-user-check',
                'badge_bg'    => 'bg-purple-50 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
                'icon_color'  => 'text-purple-600',
                'action_url'  => $requestId > 0 ? 'assignments.php?request_id=' . $requestId : 'assignments.php',
                'action_text' => 'View Assignment'
            ];

        case 'Assignment_Accepted':
        case 'StatusUpdate':
            if (isset($notif['title']) && strpos($notif['title'], 'Reject') !== false) {
                return [
                    'label'       => 'Donor Rejected',
                    'icon'        => 'fa-user-xmark',
                    'badge_bg'    => 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
                    'icon_color'  => 'text-rose-600',
                    'action_url'  => $requestId > 0 ? 'assignments.php?request_id=' . $requestId : 'assignments.php',
                    'action_text' => 'Reassign Donor'
                ];
            }
            return [
                'label'       => 'Donor Accepted',
                'icon'        => 'fa-circle-check',
                'badge_bg'    => 'bg-teal-50 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300 border border-teal-200 dark:border-teal-800',
                'icon_color'  => 'text-teal-600',
                'action_url'  => $requestId > 0 ? 'assignments.php?request_id=' . $requestId : 'assignments.php',
                'action_text' => 'View Assignment'
            ];

        case 'Assignment_Rejected':
            return [
                'label'       => 'Donor Rejected',
                'icon'        => 'fa-user-xmark',
                'badge_bg'    => 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
                'icon_color'  => 'text-rose-600',
                'action_url'  => $requestId > 0 ? 'assignments.php?request_id=' . $requestId : 'assignments.php',
                'action_text' => 'Reassign Donor'
            ];

        case 'Blood_Received':
            return [
                'label'       => 'Blood Received',
                'icon'        => 'fa-hand-holding-heart',
                'badge_bg'    => 'bg-green-50 text-green-700 dark:bg-green-900/40 dark:text-green-300 border border-green-200 dark:border-green-800',
                'icon_color'  => 'text-green-600',
                'action_url'  => 'donation_history_crud.php',
                'action_text' => 'Donation History'
            ];

        case 'Security':
            return [
                'label'       => 'Security Alert',
                'icon'        => 'fa-shield-halved',
                'badge_bg'    => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                'icon_color'  => 'text-amber-600',
                'action_url'  => 'profile.php',
                'action_text' => 'Account Security'
            ];

        default:
            return $default;
    }
}

/**
 * Format user-friendly flash message based on decoupled email results
 * 
 * @param string $actionTitle Primary action description (e.g., 'Donor assigned', 'Blood request submitted')
 * @param bool|array $emailResult Boolean or array ['success' => bool] from send_email
 * @return string
 */
function format_action_feedback($actionTitle, $emailResult) {
    $emailSuccess = is_array($emailResult) ? (!empty($emailResult['success'])) : (bool)$emailResult;
    $actionTitle = rtrim($actionTitle, ' .!');
    
    if ($emailSuccess) {
        return "{$actionTitle} successfully. Notification and email sent successfully.";
    } else {
        return "{$actionTitle} successfully. Website notification sent. Email could not be delivered.";
    }
}

