<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator'])) {
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
        $stmt = $db->prepare("DELETE FROM rased_teacher_classes WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        
        $insert_stmt = $db->prepare("INSERT INTO rased_teacher_classes (teacher_id, class_id, day_of_week, period_number) VALUES (?, ?, ?, ?)");
        
        foreach ($_POST['schedule'] as $day => $periods) {
            foreach ($periods as $period => $class_id) {
                $class_id = (int)$class_id;
                if (!$class_id) continue;
                $insert_stmt->execute([$teacher_id, $class_id, $day, $period]);
            }
        }
        $db->commit();
        $message = '<div class="msg msg-success">✅ تم حفظ الجدول بنجاح!</div>';
    } catch (Exception $e) {
        $db->rollBack();
        $message = '<div class="msg msg-error">❌ حدث خطأ أثناء الحفظ.</div>';
    }
}

// Get all available classes from DB
$allClasses = $db->query("SELECT id, name FROM rased_classes ORDER BY name ASC")->fetchAll();

// Get current schedule
$stmt = $db->prepare("
    SELECT tc.day_of_week, tc.period_number, tc.class_id
    FROM rased_teacher_classes tc 
    WHERE tc.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$schedule = [];
foreach ($stmt->fetchAll() as $s) {
    $schedule[$s['day_of_week']][$s['period_number']] = $s['class_id'];
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
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h2 { color: var(--primary); margin-bottom: 0.5rem; }

        .msg { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold; }
        .msg-success { background: #D1FAE5; color: #065F46; }
        .msg-error   { background: #FEE2E2; color: #991B1B; }

        .table-container { overflow-x: auto; margin-top: 1.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 0.6rem 0.4rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; font-size: 0.95rem; }
        th.day-header { background: #EEF2FF; color: var(--primary); font-size: 1rem; min-width: 80px; }

        select {
            width: 100%;
            padding: 0.4rem 0.3rem;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.88rem;
            background: #fff;
            color: var(--text-main);
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            text-align: center;
        }
        select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
        select.has-value { background: #EEF2FF; color: var(--primary); font-weight: bold; border-color: var(--primary); }

        .btn {
            background: var(--primary); color: white; padding: 0.75rem 2rem;
            border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;
            font-family: inherit; transition: 0.3s; margin-top: 1.5rem;
            display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .btn:hover { background: var(--primary-hover); }

        .hint { color: #6B7280; font-size: 0.9rem; margin-top: 0.4rem; }
    </style>
</head>
<body>

<div class="navbar">
    <div style="font-weight:800; color:var(--primary);">إعداد الجدول الشخصي</div>
    <a href="../<?= $_SESSION['rased_role'] ?>/index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">← العودة للوحة الرئيسية</a>
</div>

<div class="container">
    <div class="card">
        <h2>إنشاء أو تعديل جدول الحصص</h2>
        <p class="hint">اختر الصف من القائمة لكل حصة. اترك الخانة فارغة إذا لم تكن لديك حصة.</p>

        <?= $message ?>

        <?php if (empty($allClasses)): ?>
            <div class="msg msg-error" style="margin-top:1rem;">
                ⚠️ لا توجد صفوف مضافة في النظام بعد. يرجى مراجعة النائب الأكاديمي لرفع جدول الحصص أولاً.
            </div>
        <?php else: ?>
        <form method="POST">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="day-header">اليوم / الحصة</th>
                            <th>1</th><th>2</th><th>3</th><th>4</th>
                            <th>5</th><th>6</th><th>7</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($days as $day_index => $day_name): ?>
                            <tr>
                                <th class="day-header"><?= $day_name ?></th>
                                <?php for($i = 1; $i <= 7; $i++): ?>
                                    <?php $selected_id = $schedule[$day_index][$i] ?? 0; ?>
                                    <td>
                                        <select
                                            name="schedule[<?= $day_index ?>][<?= $i ?>]"
                                            class="<?= $selected_id ? 'has-value' : '' ?>"
                                            onchange="this.className = this.value ? 'has-value' : ''"
                                        >
                                            <option value="0">—</option>
                                            <?php foreach($allClasses as $cls): ?>
                                                <option value="<?= $cls['id'] ?>" <?= $selected_id == $cls['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cls['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn">💾 حفظ الجدول</button>
        </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
