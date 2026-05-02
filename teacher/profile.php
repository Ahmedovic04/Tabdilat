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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $message = '<div style="color:red; margin-bottom:1rem;">يجب أن تتكون كلمة المرور من 6 أحرف أو أرقام على الأقل.</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div style="color:red; margin-bottom:1rem;">كلمتا المرور غير متطابقتين.</div>';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE rased_users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $teacher_id]);
        $message = '<div style="color:green; margin-bottom:1rem;">تم تغيير كلمة المرور بنجاح!</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - راصد تبديلاتي</title>
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
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        h2 { color: var(--primary); margin-bottom: 1.5rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; }
        
        .btn {
            background: var(--primary); color: white; padding: 0.75rem 1.5rem; width: 100%;
            border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: 0.3s;
        }
        .btn:hover { background: var(--primary-hover); }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">الملف الشخصي</div>
    <div>
        <a href="index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة الرئيسية</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>تغيير كلمة المرور</h2>
        <?= $message ?>
        <form method="POST">
            <div class="form-group">
                <label>اسم المستخدم (ثابت للدخول)</label>
                <input type="text" value="<?= htmlspecialchars($_SESSION['rased_username']) ?>" disabled style="background:#F3F4F6;">
            </div>
            <div class="form-group">
                <label>كلمة المرور الجديدة</label>
                <input type="password" name="new_password" required placeholder="أدخل كلمة المرور الجديدة">
            </div>
            <div class="form-group">
                <label>تأكيد كلمة المرور</label>
                <input type="password" name="confirm_password" required placeholder="أعد إدخال كلمة المرور">
            </div>
            <button type="submit" class="btn">حفظ التغييرات</button>
        </form>
    </div>
</div>

</body>
</html>
