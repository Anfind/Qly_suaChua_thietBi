<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/notification_helpers.php';

require_role('technician');

$db = Database::getInstance();
$error = '';
$success = '';

// Xử lý hoàn thành sửa chữa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $final_notes = $_POST['final_notes'] ?? '';
        $repair_result = $_POST['repair_result'] ?? '';
        $final_cost = floatval($_POST['final_cost'] ?? 0);
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        if (!$user || !in_array($user['role_name'], ['technician', 'admin'])) {
            throw new Exception('Bạn không có quyền thực hiện hành động này');
        }
        
        if (empty($repair_result)) {
            throw new Exception('Vui lòng chọn kết quả sửa chữa');
        }
        
        // Cập nhật chi phí cuối cùng nếu có
        if ($final_cost > 0) {
            $db->query(
                "UPDATE repair_requests SET total_cost = ? WHERE id = ?",
                [$final_cost, $request_id]
            );
        }
        
        // Thêm kết quả sửa chữa cuối cùng
        $resultText = [
            'fixed' => 'Đã sửa chữa thành công',
            'partially_fixed' => 'Sửa chữa một phần',
            'replaced' => 'Đã thay thế linh kiện',
            'cannot_repair' => 'Không thể sửa chữa'
        ][$repair_result] ?? $repair_result;
        
        $finalDescription = "HOÀN THÀNH SỬA CHỮA: " . $resultText;
        if ($final_notes) {
            $finalDescription .= "\nGhi chú: " . $final_notes;
        }
        
        // Thêm chi tiết cuối cùng
        $detailData = [
            'request_id' => $request_id,
            'description' => $finalDescription,
            'technician_id' => $user['id'],
            'notes' => $final_notes,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('repair_details', $detailData);
        
        // Cập nhật trạng thái thành "REPAIR_COMPLETED"
        $result = $db->query(
            "UPDATE repair_requests SET 
                current_status_id = (SELECT id FROM repair_statuses WHERE code = 'REPAIR_COMPLETED'), 
                updated_at = NOW() 
             WHERE id = ?",
            [$request_id]
        );
        
        // Thêm vào lịch sử trạng thái
        $db->insert('repair_status_history', [
            'request_id' => $request_id,
            'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'REPAIR_COMPLETED'")['id'],
            'user_id' => $user['id'],
            'notes' => $finalDescription,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Gửi thông báo workflow
        $request_info = $db->fetch("SELECT request_code FROM repair_requests WHERE id = ?", [$request_id]);
        notifyRepairCompleted($request_id, $request_info['request_code'], $user['id']);
        
        // Log activity
        log_activity('complete_repair', [
            'request_id' => $request_id,
            'repair_result' => $repair_result,
            'final_cost' => $final_cost
        ]);
        
        $request = $db->fetch("SELECT request_code FROM repair_requests WHERE id = ?", [$request_id]);
        redirect('repairs/view.php?code=' . $request['request_code'], 
                'Đã hoàn thành sửa chữa thành công', 'success');
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy thông tin đơn nếu có ID
$request = null;
$repairDetails = [];
if (isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    // Kiểm tra quyền truy cập: chỉ cho phép technician xem đơn của phòng ban mình
    $request = $db->fetch(
        "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.model as equipment_model,
                u.full_name as requester_name, u.phone as requester_phone,
                d.name as department_name, s.name as status_name
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         LEFT JOIN repair_workflow_steps rws ON r.id = rws.request_id
         WHERE r.id = ? AND s.code = 'IN_PROGRESS' 
         AND (r.assigned_technician_id = ? OR rws.assigned_department_id = ?)
         LIMIT 1",
        [$request_id, $user['id'], $user['department_id']]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái đang sửa chữa', 'error');
    }
    
    // Lấy chi tiết sửa chữa
    $repairDetails = $db->fetchAll(
        "SELECT d.*, u.full_name as technician_name
         FROM repair_details d
         LEFT JOIN users u ON d.technician_id = u.id
         WHERE d.request_id = ?
         ORDER BY d.created_at ASC",
        [$request_id]
    );
}

$title = 'Hoàn thành sửa chữa';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật viên', 'url' => url('technician/')],
    ['title' => 'Hoàn thành sửa chữa', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-check me-2"></i>
            Hoàn thành sửa chữa
        </h2>
    </div>
    <div class="col-md-4 text-end">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Quay lại
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($request): ?>
    <div class="row">
        <div class="col-md-8">
            <!-- Form hoàn thành sửa chữa -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Hoàn thành sửa chữa đơn #<?= e($request['request_code']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                        
                        <div class="mb-4">
                            <label class="form-label">Kết quả sửa chữa <span class="text-danger">*</span>:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="repair_result" id="fixed" value="fixed" required>
                                        <label class="form-check-label" for="fixed">
                                            <i class="fas fa-check-circle text-success me-1"></i>Đã sửa chữa thành công
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="repair_result" id="partially_fixed" value="partially_fixed" required>
                                        <label class="form-check-label" for="partially_fixed">
                                            <i class="fas fa-adjust text-warning me-1"></i>Sửa chữa một phần
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="repair_result" id="replaced" value="replaced" required>
                                        <label class="form-check-label" for="replaced">
                                            <i class="fas fa-exchange-alt text-info me-1"></i>Đã thay thế linh kiện
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="repair_result" id="cannot_repair" value="cannot_repair" required>
                                        <label class="form-check-label" for="cannot_repair">
                                            <i class="fas fa-times-circle text-danger me-1"></i>Không thể sửa chữa
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="final_cost" class="form-label">Chi phí cuối cùng (VNĐ):</label>
                            <input type="number" class="form-control" id="final_cost" name="final_cost" 
                                   min="0" step="1000" value="<?= $request['total_cost'] ?? 0 ?>"
                                   placeholder="Nhập chi phí cuối cùng nếu khác với tổng chi phí hiện tại">
                            <div class="form-text">
                                Chi phí hiện tại: <strong><?= number_format($request['total_cost'] ?? 0) ?> VNĐ</strong>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="final_notes" class="form-label">Ghi chú hoàn thành:</label>
                            <textarea class="form-control" id="final_notes" name="final_notes" rows="4"
                                      placeholder="Ghi chú về kết quả sửa chữa, tình trạng thiết bị sau sửa chữa, khuyến nghị bảo trì..."></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Hoàn thành sửa chữa
                            </button>
                            <a href="update-progress.php?id=<?= $request['id'] ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Cập nhật thêm tiến độ
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Hủy
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Thông tin đơn -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Thông tin đơn</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Mã đơn:</strong></td>
                            <td><?= e($request['request_code']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Thiết bị:</strong></td>
                            <td><?= e($request['equipment_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Mã thiết bị:</strong></td>
                            <td><?= e($request['equipment_code']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Người đề xuất:</strong></td>
                            <td><?= e($request['requester_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Ngày bắt đầu:</strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($request['updated_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Chi phí hiện tại:</strong></td>
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
                        </tr>
                    </table>
                    <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>Xem chi tiết
                    </a>
                </div>
            </div>
            
            <!-- Tóm tắt quá trình sửa chữa -->
            <?php if (!empty($repairDetails)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Tóm tắt quá trình sửa chữa</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Tổng số bước đã thực hiện:</small>
                            <div class="h5 text-primary"><?= count($repairDetails) ?> bước</div>
                        </div>
                        
                        <?php
                        $totalTime = 0;
                        $totalPartsCost = 0;
                        $totalLaborCost = 0;
                        
                        foreach ($repairDetails as $detail) {
                            $totalTime += $detail['time_spent'] ?? 0;
                            $totalPartsCost += $detail['parts_cost'] ?? 0;
                            $totalLaborCost += $detail['labor_cost'] ?? 0;
                        }
                        ?>
                        
                        <div class="row text-center">
                            <div class="col-12 mb-2">
                                <small class="text-muted">Tổng thời gian</small>
                                <div class="fw-bold"><?= $totalTime ?> phút</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Chi phí linh kiện</small>
                                <div class="fw-bold text-info"><?= number_format($totalPartsCost) ?> VNĐ</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Chi phí nhân công</small>
                                <div class="fw-bold text-warning"><?= number_format($totalLaborCost) ?> VNĐ</div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="timeline" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($repairDetails as $index => $detail): ?>
                                <div class="timeline-item mb-2">
                                    <div class="timeline-marker">
                                        <span><?= $index + 1 ?></span>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="fw-bold" style="font-size: 0.9em;">
                                            <?= e(substr($detail['description'], 0, 50)) ?>
                                            <?php if (strlen($detail['description']) > 50): ?>...<?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('d/m H:i', strtotime($detail['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Danh sách đơn đang sửa chữa -->
    <?php
    $inProgressRequests = $db->fetchAll(
        "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
                u.full_name as requester_name, d.name as department_name
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE s.code = 'IN_PROGRESS' AND r.assigned_technician_id = ?
         ORDER BY r.updated_at ASC",
        [current_user()['id']]
    );
    ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Danh sách đơn có thể hoàn thành
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($inProgressRequests)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Thiết bị</th>
                                <th>Người đề xuất</th>
                                <th>Ngày bắt đầu</th>
                                <th>Tiến độ</th>
                                <th>Chi phí</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inProgressRequests as $req): ?>
                                <?php
                                // Đếm số bước đã thực hiện
                                $stepsCount = $db->fetch(
                                    "SELECT COUNT(*) as count FROM repair_details WHERE request_id = ?",
                                    [$req['id']]
                                )['count'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($req['request_code']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="equipment-info">
                                            <strong><?= e($req['equipment_name']) ?></strong>
                                            <small class="d-block text-muted"><?= e($req['equipment_code']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= e($req['requester_name']) ?></td>
                                    <td>
                                        <span class="text-muted"><?= date('d/m/Y H:i', strtotime($req['updated_at'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= $stepsCount ?> bước</span>
                                    </td>
                                    <td>
                                        <?php
                                        $totalCost = $req['total_cost'] ?? 0;
                                        if ($totalCost > 0) {
                                            echo '<strong class="text-primary">' . number_format($totalCost) . ' VNĐ</strong>';
                                        } else {
                                            echo '<span class="text-muted">Chưa có</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= url('repairs/view.php?code=' . $req['request_code']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="update-progress.php?id=<?= $req['id'] ?>" 
                                               class="btn btn-sm btn-warning" title="Cập nhật tiến độ">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="complete-repair.php?id=<?= $req['id'] ?>" 
                                               class="btn btn-sm btn-success" title="Hoàn thành">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không có đơn nào đang sửa chữa</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 25px;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 20px;
    height: 20px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: bold;
}

.timeline-content {
    background-color: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    border-left: 2px solid #007bff;
}

.timeline::before {
    content: '';
    position: absolute;
    left: -21px;
    top: 10px;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
