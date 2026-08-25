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
<?php 
$page_title = 'إدارة الموظفين والصلاحيات';
$active_page = 'users';
$base_url = '../';
include '../includes/header.php'; 
?>

<div class="container-fluid">
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add User Column -->
        <div class="col-xl-4">
            <div class="custom-card shadow-sm">
                <h2 class="h5 mb-4 fw-bold text-primary"><i class="bi bi-person-plus me-2"></i>إضافة موظف جديد</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">الاسم الكامل</label>
                        <input type="text" name="name" class="form-control" required placeholder="أدخل اسم الموظف">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">الصلاحية</label>
                        <select name="role" class="form-select">
                            <option value="teacher">معلم</option>
                            <option value="coordinator">منسق مادة</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">المادة الدراسية</label>
                        <select name="subject_id" class="form-select">
                            <option value="">-- غير محدد --</option>
                            <?php foreach($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-plus-lg me-1"></i> إضافة الموظف
                    </button>
                    <p class="text-muted small mt-3 text-center">
                        <i class="bi bi-info-circle me-1"></i> سيتم إنشاء اسم مستخدم تلقائي بكلمة مرور افتراضية (123456).
                    </p>
                </form>
            </div>
        </div>

        <!-- Users List Column -->
        <div class="col-xl-8">
            <div class="custom-card shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2 class="h5 mb-0 fw-bold text-primary"><i class="bi bi-people me-2"></i>قائمة الموظفين</h2>
                    
                    <!-- Search Input -->
                    <div class="input-group" style="max-width: 300px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="userSearch" class="form-control border-start-0 ps-0" placeholder="بحث عن موظف...">
                    </div>
                </div>
                
                <form id="bulk-form" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف جميع الموظفين المحددين؟');">
                    <input type="hidden" name="action" value="bulk_delete">
                    
                    <div id="bulk-actions-bar" class="alert alert-dark py-2 px-3 mb-3 d-none align-items-center justify-content-between shadow-sm">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check2-square fs-5 me-2"></i>
                            <span id="selected-count" class="fw-bold"></span>
                        </div>
                        <button type="submit" class="btn btn-sm btn-danger px-3">🗑️ حذف المحدد</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all" class="form-check-input"></th>
                                    <th>الموظف</th>
                                    <th>الدور والصلاحية</th>
                                    <th>المادة</th>
                                    <th class="text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                    <tr class="user-row">
                                        <td><input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>" class="user-checkbox form-check-input"></td>
                                        <td>
                                            <div class="fw-bold user-name"><?= htmlspecialchars($user['name']) ?></div>
                                            <div class="small text-muted user-username"><?= htmlspecialchars($user['username']) ?></div>
                                            <?php if($user['is_new']): ?>
                                                <span class="badge bg-danger p-1 small" style="font-size: 0.65rem;">جديد</span>
                                            <?php endif; ?>
                                        </td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <td>
                                                <select name="new_role" class="form-select form-select-sm" style="min-width: 120px;">
                                                    <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>معلم</option>
                                                    <option value="coordinator" <?= $user['role'] === 'coordinator' ? 'selected' : '' ?>>منسق مادة</option>
                                                    <option value="deputy" <?= $user['role'] === 'deputy' ? 'selected' : '' ?>>نائب/مدير</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="subject_id" class="form-select form-select-sm" style="min-width: 130px;">
                                                    <option value="">-- غير محدد --</option>
                                                    <?php foreach($subjects as $sub): ?>
                                                        <option value="<?= $sub['id'] ?>" <?= $user['subject_id'] == $sub['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sub['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="حفظ التعديلات"><i class="bi bi-check2"></i></button>
                                        </form>
                                                    <a href="../teacher/schedule.php?teacher_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-info" title="تعديل جدول الحصص والصفوف"><i class="bi bi-calendar3"></i></a>
                                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الموظف نهائياً؟');" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف الموظف"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search Functionality
        const searchInput = document.getElementById('userSearch');
        const tableRows = document.querySelectorAll('.user-row');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            tableRows.forEach(row => {
                const name = row.querySelector('.user-name').textContent.toLowerCase();
                const username = row.querySelector('.user-username').textContent.toLowerCase();
                
                if (name.includes(query) || username.includes(query)) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
            });
        });

        // Bulk Actions
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const bulkBar = document.getElementById('bulk-actions-bar');
        const countSpan = document.getElementById('selected-count');

        function updateBulkBar() {
            const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
            if (checkedCount > 0) {
                bulkBar.classList.remove('d-none');
                bulkBar.classList.add('d-flex');
                countSpan.textContent = `تم تحديد ${checkedCount} موظف`;
            } else {
                bulkBar.classList.add('d-none');
                bulkBar.classList.remove('d-flex');
            }
        }

        selectAll.addEventListener('change', (e) => {
            checkboxes.forEach(cb => {
                if (!cb.closest('.user-row').classList.contains('d-none')) {
                    cb.checked = e.target.checked;
                }
            });
            updateBulkBar();
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkBar);
        });
    });
</script>

<?php include '../includes/footer.php'; ?>

