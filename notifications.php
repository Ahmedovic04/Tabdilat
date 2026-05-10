<?php
require_once 'config.php';
require_once 'notifications_helper.php';
startSecureSession();

// Check authentication
if (!isset($_SESSION['rased_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['rased_user_id'];
$filter = $_GET['filter'] ?? 'all'; // all, unread

// Get notifications
$unread_only = ($filter === 'unread');
$notifications = getNotifications($user_id, 50, $unread_only);
$unread_count = getUnreadCount($user_id);

$page_title = 'الإشعارات - راصد تبديلاتي';
$active_page = 'notifications';
$base_url = '';

include 'includes/header.php';
?>

<div class="container-fluid py-4 notifications-page">
    <div class="row">
        <div class="col-12">
            <div class="custom-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">
                            <i class="bi bi-bell text-primary me-2"></i>
                            الإشعارات
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unread_count ?> جديد</span>
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted mb-0">إدارة جميع إشعاراتك في النظام</p>
                    </div>
                    
                    <?php if ($unread_count > 0): ?>
                        <button class="btn btn-outline-primary btn-sm" onclick="markAllReadAndReload()">
                            <i class="bi bi-check-all me-1"></i>
                            تحديد الكل كمقروء
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Filter Buttons -->
                <div class="mb-4">
                    <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                        الكل
                    </a>
                    <a href="?filter=unread" class="filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">
                        غير مقروء (<?= $unread_count ?>)
                    </a>
                </div>
                
                <!-- Notifications List -->
                <div class="notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-bell-slash text-muted" style="font-size: 4rem;"></i>
                            </div>
                            <h5 class="text-muted">لا توجد إشعارات</h5>
                            <p class="text-muted mb-0">
                                <?= $filter === 'unread' ? 'لا توجد إشعارات غير مقروءة' : 'سيتم إظهار الإشعارات الجديدة هنا' ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                <div class="d-flex align-items-start">
                                    <?php
                                    $icons = [
                                        'substitution_request' => 'bi-person-plus',
                                        'request_approved' => 'bi-check-circle',
                                        'request_rejected' => 'bi-x-circle',
                                        'compensation_reminder' => 'bi-calendar-event',
                                        'system' => 'bi-gear'
                                    ];
                                    $icon = $icons[$notification['type']] ?? 'bi-bell';
                                    ?>
                                    <div class="notification-icon <?= $notification['type'] ?> me-3">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                            <small class="text-muted" style="white-space: nowrap;">
                                                <?= $notification['time_ago'] ?>
                                            </small>
                                        </div>
                                        <p class="mb-2 text-muted"><?= htmlspecialchars($notification['message']) ?></p>
                                        <div class="d-flex gap-2">
                                            <?php if (!$notification['is_read']): ?>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="markAsReadAndReload(<?= $notification['id'] ?>)">
                                                    تحديد كمقروء
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($notification['related_request_id']): ?>
                                                <a href="/my_requests.php?id=<?= $notification['related_request_id'] ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    عرض الطلب
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function markAllReadAndReload() {
    try {
        const response = await fetch('/notifications_api.php?action=mark_all_read', {
            method: 'POST'
        });
        const data = await response.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function markAsReadAndReload(notificationId) {
    try {
        const response = await fetch('/notifications_api.php?action=mark_read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notification_id: notificationId })
        });
        const data = await response.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
