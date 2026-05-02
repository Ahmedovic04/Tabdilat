<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if (in_array($new_role, ['teacher', 'coordinator', 'deputy'])) {
        $stmt = $db->prepare("UPDATE rased_users SET role = ?, is_new = 0 WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        $success_msg = "تم تحديث الصلاحية بنجاح.";
    }
}

// Clear 'is_new' flag if requested
if (isset($_GET['clear_new'])) {
    $db->query("UPDATE rased_users SET is_new = 0");
    header('Location: users.php');
    exit;
}

// Fetch all users except the current deputy
$stmt = $db->prepare("SELECT * FROM rased_users WHERE id != ? ORDER BY is_new DESC, name ASC");
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
        
        select { padding: 0.4rem; border-radius: 5px; border: 1px solid var(--border-color); }
        
        .msg { background: #D1FAE5; color: #065F46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">إدارة الموظفين والصلاحيات</div>
    <div>
        <a href="index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة النائب</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>قائمة الموظفين في النظام</h2>
            <a href="?clear_new=1" class="btn" style="background: #4B5563;">مسح إشارات "معلم جديد"</a>
        </div>
        
        <?php if(isset($success_msg)): ?>
            <div class="msg"><?= $success_msg ?></div>
        <?php endif; ?>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم (للدخول)</th>
                        <th>الصلاحية الحالية</th>
                        <th>تغيير الصلاحية</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr style="<?= $user['is_new'] ? 'background-color: #FFFBEB;' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                <?php if($user['is_new']): ?>
                                    <span class="badge badge-new" style="margin-right: 0.5rem;">جديد</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-family: monospace; background: #F3F4F6; padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($user['username']) ?></span></td>
                            <td>
                                <?php 
                                    if($user['role'] === 'teacher') echo '<span class="badge badge-teacher">معلم</span>';
                                    elseif($user['role'] === 'coordinator') echo '<span class="badge badge-coord">منسق مادة</span>';
                                    elseif($user['role'] === 'deputy') echo '<span class="badge badge-deputy">نائب أكاديمي</span>';
                                ?>
                            </td>
                            <td>
                                <form method="POST" style="display: flex; gap: 0.5rem; justify-content: center; align-items: center;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="new_role">
                                        <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>معلم</option>
                                        <option value="coordinator" <?= $user['role'] === 'coordinator' ? 'selected' : '' ?>>منسق مادة</option>
                                        <option value="deputy" <?= $user['role'] === 'deputy' ? 'selected' : '' ?>>نائب أكاديمي</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm">تحديث</button>
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
