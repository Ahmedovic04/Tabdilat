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

            // ── AUTOMATED CONFLICT RESOLUTION AFTER SCHEDULE UPDATE ──
            require_once '../mail_helper.php';

            // Fetch all future active substitution requests
            $stmtReqs = $db->query("
                SELECT r.id, r.requester_id, r.substitute_id, r.class_id,
                       r.request_date, r.period_number,
                       r.repayment_date, r.repayment_period,
                       u1.name as req_name, u1.email as req_email,
                       u2.name as sub_name, u2.email as sub_email,
                       c.name as class_name
                FROM rased_requests r
                JOIN rased_users u1 ON r.requester_id = u1.id
                JOIN rased_users u2 ON r.substitute_id = u2.id
                JOIN rased_classes c ON r.class_id = c.id
                WHERE r.deputy_status != 'rejected'
                  AND r.request_date >= CURDATE()
            ");
            $active_requests = $stmtReqs->fetchAll();

            $stmtCheckNewPos = $db->prepare("
                SELECT period_number FROM rased_teacher_classes
                WHERE teacher_id = ? AND class_id = ? AND day_of_week = ?
            ");

            $stmtIsFree = $db->prepare("
                SELECT COUNT(*) FROM rased_teacher_classes
                WHERE teacher_id = ? AND day_of_week = ? AND period_number = ?
            ");

            $rescheduledCount = 0;
            $conflictedCount = 0;

            foreach ($active_requests as $req) {
                $day_of_week = (int)date('w', strtotime($req['request_date']));
                
                // 1. Find where the class is now in the new timetable
                $stmtCheckNewPos->execute([$req['requester_id'], $req['class_id'], $day_of_week]);
                $new_period = $stmtCheckNewPos->fetchColumn();

                $needsNotification = false;
                $resolved = false;

                // Check if the original slot is still valid
                if ($new_period == $req['period_number']) {
                    // Class is in the same place. Is the substitute still free?
                    $stmtIsFree->execute([$req['substitute_id'], $day_of_week, $new_period]);
                    if ($stmtIsFree->fetchColumn() > 0) {
                        // Conflict! Substitute is now busy. Try to find if class moved? 
                        // (Wait, we already checked new_period. If it's the same, then it's a hard conflict)
                        $needsNotification = true;
                    } else {
                        // Everything is still fine for this request.
                        continue;
                    }
                } else if ($new_period) {
                    // Class moved to a new period. Can the substitute cover it?
                    $stmtIsFree->execute([$req['substitute_id'], $day_of_week, $new_period]);
                    if ($stmtIsFree->fetchColumn() == 0) {
                        // Yes! Auto-reschedule
                        $db->prepare("UPDATE rased_requests SET period_number = ? WHERE id = ?")->execute([$new_period, $req['id']]);
                        
                        $subject = "تحديث آلي: تغيير موعد حصة التبديل رقم #{$req['id']}";
                        $body = "تحية طيبة،\n\nنود إبلاغكم بأنه تم تحديث الجدول المدرسي، وبناءً عليه تم تحديث موعد التبديل الخاص بكم آلياً ليتناسب مع الجدول الجديد:\n\n" .
                                "رقم الطلب: #{$req['id']}\n" .
                                "المعلم الغائب: {$req['req_name']}\n" .
                                "المعلم البديل: {$req['sub_name']}\n" .
                                "الصف: {$req['class_name']}\n" .
                                "التاريخ: {$req['request_date']}\n" .
                                "الموعد الجديد: الحصة {$new_period}\n\n" .
                                "تم تعديل البيانات في النظام بنجاح.\n\nنظام راصد تبديلاتي";
                        
                        if (!empty($req['req_email'])) sendRasedEmail($req['req_email'], $subject, $body);
                        if (!empty($req['sub_email'])) sendRasedEmail($req['sub_email'], $subject, $body);
                        
                        $rescheduledCount++;
                        $resolved = true;
                    } else {
                        // Class moved but substitute is busy there too
                        $needsNotification = true;
                    }
                } else {
                    // Class is no longer on this day at all!
                    $needsNotification = true;
                }

                if ($needsNotification && !$resolved) {
                    $conflictedCount++;
                    $subject = "تنبيه هام: تعارض في طلب التبديل رقم #{$req['id']}";
                    $body = "تحية طيبة،\n\nيرجى العلم أن التبديل رقم #{$req['id']} (بين {$req['req_name']} و {$req['sub_name']}) قد تعارض مع حصصكم الأساسية بعد تحديث الجدول المدرسي، ولم يتمكن النظام من إيجاد حل آلي مناسب.\n\n" .
                            "يرجى الدخول للنظام وإعادة تقديم طلب تبديل جديد يتوافق مع الجدول الحالي.\n\n" .
                            "نظام راصد تبديلاتي";
                    
                    if (!empty($req['req_email'])) sendRasedEmail($req['req_email'], $subject, $body);
                    if (!empty($req['sub_email'])) sendRasedEmail($req['sub_email'], $subject, $body);
                }
            }

            if ($rescheduledCount > 0 || $conflictedCount > 0) {
                $message .= " ⚠️ تم تحديث ($rescheduledCount) طلب آلياً، ووجد ($conflictedCount) تعارضات تتطلب تدخلكم.";
            }
            // ── END AUTOMATED RESOLUTION ──

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
