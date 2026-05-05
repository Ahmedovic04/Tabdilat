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
    <script>
        function toggleSidebar() {
            const body = document.body;
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 991) {
                body.classList.toggle('sidebar-open');
                if (overlay) {
                    overlay.style.display = body.classList.contains('sidebar-open') ? 'block' : 'none';
                }
            } else {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-state', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
            }
        }

        // Apply state immediately to prevent flicker
        (function() {
            const state = localStorage.getItem('sidebar-state');
            if (window.innerWidth > 991 && state === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
                // We also add it to body once it's available, but documentElement helps early
            }
        })();
    </script>
</head>
<body class="<?= (isset($_COOKIE['sidebar_state']) && $_COOKIE['sidebar_state'] == 'collapsed') ? 'sidebar-collapsed' : '' ?>">
    <script>
        // Double check state on body as well
        if (window.innerWidth > 991 && localStorage.getItem('sidebar-state') === 'collapsed') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

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
        <?php elseif ($_SESSION['rased_role'] === 'coordinator'): ?>
            <li><a href="<?= $base_url ?? '' ?>coordinator/index.php" class="<?= $active_page == 'home' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> لوحة المنسق</a></li>
            <li><a href="<?= $base_url ?? '' ?>my_requests.php" class="<?= $active_page == 'requests' ? 'active' : '' ?>"><i class="bi bi-clipboard-check"></i> طلباتي</a></li>
            <li><a href="<?= $base_url ?? '' ?>teacher/request.php" class="<?= $active_page == 'new_request' ? 'active' : '' ?>"><i class="bi bi-plus-circle"></i> طلب تبديل</a></li>
        <?php elseif ($_SESSION['rased_role'] === 'deputy'): ?>
            <li><a href="<?= $base_url ?? '' ?>index.php" class="<?= $active_page == 'home' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> لوحة التحكم</a></li>
            <li><a href="<?= $base_url ?? '' ?>deputy/users.php" class="<?= $active_page == 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i> المستخدمين</a></li>
            <li><a href="<?= $base_url ?? '' ?>deputy/reports.php" class="<?= $active_page == 'reports' ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph"></i> التقارير</a></li>
            <li><a href="<?= $base_url ?? '' ?>deputy/upload.php" class="<?= $active_page == 'upload' ? 'active' : '' ?>"><i class="bi bi-cloud-upload"></i> رفع الجداول</a></li>
        <?php endif; ?>
        
        <li class="mt-auto"><a href="<?= $base_url ?? '' ?>teacher/profile.php"><i class="bi bi-person-gear"></i> الملف الشخصي</a></li>
    </ul>



    <div class="sidebar-footer">
        <a href="<?= $base_url ?? '' ?>teacher/logout.php" class="text-danger text-decoration-none fw-bold d-block text-center py-2">
            <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
        </a>
    </div>
</div>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" onclick="toggleSidebar()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999;"></div>

<div class="main-content">
    <div class="topbar shadow-sm px-3 py-2">
        <div class="d-flex align-items-center gap-3 w-100">
            <button id="sidebarToggle" onclick="toggleSidebar()" class="btn btn-light shadow-sm d-flex align-items-center justify-content-center" style="width:40px; height:40px; border-radius:10px;">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h1 class="page-title mb-0 fs-5 fw-bold text-primary"><?= $page_title ?? 'لوحة التحكم' ?></h1>
            
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="user-nav d-none d-sm-flex align-items-center gap-2">
                    <span class="small fw-bold">مرحباً، <?= htmlspecialchars($_SESSION['rased_name']) ?></span>
                    <span class="badge bg-primary rounded-pill small"><?= $_SESSION['rased_role'] == 'teacher' ? 'معلم' : 'إدارة' ?></span>
                </div>
            </div>
        </div>
    </div>


