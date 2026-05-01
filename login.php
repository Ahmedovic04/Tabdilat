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
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --secondary: #10B981;
            --bg-color: #F3F4F6;
            --card-bg: rgba(255, 255, 255, 0.85);
            --text-main: #1F2937;
            --text-muted: #6B7280;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
        }
        .login-container {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo { font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 0.5rem; }
        .subtitle { color: var(--text-muted); margin-bottom: 2rem; }
        .input-group { margin-bottom: 1.5rem; text-align: right; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .input-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .input-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-submit:active { transform: translateY(0); }
        .error {
            background: #FEE2E2;
            color: #DC2626;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">راصد تبديلاتي</div>
    <div class="subtitle">النظام الإلكتروني لإدارة تبديل الحصص</div>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label for="username">اسم المستخدم</label>
            <input type="text" id="username" name="username" required placeholder="أدخل اسم المستخدم (مثال: t_12345)">
        </div>
        
        <div class="input-group">
            <label for="password">كلمة المرور</label>
            <input type="password" id="password" name="password" required placeholder="أدخل كلمة المرور">
        </div>
        
        <button type="submit" class="btn-submit">تسجيل الدخول</button>
    </form>
</div>

</body>
</html>
