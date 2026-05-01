<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {
    $file = $_FILES['schedule_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tr');
        
        if ($rows && $rows->length > 2) {
            $db->exec("TRUNCATE TABLE rased_teacher_classes");
            
            $default_password = password_hash('123456', PASSWORD_DEFAULT);
            $stmtUser = $db->prepare("INSERT IGNORE INTO rased_users (username, password, name, role, is_new) VALUES (?, ?, ?, 'teacher', TRUE)");
            $stmtClass = $db->prepare("INSERT IGNORE INTO rased_classes (name) VALUES (?)");
            
            foreach ($rows as $index => $row) {
                if ($index < 2) continue; // Skip header
                $cells = $xpath->query('td', $row);
                if ($cells->length < 36) continue;
                
                $teacher_name = trim($cells->item(0)->nodeValue);
                if (empty($teacher_name)) continue;
                
                $username = 't_' . crc32($teacher_name);
                $stmtUser->execute([$username, $default_password, $teacher_name]);
                
                $t_res = $db->query("SELECT id FROM rased_users WHERE name = " . $db->quote($teacher_name))->fetch();
                if (!$t_res) continue;
                $teacher_id = $t_res['id'];
                
                for ($i = 1; $i <= 35; $i++) {
                    $class_name = trim($cells->item($i)->nodeValue);
                    if (empty($class_name)) continue;
                    
                    $stmtClass->execute([$class_name]);
                    $c_res = $db->query("SELECT id FROM rased_classes WHERE name = " . $db->quote($class_name))->fetch();
                    if (!$c_res) continue;
                    
                    $day_of_week = floor(($i - 1) / 7);
                    $period_number = (($i - 1) % 7) + 1;
                    
                    $db->prepare("INSERT INTO rased_teacher_classes (teacher_id, class_id, day_of_week, period_number) VALUES (?, ?, ?, ?)")
                       ->execute([$teacher_id, $c_res['id'], $day_of_week, $period_number]);
                }
            }
            $message = "تم تحديث الجدول بنجاح.";
        } else {
            $message = "الملف غير صالح أو لا يحتوي على بيانات الجدول.";
        }
    } else {
        $message = "حدث خطأ أثناء رفع الملف.";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث الجدول - النائب الأكاديمي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4F46E5; --bg-color: #F3F4F6; --card-bg: #FFFFFF; --text-main: #1F2937; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); padding: 2rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; margin: auto; }
        .btn { background: var(--primary); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; }
        .msg { background: #D1FAE5; color: #065F46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>رفع وتحديث جدول المعلمين</h2>
    <?php if($message): ?> <div class="msg"><?= $message ?></div> <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div style="margin-bottom: 1rem;">
            <label>اختر ملف الجدول (بصيغة xls المستخرجة كـ HTML)</label><br><br>
            <input type="file" name="schedule_file" accept=".xls,.html,.htm" required>
        </div>
        <button type="submit" class="btn">رفع وتحديث</button>
        <a href="index.php" style="margin-right: 1rem; color: var(--primary);">العودة للوحة</a>
    </form>
</div>
</body>
</html>
