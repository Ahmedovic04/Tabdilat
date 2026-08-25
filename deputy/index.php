<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

$stmt = $db->query("
    SELECT r.id, r.request_date, r.repayment_date, r.repayment_period, r.period_number, 
           c.name as class_name, 
           u1.name as requester_name, 
           u2.name as substitute_name,
           r.req_coordinator_status,
           r.sub_coordinator_status,
           r.deputy_status
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE r.deputy_status = 'pending'
");
$pending_requests = $stmt->fetchAll();

$stmtNew = $db->query("SELECT COUNT(*) FROM rased_users WHERE is_new = 1 AND role = 'teacher'");
$new_teachers_count = $stmtNew->fetchColumn();

// Get Total Today's Substitutions (School-wide)
$stmtTodayTotal = $db->query("SELECT COUNT(*) FROM rased_requests WHERE request_date = CURDATE() AND deputy_status = 'approved'");
$today_total_count = $stmtTodayTotal->fetchColumn();
?>
<?php 
$page_title = 'لوحة النائب الأكاديمي - راصد تبديلاتي';
$active_page = 'home';
$base_url = '../';
include '../includes/header.php'; 

function statusBadgeFinal($status) {
    if ($status === 'approved') {
        return '<span class="badge bg-success py-2 px-3 shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>موافق</span>';
    } elseif ($status === 'rejected') {
        return '<span class="badge bg-danger py-2 px-3 shadow-sm"><i class="bi bi-x-circle-fill me-1"></i>مرفوض</span>';
    } else {
        return '<span class="badge bg-warning text-dark py-2 px-3 shadow-sm"><i class="bi bi-hourglass-split me-1"></i>معلق</span>';
    }
}
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= count($pending_requests) ?></h3>
                <p>طلبات بانتظار الاعتماد</p>
            </div>
            <div class="stat-icon text-primary"><i class="bi bi-file-earmark-check"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-right-color: var(--accent-color);">
            <div class="stat-info">
                <h3><?= $today_total_count ?></h3>
                <p>تبديلات اليوم</p>
            </div>
            <div class="stat-icon text-warning"><i class="bi bi-arrow-repeat"></i></div>
        </div>
    </div>
    <?php if($new_teachers_count > 0): ?>
    <div class="col-md-3">
        <div class="stat-card" style="border-right-color: var(--danger);">
            <div class="stat-info">
                <h3><?= $new_teachers_count ?></h3>
                <p>موظفين جدد</p>
            </div>
            <div class="stat-icon text-danger"><i class="bi bi-person-plus"></i></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="custom-card shadow-sm mb-4">
    <h2 class="h4 mb-4 fw-bold text-primary">الطلبات المعلقة (الموافقات النهائية)</h2>
    
    <?php if(empty($pending_requests)): ?>
        <div class="text-center py-5">
            <i class="bi bi-shield-check display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">لا توجد طلبات معلقة حالياً.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المعلم الغائب</th>
                        <th>المعلم البديل</th>
                        <th>الصف / الحصة</th>
                        <th>موعد التعويض</th>
                        <th>إجراء المدير</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_requests as $req): ?>
                        <tr>
                            <td class="fw-bold text-primary">#<?= $req['id'] ?></td>
                            <td><strong><?= htmlspecialchars($req['requester_name']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($req['substitute_name']) ?></strong></td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($req['class_name']) ?></span>
                                <div class="small mt-1 text-muted"><?= htmlspecialchars($req['request_date']) ?> (ح<?= $req['period_number'] ?>)</div>
                            </td>
                            <td>
                                <?php if($req['repayment_date']): ?>
                                    <div class="text-success small fw-bold"><?= htmlspecialchars($req['repayment_date']) ?></div>
                                    <span class="small text-muted">ح <?= $req['repayment_period'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">غير محدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="btn btn-sm btn-primary px-3 shadow-sm" onclick="updateStatus(<?= $req['id'] ?>, 'approved')"><i class="bi bi-check-lg"></i> اعتماد</button>
                                    <button class="btn btn-sm btn-outline-danger px-3 shadow-sm" onclick="updateStatus(<?= $req['id'] ?>, 'rejected')"><i class="bi bi-x-lg"></i> رفض</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="custom-card shadow-sm border-0 bg-white">
    <h2 class="h4 mb-4 fw-bold text-primary"><i class="bi bi-tools me-2"></i> أدوات الإدارة والتقارير</h2>
    
    <?php if($new_teachers_count > 0): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
            <div>
                <strong>تنبيه للمدير:</strong> تم اكتشاف <?= $new_teachers_count ?> موظف جديد. يرجى الدخول لإدارة الموظفين لتحديد صلاحياتهم.
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row g-3">
        <div class="col-md-3">
            <a href="../teacher/schedule.php" class="d-block p-4 text-center text-decoration-none bg-light rounded-4 hover-shadow transition">
                <i class="bi bi-calendar-plus display-5 text-primary mb-3"></i>
                <h3 class="h5 text-dark fw-bold">إضافة صفوف وجداول</h3>
                <p class="small text-muted mb-0">إضافة صفوف دراسية وتعديل جدول الحصص</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="reports.php" class="d-block p-4 text-center text-decoration-none bg-light rounded-4 hover-shadow transition">
                <i class="bi bi-file-earmark-pdf display-5 text-info mb-3"></i>
                <h3 class="h5 text-dark fw-bold">التقارير</h3>
                <p class="small text-muted mb-0">استخراج تقارير التبديلات والطباعة</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="users.php" class="d-block p-4 text-center text-decoration-none bg-light rounded-4 hover-shadow transition">
                <i class="bi bi-person-vcard display-5 text-secondary mb-3"></i>
                <h3 class="h5 text-dark fw-bold">الموظفين</h3>
                <p class="small text-muted mb-0">إدارة الصلاحيات والبيانات</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="upload.php" class="d-block p-4 text-center text-decoration-none bg-light rounded-4 hover-shadow transition">
                <i class="bi bi-file-earmark-spreadsheet display-5 text-success mb-3"></i>
                <h3 class="h5 text-dark fw-bold">رفع الجداول</h3>
                <p class="small text-muted mb-0">تحديث الجدول المدرسي (Excel)</p>
            </a>
        </div>
    </div>
</div>

<script>
async function updateStatus(id, status) {
    if(!confirm('هل أنت متأكد من هذا الإجراء؟')) return;
    
    try {
        const res = await fetch('api.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: id, status: status })
        });
        const data = await res.json();
        if(data.success) {
            alert('تم التحديث بنجاح');
            location.reload();
        } else {
            alert(data.message || 'حدث خطأ');
        }
    } catch(err) {
        alert('خطأ في الاتصال');
    }
}
</script>

<?php include '../includes/footer.php'; ?>

