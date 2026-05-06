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

$stmt = $db->prepare("
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
    ORDER BY r.request_date DESC
");
$stmt->execute([$start_date, $end_date]);
$reports = $stmt->fetchAll();
?>
<?php 
$page_title = 'تقارير التبديلات - راصد تبديلاتي';
$active_page = 'reports';
$base_url = '../';
include '../includes/header.php'; 
?>

<div class="custom-card shadow-sm p-4">
    <style>
        @media print {
            body { background: white !important; padding: 0 !important; }
            .container { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .navbar, .filter-form, .hide-print, .sidebar, .top-nav { display: none !important; }
            .card, .custom-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .report-footer { display: block !important; }
            .table-report th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
            @page { margin: 1.5cm; }
        }
        .table-report { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: center; }
        .table-report th { background: #f1f5f9; padding: 12px 10px; border: 1px solid var(--border-color); font-weight: 800; color: var(--primary); font-size: 0.9rem; }
        .table-report td { padding: 12px 10px; border: 1px solid var(--border-color); font-size: 0.95rem; line-height: 1.4; }
    </style>
    <!-- Professional Header for Print -->
    <div class="report-header text-center mb-5">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px;">
            <div style="text-align: right;">
                <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 5px;">مدرسة معيذر الابتدائية للبنين</h1>
                <p style="margin: 0; color: #666;">نظام راصد تبديلاتي</p>
            </div>
            <div style="text-align: center;">
                <img src="../assets/img/logo.png" alt="Logo" style="height: 80px; display: none;" onerror="this.style.display='none';">
                <span style="font-size: 3rem;">🛰️</span>
            </div>
            <div style="text-align: left; font-size: 0.9rem; color: #666;">
                <div>تاريخ التقرير: <?= date('Y-m-d') ?></div>
                <div>وقت الاستخراج: <?= date('H:i') ?></div>
            </div>
        </div>
        <h2 class="fw-bold" style="background: #f8f9fa; padding: 10px; border-radius: 8px;">تقرير استبدال الحصص للفترة</h2>
        <p class="text-muted">من: <?= $start_date ?> | إلى: <?= $end_date ?></p>
    </div>
    
    <div class="card shadow-sm border-0">
        <form method="GET" class="filter-form hide-print">
            <div class="group">
                <label>من تاريخ:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
            </div>
            <div class="group">
                <label>إلى تاريخ:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
            </div>
            <div class="group">
                <button type="submit" class="btn">تصفية التقرير</button>
            </div>
            <div class="group" style="margin-right: auto;">
                <button type="button" class="btn btn-print" onclick="window.print()">🖨️ طباعة التقرير</button>
            </div>
        </form>

        <?php if(empty($reports)): ?>
            <div class="text-center py-5">
                <p style="font-size: 1.2rem; color: #6B7280;">لا توجد تبديلات في هذه الفترة المحددة.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table-report">
                    <thead>
                        <tr>
                            <th>م</th>
                            <th>تاريخ الغياب</th>
                            <th>المعلم الغائب</th>
                            <th>الصف / الحصة</th>
                            <th>المعلم البديل</th>
                            <th>موعد التعويض</th>
                            <th>الحالة النهائية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reports as $index => $req): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($req['request_date']) ?></td>
                                <td><strong><?= htmlspecialchars($req['requester_name']) ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($req['class_name']) ?></strong><br>
                                    <span class="period-label">حصة <?= $req['period_number'] ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($req['substitute_name']) ?></strong></td>
                                <td>
                                    <?php if($req['repayment_date']): ?>
                                        <strong><?= htmlspecialchars($req['repayment_date']) ?></strong><br>
                                        <span class="period-label">حصة <?= $req['repayment_period'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="hide-print" style="margin-bottom: 5px;">
                                    <?php if($req['deputy_status'] === 'approved' || $req['deputy_status'] === 'approved_with_mod'): ?>
                                        <button class="btn" style="background: var(--danger); padding: 4px 8px; font-size: 0.75rem;" onclick="revokeStatus(<?= $req['id'] ?>)">
                                            ⚠️ إلغاء الاعتماد
                                        </button>
                                        &nbsp;
                                        <a href="../teacher/request.php?request_id=<?= $req['id'] ?>" class="btn" style="background: var(--primary); padding: 4px 8px; font-size: 0.75rem; text-decoration: none;">
                                            ✏️ تعديل
                                        </a>
                                    <?php endif; ?>
                                    </div>
                                    <?php 
                                        if($req['deputy_status'] === 'approved' || $req['deputy_status'] === 'approved_with_mod') 
                                            echo '<span class="status-badge approved">معتمد</span>';
                                        elseif($req['deputy_status'] === 'rejected') 
                                            echo '<span class="status-badge rejected">مرفوض</span>';
                                        else 
                                            echo '<span class="status-badge pending">معلق</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="report-footer mt-5" style="display: none;">
        <div style="display: flex; justify-content: space-between; margin-top: 50px;">
            <div style="text-align: center; width: 200px; border-top: 1px solid #333; padding-top: 10px;">
                توقيع المنسق
            </div>
            <div style="text-align: center; width: 200px; border-top: 1px solid #333; padding-top: 10px;">
                توقيع النائب الأكاديمي
            </div>
            <div style="text-align: center; width: 200px; border-top: 1px solid #333; padding-top: 10px;">
                يعتمد ،، مدير المدرسة
            </div>
        </div>
</div>

<?php include '../includes/footer.php'; ?>
