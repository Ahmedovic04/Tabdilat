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

// Get ALL requests for teachers in this coordinator's subject (Section Tasks) - Read Only
$stmt = $db->prepare("
    SELECT r.id, r.request_date, r.period_number, 
           c.name as class_name, 
           u1.name as requester_name, 
           u2.name as substitute_name,
           r.sub_coordinator_status,
           r.deputy_status,
           r.repayment_date,
           r.repayment_period
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE u1.subject_id = ? OR u2.subject_id = ?
    ORDER BY r.request_date DESC
");
$stmt->execute([$coord_subject, $coord_subject]);
$pending_requests = $stmt->fetchAll();

// Get Total Today's Substitutions (School-wide)
$stmtTodayTotal = $db->query("SELECT COUNT(*) FROM rased_requests WHERE request_date = CURDATE() AND deputy_status = 'approved'");
$today_total_count = $stmtTodayTotal->fetchColumn();
?>
<?php 
$page_title = 'لوحة المنسق - راصد تبديلاتي';
$active_page = 'home';
$base_url = '../';
include '../includes/header.php'; 
?>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= count($pending_requests) ?></h3>
                <p>طلبات التبديل في قسمك</p>
            </div>
            <div class="stat-icon text-warning"><i class="bi bi-hourglass-split"></i></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card" style="border-right-color: var(--accent-color);">
            <div class="stat-info">
                <h3><?= $today_total_count ?></h3>
                <p>تبديلات اليوم (مدرسة)</p>
            </div>
            <div class="stat-icon text-primary"><i class="bi bi-arrow-repeat"></i></div>
        </div>
    </div>
</div>

<div class="custom-card shadow-sm mb-4">
    <h2 class="h4 mb-4 fw-bold text-primary">تبديلات القسم (للمتابعة والاطلاع)</h2>
    
    <?php if(!$coord_subject): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            تنبيه: لم يتم تعيين قسم / مادة دراسية لك. يرجى مراجعة النائب الأكاديمي لربط حسابك بالمادة.
        </div>
    <?php elseif(empty($pending_requests)): ?>
        <div class="text-center py-5">
            <i class="bi bi-check2-circle display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">لا توجد تبديلات حالياً في قسمك.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>رقم الطلب</th>
                        <th>التاريخ</th>
                        <th>المعلم الغائب</th>
                        <th>الحصة / الصف</th>
                        <th>المعلم البديل</th>
                        <th>موعد التعويض</th>
                        <th>رد البديل</th>
                        <th>حالة النائب</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_requests as $req): ?>
                        <tr>
                            <td class="fw-bold text-primary">#<?= $req['id'] ?></td>
                            <td><?= htmlspecialchars($req['request_date']) ?></td>
                            <td><strong><?= htmlspecialchars($req['requester_name']) ?></strong></td>
                            <td>
                                <span class="badge bg-info text-dark">حصة <?= $req['period_number'] ?></span><br>
                                <small><?= htmlspecialchars($req['class_name']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($req['substitute_name']) ?></td>
                            <td>
                                <?php if($req['repayment_date']): ?>
                                    <div class="text-success fw-bold"><?= htmlspecialchars($req['repayment_date']) ?></div>
                                    <small class="text-muted">الحصة <?= $req['repayment_period'] ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">لم يحدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['sub_coordinator_status'] === 'approved'): ?>
                                    <span class="badge bg-success">✅ وافق</span>
                                <?php elseif ($req['sub_coordinator_status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">❌ رفض</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">⏳ بانتظار البديل</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $ds = $req['deputy_status'];
                                    if ($ds === 'approved' || $ds === 'approved_with_mod')
                                        echo '<span class="badge bg-primary">🛡️ معتمد</span>';
                                    elseif ($ds === 'rejected')
                                        echo '<span class="badge bg-danger">❌ مرفوض</span>';
                                    else
                                        echo '<span class="badge bg-light text-dark border">⏳ بانتظار النائب</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Coordinator view is now read-only as per latest requirements.
</script>

<?php include '../includes/footer.php'; ?>

