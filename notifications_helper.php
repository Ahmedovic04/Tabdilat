<?php
require_once 'config.php';

/**
 * Create a notification for a user
 */
function createNotification($user_id, $type, $title, $message, $related_request_id = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO rased_notifications (user_id, type, title, message, related_request_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $type, $title, $message, $related_request_id]);
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications count for a user
 */
function getUnreadCount($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM rased_notifications
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get notifications for a user
 */
function getNotifications($user_id, $limit = 20, $unread_only = false) {
    try {
        $db = getDB();
        $sql = "
            SELECT n.*, 
                   DATE_FORMAT(n.created_at, '%Y-%m-%d %H:%i') as formatted_date,
                   CASE 
                       WHEN n.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'قبل قليل'
                       WHEN n.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN CONCAT(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), ' ساعة')
                       WHEN n.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN CONCAT(TIMESTAMPDIFF(DAY, n.created_at, NOW()), ' يوم')
                       ELSE DATE_FORMAT(n.created_at, '%Y-%m-%d')
                   END as time_ago
            FROM rased_notifications n
            WHERE n.user_id = ?
        ";
        
        if ($unread_only) {
            $sql .= " AND n.is_read = FALSE";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Mark notification as read
 */
function markAsRead($notification_id, $user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE rased_notifications
            SET is_read = TRUE
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllAsRead($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE rased_notifications
            SET is_read = TRUE
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Create notifications when a request is submitted
 */
function notifyRequestSubmitted($request_id, $requester_id, $substitute_id, $request_date, $period_number) {
    // Notify substitute
    createNotification(
        $substitute_id,
        'substitution_request',
        'طلب تبديل جديد',
        "تم إرسال طلب تبديل حصة جديد لك بتاريخ $request_date الحصة $period_number",
        $request_id
    );
}

/**
 * Create notifications when a request is approved by substitute
 */
function notifyRequestApproved($request_id, $requester_id, $substitute_id, $substitute_name) {
    // Notify requester
    createNotification(
        $requester_id,
        'request_approved',
        'تمت الموافقة على طلبك',
        "قام $substitute_name بالموافقة على طلب تبديل الحصة",
        $request_id
    );
}

/**
 * Create notifications when a request is rejected
 */
function notifyRequestRejected($request_id, $requester_id, $substitute_id, $substitute_name) {
    // Notify requester
    createNotification(
        $requester_id,
        'request_rejected',
        'تم رفض طلبك',
        "قام $substitute_name برفض طلب تبديل الحصة",
        $request_id
    );
}

/**
 * Create notification for compensation reminder
 */
function notifyCompensationReminder($user_id, $request_id, $repayment_date, $repayment_period) {
    createNotification(
        $user_id,
        'compensation_reminder',
        'تذكير بتعويض حصة',
        "لديك تعويض حصة مجدول غداً $repayment_date الحصة $repayment_period",
        $request_id
    );
}

/**
 * Delete old notifications (keep last 90 days)
 */
function cleanupOldNotifications() {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            DELETE FROM rased_notifications
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}
