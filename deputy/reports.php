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
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير التبديلات - راصد تبديلاتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --danger: #EF4444; --warning: #F59E0B;
            --bg-color: #F3F4F6; --card-bg: #FFFFFF; --text-main: #1F2937; --border-color: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); }
        .navbar {
            background: var(--card-bg); padding: 1rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; }
        .filter-form .group { display: flex; flex-direction: column; }
        .filter-form label { margin-bottom: 0.5rem; font-weight: bold; }
        .filter-form input { padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; }
        .btn {
            background: var(--primary); color: white; padding: 0.75rem 1.5rem;
            border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: 0.3s;
            text-decoration: none; display: inline-block;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-print { background: #1F2937; }
        .btn-print:hover { background: #000; }
        
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 1rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; color: var(--text-main); }
        
        .status-approved { color: var(--success); font-weight: bold; }
        .status-pending { color: var(--warning); font-weight: bold; }
        .status-rejected { color: var(--danger); font-weight: bold; }

        @media print {
            body { background: white; }
            .navbar, .filter-form, .btn-print, .hide-print { display: none !important; }
            .card { box-shadow: none; border: none; padding: 0; margin: 0; }
            table { border: 2px solid #000; }
            th, td { border: 1px solid #000; color: #000; }
            h2 { color: #000; text-align: center; margin-bottom: 2rem; }
        }
    </style>
</head>
<body>

<div class="navbar hide-print">
    <div class="brand">راصد تبديلاتي - التقارير الشاملة</div>
    <div>
        <a href="index.php" style="color: var(--primary); text-decoration: none; font-weight: bold;">العودة للوحة النائب</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>تقرير استبدال الحصص</h2>
        
        <form method="GET" class="filter-form">
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
            <p style="text-align:center; padding: 2rem; color: var(--text-muted);">لا توجد تبديلات في هذه الفترة المحددة.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>م</th>
                            <th>تاريخ الغياب</th>
                            <th>المعلم الغائب</th>
                            <th>الحصة / الصف</th>
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
                                    <?= htmlspecialchars($req['class_name']) ?> <br>
                                    <span style="font-size:0.9em; color:#6B7280;">(الحصة <?= $req['period_number'] ?>)</span>
                                </td>
                                <td><strong><?= htmlspecialchars($req['substitute_name']) ?></strong></td>
                                <td>
                                    <?php if($req['repayment_date']): ?>
                                        <?= htmlspecialchars($req['repayment_date']) ?> <br>
                                        <span style="font-size:0.9em; color:#6B7280;">(الحصة <?= $req['repayment_period'] ?>)</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        if($req['deputy_status'] === 'approved' || $req['deputy_status'] === 'approved_with_mod') 
                                            echo '<span class="status-approved">معتمد</span>';
                                        elseif($req['deputy_status'] === 'rejected') 
                                            echo '<span class="status-rejected">مرفوض</span>';
                                        else 
                                            echo '<span class="status-pending">قيد الانتظار</span>';
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
