<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

// Fetch Pending Requests for Deputy
$stmt = $db->query("
    SELECT r.id, r.request_date, r.period_number, 
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

// Check for new teachers
$stmtNew = $db->query("SELECT COUNT(*) FROM rased_users WHERE is_new = 1 AND role = 'teacher'");
$new_teachers_count = $stmtNew->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة النائب الأكاديمي - راصد تبديلاتي</title>
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
        h2 { color: var(--primary); margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 1rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; }
        .btn {
            padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; color: white; transition: 0.3s; margin: 0 0.2rem;
        }
        .btn-approve { background: var(--success); }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: var(--danger); }
        .btn-reject:hover { background: #DC2626; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">راصد تبديلاتي - النائب الأكاديمي</div>
    <div>
        مرحباً، <?= htmlspecialchars($_SESSION['rased_name']) ?> | 
        <a href="../teacher/logout.php" style="color: var(--danger); text-decoration: none;">تسجيل خروج</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>الطلبات المعلقة (الموافقات النهائية)</h2>
        <?php if(empty($pending_requests)): ?>
            <p>لا توجد طلبات معلقة حالياً.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>المعلم الغائب</th>
                            <th>المعلم البديل</th>
                            <th>الصف</th>
                            <th>تاريخ الغياب</th>
                            <th>الحصة</th>
                            <th>حالة المنسقين</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_requests as $req): ?>
                            <tr>
                                <td>#<?= $req['id'] ?></td>
                                <td><?= htmlspecialchars($req['requester_name']) ?></td>
                                <td><?= htmlspecialchars($req['substitute_name']) ?></td>
                                <td><?= htmlspecialchars($req['class_name']) ?></td>
                                <td><?= htmlspecialchars($req['request_date']) ?></td>
                                <td><?= $req['period_number'] ?></td>
                                <td>
                                    م. الأساسي: <?= $req['req_coordinator_status'] ?> <br>
                                    م. البديل: <?= $req['sub_coordinator_status'] ?>
                                </td>
                                <td>
                                    <button class="btn btn-approve" onclick="updateStatus(<?= $req['id'] ?>, 'approved')">موافقة</button>
                                    <button class="btn btn-reject" onclick="updateStatus(<?= $req['id'] ?>, 'rejected')">رفض</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>إدارة النظام</h2>
        <?php if($new_teachers_count > 0): ?>
            <div style="background: #FEF2F2; color: #991B1B; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-right: 4px solid #DC2626;">
                <strong>تنبيه:</strong> تم اكتشاف <?= $new_teachers_count ?> معلم جديد في الجدول الأخير. يرجى تحديث بياناتهم والمواد الخاصة بهم.
            </div>
        <?php endif; ?>
        
        <p>من هنا يمكنك إدارة إعدادات المنسقين والمواد لاحقاً.</p>
        <div style="margin-top: 1rem;">
            <a href="upload.php" class="btn btn-approve" style="text-decoration:none; display:inline-block;">رفع / تحديث جدول المعلمين</a>
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

</body>
</html>
