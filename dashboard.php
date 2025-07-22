<?php
require_once 'config/config.php';
require_login();

$title = 'Dashboard'    $recent_requests = $db->fetchAll(
        "SELECT r.request_code, r.created_at, e.name as equipment_name,
                u.full_name as requester_name, s.name as status_name, s.color as status_color,
                s.icon as status_icon, r.urgency_level
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE $condition
         ORDER BY r.created_at DESC
         LIMIT 5"
    );current_user();

// Lấy thống kê tổng quan
$db = Database::getInstance();

// Thống kê cho admin
if (has_role('admin')) {
    $stats = [
        'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests")['count'],
        'pending_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE s.code != 'COMPLETED' AND s.code != 'CANCELLED'")['count'],
        'completed_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE s.code = 'COMPLETED'")['count'],
        'total_equipments' => $db->fetch("SELECT COUNT(*) as count FROM equipments WHERE status != 'disposed'")['count'],
        'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
        'total_departments' => $db->fetch("SELECT COUNT(*) as count FROM departments WHERE status = 'active'")['count']
    ];
    
    // Requests by status cho biểu đồ
    $requests_by_status = $db->fetchAll(
        "SELECT s.name, s.color, COUNT(r.id) as count 
         FROM repair_statuses s 
         LEFT JOIN repair_requests r ON s.id = r.current_status_id 
         WHERE s.code != 'CANCELLED'
         GROUP BY s.id, s.name, s.color 
         ORDER BY s.step_order"
    );
    
    // Recent requests
    $recent_requests = $db->fetchAll(
        "SELECT r.request_code, r.created_at, e.name as equipment_name, 
                u.full_name as requester_name, s.name as status_name, s.color as status_color
         FROM repair_requests r
         JOIN equipments e ON r.equipment_id = e.id
         JOIN users u ON r.requester_id = u.id
         JOIN repair_statuses s ON r.current_status_id = s.id
         ORDER BY r.created_at DESC
         LIMIT 10"
    );
}

// Thống kê cho người đề xuất
elseif (has_role('requester')) {
    $stats = [
        'my_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests WHERE requester_id = ?", [$user['id']])['count'],
        'pending_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE r.requester_id = ? AND s.code IN ('PENDING_HANDOVER', 'HANDED_TO_CLERK')", [$user['id']])['count'],
        'in_progress_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE r.requester_id = ? AND s.code IN ('SENT_TO_REPAIR', 'IN_PROGRESS', 'REPAIR_COMPLETED', 'RETRIEVED')", [$user['id']])['count'],
        'completed_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE r.requester_id = ? AND s.code = 'COMPLETED'", [$user['id']])['count']
    ];
    
    $recent_requests = $db->fetchAll(
        "SELECT r.request_code, r.created_at, e.name as equipment_name, 
                s.name as status_name, s.color as status_color, s.icon as status_icon
         FROM repair_requests r
         JOIN equipments e ON r.equipment_id = e.id
         JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.requester_id = ?
         ORDER BY r.created_at DESC
         LIMIT 5",
        [$user['id']]
    );
}

// Thống kê cho các role khác
else {
    $role_conditions = [
        'logistics' => "s.code IN ('PENDING_HANDOVER', 'RETRIEVED')",
        'clerk' => "s.code IN ('HANDED_TO_CLERK', 'REPAIR_COMPLETED')", 
        'technician' => "s.code IN ('SENT_TO_REPAIR', 'IN_PROGRESS')"
    ];
    
    $condition = $role_conditions[$user['role_name']] ?? "1=1";
    
    $stats = [
        'pending_tasks' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE $condition")['count'],
        'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests")['count'],
        'completed_today' => $db->fetch("SELECT COUNT(*) as count FROM repair_status_history WHERE DATE(created_at) = CURDATE() AND user_id = ?", [$user['id']])['count']
    ];
    
    $recent_requests = $db->fetchAll(
        "SELECT r.request_code, r.created_at, e.name as equipment_name, 
                u.full_name as requester_name, s.name as status_name, s.color as status_color
         FROM repair_requests r
         JOIN equipments e ON r.equipment_id = e.id
         JOIN users u ON r.requester_id = u.id
         JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE $condition
         ORDER BY r.created_at DESC
         LIMIT 10"
    );
}

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')]
];

ob_start();
?>

<div class="row mb-4">
    <?php if (has_role('admin')): ?>
        <!-- Admin Dashboard -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-primary text-white">
                <div class="stats-number"><?= number_format($stats['total_requests']) ?></div>
                <div class="stats-label">Tổng đơn sửa chữa</div>
                <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-warning text-white">
                <div class="stats-number"><?= number_format($stats['pending_requests']) ?></div>
                <div class="stats-label">Đang xử lý</div>
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-success text-white">
                <div class="stats-number"><?= number_format($stats['completed_requests']) ?></div>
                <div class="stats-label">Đã hoàn thành</div>
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card bg-info text-white">
                <div class="stats-number"><?= number_format($stats['total_equipments']) ?></div>
                <div class="stats-label">Thiết bị</div>
                <div class="stats-icon"><i class="fas fa-desktop"></i></div>
            </div>
        </div>
                    <div style="color: #10b981;">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: #6366f1;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number" style="color: #6366f1;"><?= number_format($stats['total_equipments']) ?></div>
                        <div class="stats-label">Thiết bị quản lý</div>
                    </div>
                    <div style="color: #6366f1;">
                        <i class="fas fa-desktop fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif (has_role('requester')): ?>
        <!-- Requester Dashboard -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number"><?= number_format($stats['my_requests']) ?></div>
                        <div class="stats-label">Đơn của tôi</div>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-list-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: #f59e0b;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number" style="color: #f59e0b;"><?= number_format($stats['pending_requests']) ?></div>
                        <div class="stats-label">Chờ xử lý</div>
                    </div>
                    <div style="color: #f59e0b;">
                        <i class="fas fa-hourglass-half fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: #3b82f6;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number" style="color: #3b82f6;"><?= number_format($stats['in_progress_requests']) ?></div>
                        <div class="stats-label">Đang sửa chữa</div>
                    </div>
                    <div style="color: #3b82f6;">
                        <i class="fas fa-tools fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: #10b981;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number" style="color: #10b981;"><?= number_format($stats['completed_requests']) ?></div>
                        <div class="stats-label">Đã hoàn thành</div>
                    </div>
                    <div style="color: #10b981;">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Other roles dashboard -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stats-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number"><?= number_format($stats['pending_tasks']) ?></div>
                        <div class="stats-label">Công việc cần xử lý</div>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-tasks fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: #10b981;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number" style="color: #10b981;"><?= number_format($stats['completed_today']) ?></div>
                        <div class="stats-label">Hoàn thành hôm nay</div>
                    </div>
                    <div style="color: #10b981;">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="stats-card" style="border-left-color: #6366f1;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stats-number" style="color: #6366f1;"><?= number_format($stats['total_requests']) ?></div>
                        <div class="stats-label">Tổng đơn trong hệ thống</div>
                    </div>
                    <div style="color: #6366f1;">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Charts column -->
    <?php if (has_role('admin')): ?>
    <div class="col-xl-8 col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-chart-pie me-2"></i>
                    Thống kê đơn sửa chữa theo trạng thái
                </h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent requests -->
    <div class="<?= has_role('admin') ? 'col-xl-4 col-lg-5' : 'col-12' ?>">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title">
                    <i class="fas fa-history me-2"></i>
                    <?php if (has_role('requester')): ?>
                        Đơn gần đây của tôi
                    <?php else: ?>
                        Đơn cần xử lý
                    <?php endif; ?>
                </h5>
                <a href="<?= url('requests/index.php') ?>" class="btn btn-sm btn-outline-primary">
                    Xem tất cả
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_requests)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Chưa có đơn nào</p>
                        <?php if (has_role('requester')): ?>
                            <a href="<?= url('requests/create.php') ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Tạo đơn mới
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_requests as $request): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?= e($request['request_code']) ?></h6>
                                        <p class="mb-1 text-muted small"><?= e($request['equipment_name']) ?></p>
                                        <?php if (!has_role('requester') && isset($request['requester_name'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?= e($request['requester_name']) ?>
                                            </small><br>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?= time_ago($request['created_at']) ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge rounded-pill" style="background-color: <?= e($request['status_color']) ?>; color: white;">
                                            <?php if (isset($request['status_icon'])): ?>
                                                <i class="<?= e($request['status_icon']) ?> me-1"></i>
                                            <?php endif; ?>
                                            <?= e($request['status_name']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-bolt me-2"></i>
                    Thao tác nhanh
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (has_role('requester')): ?>
                        <div class="col-md-3 mb-3">
                            <a href="<?= url('requests/create.php') ?>" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                Tạo đề xuất sửa chữa
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?= url('requests/my-requests.php') ?>" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-list-alt fa-2x d-block mb-2"></i>
                                Xem đơn của tôi
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (has_role('admin')): ?>
                        <div class="col-md-3 mb-3">
                            <a href="<?= url('admin/equipments.php') ?>" class="btn btn-success w-100 py-3">
                                <i class="fas fa-desktop fa-2x d-block mb-2"></i>
                                Quản lý thiết bị
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?= url('admin/users.php') ?>" class="btn btn-info w-100 py-3">
                                <i class="fas fa-users fa-2x d-block mb-2"></i>
                                Quản lý người dùng
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="<?= url('reports/overview.php') ?>" class="btn btn-warning w-100 py-3">
                                <i class="fas fa-chart-bar fa-2x d-block mb-2"></i>
                                Xem báo cáo
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (has_role('logistics')): ?>
                        <div class="col-md-4 mb-3">
                            <a href="<?= url('logistics/handover.php') ?>" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-hand-holding fa-2x d-block mb-2"></i>
                                Bàn giao thiết bị
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="<?= url('logistics/return.php') ?>" class="btn btn-success w-100 py-3">
                                <i class="fas fa-undo fa-2x d-block mb-2"></i>
                                Trả lại thiết bị
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (has_role('clerk')): ?>
                        <div class="col-md-4 mb-3">
                            <a href="<?= url('clerk/receive.php') ?>" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                Tiếp nhận thiết bị
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="<?= url('clerk/transfer.php') ?>" class="btn btn-warning w-100 py-3">
                                <i class="fas fa-shipping-fast fa-2x d-block mb-2"></i>
                                Chuyển sửa chữa
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="<?= url('clerk/retrieve.php') ?>" class="btn btn-success w-100 py-3">
                                <i class="fas fa-clipboard-check fa-2x d-block mb-2"></i>
                                Thu hồi thiết bị
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (has_role('technician')): ?>
                        <div class="col-md-6 mb-3">
                            <a href="<?= url('repair/in-progress.php') ?>" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-tools fa-2x d-block mb-2"></i>
                                Đang sửa chữa
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="<?= url('repair/completed.php') ?>" class="btn btn-success w-100 py-3">
                                <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                                Đã hoàn thành
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom JS for charts
$custom_js = '';
if (has_role('admin') && !empty($requests_by_status)):
    $custom_js = "
    <script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>
    <script>
        // Status chart
        const ctx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [" . implode(',', array_map(function($item) { return "'" . addslashes($item['name']) . "'"; }, $requests_by_status)) . "],
                datasets: [{
                    data: [" . implode(',', array_column($requests_by_status, 'count')) . "],
                    backgroundColor: [" . implode(',', array_map(function($item) { return "'" . $item['color'] . "'"; }, $requests_by_status)) . "],
                    borderWidth: 0
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
    </script>
    ";
endif;

include 'layouts/app.php';
?>
