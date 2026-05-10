<?php
require_once 'config.php';
require_once 'notifications_helper.php';

startSecureSession();

// Check authentication
if (!isset($_SESSION['rased_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['rased_user_id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_notifications':
        $limit = intval($_GET['limit'] ?? 20);
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        $notifications = getNotifications($user_id, $limit, $unread_only);
        $unread_count = getUnreadCount($user_id);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'mark_read':
        $data = json_decode(file_get_contents('php://input'), true);
        $notification_id = intval($data['notification_id'] ?? 0);
        
        if ($notification_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        
        $success = markAsRead($notification_id, $user_id);
        $unread_count = getUnreadCount($user_id);
        
        echo json_encode([
            'success' => $success,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'mark_all_read':
        $count = markAllAsRead($user_id);
        
        echo json_encode([
            'success' => true,
            'marked_count' => $count,
            'unread_count' => 0
        ]);
        break;
        
    case 'get_unread_count':
        $count = getUnreadCount($user_id);
        
        echo json_encode([
            'success' => true,
            'unread_count' => $count
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
