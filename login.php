<?php
require_once 'config.php';
startSecureSession();

if (isset($_SESSION['rased_user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM rased_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['rased_user_id'] = $user['id'];
            $_SESSION['rased_username'] = $user['username'];
            $_SESSION['rased_name'] = $user['name'];
            $_SESSION['rased_role'] = $user['role'];
            $_SESSION['rased_is_new'] = $user['is_new'];
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    } else {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - راصد تبديلاتي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-logo {
            width: 80px;
            height: 80px;
            background: var(--accent-color);
            border-radius: 20px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary-dark);
            box-shadow: 0 10px 20px rgba(240, 165, 0, 0.3);
        }
        .login-title {
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: -1px;
        }
        .login-subtitle {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="login-body">

<div class="login-card shadow-lg">
    <div class="text-center">
        <div class="login-logo">🛰️</div>
        <h2 class="login-title">راصد تبديلاتي</h2>
        <p class="login-subtitle">نظام إدارة التبديلات المدرسية الذكي</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger text-center py-2" style="border-radius: 10px; font-size: 0.9rem;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <label class="form-label mb-2 fw-500">اسم المستخدم</label>
            <input type="text" name="username" class="form-control" required placeholder="أدخل اسم المستخدم">
        </div>
        
        <div class="mb-4">
            <label class="form-label mb-2 fw-500">كلمة المرور</label>
            <input type="password" name="password" class="form-control" required placeholder="أدخل كلمة المرور">
        </div>
        
        <button type="submit" class="btn btn-accent w-100 py-3 mb-3">دخول النظام</button>
        <p class="text-center m-0" style="font-size: 0.8rem; color: #555;">&copy; 2026 مدرسة معيذر الابتدائية للبنين</p>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

