<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $user_ids = $_POST['user_ids'] ?? [];
    if (!empty($user_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $stmt = $db->prepare("DELETE FROM rased_users WHERE id IN ($placeholders) AND role != 'deputy'");
        $stmt->execute($user_ids);
        $success_msg = "تم حذف الموظفين المحددين بنجاح.";
    }
}

// Fetch predefined subjects
$stmt = $db->query("SELECT * FROM rased_subjects");
$subjects = $stmt->fetchAll();

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $name = trim($_POST['name']);
    $role = $_POST['role'];
    $subject_id = $_POST['subject_id'] ? (int)$_POST['subject_id'] : null;
    
    if ($name && $role) {
        $username = 't_' . crc32($name . time());
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

// Handle single delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    $stmt = $db->prepare("DELETE FROM rased_users WHERE id = ? AND role != 'deputy'");
    $stmt->execute([$user_id]);
    $success_msg = "تم حذف الموظف بنجاح.";
}

// Fetch all users
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
    <title>إدارة الموظفين - النائب الأكاديمي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --primary-hover: #4338CA;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444;
            --bg-color: #F3F4F6; --card-bg: #FFFFFF; --text-main: #1F2937; --border-color: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Tajawal', sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); }
        .navbar { background: var(--card-bg); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--card-bg); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        h2 { color: var(--primary); margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: center; }
        th, td { padding: 1rem; border: 1px solid var(--border-color); }
        th { background: #F9FAFB; font-weight: 700; }
        .btn { background: var(--primary); color: white; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-block; }
        .btn:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #DC2626; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: bold; }
        .badge-teacher { background: #E0E7FF; color: #4338CA; }
        .badge-coord { background: #D1FAE5; color: #059669; }
        .msg { background: #D1FAE5; color: #065F46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-right: 4px solid #059669; }
        .bulk-actions { margin-bottom: 1rem; display: none; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">إدارة الموظفين والمواد</div>
    <div><a href="index.php" style="color: var(--primary); font-weight:bold; text-decoration: none;">العودة للوحة النائب</a></div>
</div>

<div class="container">
    <?php if(isset($success_msg)): ?>
        <div class="msg"><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>إضافة موظف جديد يدوياً</h2>
        <form method="POST" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; background: #F9FAFB; padding: 1.5rem; border-radius: 8px;">
            <input type="hidden" name="action" value="add_user">
            <div style="flex: 1; min-width: 200px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">الاسم</label>
                <input type="text" name="name" required style="width:100%; padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">الصلاحية</label>
                <select name="role" style="width:100%; padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
                    <option value="teacher">معلم</option>
                    <option value="coordinator">منسق مادة</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">المادة</label>
                <select name="subject_id" style="width:100%; padding: 0.5rem; border-radius: 5px; border: 1px solid #ddd;">
                    <option value="">-- اختر --</option>
                    <?php foreach($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn" style="background: var(--success); padding: 0.6rem 1.5rem;">➕ إضافة</button>
        </form>
    </div>

    <div class="card">
        <h2>قائمة الموظفين</h2>
        
        <form id="bulk-form" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف جميع الموظفين المحددين؟');">
            <input type="hidden" name="action" value="bulk_delete">
            
            <div id="bulk-actions-bar" class="bulk-actions">
                <button type="submit" class="btn btn-danger">🗑️ حذف الموظفين المحددين</button>
                <span id="selected-count" style="margin-right: 1rem; font-weight: bold;"></span>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                            <th>الاسم</th>
                            <th>الدور</th>
                            <th>المادة</th>
                            <th>تحديث</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                            <tr>
                                <td><input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>" class="user-checkbox"></td>
                                <td><strong><?= htmlspecialchars($user['name']) ?></strong><br><small><?= htmlspecialchars($user['username']) ?></small></td>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <td>
                                        <select name="new_role">
                                            <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>معلم</option>
                                            <option value="coordinator" <?= $user['role'] === 'coordinator' ? 'selected' : '' ?>>منسق</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="subject_id">
                                            <option value="">--</option>
                                            <?php foreach($subjects as $sub): ?>
                                                <option value="<?= $sub['id'] ?>" <?= $user['subject_id'] == $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><button type="submit" class="btn btn-sm">تحديث</button></td>
                                </form>
                                <td>
                                    <form method="POST" onsubmit="return confirm('حذف هذا الموظف؟');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.6rem;">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    const bulkBar = document.getElementById('bulk-actions-bar');
    const countSpan = document.getElementById('selected-count');

    function updateBulkBar() {
        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        if (checkedCount > 0) {
            bulkBar.style.display = 'block';
            countSpan.textContent = `تم تحديد ${checkedCount} موظف`;
        } else {
            bulkBar.style.display = 'none';
        }
    }

    selectAll.addEventListener('change', (e) => {
        checkboxes.forEach(cb => cb.checked = e.target.checked);
        updateBulkBar();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });
</script>

</body>
</html>
