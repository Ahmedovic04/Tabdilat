<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = '';

// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_class') {
    $class_name = trim($_POST['class_name'] ?? '');
    if (!empty($class_name)) {
        try {
            $stmt = $db->prepare("INSERT INTO rased_classes (name) VALUES (?)");
            $stmt->execute([$class_name]);
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>تمت إضافة الصف "<strong>' . htmlspecialchars($class_name) . '</strong>" بنجاح.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>الصف "<strong>' . htmlspecialchars($class_name) . '</strong>" موجود بالفعل أو حدث خطأ أثناء الإضافة.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Handle Update Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_class') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $new_name = trim($_POST['class_name'] ?? '');
    if ($class_id > 0 && !empty($new_name)) {
        try {
            $stmt = $db->prepare("UPDATE rased_classes SET name = ? WHERE id = ?");
            $stmt->execute([$new_name, $class_id]);
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>تم تحديث اسم الصف بنجاح.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>لم يتم التحديث، قد يكون الاسم مكرراً.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Handle Delete Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_class') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    if ($class_id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM rased_classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>تم حذف الصف بنجاح.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>لا يمكن حذف الصف لاقترانه بحصص أو طلبات سابقة في النظام.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

// Fetch all classes with count of assigned schedule periods
$stmt = $db->query("
    SELECT c.id, c.name, COUNT(tc.id) as sessions_count
    FROM rased_classes c
    LEFT JOIN rased_teacher_classes tc ON c.id = tc.class_id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$classes = $stmt->fetchAll();

$page_title = 'إدارة الصفوف الدراسية - النائب الأكاديمي';
$active_page = 'classes';
$base_url = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="h3 fw-bold text-primary mb-1">
            <i class="bi bi-door-open me-2"></i>إدارة الصفوف الدراسية
        </h2>
        <p class="text-muted mb-0">إضافة وتعديل صفوف المدرسة المتاحة لجدول المعلمين</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="bi bi-plus-lg me-1"></i> إضافة صف جديد
        </button>
    </div>
</div>

<?= $message ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= count($classes) ?></h3>
                <p>إجمالي الصفوف بالمدرسة</p>
            </div>
            <div class="stat-icon text-primary"><i class="bi bi-building"></i></div>
        </div>
    </div>
</div>

<div class="custom-card shadow-sm mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h3 class="h5 fw-bold text-primary mb-0"><i class="bi bi-list-stars me-2"></i>قائمة الصفوف المعرفة</h3>
        <div class="input-group" style="max-width: 280px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="classSearch" class="form-control border-start-0 ps-0" placeholder="بحث عن صف...">
        </div>
    </div>

    <?php if (empty($classes)): ?>
        <div class="text-center py-5">
            <i class="bi bi-door-closed display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">لا توجد صفوف معرفة في النظام حالياً.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle text-center" id="classesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">#</th>
                        <th>اسم الصف الدراسـي</th>
                        <th>عدد الحصص المسجلة بالجدول</th>
                        <th style="width: 150px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $index => $cls): ?>
                        <tr class="class-row">
                            <td class="fw-bold text-muted"><?= $index + 1 ?></td>
                            <td class="fw-bold fs-6 text-primary class-name"><?= htmlspecialchars($cls['name']) ?></td>
                            <td>
                                <span class="badge bg-info text-dark px-3 py-2 fs-6"><?= $cls['sessions_count'] ?> حصة</span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="openEditModal(<?= $cls['id'] ?>, '<?= htmlspecialchars($cls['name'], ENT_QUOTES) ?>')"
                                            title="تعديل اسم الصف">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الصف؟');" class="d-inline">
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف الصف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Add New Class -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_class">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-primary" id="addClassModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>إضافة صف دراسي جديد
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_name" class="form-label fw-bold">اسم الصف (مثال: 1/1، 2/3):</label>
                        <input type="text" class="form-control form-control-lg" id="class_name" name="class_name" placeholder="أدخل اسم الصف..." required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة الصف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Class -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_class">
                <input type="hidden" name="class_id" id="edit_class_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-primary" id="editClassModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>تعديل اسم الصف
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_class_name" class="form-label fw-bold">الاسم الجديد للصف:</label>
                        <input type="text" class="form-control form-control-lg" id="edit_class_name" name="class_name" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, name) {
    document.getElementById('edit_class_id').value = id;
    document.getElementById('edit_class_name').value = name;
    new bootstrap.Modal(document.getElementById('editClassModal')).show();
}

document.getElementById('classSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.class-row').forEach(row => {
        const name = row.querySelector('.class-name').textContent.toLowerCase();
        row.style.display = name.includes(q) ? '' : 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
