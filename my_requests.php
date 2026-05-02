<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator'])) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['rased_user_id'];

// Fetch my personal requests
$stmtMy = $db->prepare("
    SELECT r.*, c.name as class_name, u2.name as substitute_name
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE r.requester_id = ?
    ORDER BY r.request_date DESC
");
$stmtMy->execute([$user_id]);
$my_requests = $stmtMy->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي - راصد تبديلاتي</title>
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
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        h2 { color: var(--primary); margin-bottom: 1.5rem; }
        
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 1rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; }
        
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: bold; }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-approved { background: #D1FAE5; color: #059669; }
        .status-rejected { background: #FEE2E2; color: #DC2626; }
        
        .btn {
            background: var(--primary); color: white; padding: 0.5rem 1rem;
            border: none; border-radius: 6px; cursor: pointer; transition: 0.3s;
            text-decoration: none; display: inline-block;
        }
        .btn:hover { background: var(--primary-hover); }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">متابعة طلباتي</div>
    <div>
        <a href="<?= $_SESSION['rased_role'] ?>/index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة الرئيسية</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>قائمة طلبات التبديل الخاصة بي</h2>
        <?php if(empty($my_requests)): ?>
            <p>لا توجد طلبات سابقة لك حالياً.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الحصة</th>
                            <th>البديل</th>
                            <th>موعد التعويض</th>
                            <th>حالة المنسق</th>
                            <th>حالة النائب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($my_requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['request_date']) ?></td>
                                <td><?= $req['period_number'] ?></td>
                                <td><strong><?= htmlspecialchars($req['substitute_name']) ?></strong></td>
                                <td><?= $req['repayment_date'] ? htmlspecialchars($req['repayment_date']) : '-' ?></td>
                                <td>
                                    <?php 
                                        // Simplified coordinator check
                                        if($req['req_coordinator_status'] == 'rejected' || $req['sub_coordinator_status'] == 'rejected')
                                            echo '<span class="status-badge status-rejected">مرفوض</span>';
                                        elseif($req['req_coordinator_status'] == 'approved' && $req['sub_coordinator_status'] == 'approved')
                                            echo '<span class="status-badge status-approved">مقبول</span>';
                                        else
                                            echo '<span class="status-badge status-pending">قيد المراجعة</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        if($req['deputy_status'] == 'approved' || $req['deputy_status'] == 'approved_with_mod')
                                            echo '<span class="status-badge status-approved">معتمد</span>';
                                        elseif($req['deputy_status'] == 'rejected')
                                            echo '<span class="status-badge status-rejected">مرفوض</span>';
                                        else
                                            echo '<span class="status-badge status-pending">بانتظار النائب</span>';
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
