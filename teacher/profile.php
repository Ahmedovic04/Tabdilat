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
    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) || empty($email)) {
            $stmt = $db->prepare("UPDATE rased_users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user_id]);
            $message = '<div style="color:green; margin-bottom:1rem; padding:1rem; background:#D1FAE5; border-radius:8px;">تم تحديث البيانات بنجاح!</div>';
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
<?php 
$page_title = 'الملف الشخصي - راصد تبديلاتي';
$active_page = 'profile';
$base_url = '../';
include '../includes/header.php'; 

// Additional logic for adding another admin if deputy
if ($_SESSION['rased_role'] === 'deputy' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $admin_name = trim($_POST['admin_name']);
    $admin_username = trim($_POST['admin_username']);
    $admin_password = $_POST['admin_password'];
    
    if ($admin_name && $admin_username && $admin_password) {
        // Check if username exists
        $check = $db->prepare("SELECT id FROM rased_users WHERE username = ?");
        $check->execute([$admin_username]);
        if ($check->fetch()) {
            $message = '<div class="alert alert-danger">اسم المستخدم موجود مسبقاً.</div>';
        } else {
            $hashed_pass = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO rased_users (username, password, name, role, is_new) VALUES (?, ?, ?, 'deputy', 0)");
            $stmt->execute([$admin_username, $hashed_pass, $admin_name]);
            $message = '<div class="alert alert-success">تم إضافة المدير الجديد بنجاح!</div>';
        }
    }
}
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="custom-card shadow-sm h-100">
            <h2 class="h4 mb-4 fw-bold text-primary"><i class="bi bi-person-lock me-2"></i>تأمين الحساب</h2>
            <?= $message ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold">البريد الإلكتروني</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control border-start-0" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" placeholder="example@school.com">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
                        <input type="password" name="new_password" class="form-control border-start-0" placeholder="اتركها فارغة إذا لم ترد التغيير">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">تأكيد كلمة المرور</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-check text-muted"></i></span>
                        <input type="password" name="confirm_password" class="form-control border-start-0" placeholder="أعد إدخال كلمة المرور">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-3 shadow-sm">
                    <i class="bi bi-save me-2"></i> حفظ التغييرات
                </button>
            </form>
        </div>
    </div>

    <?php if ($_SESSION['rased_role'] === 'deputy'): ?>
    <div class="col-md-6">
        <div class="custom-card shadow-sm h-100">
            <h2 class="h4 mb-4 fw-bold text-success"><i class="bi bi-person-plus-fill me-2"></i>إضافة مدير (أدمن) جديد</h2>
            <p class="text-muted small mb-4">يمكنك منح صلاحيات إدارية كاملة لشخص آخر من خلال هذا النموذج.</p>
            <form method="POST">
                <input type="hidden" name="add_admin" value="1">
                <div class="mb-3">
                    <label class="form-label fw-bold small">اسم المدير الكامل</label>
                    <input type="text" name="admin_name" class="form-control bg-light border-0" required placeholder="أدخل الاسم الرباعي">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">اسم المستخدم (للدخول)</label>
                    <input type="text" name="admin_username" class="form-control bg-light border-0" required placeholder="مثال: admin_2">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold small">كلمة المرور</label>
                    <input type="password" name="admin_password" class="form-control bg-light border-0" required placeholder="أدخل كلمة مرور قوية">
                </div>
                <button type="submit" class="btn btn-success w-100 py-3 shadow-sm">
                    <i class="bi bi-person-plus me-2"></i> إنشاء حساب المدير
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

