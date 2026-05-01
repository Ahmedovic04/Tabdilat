<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$teacher_id = $_SESSION['rased_user_id'];

// Get Teacher's Schedule
$stmt = $db->prepare("
    SELECT tc.day_of_week, tc.period_number, c.name as class_name 
    FROM rased_teacher_classes tc 
    JOIN rased_classes c ON tc.class_id = c.id 
    WHERE tc.teacher_id = ?
    ORDER BY tc.day_of_week, tc.period_number
");
$stmt->execute([$teacher_id]);
$schedule = $stmt->fetchAll();

$days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المعلم - راصد تبديلاتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --secondary: #10B981;
            --bg-color: #F3F4F6;
            --card-bg: #FFFFFF;
            --text-main: #1F2937;
            --border-color: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); }
        .navbar {
            background: var(--card-bg);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .navbar .brand { font-size: 1.5rem; font-weight: 800; color: var(--primary); }
        .navbar .user-info { font-weight: 500; }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        h2 { margin-bottom: 1.5rem; color: var(--primary); }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 1rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; }
        .btn {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #4338CA; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">راصد تبديلاتي</div>
    <div class="user-info">
        مرحباً، <?= htmlspecialchars($_SESSION['rased_name']) ?> | 
        <a href="logout.php" style="color: #DC2626; text-decoration: none;">تسجيل خروج</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>جدول الحصص الخاص بك</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>اليوم / الحصة</th>
                        <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($days as $index => $day): ?>
                        <tr>
                            <th><?= $day ?></th>
                            <?php for($i=1; $i<=7; $i++): ?>
                                <?php 
                                    $class = '';
                                    foreach($schedule as $s) {
                                        if($s['day_of_week'] == $index && $s['period_number'] == $i) {
                                            $class = $s['class_name'];
                                            break;
                                        }
                                    }
                                ?>
                                <td><?= htmlspecialchars($class) ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 2rem; text-align: left;">
            <a href="request.php" class="btn">طلب تبديل حصة</a>
        </div>
    </div>
</div>

</body>
</html>
