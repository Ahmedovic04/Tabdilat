<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id'])) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['rased_user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_email'])) {
        $email = trim($_POST['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) || empty($email)) {
            $stmt = $db->prepare("UPDATE rased_users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
            $message = '<div style="color:green; margin-bottom:1rem; padding:1rem; background:#D1FAE5; border-radius:8px;">تم تحديث البريد الإلكتروني بنجاح!</div>';
        } else {
            $message = '<div style="color:red; margin-bottom:1rem; padding:1rem; background:#FEE2E2; border-radius:8px;">يرجى إدخال بريد إلكتروني صحيح.</div>';
        }
    }
    
    if (isset($_POST['new_password']) && !empty($_POST['new_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 6) {
            $message = '<div style="color:red; margin-bottom:1rem; padding:1rem; background:#FEE2E2; border-radius:8px;">كلمة المرور قصيرة جداً.</div>';
        } elseif ($new_password !== $confirm_password) {
            $message = '<div style="color:red; margin-bottom:1rem; padding:1rem; background:#FEE2E2; border-radius:8px;">كلمتا المرور غير متطابقتين.</div>';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE rased_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            $message = '<div style="color:green; margin-bottom:1rem; padding:1rem; background:#D1FAE5; border-radius:8px;">تم تحديث كلمة المرور بنجاح!</div>';
        }
    }
}

// Get current user data
$stmt = $db->prepare("SELECT * FROM rased_users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
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
        .navbar { background: var(--card-bg); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        h2 { color: var(--primary); margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; }
        .btn { background: var(--primary); color: white; padding: 0.75rem 1.5rem; width: 100%; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: 0.3s; margin-top: 10px; }
        .btn:hover { background: var(--primary-hover); }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">الملف الشخصي</div>
    <div>
        <a href="../<?= $_SESSION['rased_role'] ?>/index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة الرئيسية</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>تحديث البريد الإلكتروني</h2>
        <p style="margin-bottom: 1rem; font-size: 0.9em; color: #6B7280;">سنستخدم هذا البريد لإرسال إشعارات التبديلات المعتمدة إليك.</p>
        <form method="POST">
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" placeholder="example@school.com">
            </div>
            <button type="submit" name="update_email" class="btn">حفظ البريد الإلكتروني</button>
        </form>
    </div>

    <div class="card">
        <h2>تغيير كلمة المرور</h2>
        <?= $message ?>
        <form method="POST">
            <div class="form-group">
                <label>كلمة المرور الجديدة</label>
                <input type="password" name="new_password" placeholder="أدخل كلمة المرور الجديدة">
            </div>
            <div class="form-group">
                <label>تأكيد كلمة المرور</label>
                <input type="password" name="confirm_password" placeholder="أعد إدخال كلمة المرور">
            </div>
            <button type="submit" class="btn" style="background:#4B5563;">تغيير كلمة المرور</button>
        </form>
    </div>
</div>

</body>
</html>
