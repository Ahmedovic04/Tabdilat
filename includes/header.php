<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'راصد تبديلاتي' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $base_url ?? '' ?>assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">🛰️ راصد تبديلاتي</div>
        <div class="small text-muted mt-1">مدرسة معيذر الابتدائية</div>
    </div>
    
    <ul class="sidebar-menu">
        <?php if ($_SESSION['rased_role'] === 'teacher'): ?>
            <li><a href="<?= $base_url ?? '' ?>teacher/index.php" class="<?= $active_page == 'home' ? 'active' : '' ?>"><i class="bi bi-house-door"></i> الرئيسية</a></li>
            <li><a href="<?= $base_url ?? '' ?>my_requests.php" class="<?= $active_page == 'requests' ? 'active' : '' ?>"><i class="bi bi-clipboard-check"></i> طلباتي</a></li>
            <li><a href="<?= $base_url ?? '' ?>teacher/schedule.php" class="<?= $active_page == 'schedule' ? 'active' : '' ?>"><i class="bi bi-calendar3"></i> جدولي</a></li>
            <li><a href="<?= $base_url ?? '' ?>teacher/request.php" class="<?= $active_page == 'new_request' ? 'active' : '' ?>"><i class="bi bi-plus-circle"></i> طلب جديد</a></li>
        <?php elseif ($_SESSION['rased_role'] === 'coordinator' || $_SESSION['rased_role'] === 'deputy'): ?>
            <li><a href="<?= $base_url ?? '' ?>index.php" class="<?= $active_page == 'home' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> لوحة التحكم</a></li>
            <li><a href="<?= $base_url ?? '' ?>deputy/users.php" class="<?= $active_page == 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i> المستخدمين</a></li>
            <li><a href="<?= $base_url ?? '' ?>deputy/reports.php" class="<?= $active_page == 'reports' ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph"></i> التقارير</a></li>
            <li><a href="<?= $base_url ?? '' ?>deputy/upload.php" class="<?= $active_page == 'upload' ? 'active' : '' ?>"><i class="bi bi-cloud-upload"></i> رفع الجداول</a></li>
        <?php endif; ?>
        
        <li class="mt-auto"><a href="<?= $base_url ?? '' ?>teacher/profile.php"><i class="bi bi-person-gear"></i> الملف الشخصي</a></li>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= $base_url ?? '' ?>teacher/logout.php" class="text-danger text-decoration-none fw-bold d-block text-center">
            <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
        </a>
    </div>
</div>

<div class="main-content">
    <div class="topbar shadow-sm">
        <h1 class="page-title"><?= $page_title ?? 'لوحة التحكم' ?></h1>
        <div class="user-nav">
            <span class="fw-bold d-none d-md-inline">مرحباً، <?= htmlspecialchars($_SESSION['rased_name']) ?></span>
            <div class="badge bg-primary px-3 py-2 rounded-pill"><?= $_SESSION['rased_role'] == 'teacher' ? 'معلم' : 'إدارة' ?></div>
        </div>
    </div>
