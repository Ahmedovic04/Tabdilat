<?php
require_once 'config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['rased_user_id'];
$base_url = '';

$db = getDB();

// Filters
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? ''; // 'requester' or 'substitute'
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Build query
$where_conditions = ['(r.requester_id = ? OR r.substitute_id = ?)'];
$params = [$user_id, $user_id];

if ($search) {
    $where_conditions[] = '(u1.name LIKE ? OR u2.name LIKE ? OR c.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_from) {
    $where_conditions[] = 'r.request_date >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = 'r.request_date <= ?';
    $params[] = $date_to;
}

if ($status) {
    switch ($status) {
        case 'approved':
            $where_conditions[] = "(r.sub_coordinator_status = 'approved' OR r.deputy_status = 'approved')";
            break;
        case 'rejected':
            $where_conditions[] = "(r.sub_coordinator_status = 'rejected' OR r.deputy_status = 'rejected')";
            break;
        case 'pending':
            $where_conditions[] = "(r.sub_coordinator_status = 'pending' AND (r.deputy_status = 'pending' OR r.deputy_status IS NULL))";
            break;
    }
}

if ($role === 'requester') {
    $where_conditions[] = 'r.requester_id = ?';
    $params[] = $user_id;
} elseif ($role === 'substitute') {
    $where_conditions[] = 'r.substitute_id = ?';
    $params[] = $user_id;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM rased_requests r 
              JOIN rased_classes c ON r.class_id = c.id
              JOIN rased_users u1 ON r.requester_id = u1.id
              JOIN rased_users u2 ON r.substitute_id = u2.id
              WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get history records
$sql = "SELECT r.*, 
        c.name as class_name,
        u1.name as requester_name,
        u2.name as substitute_name,
        DATE_FORMAT(r.request_date, '%d/%m/%Y') as formatted_date,
        DATE_FORMAT(r.repayment_date, '%d/%m/%Y') as formatted_repayment_date
        FROM rased_requests r
        JOIN rased_classes c ON r.class_id = c.id
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE $where_clause
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.sub_coordinator_status = 'approved' OR r.deputy_status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN r.sub_coordinator_status = 'rejected' OR r.deputy_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN r.sub_coordinator_status = 'pending' AND (r.deputy_status = 'pending' OR r.deputy_status IS NULL) THEN 1 ELSE 0 END) as pending
    FROM rased_requests r
    WHERE (r.requester_id = ? OR r.substitute_id = ?)";
$stmt = $db->prepare($stats_sql);
$stmt->execute([$user_id, $user_id]);
$summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'سجل التبديلات - راصد تبديلاتي';
$active_page = 'history';

include 'includes/header.php';

// Helper function for status badge
function getStatusBadge($record) {
    if ($record['sub_coordinator_status'] === 'approved' || $record['deputy_status'] === 'approved') {
        return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>معتمد</span>';
    } elseif ($record['sub_coordinator_status'] === 'rejected' || $record['deputy_status'] === 'rejected') {
        return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>مرفوض</span>';
    } else {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>معلق</span>';
    }
}

// Helper function for role badge
function getRoleBadge($record, $user_id) {
    if ($record['requester_id'] == $user_id) {
        return '<span class="badge bg-info text-dark">طالب</span>';
    } else {
        return '<span class="badge bg-orange text-white">بديل</span>';
    }
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-clock-history text-primary me-2"></i>
                سجل التبديلات
            </h4>
            <p class="text-muted mb-0">عرض كامل سجل طلبات التبديل والتعويض</p>
        </div>
        
        <!-- Summary Stats -->
        <div class="d-flex gap-2">
            <span class="badge bg-primary">الكل: <?= $summary_stats['total'] ?></span>
            <span class="badge bg-success">معتمد: <?= $summary_stats['approved'] ?></span>
            <span class="badge bg-warning text-dark">معلق: <?= $summary_stats['pending'] ?></span>
            <span class="badge bg-danger">مرفوض: <?= $summary_stats['rejected'] ?></span>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="custom-card mb-4">
        <form method="GET" class="row g-3">
            <!-- Search -->
            <div class="col-md-3">
                <label class="form-label">البحث</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="اسم المعلم، الصف..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            
            <!-- Date From -->
            <div class="col-md-2">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            
            <!-- Date To -->
            <div class="col-md-2">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            
            <!-- Status -->
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select">
                    <option value="">الكل</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>معتمد</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>معلق</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                </select>
            </div>
            
            <!-- Role -->
            <div class="col-md-2">
                <label class="form-label">دوري</label>
                <select name="role" class="form-select">
                    <option value="">الكل</option>
                    <option value="requester" <?= $role === 'requester' ? 'selected' : '' ?>>طالب</option>
                    <option value="substitute" <?= $role === 'substitute' ? 'selected' : '' ?>>بديل</option>
                </select>
            </div>
            
            <!-- Buttons -->
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="history.php" class="btn btn-outline-secondary w-100" title="إعادة تعيين">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Count -->
    <div class="mb-3">
        <span class="text-muted">
            عرض <?= count($history) ?> من <?= $total_records ?> نتيجة
            <?php if ($page > 1): ?>
                - الصفحة <?= $page ?> من <?= $total_pages ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- History Table -->
    <div class="custom-card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>الحصة</th>
                        <th>الصف</th>
                        <th>الطالب</th>
                        <th>البديل</th>
                        <th>التعويض</th>
                        <th>الحالة</th>
                        <th>دوري</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2 mb-0">لا توجد نتائج مطابقة للبحث</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $index => $record): ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td><?= $record['formatted_date'] ?></td>
                                <td><span class="badge bg-info text-dark">حصة <?= $record['period_number'] ?></span></td>
                                <td><?= htmlspecialchars($record['class_name']) ?></td>
                                <td><?= htmlspecialchars($record['requester_name']) ?></td>
                                <td><?= htmlspecialchars($record['substitute_name']) ?></td>
                                <td>
                                    <?php if ($record['repayment_date']): ?>
                                        <small class="text-success">
                                            <?= $record['formatted_repayment_date'] ?><br>
                                            حصة <?= $record['repayment_period'] ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($record) ?></td>
                                <td><?= getRoleBadge($record, $user_id) ?></td>
                                <td>
                                    <a href="my_requests.php?id=<?= $record['id'] ?>" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <!-- Previous -->
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                
                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <!-- Next -->
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<style>
.bg-orange {
    background-color: #FF9800 !important;
}
.text-orange {
    color: #FF9800 !important;
}
</style>

<?php include 'includes/footer.php'; ?>
