<?php
require_once 'config.php';
startSecureSession();
 
if (!isset($_SESSION['rased_user_id'])) {
    header('Location: login.php');
    exit;
}
 
$db = getDB();
$user_id = $_SESSION['rased_user_id'];
 
// 1. Fetch requests where I am the requester (Absent Teacher)
$stmtMy = $db->prepare("
    SELECT r.*, c.name as class_name, u2.name as substitute_name
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE r.requester_id = ?
    ORDER BY r.request_date DESC
");
$stmtMy->execute([$user_id]);
$my_absent_requests = $stmtMy->fetchAll();
 
// 2. Fetch requests where I am the substitute (Replacing Teacher)
$stmtSub = $db->prepare("
    SELECT r.*, c.name as class_name, u1.name as requester_name
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    WHERE r.substitute_id = ?
    ORDER BY r.request_date DESC
");
$stmtSub->execute([$user_id]);
$my_substitute_tasks = $stmtSub->fetchAll();
 
function statusBadge($status, $label_pending, $label_approved, $label_rejected) {
    if ($status === 'approved') {
        return '<span class="status-badge status-approved">✅ ' . $label_approved . '</span>';
    } elseif ($status === 'rejected') {
        return '<span class="status-badge status-rejected">❌ ' . $label_rejected . '</span>';
    } else {
        return '<span class="status-badge status-pending">⏳ ' . $label_pending . '</span>';
    }
}
?>
<?php 
$page_title = 'متابعة طلباتي - راصد تبديلاتي';
$active_page = 'requests';
$base_url = './';
include 'includes/header.php'; 

function statusBadgeV2($status, $label_pending, $label_approved, $label_rejected) {
    if ($status === 'approved') {
        return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>' . $label_approved . '</span>';
    } elseif ($status === 'rejected') {
        return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>' . $label_rejected . '</span>';
    } else {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass me-1"></i>' . $label_pending . '</span>';
    }
}
?>

<div class="custom-card shadow-sm mb-4">
    <div class="section-title h5 mb-4 fw-bold text-primary border-bottom pb-2">🗓️ طلبات التبديل التي قدمتها (أنا الغائب)</div>
    <?php if(empty($my_absent_requests)): ?>
        <div class="text-center py-4 text-muted italic">لم تقم بتقديم أي طلبات تبديل حتى الآن.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>الحصة</th>
                        <th>الصف</th>
                        <th>المعلم البديل</th>
                        <th>حالة البديل</th>
                        <th>موعد التعويض</th>
                        <th>النائب الأكاديمي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($my_absent_requests as $req): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($req['request_date']) ?></td>
                            <td><span class="badge bg-info text-dark">حصة <?= $req['period_number'] ?></span></td>
                            <td><?= htmlspecialchars($req['class_name']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($req['substitute_name']) ?></td>
                            <td><?= statusBadgeV2($req['sub_coordinator_status'], 'بانتظار البديل', 'وافق البديل', 'رفض البديل') ?></td>
                            <td>
                                <?= $req['repayment_date']
                                    ? '<div class="text-success fw-bold">'.htmlspecialchars($req['repayment_date']).'</div><small class="text-muted">الحصة '.$req['repayment_period'].'</small>'
                                    : '<span class="text-muted small">لم يحدد</span>' ?>
                            </td>
                            <td>
                                <?php
                                    $ds = $req['deputy_status'];
                                    if ($ds === 'approved' || $ds === 'approved_with_mod')
                                        echo '<span class="badge bg-primary text-white"><i class="bi bi-shield-fill-check me-1"></i>معتمد</span>';
                                    elseif ($ds === 'rejected')
                                        echo '<span class="badge bg-danger"><i class="bi bi-shield-fill-x me-1"></i>مرفوض</span>';
                                    else
                                        echo '<span class="badge bg-light text-dark border"><i class="bi bi-shield-fill-exclamation me-1"></i>بانتظار النائب</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="custom-card shadow-sm mb-4">
    <div class="section-title h5 mb-4 fw-bold text-success border-bottom pb-2">🤝 طلبات التغطية المكلف بها (أنا البديل)</div>
    <?php if(empty($my_substitute_tasks)): ?>
        <div class="text-center py-4 text-muted italic">لا توجد طلبات تغطية مكلف بها حالياً.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>الحصة</th>
                        <th>الصف</th>
                        <th>المعلم الغائب</th>
                        <th>موعد التعويض</th>
                        <th>النائب الأكاديمي</th>
                        <th>إجراءات البديل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($my_substitute_tasks as $req): ?>
                        <tr id="req-row-<?= $req['id'] ?>">
                            <td class="fw-bold"><?= htmlspecialchars($req['request_date']) ?></td>
                            <td><span class="badge bg-info text-dark">حصة <?= $req['period_number'] ?></span></td>
                            <td><?= htmlspecialchars($req['class_name']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($req['requester_name']) ?></td>
                            <td>
                                <?= $req['repayment_date']
                                    ? '<div class="text-success fw-bold">'.htmlspecialchars($req['repayment_date']).'</div><small class="text-muted">الحصة '.$req['repayment_period'].'</small>'
                                    : '<span class="text-muted small">لم يحدد</span>' ?>
                            </td>
                            <td>
                                <?php
                                    $ds = $req['deputy_status'];
                                    if ($ds === 'approved' || $ds === 'approved_with_mod')
                                        echo '<span class="badge bg-primary text-white"><i class="bi bi-shield-fill-check me-1"></i>معتمد</span>';
                                    elseif ($ds === 'rejected')
                                        echo '<span class="badge bg-danger"><i class="bi bi-shield-fill-x me-1"></i>مرفوض</span>';
                                    else
                                        echo '<span class="badge bg-light text-dark border"><i class="bi bi-shield-fill-exclamation me-1"></i>بانتظار النائب</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($req['sub_coordinator_status'] === 'pending'): ?>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button class="btn btn-sm btn-success" onclick="updateSubStatus(<?= $req['id'] ?>, 'approved')">موافقة</button>
                                        <button class="btn btn-sm btn-danger" onclick="updateSubStatus(<?= $req['id'] ?>, 'rejected')">رفض</button>
                                    </div>
                                <?php else: ?>
                                    <?= statusBadgeV2($req['sub_coordinator_status'], 'معلق', 'تمت الموافقة', 'تم الرفض') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
async function updateSubStatus(requestId, status) {
    if (!confirm('هل أنت متأكد من ' + (status === 'approved' ? 'الموافقة على' : 'رفض') + ' هذا الطلب؟')) return;
    
    try {
        const res = await fetch('teacher/api.php?action=sub_approve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId, status: status })
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'حدث خطأ');
        }
    } catch (err) {
        alert('خطأ في الاتصال');
    }
}
</script>

<?php include 'includes/footer.php'; ?>

