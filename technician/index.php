<?php
require_once __DIR__ . '/../config/config.php';

// Debug user role trước khi require_role
$current_user = current_user();
error_log("=== TECHNICIAN PAGE LOAD DEBUG ===");
error_log("Current user: " . print_r($current_user, true));
error_log("User role_name: " . ($current_user['role_name'] ?? 'NULL'));
error_log("====================================");

require_role('technician');

$controller = new RepairController();
$data = $controller->index();

$title = 'Kỹ thuật viên - Sửa chữa thiết bị';
$user = current_user();

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật viên', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-tools me-2"></i>
            Công việc kỹ thuật viên
        </h2>
        <p class="text-muted">Quản lý việc sửa chữa thiết bị và cập nhật tiến độ công việc</p>
    </div>
    <div class="col-md-4 text-end">
        <div class="btn-group" role="group">
            <a href="workflow.php" class="btn btn-primary">
                <i class="fas fa-project-diagram me-1"></i>Quy trình đa phòng ban
            </a>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Quay lại Dashboard
            </a>
        </div>
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
                            Chờ tiếp nhận
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['sent']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-inbox fa-2x text-info"></i>
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
                            Đang sửa chữa
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['inProgress']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-wrench fa-2x text-warning"></i>
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
                            Hoàn thành hôm nay
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php
                            $today = date('Y-m-d');
                            $completedToday = 0;
                            foreach (array_merge($data['sent'], $data['inProgress']) as $request) {
                                if (isset($request['updated_at']) && date('Y-m-d', strtotime($request['updated_at'])) === $today) {
                                    $completedToday++;
                                }
                            }
                            echo $completedToday;
                            ?>
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
        <div class="card border-left-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Tổng công việc
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= count($data['sent']) + count($data['inProgress']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Đơn chờ tiếp nhận -->
<?php if (!empty($data['sent'])): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-inbox me-2"></i>
            Đơn chờ tiếp nhận (<?= count($data['sent']) ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Thiết bị</th>
                        <th>Người đề xuất</th>
                        <th>Phòng ban</th>
                        <th>Mô tả sự cố</th>
                        <th>Ngày tạo</th>
                        <th>Độ ưu tiên</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['sent'] as $request): ?>
                        <tr>
                            <td>
                                <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" class="text-decoration-none">
                                    <strong><?= e($request['request_code']) ?></strong>
                                </a>
                            </td>
                            <td>
                                <div class="equipment-info">
                                    <strong><?= e($request['equipment_name']) ?></strong>
                                    <small class="d-block text-muted"><?= e($request['equipment_code']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <span><?= e($request['requester_name']) ?></span>
                                    <?php if ($request['requester_phone']): ?>
                                        <small class="d-block text-muted">
                                            <i class="fas fa-phone me-1"></i><?= e($request['requester_phone']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= e($request['department_name']) ?></td>
                            <td>
                                <div class="problem-desc" style="max-width: 200px;">
                                    <?= e(substr($request['problem_description'], 0, 100)) ?>
                                    <?php if (strlen($request['problem_description']) > 100): ?>...<?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?></span>
                            </td>
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
                                ][$request['urgency_level']] ?? ucfirst($request['urgency_level']);
                                ?>
                                <span class="badge badge-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="start-repair.php?id=<?= $request['id'] ?>" 
                                       class="btn btn-sm btn-success" title="Bắt đầu sửa chữa">
                                        <i class="fas fa-play"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-inbox me-2"></i>
            Đơn chờ tiếp nhận (0)
        </h5>
    </div>
    <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <p class="text-muted">Không có đơn nào chờ tiếp nhận</p>
    </div>
</div>
<?php endif; ?>

<!-- Đơn đang sửa chữa -->
<?php if (!empty($data['inProgress'])): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-wrench me-2"></i>
            Đơn đang sửa chữa (<?= count($data['inProgress']) ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Thiết bị</th>
                        <th>Người đề xuất</th>
                        <th>Tiến độ</th>
                        <th>Ngày bắt đầu</th>
                        <th>Chi phí hiện tại</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['inProgress'] as $request): ?>
                        <tr>
                            <td>
                                <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" class="text-decoration-none">
                                    <strong><?= e($request['request_code']) ?></strong>
                                </a>
                            </td>
                            <td>
                                <div class="equipment-info">
                                    <strong><?= e($request['equipment_name']) ?></strong>
                                    <small class="d-block text-muted"><?= e($request['equipment_code']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <span><?= e($request['requester_name']) ?></span>
                                    <?php if ($request['requester_phone']): ?>
                                        <small class="d-block text-muted">
                                            <i class="fas fa-phone me-1"></i><?= e($request['requester_phone']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                // Tính toán tiến độ dựa trên số lượng chi tiết sửa chữa
                                $db = Database::getInstance();
                                $repairDetailsCount = $db->fetch(
                                    "SELECT COUNT(*) as count FROM repair_details WHERE request_id = ?",
                                    [$request['id']]
                                )['count'] ?? 0;
                                
                                if ($repairDetailsCount == 0) {
                                    $progress = 10; // Vừa bắt đầu
                                    $progressText = "Vừa bắt đầu";
                                    $progressClass = "warning";
                                } elseif ($repairDetailsCount < 3) {
                                    $progress = 40;
                                    $progressText = "Đang tiến hành";
                                    $progressClass = "info";
                                } else {
                                    $progress = 80;
                                    $progressText = "Gần hoàn thành";
                                    $progressClass = "success";
                                }
                                ?>
                                <div class="progress mb-1" style="height: 8px;">
                                    <div class="progress-bar bg-<?= $progressClass ?>" style="width: <?= $progress ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $progressText ?> (<?= $repairDetailsCount ?> bước)</small>
                            </td>
                            <td>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($request['updated_at'])) ?></span>
                            </td>
                            <td>
                                <?php
                                $totalCost = $request['total_cost'] ?? 0;
                                if ($totalCost > 0) {
                                    echo '<strong class="text-primary">' . number_format($totalCost) . ' VNĐ</strong>';
                                } else {
                                    echo '<span class="text-muted">Chưa có</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="update-progress.php?id=<?= $request['id'] ?>" 
                                       class="btn btn-sm btn-warning" title="Cập nhật tiến độ">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="complete-repair.php?id=<?= $request['id'] ?>" 
                                       class="btn btn-sm btn-success" title="Hoàn thành sửa chữa">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-wrench me-2"></i>
            Đơn đang sửa chữa (0)
        </h5>
    </div>
    <div class="card-body text-center py-5">
        <i class="fas fa-wrench fa-3x text-muted mb-3"></i>
        <p class="text-muted">Không có đơn nào đang sửa chữa</p>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
