<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'coordinator') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$coord_id = $_SESSION['rased_user_id'];

// Get coordinator's subject
$stmt = $db->prepare("SELECT subject_id FROM rased_users WHERE id = ?");
$stmt->execute([$coord_id]);
$coord_subject = $stmt->fetchColumn();

// Get requests for teachers in this coordinator's subject (Section Tasks)
$stmt = $db->prepare("
    SELECT r.id, r.request_date, r.period_number, 
           c.name as class_name, 
           u1.name as requester_name, 
           u2.name as substitute_name,
           r.req_coordinator_status,
           r.sub_coordinator_status
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE (u1.subject_id = ? AND r.req_coordinator_status = 'pending')
       OR (u2.subject_id = ? AND r.sub_coordinator_status = 'pending')
");
$stmt->execute([$coord_subject, $coord_subject]);
$pending_requests = $stmt->fetchAll();

?>
<?php 
$page_title = 'لوحة المنسق - راصد تبديلاتي';
$active_page = 'home';
$base_url = '../';
include '../includes/header.php'; 
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= count($pending_requests) ?></h3>
                <p>طلبات بانتظار الموافقة</p>
            </div>
            <div class="stat-icon text-warning"><i class="bi bi-hourglass-split"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="border-right-color: var(--accent-color);">
            <div class="stat-info">
                <h5 class="fw-bold mb-1">إجراء سريع</h5>
                <a href="../teacher/request.php" class="btn btn-sm btn-accent">طلب تبديل لي</a>
            </div>
            <div class="stat-icon"><i class="bi bi-plus-circle"></i></div>
        </div>
    </div>
</div>

<div class="custom-card shadow-sm mb-4">
    <h2 class="h4 mb-4 fw-bold text-primary">طلبات القسم (تحتاج موافقتك)</h2>
    
    <?php if(!$coord_subject): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            تنبيه: لم يتم تعيين قسم / مادة دراسية لك. يرجى مراجعة النائب الأكاديمي لربط حسابك بالمادة.
        </div>
    <?php elseif(empty($pending_requests)): ?>
        <div class="text-center py-5">
            <i class="bi bi-check2-circle display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">لا توجد طلبات معلقة حالياً في قسمك.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المعلم الغائب</th>
                        <th>المعلم البديل</th>
                        <th>الصف</th>
                        <th>تاريخ الغياب</th>
                        <th>الحصة</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_requests as $req): ?>
                        <tr>
                            <td class="fw-bold text-primary">#<?= $req['id'] ?></td>
                            <td><?= htmlspecialchars($req['requester_name']) ?></td>
                            <td><?= htmlspecialchars($req['substitute_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($req['class_name']) ?></span></td>
                            <td><?= htmlspecialchars($req['request_date']) ?></td>
                            <td><span class="badge bg-info text-dark">حصة <?= $req['period_number'] ?></span></td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="btn btn-sm btn-success px-3" onclick="updateStatus(<?= $req['id'] ?>, 'approved')">موافقة</button>
                                    <button class="btn btn-sm btn-danger px-3" onclick="updateStatus(<?= $req['id'] ?>, 'rejected')">رفض</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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

