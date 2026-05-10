<?php
require_once '../config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$teacher_id = $_SESSION['rased_user_id'];

// Get Teacher's Schedule
$stmt = $db->prepare("
    SELECT tc.day_of_week, tc.period_number, c.name as class_name 
    FROM rased_teacher_classes tc 
    JOIN rased_classes c ON tc.class_id = c.id 
    WHERE tc.teacher_id = ?
    ORDER BY tc.day_of_week, tc.period_number
");
$stmt->execute([$teacher_id]);
$schedule = $stmt->fetchAll();

$days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'];

// Get Today's Substitutions Count for this teacher (approved by substitute is now fully approved)
$stmtToday = $db->prepare("
    SELECT COUNT(*) 
    FROM rased_requests 
    WHERE (requester_id = ? OR substitute_id = ?) 
    AND request_date = CURDATE()
    AND (sub_coordinator_status = 'approved' OR deputy_status = 'approved')
");
$stmtToday->execute([$teacher_id, $teacher_id]);
$today_substitutions_count = $stmtToday->fetchColumn();
?>
<?php 
$page_title = 'لوحة المعلم - راصد تبديلاتي';
$active_page = 'home';
$base_url = '../';
include '../includes/header.php'; 
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= count($schedule) ?></h3>
                <p>إجمالي الحصص</p>
            </div>
            <div class="stat-icon"><i class="bi bi-book"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-right-color: var(--accent-color);">
            <div class="stat-info">
                <h3><?= $today_substitutions_count ?></h3>
                <p>تبديلات اليوم</p>
            </div>
            <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
        </div>
    </div>
</div>

<div class="custom-card shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0 fw-bold text-primary">جدول الحصص الخاص بك</h2>
        <div class="d-flex gap-2">
            <!-- Schedule setting disabled for teachers to prevent manual tampering -->
            <a href="request.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> طلب تبديل</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th style="width: 150px;">اليوم / الحصة</th>
                    <?php for($i=1; $i<=7; $i++): ?>
                        <th><?= $i ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($days as $index => $day): ?>
                    <tr>
                        <td class="fw-bold bg-light"><?= $day ?></td>
                        <?php for($i=1; $i<=7; $i++): ?>
                            <?php 
                                $class = '';
                                foreach($schedule as $s) {
                                    if($s['day_of_week'] == $index && $s['period_number'] == $i) {
                                        $class = $s['class_name'];
                                        break;
                                    }
                                }
                            ?>
                            <td class="<?= $class ? 'bg-white' : 'bg-light text-muted' ?>">
                                <?= $class ? '<span class="badge bg-info text-dark">'.$class.'</span>' : '-' ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

