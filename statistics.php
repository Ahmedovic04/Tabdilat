<?php
require_once 'config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['rased_user_id'];
$role = $_SESSION['rased_role'];
$base_url = '';

$db = getDB();

// Date range filter
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default to start of current month
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Default to end of current month

// Statistics Queries

// 1. Total substitutions count
$stmt = $db->prepare("
    SELECT COUNT(*) FROM rased_requests 
    WHERE (requester_id = ? OR substitute_id = ?)
    AND request_date BETWEEN ? AND ?
    AND (sub_coordinator_status = 'approved' OR deputy_status = 'approved')
");
$stmt->execute([$user_id, $user_id, $date_from, $date_to]);
$total_substitutions = $stmt->fetchColumn();

// 2. As requester vs as substitute
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN requester_id = ? THEN 1 ELSE 0 END) as as_requester,
        SUM(CASE WHEN substitute_id = ? THEN 1 ELSE 0 END) as as_substitute
    FROM rased_requests 
    WHERE (requester_id = ? OR substitute_id = ?)
    AND request_date BETWEEN ? AND ?
    AND (sub_coordinator_status = 'approved' OR deputy_status = 'approved')
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $date_from, $date_to]);
$role_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. Monthly statistics (last 6 months)
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(request_date, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(CASE WHEN requester_id = ? THEN 1 ELSE 0 END) as requester_count,
        SUM(CASE WHEN substitute_id = ? THEN 1 ELSE 0 END) as substitute_count
    FROM rased_requests 
    WHERE (requester_id = ? OR substitute_id = ?)
    AND request_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND (sub_coordinator_status = 'approved' OR deputy_status = 'approved')
    GROUP BY DATE_FORMAT(request_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Top classes requested
$stmt = $db->prepare("
    SELECT c.name, COUNT(*) as count
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    WHERE (r.requester_id = ? OR r.substitute_id = ?)
    AND r.request_date BETWEEN ? AND ?
    AND (r.sub_coordinator_status = 'approved' OR r.deputy_status = 'approved')
    GROUP BY c.id
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id, $date_from, $date_to]);
$top_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Top substitutes (if user is requester)
$stmt = $db->prepare("
    SELECT u.name, COUNT(*) as count
    FROM rased_requests r
    JOIN rased_users u ON r.substitute_id = u.id
    WHERE r.requester_id = ?
    AND r.request_date BETWEEN ? AND ?
    AND (r.sub_coordinator_status = 'approved' OR r.deputy_status = 'approved')
    GROUP BY r.substitute_id
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_id, $date_from, $date_to]);
$top_substitutes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Status breakdown
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN sub_coordinator_status = 'approved' OR deputy_status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN sub_coordinator_status = 'rejected' OR deputy_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN sub_coordinator_status = 'pending' AND (deputy_status = 'pending' OR deputy_status IS NULL) THEN 1 ELSE 0 END) as pending
    FROM rased_requests 
    WHERE (requester_id = ? OR substitute_id = ?)
    AND request_date BETWEEN ? AND ?
");
$stmt->execute([$user_id, $user_id, $date_from, $date_to]);
$status_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 7. Daily distribution (period numbers)
$stmt = $db->prepare("
    SELECT 
        period_number,
        COUNT(*) as count
    FROM rased_requests 
    WHERE (requester_id = ? OR substitute_id = ?)
    AND request_date BETWEEN ? AND ?
    AND (sub_coordinator_status = 'approved' OR deputy_status = 'approved')
    GROUP BY period_number
    ORDER BY period_number ASC
");
$stmt->execute([$user_id, $user_id, $date_from, $date_to]);
$period_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'الإحصائيات والتقارير - راصد تبديلاتي';
$active_page = 'statistics';

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-graph-up text-primary me-2"></i>
                الإحصائيات والتقارير
            </h4>
            <p class="text-muted mb-0">تحليل بيانات التبديل والتعويض</p>
        </div>
        
        <!-- Date Filter -->
        <form class="d-flex gap-2" method="GET">
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            <span class="align-self-center">إلى</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" style="border-right-color: #4CAF50;">
                <div class="stat-info">
                    <h3><?= $total_substitutions ?></h3>
                    <p>إجمالي التبديلات</p>
                </div>
                <div class="stat-icon" style="background: #E8F5E9; color: #4CAF50;">
                    <i class="bi bi-clipboard-check"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-right-color: #2196F3;">
                <div class="stat-info">
                    <h3><?= $role_stats['as_requester'] ?? 0 ?></h3>
                    <p>كنت طالباً</p>
                </div>
                <div class="stat-icon" style="background: #E3F2FD; color: #2196F3;">
                    <i class="bi bi-person-up"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-right-color: #FF9800;">
                <div class="stat-info">
                    <h3><?= $role_stats['as_substitute'] ?? 0 ?></h3>
                    <p>كنت بديلاً</p>
                </div>
                <div class="stat-icon" style="background: #FFF3E0; color: #FF9800;">
                    <i class="bi bi-person-down"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-right-color: #9C27B0;">
                <div class="stat-info">
                    <h3><?= number_format(($role_stats['as_requester'] ?? 0) > 0 ? ($role_stats['as_substitute'] ?? 0) / $role_stats['as_requester'] * 100 : 0, 1) ?>%</h3>
                    <p>نسبة التعويض</p>
                </div>
                <div class="stat-icon" style="background: #F3E5F5; color: #9C27B0;">
                    <i class="bi bi-percent"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="custom-card">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-bar-chart-line text-primary me-2"></i>
                    الإحصائيات الشهرية (آخر 6 أشهر)
                </h5>
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="custom-card">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-pie-chart text-primary me-2"></i>
                    حالة الطلبات
                </h5>
                <canvas id="statusChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="custom-card">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-clock-history text-primary me-2"></i>
                    توزيع الحصص اليومية
                </h5>
                <canvas id="periodChart" height="200"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="custom-card">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-building text-primary me-2"></i>
                    أكثر الصفوف تبديلاً
                </h5>
                <?php if (empty($top_classes)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">لا توجد بيانات</p>
                    </div>
                <?php else: ?>
                    <canvas id="classesChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Substitutes Table -->
    <?php if (!empty($top_substitutes)): ?>
    <div class="row">
        <div class="col-12">
            <div class="custom-card">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-people text-primary me-2"></i>
                    أكثر المعلمين استبدالاً منك
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المعلم</th>
                                <th>عدد مرات التبديل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_substitutes as $index => $sub): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($sub['name']) ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $sub['count'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?= json_encode($monthly_stats) ?>;

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => {
            const [year, month] = d.month.split('-');
            return `${month}/${year}`;
        }),
        datasets: [
            {
                label: 'كنت طالباً',
                data: monthlyData.map(d => d.requester_count),
                backgroundColor: '#2196F3',
                borderRadius: 5
            },
            {
                label: 'كنت بديلاً',
                data: monthlyData.map(d => d.substitute_count),
                backgroundColor: '#FF9800',
                borderRadius: 5
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                rtl: true
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['معتمد', 'مرفوض', 'معلق'],
        datasets: [{
            data: [
                <?= $status_stats['approved'] ?? 0 ?>,
                <?= $status_stats['rejected'] ?? 0 ?>,
                <?= $status_stats['pending'] ?? 0 ?>
            ],
            backgroundColor: ['#4CAF50', '#f44336', '#FFC107'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                rtl: true
            }
        }
    }
});

// Period Chart
const periodCtx = document.getElementById('periodChart').getContext('2d');
const periodData = <?= json_encode($period_stats) ?>;

new Chart(periodCtx, {
    type: 'line',
    data: {
        labels: periodData.map(d => 'الحصة ' + d.period_number),
        datasets: [{
            label: 'عدد التبديلات',
            data: periodData.map(d => d.count),
            borderColor: '#9C27B0',
            backgroundColor: 'rgba(156, 39, 176, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Classes Chart
<?php if (!empty($top_classes)): ?>
const classesCtx = document.getElementById('classesChart').getContext('2d');
const classesData = <?= json_encode($top_classes) ?>;

new Chart(classesCtx, {
    type: 'bar',
    data: {
        labels: classesData.map(d => d.name),
        datasets: [{
            label: 'عدد التبديلات',
            data: classesData.map(d => d.count),
            backgroundColor: '#00BCD4',
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
