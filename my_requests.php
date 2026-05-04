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
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>متابعة طلباتي - راصد تبديلاتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B;
            --bg-color: #F3F4F6; --card-bg: #FFFFFF; --text-main: #1F2937; --border-color: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); }
        .navbar { background: var(--card-bg); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        h2 { color: var(--primary); margin-bottom: 1.5rem; border-bottom: 2px solid var(--primary); display: inline-block; padding-bottom: 5px; }
 
        table { width: 100%; border-collapse: collapse; text-align: center; margin-bottom: 2rem; }
        th, td { padding: 0.85rem 1rem; border: 1px solid var(--border-color); vertical-align: middle; }
        th { background: #F9FAFB; font-weight: 700; color: #4B5563; }
 
        .status-badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.82rem; font-weight: bold; white-space: nowrap; }
        .status-pending  { background: #FEF3C7; color: #D97706; }
        .status-approved { background: #D1FAE5; color: #059669; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
 
        /* Approval pipeline display */
        .pipeline { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
 
        .section-title { background: #EEF2FF; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; color: #4338CA; font-weight: 800; }
 
        .deputy-final-approved { background: #D1FAE5; color: #065F46; font-weight: bold; }
        .deputy-final-pending  { background: #FEF9C3; color: #92400E; }
        .deputy-final-rejected { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>
 
<div class="navbar">
    <div class="brand">مركز متابعة الطلبات</div>
    <div>
        <a href="<?= $_SESSION['rased_role'] ?>/index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة الرئيسية</a>
    </div>
</div>
 
<div class="container">
 
    <!-- القسم الأول: طلبات الغياب الخاصة بي -->
    <div class="card">
        <div class="section-title">🗓️ طلبات التبديل التي قدمتها (أنا الغائب)</div>
        <?php if(empty($my_absent_requests)): ?>
            <p style="padding: 1rem; color: #6B7280;">لم تقم بتقديم أي طلبات تبديل حتى الآن.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>تاريخ الغياب</th>
                            <th>الحصة</th>
                            <th>الصف</th>
                            <th>المعلم البديل</th>
                            <th>موعد التعويض</th>
                            <th>منسق الغائب</th>
                            <th>منسق البديل</th>
                            <th>النائب الأكاديمي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($my_absent_requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['request_date']) ?></td>
                                <td><?= $req['period_number'] ?></td>
                                <td><?= htmlspecialchars($req['class_name']) ?></td>
                                <td><strong><?= htmlspecialchars($req['substitute_name']) ?></strong></td>
                                <td>
                                    <?= $req['repayment_date']
                                        ? htmlspecialchars($req['repayment_date']) . '<br><small style="color:#6B7280;">الحصة ' . $req['repayment_period'] . '</small>'
                                        : '<span style="color:#9CA3AF;">لم يحدد</span>' ?>
                                </td>
                                <!-- منسق الغائب -->
                                <td><?= statusBadge($req['req_coordinator_status'], 'بانتظار المنسق', 'وافق المنسق', 'رفض المنسق') ?></td>
                                <!-- منسق البديل -->
                                <td><?= statusBadge($req['sub_coordinator_status'], 'بانتظار المنسق', 'وافق المنسق', 'رفض المنسق') ?></td>
                                <!-- النائب -->
                                <td>
                                    <?php
                                        $ds = $req['deputy_status'];
                                        if ($ds === 'approved' || $ds === 'approved_with_mod')
                                            echo '<span class="status-badge status-approved">✅ معتمد</span>';
                                        elseif ($ds === 'rejected')
                                            echo '<span class="status-badge status-rejected">❌ مرفوض</span>';
                                        else
                                            echo '<span class="status-badge status-pending">⏳ بانتظار النائب</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
 
    <!-- القسم الثاني: المهام المكلف بها كبديل -->
    <div class="card">
        <div class="section-title">🤝 طلبات التغطية المكلف بها (أنا البديل)</div>
        <?php if(empty($my_substitute_tasks)): ?>
            <p style="padding: 1rem; color: #6B7280;">لا توجد طلبات تغطية مكلف بها حالياً.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>تاريخ التغطية</th>
                            <th>الحصة</th>
                            <th>الصف</th>
                            <th>المعلم الغائب</th>
                            <th>منسق الغائب</th>
                            <th>منسق البديل</th>
                            <th>النائب الأكاديمي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($my_substitute_tasks as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['request_date']) ?></td>
                                <td><?= $req['period_number'] ?></td>
                                <td><?= htmlspecialchars($req['class_name']) ?></td>
                                <td><strong><?= htmlspecialchars($req['requester_name']) ?></strong></td>
                                <td><?= statusBadge($req['req_coordinator_status'], 'بانتظار المنسق', 'وافق المنسق', 'رفض المنسق') ?></td>
                                <td><?= statusBadge($req['sub_coordinator_status'], 'بانتظار المنسق', 'وافق المنسق', 'رفض المنسق') ?></td>
                                <td>
                                    <?php
                                        $ds = $req['deputy_status'];
                                        if ($ds === 'approved' || $ds === 'approved_with_mod')
                                            echo '<span class="status-badge status-approved">✅ معتمد</span>';
                                        elseif ($ds === 'rejected')
                                            echo '<span class="status-badge status-rejected">❌ مرفوض</span>';
                                        else
                                            echo '<span class="status-badge status-pending">⏳ بانتظار النائب</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
 
</div>
 
</body>
</html>
