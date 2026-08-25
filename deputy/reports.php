<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status_filter = $_GET['status'] ?? 'all';

// Build SQL query with date & status filters
$sql = "
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
    WHERE r.request_date BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

if ($status_filter === 'approved') {
    $sql .= " AND (r.deputy_status = 'approved' OR r.deputy_status = 'approved_with_mod')";
} elseif ($status_filter === 'pending') {
    $sql .= " AND r.deputy_status = 'pending'";
} elseif ($status_filter === 'rejected') {
    $sql .= " AND r.deputy_status = 'rejected'";
}

$sql .= " ORDER BY r.request_date DESC, r.period_number ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Calculate Summary Metrics
$total_count = count($reports);
$approved_count = 0;
$pending_count = 0;
$rejected_count = 0;

foreach ($reports as $r) {
    if ($r['deputy_status'] === 'approved' || $r['deputy_status'] === 'approved_with_mod') {
        $approved_count++;
    } elseif ($r['deputy_status'] === 'rejected') {
        $rejected_count++;
    } else {
        $pending_count++;
    }
}

$page_title = 'تقارير التبديلات - راصد تبديلاتي';
$active_page = 'reports';
$base_url = '../';
include '../includes/header.php'; 
?>

<style>
    @media print {
        body { background: white !important; color: black !important; padding: 0 !important; font-family: 'Tajawal', sans-serif !important; }
        .main-content { margin-right: 0 !important; padding: 0 !important; }
        .sidebar, .topbar, .hide-print, #sidebarOverlay { display: none !important; }
        .custom-card { box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; }
        .print-header { display: block !important; }
        .print-footer { display: block !important; margin-top: 50px !important; page-break-inside: avoid; }
        .table-report th { background: #f1f5f9 !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .table-report td, .table-report th { border: 1px solid #333 !important; font-size: 0.9rem !important; padding: 8px !important; }
        .badge { border: 1px solid #666 !important; background: transparent !important; color: #000 !important; }
    }
    .print-header, .print-footer { display: none; }
</style>

<!-- Printable Header -->
<div class="print-header text-center mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom border-2 border-dark pb-3 mb-3">
        <div class="text-start">
            <h3 class="fw-bold mb-1 fs-5">دولة قطر - وزارة التربية والتعليم والتعليم العالي</h3>
            <h4 class="fw-bold mb-0 fs-6 text-muted">مدرسة معيذر الابتدائية للبنين</h4>
        </div>
        <div class="text-center">
            <span class="fs-1">🛰️</span>
            <div class="fw-bold small">نظام راصد تبديلاتي</div>
        </div>
        <div class="text-end small text-muted">
            <div>تاريخ التقرير: <?= date('Y-m-d') ?></div>
            <div>وقت الاستخراج: <?= date('H:i') ?></div>
        </div>
    </div>
    <h3 class="fw-bold bg-light py-2 rounded border">تقرير استبدال الحصص للفترة</h3>
    <p class="fw-bold text-secondary mb-0">من: <?= htmlspecialchars($start_date) ?> &nbsp;|&nbsp; إلى: <?= htmlspecialchars($end_date) ?></p>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 hide-print">
    <div>
        <h2 class="h3 fw-bold text-primary mb-1">
            <i class="bi bi-file-earmark-bar-graph me-2"></i>تقارير وإحصائيات التبديلات
        </h2>
        <p class="text-muted mb-0">استعراض وتصفية كافة طلبات استبدال الحصص والطباعة الرسمية</p>
    </div>
    <button type="button" class="btn btn-success btn-lg px-4 shadow-sm" onclick="window.print()">
        <i class="bi bi-printer-fill me-2"></i> طباعة التقرير
    </button>
</div>

<!-- Summary Metrics Cards (Hide on Print) -->
<div class="row g-3 mb-4 hide-print">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= $total_count ?></h3>
                <p>إجمالي الطلبات للفترة</p>
            </div>
            <div class="stat-icon text-primary"><i class="bi bi-files"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-right-color: var(--bs-success);">
            <div class="stat-info">
                <h3><?= $approved_count ?></h3>
                <p>الطلبات المعتمدة</p>
            </div>
            <div class="stat-icon text-success"><i class="bi bi-check-circle"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-right-color: var(--bs-warning);">
            <div class="stat-info">
                <h3><?= $pending_count ?></h3>
                <p>الطلبات المعلقة</p>
            </div>
            <div class="stat-icon text-warning"><i class="bi bi-hourglass-split"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-right-color: var(--bs-danger);">
            <div class="stat-info">
                <h3><?= $rejected_count ?></h3>
                <p>الطلبات المرفوضة</p>
            </div>
            <div class="stat-icon text-danger"><i class="bi bi-x-circle"></i></div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="custom-card shadow-sm mb-4 hide-print">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-bold text-secondary small"><i class="bi bi-calendar-event me-1"></i> من تاريخ:</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold text-secondary small"><i class="bi bi-calendar-event me-1"></i> إلى تاريخ:</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold text-secondary small"><i class="bi bi-funnel me-1"></i> حالة الطلب:</label>
            <select name="status" class="form-select">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>جميع الحالات</option>
                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>معتمد فقط</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>معلق فقط</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>مرفوض فقط</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-bold">
                <i class="bi bi-search me-1"></i> تصفية
            </button>
            <a href="reports.php" class="btn btn-outline-secondary" title="إعادة ضبط">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </form>
</div>

<!-- Report Table Card -->
<div class="custom-card shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 hide-print">
        <h3 class="h5 fw-bold text-primary mb-0"><i class="bi bi-journal-text me-2"></i>نتائج التقرير التفصيلية</h3>
        
        <!-- Live Instant Search -->
        <div class="input-group" style="max-width: 320px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-filter text-muted"></i></span>
            <input type="text" id="reportSearch" class="form-control border-start-0 ps-0" placeholder="بحث فورى (اسم المعلم، الصف، الرقم)...">
        </div>
    </div>

    <?php if (empty($reports)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted fs-5">لا توجد سجلات تبديل متوافقة مع شروط البحث للفترة المحددة.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center table-report mb-0" id="reportsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">م</th>
                        <th>تاريخ الطلب</th>
                        <th>المعلم الغائب</th>
                        <th>الصف والحصة</th>
                        <th>المعلم البديل</th>
                        <th>موعد التعويض</th>
                        <th>الحالة</th>
                        <th class="hide-print" style="width: 140px;">إجراءات الإدارة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $index => $req): ?>
                        <tr class="report-row">
                            <td class="fw-bold text-muted"><?= $index + 1 ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($req['request_date']) ?></div>
                                <span class="badge bg-light text-dark border">#<?= $req['id'] ?></span>
                            </td>
                            <td class="searchable-text fw-bold text-dark"><?= htmlspecialchars($req['requester_name']) ?></td>
                            <td>
                                <span class="badge bg-primary px-2 py-1 mb-1 searchable-text"><?= htmlspecialchars($req['class_name']) ?></span>
                                <div class="small fw-bold text-muted searchable-text">حصة <?= $req['period_number'] ?></div>
                            </td>
                            <td class="searchable-text fw-bold text-dark"><?= htmlspecialchars($req['substitute_name']) ?></td>
                            <td>
                                <?php if ($req['repayment_date']): ?>
                                    <div class="text-success fw-bold searchable-text"><?= htmlspecialchars($req['repayment_date']) ?></div>
                                    <small class="text-muted searchable-text">حصة <?= $req['repayment_period'] ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">غير محدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $st = $req['deputy_status'];
                                    if ($st === 'approved' || $st === 'approved_with_mod') {
                                        echo '<span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>معتمد</span>';
                                    } elseif ($st === 'rejected') {
                                        echo '<span class="badge bg-danger px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i>مرفوض</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-hourglass-split me-1"></i>معلق</span>';
                                    }
                                ?>
                            </td>
                            <td class="hide-print">
                                <div class="d-flex gap-1 justify-content-center">
                                    <?php if ($st === 'approved' || $st === 'approved_with_mod'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                onclick="revokeStatus(<?= $req['id'] ?>)" 
                                                title="إلغاء الاعتماد وإعادته للمعلق">
                                            <i class="bi bi-arrow-counterclockwise"></i> إلغاء
                                        </button>
                                    <?php endif; ?>
                                    <a href="../teacher/request.php?request_id=<?= $req['id'] ?>" class="btn btn-sm btn-outline-primary" title="تعديل الطلب">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Printable Signatures Footer -->
<div class="print-footer mt-5">
    <div class="d-flex justify-content-between align-items-center text-center mt-5 pt-4">
        <div style="width: 220px; border-top: 2px solid #333; padding-top: 8px;">
            <strong class="d-block">توقيع المنسق</strong>
        </div>
        <div style="width: 220px; border-top: 2px solid #333; padding-top: 8px;">
            <strong class="d-block">توقيع النائب الأكاديمي</strong>
        </div>
        <div style="width: 220px; border-top: 2px solid #333; padding-top: 8px;">
            <strong class="d-block">يعتمد ،، مدير المدرسة</strong>
        </div>
    </div>
</div>

<script>
// Live Search Filtering
document.getElementById('reportSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.report-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
});

// Revoke Approval Action
async function revokeStatus(requestId) {
    if (!confirm('هل أنت متأكد من إلغاء اعتماد هذا الطلب وإعادته لحالة المعلق؟')) return;
    
    try {
        const response = await fetch('api.php?action=revoke_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: requestId })
        });
        const data = await response.json();
        if (data.success) {
            alert('تم إلغاء اعتماد الطلب بنجاح.');
            location.reload();
        } else {
            alert(data.message || 'حدث خطأ أثناء تنفيذ الإلغاء');
        }
    } catch (err) {
        alert('حدث خطأ في الاتصال بالخادم.');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
