<?php
require_once __DIR__ . '/../config/config.php';

// Debug user role trước khi require_role
$current_user = current_user();
error_log("=== CLERK PAGE LOAD DEBUG ===");
error_log("Current user: " . print_r($current_user, true));
error_log("User role_name: " . ($current_user['role_name'] ?? 'NULL'));
error_log("================================");

require_role('clerk');

$controller = new RepairController();
$data = $controller->index();

$title = 'Văn thư - Quản lý thiết bị';
$user = current_user();

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Văn thư', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-file-signature me-2"></i>
            Công việc văn thư
        </h2>
        <p class="text-muted">Quản lý việc chuyển thiết bị đến sửa chữa và thu hồi thiết bị đã sửa xong</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="<?= url('dashboard.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Quay lại Dashboard
        </a>
    </div>
</div>

<!-- Quick stats -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Chờ chuyển sửa chữa
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['handed']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Chờ thu hồi
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['completed']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Tổng đã xử lý
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['handed']) + count($data['completed']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Hiệu suất
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['handed']) + count($data['completed']) > 0 ? '100%' : '0%' ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-hand-holding me-2"></i>
                    Đã bàn giao - Chờ chuyển (<?= count($data['handed']) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($data['handed'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">Không có đơn nào chờ chuyển</h5>
                        <p class="text-muted">Tất cả thiết bị đã được chuyển đến sửa chữa</p>
                        <a href="<?= url('dashboard.php') ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Quay lại Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($data['handed'] as $request): ?>
                            <div class="list-group-item hover-highlight">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold mb-1">
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="text-decoration-none">
                                                <?= e($request['request_code']) ?>
                                            </a>
                                        </div>
                                        <div class="mb-1">
                                            <strong><?= e($request['equipment_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= e($request['equipment_code'] ?? '') ?></small>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?= e($request['requester_name'] ?? '') ?>
                                            <?php if (!empty($request['department_name'])): ?>
                                                - <?= e($request['department_name']) ?>
                                            <?php endif; ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= time_ago($request['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <a href="send.php?id=<?= $request['id'] ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-shipping-fast me-1"></i>Chuyển sửa chữa
                                        </a>
                                        <br>
                                        <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                           class="btn btn-sm btn-outline-secondary mt-1">
                                            <i class="fas fa-eye me-1"></i>Chi tiết
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Đã sửa xong - Chờ thu hồi (<?= count($data['completed']) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($data['completed'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">Không có đơn nào chờ thu hồi</h5>
                        <p class="text-muted mb-3">
                            Hiện tại không có thiết bị nào đã sửa xong cần thu hồi.<br>
                            Đơn sẽ xuất hiện ở đây khi kỹ thuật hoàn thành sửa chữa.
                        </p>
                        
                        <!-- Debug info cho admin -->
                        <?php if ($user && $user['role_name'] === 'admin'): ?>
                            <div class="alert alert-info text-start">
                                <strong>Debug Info (Admin only):</strong><br>
                                - Cần có đơn ở trạng thái "REPAIR_COMPLETED"<br>
                                - <a href="<?= url('debug_clerk.php') ?>" target="_blank">🔍 Debug & Tạo data test</a><br>
                                - <a href="<?= url('technician/') ?>" target="_blank">🔧 Kiểm tra trang Kỹ thuật</a>
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?= url('dashboard.php') ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Quay lại Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($data['completed'] as $request): ?>
                            <div class="list-group-item hover-highlight">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold mb-1">
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="text-decoration-none">
                                                <?= e($request['request_code']) ?>
                                            </a>
                                        </div>
                                        <div class="mb-1">
                                            <strong><?= e($request['equipment_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= e($request['equipment_code'] ?? '') ?></small>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?= e($request['requester_name'] ?? '') ?>
                                            <?php if (!empty($request['department_name'])): ?>
                                                - <?= e($request['department_name']) ?>
                                            <?php endif; ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= time_ago($request['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <a href="retrieve.php?id=<?= $request['id'] ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-undo me-1"></i>Thu hồi
                                        </a>
                                        <br>
                                        <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                           class="btn btn-sm btn-outline-secondary mt-1">
                                            <i class="fas fa-eye me-1"></i>Chi tiết
                                        </a>
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

<style>
.border-left-primary {
    border-left: 4px solid #007bff !important;
}
.border-left-success {
    border-left: 4px solid #28a745 !important;
}
.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}
.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}
.hover-highlight:hover {
    background-color: #f8f9fa;
}
</style>

<?php
$content = ob_get_clean();
include '../layouts/app.php';
?>
