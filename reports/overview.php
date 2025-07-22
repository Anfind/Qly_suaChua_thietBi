<?php
require_once '../config/config.php';
require_any_role(['admin', 'clerk']);

$title = 'Báo cáo tổng quan';
$db = Database::getInstance();

// Lấy filters
$filters = [
    'from_date' => $_GET['from_date'] ?? date('Y-m-01'), // Đầu tháng hiện tại
    'to_date' => $_GET['to_date'] ?? date('Y-m-d'), // Hôm nay
    'department_id' => $_GET['department_id'] ?? '',
    'equipment_type_id' => $_GET['equipment_type_id'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Build where conditions
$whereConditions = [];
$params = [];

if ($filters['from_date']) {
    $whereConditions[] = "r.created_at >= ?";
    $params[] = $filters['from_date'] . ' 00:00:00';
}

if ($filters['to_date']) {
    $whereConditions[] = "r.created_at <= ?";
    $params[] = $filters['to_date'] . ' 23:59:59';
}

if ($filters['department_id']) {
    $whereConditions[] = "u.department_id = ?";
    $params[] = $filters['department_id'];
}

if ($filters['equipment_type_id']) {
    $whereConditions[] = "et.id = ?";
    $params[] = $filters['equipment_type_id'];
}

if ($filters['status']) {
    $whereConditions[] = "s.code = ?";
    $params[] = $filters['status'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Thống kê tổng quan
$totalRequests = $db->fetch(
    "SELECT COUNT(*) as count FROM repair_requests r
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause",
    $params
)['count'];

$completedRequests = $db->fetch(
    "SELECT COUNT(*) as count FROM repair_requests r
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause " . (!empty($whereConditions) ? 'AND' : 'WHERE') . " s.code = 'COMPLETED'",
    array_merge($params, ['COMPLETED'])
)['count'];

$pendingRequests = $db->fetch(
    "SELECT COUNT(*) as count FROM repair_requests r
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause " . (!empty($whereConditions) ? 'AND' : 'WHERE') . " s.code NOT IN ('COMPLETED', 'CANCELLED')",
    $params
)['count'];

$totalCost = $db->fetch(
    "SELECT COALESCE(SUM(r.total_cost), 0) as total FROM repair_requests r
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause",
    $params
)['total'];

// Thống kê theo trạng thái
$statusStats = $db->fetchAll(
    "SELECT s.name, s.color, s.icon, COUNT(r.id) as count,
            ROUND(COUNT(r.id) * 100.0 / NULLIF((SELECT COUNT(*) FROM repair_requests r2
                                                 LEFT JOIN users u2 ON r2.requester_id = u2.id
                                                 LEFT JOIN equipments e2 ON r2.equipment_id = e2.id
                                                 LEFT JOIN equipment_types et2 ON e2.type_id = et2.id
                                                 LEFT JOIN repair_statuses s2 ON r2.current_status_id = s2.id
                                                 $whereClause), 0), 1) as percentage
     FROM repair_statuses s
     LEFT JOIN repair_requests r ON s.id = r.current_status_id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     $whereClause
     GROUP BY s.id, s.name, s.color, s.icon
     ORDER BY s.step_order",
    $params
);

// Thống kê theo đơn vị
$departmentStats = $db->fetchAll(
    "SELECT d.name, d.code, COUNT(r.id) as request_count,
            COALESCE(SUM(r.total_cost), 0) as total_cost,
            COUNT(CASE WHEN s.code = 'COMPLETED' THEN 1 END) as completed_count
     FROM departments d
     LEFT JOIN users u ON d.id = u.department_id
     LEFT JOIN repair_requests r ON u.id = r.requester_id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause
     GROUP BY d.id, d.name, d.code
     HAVING request_count > 0
     ORDER BY request_count DESC
     LIMIT 10",
    $params
);

// Thống kê theo loại thiết bị
$equipmentTypeStats = $db->fetchAll(
    "SELECT et.name, et.icon, COUNT(r.id) as request_count,
            COALESCE(SUM(r.total_cost), 0) as total_cost,
            AVG(DATEDIFF(r.actual_completion, r.created_at)) as avg_repair_days
     FROM equipment_types et
     LEFT JOIN equipments e ON et.id = e.type_id
     LEFT JOIN repair_requests r ON e.id = r.equipment_id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause
     GROUP BY et.id, et.name, et.icon
     HAVING request_count > 0
     ORDER BY request_count DESC
     LIMIT 10",
    $params
);

// Thống kê theo thời gian (theo ngày trong khoảng thời gian)
$dailyStats = $db->fetchAll(
    "SELECT DATE(r.created_at) as date, COUNT(*) as count
     FROM repair_requests r
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause
     GROUP BY DATE(r.created_at)
     ORDER BY date",
    $params
);

// Top thiết bị có nhiều sự cố
$topProblematicEquipments = $db->fetchAll(
    "SELECT e.name, e.code, et.name as type_name, d.name as department_name,
            COUNT(r.id) as repair_count,
            COALESCE(SUM(r.total_cost), 0) as total_cost
     FROM equipments e
     LEFT JOIN equipment_types et ON e.type_id = et.id
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN repair_requests r ON e.id = r.equipment_id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     $whereClause
     GROUP BY e.id, e.name, e.code, et.name, d.name
     HAVING repair_count > 0
     ORDER BY repair_count DESC, total_cost DESC
     LIMIT 10",
    $params
);

// Lấy data cho filters
$departments = $db->fetchAll("SELECT * FROM departments WHERE status = 'active' ORDER BY name");
$equipmentTypes = $db->fetchAll("SELECT * FROM equipment_types ORDER BY name");
$statuses = $db->fetchAll("SELECT * FROM repair_statuses ORDER BY step_order");

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Báo cáo', 'url' => ''],
    ['title' => 'Tổng quan', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-chart-bar me-2"></i>
            Báo cáo tổng quan
        </h2>
        <p class="text-muted">
            Từ <?= format_date($filters['from_date']) ?> đến <?= format_date($filters['to_date']) ?>
        </p>
    </div>
    <div class="col-md-4 text-end">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="exportReport('excel')">
                <i class="fas fa-file-excel me-1"></i>Excel
            </button>
            <button type="button" class="btn btn-outline-danger" onclick="exportReport('pdf')">
                <i class="fas fa-file-pdf me-1"></i>PDF
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>In
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-filter me-2"></i>Bộ lọc
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Từ ngày</label>
                <input type="date" name="from_date" class="form-control" value="<?= e($filters['from_date']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="to_date" class="form-control" value="<?= e($filters['to_date']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Đơn vị</label>
                <select name="department_id" class="form-select">
                    <option value="">Tất cả</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filters['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                            <?= e($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Loại thiết bị</label>
                <select name="equipment_type_id" class="form-select">
                    <option value="">Tất cả</option>
                    <?php foreach ($equipmentTypes as $type): ?>
                        <option value="<?= $type['id'] ?>" <?= $filters['equipment_type_id'] == $type['id'] ? 'selected' : '' ?>>
                            <?= e($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status['code'] ?>" <?= $filters['status'] === $status['code'] ? 'selected' : '' ?>>
                            <?= e($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Lọc dữ liệu
                </button>
                <a href="<?= url('reports/overview.php') ?>" class="btn btn-secondary">
                    <i class="fas fa-redo me-1"></i>Làm mới
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card bg-primary text-white">
            <div class="stats-number"><?= number_format($totalRequests) ?></div>
            <div class="stats-label">Tổng đơn sửa chữa</div>
            <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card bg-success text-white">
            <div class="stats-number"><?= number_format($completedRequests) ?></div>
            <div class="stats-label">Đã hoàn thành</div>
            <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card bg-warning text-white">
            <div class="stats-number"><?= number_format($pendingRequests) ?></div>
            <div class="stats-label">Đang xử lý</div>
            <div class="stats-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card bg-info text-white">
            <div class="stats-number"><?= format_money($totalCost) ?></div>
            <div class="stats-label">Tổng chi phí</div>
            <div class="stats-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Status Distribution -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Phân bố theo trạng thái</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Daily Trend -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Xu hướng theo ngày</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row">
    <!-- Department Stats -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Top đơn vị có nhiều đơn nhất</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($departmentStats)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-building fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Không có dữ liệu</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Đơn vị</th>
                                    <th class="text-center">Số đơn</th>
                                    <th class="text-center">Hoàn thành</th>
                                    <th class="text-end">Chi phí</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departmentStats as $dept): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($dept['name']) ?></strong><br>
                                            <small class="text-muted"><?= e($dept['code']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill">
                                                <?= number_format($dept['request_count']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success rounded-pill">
                                                <?= number_format($dept['completed_count']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= format_money($dept['total_cost']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Equipment Type Stats -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Top loại thiết bị có nhiều sự cố</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($equipmentTypeStats)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-desktop fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Không có dữ liệu</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Loại thiết bị</th>
                                    <th class="text-center">Số đơn</th>
                                    <th class="text-center">Thời gian TB</th>
                                    <th class="text-end">Chi phí</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipmentTypeStats as $type): ?>
                                    <tr>
                                        <td>
                                            <i class="<?= e($type['icon']) ?> me-2"></i>
                                            <?= e($type['name']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning rounded-pill">
                                                <?= number_format($type['request_count']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?= $type['avg_repair_days'] ? round($type['avg_repair_days']) . ' ngày' : 'N/A' ?>
                                        </td>
                                        <td class="text-end"><?= format_money($type['total_cost']) ?></td>
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

<!-- Top Problematic Equipments -->
<?php if (!empty($topProblematicEquipments)): ?>
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
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã thiết bị</th>
                                <th>Tên thiết bị</th>
                                <th>Loại</th>
                                <th>Đơn vị</th>
                                <th class="text-center">Số lần sửa</th>
                                <th class="text-end">Tổng chi phí</th>
                                <th class="text-center">Mức độ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProblematicEquipments as $equipment): ?>
                                <tr>
                                    <td><strong><?= e($equipment['code']) ?></strong></td>
                                    <td><?= e($equipment['name']) ?></td>
                                    <td><?= e($equipment['type_name']) ?></td>
                                    <td><?= e($equipment['department_name']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $equipment['repair_count'] > 5 ? 'danger' : ($equipment['repair_count'] > 3 ? 'warning' : 'info') ?> rounded-pill">
                                            <?= number_format($equipment['repair_count']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= format_money($equipment['total_cost']) ?></td>
                                    <td class="text-center">
                                        <?php if ($equipment['repair_count'] > 5): ?>
                                            <span class="badge bg-danger">Nghiêm trọng</span>
                                        <?php elseif ($equipment['repair_count'] > 3): ?>
                                            <span class="badge bg-warning">Cần chú ý</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Bình thường</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();

// Custom CSS for print
$custom_css = "
<style>
    @media print {
        .btn-group, .card-header .btn, #filterCard { display: none !important; }
        .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        .page-title { font-size: 1.5rem !important; }
        .stats-card { border: 1px solid #dee2e6 !important; }
    }
    
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
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [" . implode(',', array_map(function($item) { 
                return "'" . addslashes($item['name']) . "'"; 
            }, $statusStats)) . "],
            datasets: [{
                data: [" . implode(',', array_column($statusStats, 'count')) . "],
                backgroundColor: [" . implode(',', array_map(function($item) { 
                    return "'" . $item['color'] . "'"; 
                }, $statusStats)) . "],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        }
    });
    
    // Daily Chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    const dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: [" . implode(',', array_map(function($item) { 
                return "'" . date('d/m', strtotime($item['date'])) . "'"; 
            }, $dailyStats)) . "],
            datasets: [{
                label: 'Số đơn sửa chữa',
                data: [" . implode(',', array_column($dailyStats, 'count')) . "],
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
    
    // Export functions
    function exportReport(format) {
        const params = new URLSearchParams(window.location.search);
        params.set('export', format);
        window.open('export-report.php?' + params.toString(), '_blank');
    }
</script>
";

include '../layouts/app.php';
?>
