<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$teacher_id = $_SESSION['rased_user_id'];
$message = '';

// Handle save schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule'])) {
    $db->beginTransaction();
    try {
        // Delete old schedule
        $stmt = $db->prepare("DELETE FROM rased_teacher_classes WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        
        $insert_stmt = $db->prepare("INSERT INTO rased_teacher_classes (teacher_id, class_id, day_of_week, period_number) VALUES (?, ?, ?, ?)");
        $class_check = $db->prepare("SELECT id FROM rased_classes WHERE name = ?");
        $class_insert = $db->prepare("INSERT INTO rased_classes (name) VALUES (?)");
        
        foreach ($_POST['schedule'] as $day => $periods) {
            foreach ($periods as $period => $class_name) {
                $class_name = trim($class_name);
                if (empty($class_name)) continue;
                
                // Check if class exists
                $class_check->execute([$class_name]);
                $c_res = $class_check->fetch();
                
                if ($c_res) {
                    $class_id = $c_res['id'];
                } else {
                    $class_insert->execute([$class_name]);
                    $class_id = $db->lastInsertId();
                }
                
                $insert_stmt->execute([$teacher_id, $class_id, $day, $period]);
            }
        }
        $db->commit();
        $message = '<div style="color:green; margin-bottom:1rem; padding:1rem; background:#D1FAE5; border-radius:8px;">تم حفظ الجدول بنجاح!</div>';
    } catch (Exception $e) {
        $db->rollBack();
        $message = '<div style="color:red; margin-bottom:1rem; padding:1rem; background:#FEE2E2; border-radius:8px;">حدث خطأ أثناء الحفظ.</div>';
    }
}

// Get current schedule
$stmt = $db->prepare("
    SELECT tc.day_of_week, tc.period_number, c.name as class_name 
    FROM rased_teacher_classes tc 
    JOIN rased_classes c ON tc.class_id = c.id 
    WHERE tc.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$current_schedule_raw = $stmt->fetchAll();

$schedule = [];
foreach ($current_schedule_raw as $s) {
    $schedule[$s['day_of_week']][$s['period_number']] = $s['class_name'];
}

$days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد الجدول يدوياً - راصد تبديلاتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
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
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 1rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; }
        input[type="text"] { width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 5px; font-family: inherit; text-align: center; }
        
        .btn {
            background: var(--primary); color: white; padding: 0.75rem 1.5rem;
            border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: 0.3s; display: inline-block; text-decoration: none;
        }
        .btn:hover { background: var(--primary-hover); }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">إعداد الجدول اليدوي</div>
    <div>
        <a href="index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة الرئيسية</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>إنشاء أو تعديل جدول الحصص</h2>
        <p style="margin-bottom: 1.5rem; color: #6B7280;">أدخل اسم الصف في الخانة المناسبة (مثال: S1 أو الأول أ). اترك الخانة فارغة إذا لم تكن لديك حصة.</p>
        
        <?= $message ?>
        
        <form method="POST">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>اليوم / الحصة</th>
                            <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($days as $day_index => $day_name): ?>
                            <tr>
                                <th><?= $day_name ?></th>
                                <?php for($i=1; $i<=7; $i++): ?>
                                    <?php $val = isset($schedule[$day_index][$i]) ? $schedule[$day_index][$i] : ''; ?>
                                    <td>
                                        <input type="text" name="schedule[<?= $day_index ?>][<?= $i ?>]" value="<?= htmlspecialchars($val) ?>">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 2rem; text-align: left;">
                <button type="submit" class="btn">💾 حفظ الجدول</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
