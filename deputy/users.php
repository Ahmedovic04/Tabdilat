<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

// Fetch predefined subjects
$stmt = $db->query("SELECT * FROM rased_subjects");
$subjects = $stmt->fetchAll();

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $name = trim($_POST['name']);
    $role = $_POST['role'];
    $subject_id = $_POST['subject_id'] ? (int)$_POST['subject_id'] : null;
    
    if ($name && $role) {
        $username = 't_' . crc32($name . time()); // ensure unique
        $password = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO rased_users (username, password, name, role, subject_id, is_new) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$username, $password, $name, $role, $subject_id]);
        $success_msg = "تمت إضافة الموظف بنجاح. اسم المستخدم: $username";
    }
}

// Handle update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    $subject_id = $_POST['subject_id'] ? (int)$_POST['subject_id'] : null;
    
    if (in_array($new_role, ['teacher', 'coordinator', 'deputy'])) {
        $stmt = $db->prepare("UPDATE rased_users SET role = ?, subject_id = ?, is_new = 0 WHERE id = ?");
        $stmt->execute([$new_role, $subject_id, $user_id]);
        $success_msg = "تم تحديث بيانات الموظف بنجاح.";
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    $stmt = $db->prepare("DELETE FROM rased_users WHERE id = ? AND role != 'deputy'");
    $stmt->execute([$user_id]);
    $success_msg = "تم حذف الموظف بنجاح.";
}

// Clear 'is_new' flag if requested
if (isset($_GET['clear_new'])) {
    $db->query("UPDATE rased_users SET is_new = 0");
    header('Location: users.php');
    exit;
}

// Fetch all users except the current deputy
$stmt = $db->prepare("
    SELECT u.*, s.name as subject_name 
    FROM rased_users u 
    LEFT JOIN rased_subjects s ON u.subject_id = s.id 
    WHERE u.id != ? 
    ORDER BY u.is_new DESC, u.name ASC
");
$stmt->execute([$_SESSION['rased_user_id']]);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموظفين والصلاحيات - النائب الأكاديمي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444;
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
        
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: bold; }
        .badge-new { background: #FEF3C7; color: #D97706; }
        .badge-teacher { background: #E0E7FF; color: #4338CA; }
        .badge-coord { background: #D1FAE5; color: #059669; }
        .badge-deputy { background: #FEE2E2; color: #DC2626; }
        
        .btn {
            background: var(--primary); color: white; padding: 0.5rem 1rem;
            border: none; border-radius: 6px; cursor: pointer; transition: 0.3s;
            text-decoration: none; display: inline-block;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.9rem; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #DC2626; }
        .btn-success { background: var(--success); }
        .btn-success:hover { background: #059669; }
        
        select, input[type="text"] { padding: 0.4rem; border-radius: 5px; border: 1px solid var(--border-color); font-family: inherit; }
        
        .msg { background: #D1FAE5; color: #065F46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-right: 4px solid #059669;}
        
        .add-form { display: flex; gap: 1rem; align-items: flex-end; background: #F9FAFB; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border-color); flex-wrap: wrap; }
        .add-form .group { display: flex; flex-direction: column; flex: 1; min-width: 200px; }
        .add-form label { margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">إدارة الموظفين والمواد</div>
    <div>
        <a href="index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة النائب</a>
    </div>
</div>

<div class="container">

    <?php if(isset($success_msg)): ?>
        <div class="msg"><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>إضافة موظف جديد يدوياً</h2>
        <form method="POST" class="add-form">
            <input type="hidden" name="action" value="add_user">
            <div class="group">
                <label>اسم الموظف</label>
                <input type="text" name="name" required placeholder="مثال: أحمد محمد">
            </div>
            <div class="group">
                <label>نوع الصلاحية</label>
                <select name="role" required>
                    <option value="teacher">معلم</option>
                    <option value="coordinator">منسق مادة</option>
                    <option value="deputy">نائب أكاديمي</option>
                </select>
            </div>
            <div class="group">
                <label>تحديد المادة (القسم)</label>
                <select name="subject_id">
                    <option value="">-- بدون مادة --</option>
                    <?php foreach($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="group" style="flex: 0; min-width: auto;">
                <button type="submit" class="btn btn-success" style="height: 38px;">➕ إضافة</button>
            </div>
        </form>
        <p style="margin-top: 1rem; font-size: 0.9em; color: #6B7280;">ملاحظة: كلمة المرور الافتراضية لأي موظف جديد هي <strong>123456</strong>.</p>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>قائمة الموظفين في النظام</h2>
            <a href="?clear_new=1" class="btn" style="background: #4B5563;">مسح إشارات "معلم جديد"</a>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>القسم / المادة</th>
                        <th>تحديث البيانات</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr style="<?= $user['is_new'] ? 'background-color: #FFFBEB;' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($user['name']) ?></strong><br>
                                <?php 
                                    if($user['role'] === 'teacher') echo '<span class="badge badge-teacher" style="margin-top:4px;">معلم</span>';
                                    elseif($user['role'] === 'coordinator') echo '<span class="badge badge-coord" style="margin-top:4px;">منسق مادة</span>';
                                    elseif($user['role'] === 'deputy') echo '<span class="badge badge-deputy" style="margin-top:4px;">نائب أكاديمي</span>';
                                ?>
                                <?php if($user['is_new']): ?>
                                    <span class="badge badge-new" style="margin-right: 0.5rem; margin-top:4px;">جديد</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-family: monospace; background: #F3F4F6; padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($user['username']) ?></span></td>
                            
                            <!-- Update Form -->
                            <form method="POST">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <td>
                                    <select name="subject_id" style="width:100%;">
                                        <option value="">-- غير محدد --</option>
                                        <?php foreach($subjects as $sub): ?>
                                            <option value="<?= $sub['id'] ?>" <?= $user['subject_id'] == $sub['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sub['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.5rem; justify-content:center;">
                                        <select name="new_role">
                                            <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>معلم</option>
                                            <option value="coordinator" <?= $user['role'] === 'coordinator' ? 'selected' : '' ?>>منسق مادة</option>
                                            <option value="deputy" <?= $user['role'] === 'deputy' ? 'selected' : '' ?>>نائب أكاديمي</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm">تحديث</button>
                                    </div>
                                </td>
                            </form>
                            
                            <!-- Delete Form -->
                            <td>
                                <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الموظف نهائياً؟ سيتم حذف جدوله وطلباته أيضاً.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️ حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

</body>
</html>
