<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator', 'deputy'])) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = '';

// Determine target teacher ID
$logged_user_id = (int)$_SESSION['rased_user_id'];
$logged_role = $_SESSION['rased_role'];

$teacher_id = $logged_user_id;
$editing_other = false;
$teacher_name = '';

if ($logged_role === 'deputy' && isset($_GET['teacher_id']) && (int)$_GET['teacher_id'] > 0) {
    $teacher_id = (int)$_GET['teacher_id'];
    $editing_other = true;
}

// Fetch teacher name
$stmtName = $db->prepare("SELECT name FROM rased_users WHERE id = ?");
$stmtName->execute([$teacher_id]);
$teacher_name = $stmtName->fetchColumn();

// Handle Add New Class (Deputy only permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_class'])) {
    if ($logged_role !== 'deputy') {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i>عذراً، إضافة وإدارة الصفوف مقتصرة فقط على النائب الأكاديمي / إدارة النظام.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } else {
        $new_class_name = trim($_POST['new_class_name'] ?? '');
        if (!empty($new_class_name)) {
            try {
                $stmtAddCls = $db->prepare("INSERT INTO rased_classes (name) VALUES (?)");
                $stmtAddCls->execute([$new_class_name]);
                $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>تم إضافة الصف "<strong>' . htmlspecialchars($new_class_name) . '</strong>" بنجاح إلى النظام.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            } catch (Exception $e) {
                $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>الصف "<strong>' . htmlspecialchars($new_class_name) . '</strong>" موجود بالفعل أو لم تكتمل الإضافة.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
            }
        }
    }
}

// Handle save schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("DELETE FROM rased_teacher_classes WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        
        $insert_stmt = $db->prepare("INSERT INTO rased_teacher_classes (teacher_id, class_id, day_of_week, period_number) VALUES (?, ?, ?, ?)");
        
        if (isset($_POST['schedule']) && is_array($_POST['schedule'])) {
            foreach ($_POST['schedule'] as $day => $periods) {
                foreach ($periods as $period => $class_id) {
                    $class_id = (int)$class_id;
                    if (!$class_id) continue;
                    $insert_stmt->execute([$teacher_id, $class_id, (int)$day, (int)$period]);
                }
            }
        }
        $db->commit();
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>تم حفظ الجدول المدرسي بنجاح!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    } catch (Exception $e) {
        $db->rollBack();
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i>حدث خطأ أثناء حفظ الجدول: ' . htmlspecialchars($e->getMessage()) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

// Get all available classes from DB
$allClasses = $db->query("SELECT id, name FROM rased_classes ORDER BY name ASC")->fetchAll();

// Get current schedule for target teacher
$stmt = $db->prepare("
    SELECT tc.day_of_week, tc.period_number, tc.class_id
    FROM rased_teacher_classes tc 
    WHERE tc.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$schedule = [];
foreach ($stmt->fetchAll() as $s) {
    $schedule[$s['day_of_week']][$s['period_number']] = $s['class_id'];
}

$days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];
$page_title = 'إعداد جدول الحصص - راصد تبديلاتي';
$active_page = 'schedule';
$base_url = '../';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold text-primary mb-1">
            <i class="bi bi-calendar3 me-2"></i>إعداد وتعديل جدول الحصص
        </h2>
        <p class="text-muted mb-0">
            <?= $editing_other ? 'تعديل جدول المعلم: <strong>' . htmlspecialchars($teacher_name) . '</strong>' : 'قم بتنسيق جدول الحصص الأسبوعي الخاص بك' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($logged_role === 'deputy'): ?>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="bi bi-plus-lg me-1"></i> إضافة صف جديد
            </button>
        <?php endif; ?>
        <a href="<?= $base_url . $_SESSION['rased_role'] ?>/index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right me-1"></i> العودة
        </a>
    </div>
</div>

<?= $message ?>

<div class="custom-card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
            <div>
                <strong>توجيهات إدخال الجدول:</strong> حدد الصف المناسب أمام كل حصة من الحصص (1 إلى 7) لجميع أيام الأسبوع. اترك الحصة على الخيار (—) إذا كنت غير مكلف بحصة في ذلك التوقيت.
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="save_schedule" value="1">
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;" class="bg-primary text-white">اليوم / الحصة</th>
                            <?php for($i = 1; $i <= 7; $i++): ?>
                                <th class="bg-light fw-bold fs-6">الحصة <?= $i ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($days as $day_index => $day_name): ?>
                            <tr>
                                <td class="fw-bold bg-light text-primary fs-6"><?= $day_name ?></td>
                                <?php for($i = 1; $i <= 7; $i++): ?>
                                    <?php $selected_id = $schedule[$day_index][$i] ?? 0; ?>
                                    <td class="<?= $selected_id ? 'bg-white' : 'bg-light' ?>">
                                        <select
                                            name="schedule[<?= $day_index ?>][<?= $i ?>]"
                                            class="form-select form-select-sm text-center shadow-none <?= $selected_id ? 'border-primary fw-bold text-primary bg-primary-subtle' : '' ?>"
                                            onchange="this.className = this.value ? 'form-select form-select-sm text-center shadow-none border-primary fw-bold text-primary bg-primary-subtle' : 'form-select form-select-sm text-center shadow-none'"
                                        >
                                            <option value="0">— لا يوجد —</option>
                                            <?php foreach($allClasses as $cls): ?>
                                                <option value="<?= $cls['id'] ?>" <?= $selected_id == $cls['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cls['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-4 gap-2">
                <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                    <i class="bi bi-floppy-fill me-2"></i> حفظ الجدول
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($logged_role === 'deputy'): ?>
<!-- Modal: Add New Class (Deputy Only) -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_new_class" value="1">
                <div class="modal-header">
                    <h5 class="modal-header-title fw-bold text-primary mb-0" id="addClassModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>إضافة صف جديد للنظام
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_class_name" class="form-label fw-bold">اسم الصف (مثال: 5/1، 6/2):</label>
                        <input type="text" class="form-control form-control-lg" id="new_class_name" name="new_class_name" placeholder="أدخل اسم الصف..." required>
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
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
