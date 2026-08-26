<?php
/**
 * Admin Notifications AJAX Handler
 * 
 * Provides dynamic AJAX actions for the Admin notification bell and dashboard.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notification_helper.php';

// Security check: Must be logged in as Admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$adminId = (int)($_SESSION['user_id'] ?? 0);
if ($adminId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid admin session.']);
    exit;
}

$action = $_REQUEST['action'] ?? 'get_latest';

/**
 * Format relative time (e.g., "5 mins ago", "Yesterday")
 */
function format_time_ago($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = max(1, floor($diff / 60));
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 172800) {
        return 'Yesterday · ' . date('g:i A', $time);
    } else {
        return date('M j, Y · g:i A', $time);
    }
}

try {
    switch ($action) {
        case 'get_latest':
            $limit = isset($_GET['limit']) ? min(20, max(1, (int)$_GET['limit'])) : 6;
            $unreadCount = get_admin_unread_count($conn, $adminId);
            $rawList = get_admin_notifications($conn, $adminId, $limit, 0, 'all');
            
            $formattedList = [];
            foreach ($rawList as $item) {
                $meta = get_notification_meta($item);
                $formattedList[] = [
                    'id'          => (int)$item['id'],
                    'type'        => $item['type'],
                    'title'       => $item['title'],
                    'message'     => $item['message'],
                    'is_read'     => (int)$item['is_read'],
                    'created_at'  => $item['created_at'],
                    'time_ago'    => format_time_ago($item['created_at']),
                    'label'       => $meta['label'],
                    'icon'        => $meta['icon'],
                    'badge_bg'    => $meta['badge_bg'],
                    'icon_color'  => $meta['icon_color'],
                    'action_url'  => $meta['action_url'],
                    'action_text' => $meta['action_text'],
                ];
            }
            
            echo json_encode([
                'success'       => true,
                'unread_count'  => $unreadCount,
                'notifications' => $formattedList
            ]);
            exit;

        case 'mark_read':
            $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($notifId > 0) {
                mark_notification_read($conn, $notifId, $adminId);
            }
            $unreadCount = get_admin_unread_count($conn, $adminId);
            echo json_encode([
                'success'      => true,
                'id'           => $notifId,
                'unread_count' => $unreadCount
            ]);
            exit;

        case 'mark_unread':
            $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($notifId > 0) {
                mark_notification_unread($conn, $notifId, $adminId);
            }
            $unreadCount = get_admin_unread_count($conn, $adminId);
            echo json_encode([
                'success'      => true,
                'id'           => $notifId,
                'unread_count' => $unreadCount
            ]);
            exit;

        case 'mark_all_read':
            mark_all_notifications_read($conn, $adminId);
            echo json_encode([
                'success'      => true,
                'unread_count' => 0,
                'message'      => 'All notifications marked as read.'
            ]);
            exit;

        case 'delete':
            $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($notifId > 0) {
                delete_notification($conn, $notifId, $adminId);
            }
            $unreadCount = get_admin_unread_count($conn, $adminId);
            echo json_encode([
                'success'      => true,
                'id'           => $notifId,
                'unread_count' => $unreadCount
            ]);
            exit;

        case 'delete_all_read':
            delete_all_read_notifications($conn, $adminId);
            $unreadCount = get_admin_unread_count($conn, $adminId);
            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount,
                'message'      => 'All read notifications have been deleted.'
            ]);
            exit;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action.'
            ]);
            exit;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
