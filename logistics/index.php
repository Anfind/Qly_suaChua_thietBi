<?php
require_once __DIR__ . '/../config/config.php';
require_role('logistics');

$title = 'Giao liên - Dashboard';
$controller = new RepairController();
$data = $controller->logisticsDashboard(); // Sửa từ index() thành logisticsDashboard()

$pendingHandover = $data['pendingHandover'] ?? [];
$readyForReturn = $data['readyForReturn'] ?? [];

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Giao liên', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="page-title">
            <i class="fas fa-truck me-2"></i>
            Dashboard Giao liên
        </h2>
        <p class="text-muted">Quản lý nhận đề xuất và thu hồi thiết bị</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1"><?= count($pendingHandover) ?></h3>
                        <p class="mb-0">Chờ nhận đề xuất</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-handshake fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="handover.php" class="text-white text-decoration-none">
                    <i class="fas fa-arrow-right me-1"></i>Xem chi tiết
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1"><?= count($readyForReturn) ?></h3>
                        <p class="mb-0">Sẵn sàng trả lại</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-undo fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="return.php" class="text-white text-decoration-none">
                    <i class="fas fa-arrow-right me-1"></i>Xem chi tiết
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Pending Handover -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-handshake me-2"></i>
            Đơn chờ nhận đề xuất (<?= count($pendingHandover) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($pendingHandover)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">Không có đơn nào cần nhận đề xuất</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Thiết bị</th>
                            <th>Người đề xuất</th>
                            <th>Phòng ban</th>
                            <th>Mức độ</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingHandover as $request): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                       class="text-decoration-none">
                                        <strong><?= e($request['request_code']) ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= e($request['equipment_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($request['equipment_code']) ?></small>
                                </td>
                                <td><?= e($request['requester_name']) ?></td>
                                <td><?= e($request['department_name']) ?></td>
                                <td>
                                    <?php
                                    $urgencyClass = [
                                        'low' => 'success',
                                        'medium' => 'warning', 
                                        'high' => 'danger',
                                        'critical' => 'dark'
                                    ][$request['urgency_level']] ?? 'secondary';
                                    
                                    $urgencyText = [
                                        'low' => 'Thấp',
                                        'medium' => 'Trung bình',
                                        'high' => 'Cao',
                                        'critical' => 'Khẩn cấp'
                                    ][$request['urgency_level']] ?? 'Không xác định';
                                    ?>
                                    <span class="badge bg-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                                </td>
                                <td><?= format_date($request['created_at']) ?></td>
                                <td>
                                    <a href="handover.php?id=<?= $request['id'] ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-handshake me-1"></i>Nhận đề xuất
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ready for Return -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-undo me-2"></i>
            Đơn sẵn sàng trả lại (<?= count($readyForReturn) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($readyForReturn)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">Không có đơn nào cần trả lại</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Thiết bị</th>
                            <th>Người đề xuất</th>
                            <th>Phòng ban</th>
                            <th>Ngày sửa xong</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($readyForReturn as $request): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                       class="text-decoration-none">
                                        <strong><?= e($request['request_code']) ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= e($request['equipment_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($request['equipment_code']) ?></small>
                                </td>
                                <td><?= e($request['requester_name']) ?></td>
                                <td><?= e($request['department_name']) ?></td>
                                <td><?= format_date($request['updated_at']) ?></td>
                                <td>
                                    <a href="return.php?id=<?= $request['id'] ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="fas fa-undo me-1"></i>Trả lại
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layouts/app.php';
?>
