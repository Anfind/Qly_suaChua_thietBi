<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$title = 'Tổng quan hệ thống';
$db = Database::getInstance();

// Lấy thống kê tổng quan
$stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
    'total_departments' => $db->fetch("SELECT COUNT(*) as count FROM departments WHERE status = 'active'")['count'],
    'total_equipments' => $db->fetch("SELECT COUNT(*) as count FROM equipments WHERE status != 'disposed'")['count'],
    'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests")['count'],
    'pending_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE s.code NOT IN ('COMPLETED', 'CANCELLED')")['count'],
    'completed_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE s.code = 'COMPLETED'")['count']
];

// Thống kê theo thời gian (30 ngày gần nhất)
$daily_stats = $db->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count 
     FROM repair_requests 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(created_at) 
     ORDER BY date DESC"
);

// Top equipments có nhiều đơn sửa chữa nhất
$top_equipments = $db->fetchAll(
    "SELECT e.name, e.code, COUNT(r.id) as repair_count,
            t.name as type_name, d.name as department_name
     FROM equipments e
     LEFT JOIN repair_requests r ON e.id = r.equipment_id
     LEFT JOIN equipment_types t ON e.type_id = t.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE e.status != 'disposed'
     GROUP BY e.id
     HAVING repair_count > 0
     ORDER BY repair_count DESC
     LIMIT 10"
);

// Users hoạt động nhiều nhất
$active_users = $db->fetchAll(
    "SELECT u.full_name, u.username, r.display_name as role_name,
            COUNT(CASE WHEN h.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN repair_status_history h ON u.id = h.user_id
     WHERE u.status = 'active'
     GROUP BY u.id
     ORDER BY activities_this_week DESC
     LIMIT 10"
);

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Quản trị hệ thống', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="page-title">
            <i class="fas fa-tachometer-alt me-2"></i>
            Tổng quan hệ thống
        </h2>
        <p class="text-muted">Quản lý và giám sát toàn bộ hoạt động của hệ thống</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card bg-primary text-white">
            <div class="stats-number"><?= number_format($stats['total_users']) ?></div>
            <div class="stats-label">Người dùng</div>
            <div class="stats-icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card bg-success text-white">
            <div class="stats-number"><?= number_format($stats['total_departments']) ?></div>
            <div class="stats-label">Đơn vị</div>
            <div class="stats-icon"><i class="fas fa-building"></i></div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card bg-info text-white">
            <div class="stats-number"><?= number_format($stats['total_equipments']) ?></div>
            <div class="stats-label">Thiết bị</div>
            <div class="stats-icon"><i class="fas fa-desktop"></i></div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card bg-warning text-white">
            <div class="stats-number"><?= number_format($stats['total_requests']) ?></div>
            <div class="stats-label">Tổng đơn</div>
            <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card bg-orange text-white">
            <div class="stats-number"><?= number_format($stats['pending_requests']) ?></div>
            <div class="stats-label">Đang xử lý</div>
            <div class="stats-icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card bg-success text-white">
            <div class="stats-number"><?= number_format($stats['completed_requests']) ?></div>
            <div class="stats-label">Hoàn thành</div>
            <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
</div>

<!-- Charts & Tables -->
<div class="row">
    <!-- Daily Activity Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-chart-line me-2"></i>
                    Hoạt động 30 ngày gần nhất
                </h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Active Users -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-user-clock me-2"></i>
                    Người dùng hoạt động (7 ngày)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($active_users)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Chưa có hoạt động</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($active_users as $user): ?>
                            <div class="list-group-item border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= e($user['full_name']) ?></h6>
                                        <small class="text-muted"><?= e($user['role_name']) ?></small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <?= number_format($user['activities_this_week']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Equipment Problems -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Thiết bị có nhiều sự cố nhất
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_equipments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tools fa-2x text-success mb-2"></i>
                        <p class="text-muted">Tất cả thiết bị đang hoạt động tốt!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã thiết bị</th>
                                    <th>Tên thiết bị</th>
                                    <th>Loại</th>
                                    <th>Đơn vị</th>
                                    <th class="text-center">Số lần sửa</th>
                                    <th class="text-center">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_equipments as $equipment): ?>
                                    <tr>
                                        <td><strong><?= e($equipment['code']) ?></strong></td>
                                        <td><?= e($equipment['name']) ?></td>
                                        <td><?= e($equipment['type_name']) ?></td>
                                        <td><?= e($equipment['department_name']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $equipment['repair_count'] > 5 ? 'danger' : ($equipment['repair_count'] > 2 ? 'warning' : 'info') ?> rounded-pill">
                                                <?= number_format($equipment['repair_count']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($equipment['repair_count'] > 5): ?>
                                                <span class="badge bg-danger">Cần chú ý</span>
                                            <?php elseif ($equipment['repair_count'] > 2): ?>
                                                <span class="badge bg-warning">Theo dõi</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Bình thường</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-bolt me-2"></i>
                    Thao tác quản trị
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="<?= url('admin/users.php') ?>" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-users fa-2x d-block mb-2"></i>
                            Quản lý người dùng
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?= url('admin/departments.php') ?>" class="btn btn-success w-100 py-3">
                            <i class="fas fa-building fa-2x d-block mb-2"></i>
                            Quản lý đơn vị
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?= url('admin/equipments.php') ?>" class="btn btn-info w-100 py-3">
                            <i class="fas fa-desktop fa-2x d-block mb-2"></i>
                            Quản lý thiết bị
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?= url('admin/repair-contents.php') ?>" class="btn btn-warning w-100 py-3">
                            <i class="fas fa-wrench fa-2x d-block mb-2"></i>
                            Nội dung sửa chữa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom CSS
$custom_css = "
<style>
    .bg-orange { background-color: #fd7e14 !important; }
    .stats-card {
        position: relative;
        overflow: hidden;
    }
    .stats-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 2rem;
        opacity: 0.3;
    }
    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }
    .stats-label {
        font-size: 0.875rem;
        opacity: 0.8;
        margin-top: 0.5rem;
    }
</style>
";

// Custom JS cho charts
$custom_js = "
<script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>
<script>
    // Daily activity chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    const dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: [" . implode(',', array_map(function($item) { 
                return "'" . date('d/m', strtotime($item['date'])) . "'"; 
            }, array_reverse($daily_stats))) . "],
            datasets: [{
                label: 'Đơn sửa chữa',
                data: [" . implode(',', array_column(array_reverse($daily_stats), 'count')) . "],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
</script>
";

include '../layouts/app.php';
?>
