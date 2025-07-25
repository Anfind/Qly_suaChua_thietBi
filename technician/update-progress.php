<?php
require_once __DIR__ . '/../config/config.php';

require_role('technician');

$db = Database::getInstance();
$error = '';
$success = '';

// Xử lý cập nhật tiến độ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $description = $_POST['description'];
        $parts_replaced = $_POST['parts_replaced'] ?? '';
        $parts_cost = floatval($_POST['parts_cost'] ?? 0);
        $labor_cost = floatval($_POST['labor_cost'] ?? 0);
        $time_spent = intval($_POST['time_spent'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        if (!$user || !in_array($user['role_name'], ['technician', 'admin'])) {
            throw new Exception('Bạn không có quyền thực hiện hành động này');
        }
        
        if (empty($description)) {
            throw new Exception('Vui lòng nhập mô tả công việc');
        }
        
        // Xử lý upload hình ảnh
        $images = [];
        if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['images']['name'][$i],
                        'type' => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error' => $_FILES['images']['error'][$i],
                        'size' => $_FILES['images']['size'][$i]
                    ];
                    
                    try {
                        $filename = upload_file($file, UPLOAD_REQUEST_PATH, ALLOWED_IMAGE_TYPES);
                        $images[] = $filename;
                    } catch (Exception $e) {
                        // Bỏ qua lỗi upload file và tiếp tục
                        error_log("File upload error: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Thêm chi tiết sửa chữa
        $detailData = [
            'request_id' => $request_id,
            'description' => $description,
            'parts_replaced' => $parts_replaced,
            'parts_cost' => $parts_cost,
            'labor_cost' => $labor_cost,
            'time_spent' => $time_spent,
            'technician_id' => $user['id'],
            'notes' => $notes,
            'images' => json_encode($images),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $detail_id = $db->insert('repair_details', $detailData);
        
        // Cập nhật tổng chi phí
        $totalCost = $db->fetch(
            "SELECT SUM(parts_cost + labor_cost) as total FROM repair_details WHERE request_id = ?",
            [$request_id]
        )['total'] ?? 0;
        
        $db->query(
            "UPDATE repair_requests SET total_cost = ?, updated_at = NOW() WHERE id = ?",
            [$totalCost, $request_id]
        );
        
        // Log activity
        log_activity('update_repair_progress', [
            'request_id' => $request_id,
            'description' => $description,
            'cost' => $parts_cost + $labor_cost
        ]);
        
        $request = $db->fetch("SELECT request_code FROM repair_requests WHERE id = ?", [$request_id]);
        redirect('repairs/view.php?code=' . $request['request_code'], 
                'Đã cập nhật tiến độ thành công', 'success');
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy thông tin đơn nếu có ID
$request = null;
if (isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    $request = $db->fetch(
        "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.model as equipment_model,
                u.full_name as requester_name, u.phone as requester_phone,
                d.name as department_name, s.name as status_name
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.id = ? AND s.code = 'IN_PROGRESS'",
        [$request_id]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái đang sửa chữa', 'error');
    }
    
    // Lấy chi tiết sửa chữa hiện tại
    $repairDetails = $db->fetchAll(
        "SELECT d.*, u.full_name as technician_name
         FROM repair_details d
         LEFT JOIN users u ON d.technician_id = u.id
         WHERE d.request_id = ?
         ORDER BY d.created_at DESC",
        [$request_id]
    );
}

// Lấy danh sách nội dung sửa chữa template
$repairContents = $db->fetchAll("SELECT * FROM repair_contents ORDER BY name");

$title = 'Cập nhật tiến độ sửa chữa';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật viên', 'url' => url('technician/')],
    ['title' => 'Cập nhật tiến độ', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-edit me-2"></i>
            Cập nhật tiến độ sửa chữa
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
            <!-- Form cập nhật tiến độ -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Thêm tiến độ cho đơn #<?= e($request['request_code']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Mô tả công việc <span class="text-danger">*</span>:</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required
                                      placeholder="Mô tả chi tiết công việc đã thực hiện..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="parts_replaced" class="form-label">Linh kiện đã thay thế:</label>
                                    <textarea class="form-control" id="parts_replaced" name="parts_replaced" rows="2"
                                              placeholder="Danh sách linh kiện đã thay thế..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="time_spent" class="form-label">Thời gian thực hiện (phút):</label>
                                    <input type="number" class="form-control" id="time_spent" name="time_spent" min="0"
                                           placeholder="60">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="parts_cost" class="form-label">Chi phí linh kiện (VNĐ):</label>
                                    <input type="number" class="form-control" id="parts_cost" name="parts_cost" min="0" step="1000"
                                           placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="labor_cost" class="form-label">Chi phí nhân công (VNĐ):</label>
                                    <input type="number" class="form-control" id="labor_cost" name="labor_cost" min="0" step="1000"
                                           placeholder="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="images" class="form-label">Hình ảnh minh họa:</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                            <div class="form-text">Có thể đính kèm nhiều hình ảnh về quá trình sửa chữa</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Ghi chú thêm:</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"
                                      placeholder="Ghi chú thêm về quá trình sửa chữa..."></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Lưu tiến độ
                            </button>
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
            
            <!-- Lịch sử tiến độ -->
            <?php if (!empty($repairDetails)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Lịch sử tiến độ</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($repairDetails as $detail): ?>
                                <div class="timeline-item mb-3">
                                    <div class="timeline-marker">
                                        <i class="fas fa-tools"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?= e($detail['description']) ?></h6>
                                        <small class="text-muted">
                                            <?= e($detail['technician_name']) ?> - 
                                            <?= date('d/m/Y H:i', strtotime($detail['created_at'])) ?>
                                        </small>
                                        
                                        <?php if ($detail['parts_replaced']): ?>
                                            <div class="mt-1">
                                                <small><strong>Linh kiện:</strong> <?= e($detail['parts_replaced']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($detail['parts_cost'] > 0 || $detail['labor_cost'] > 0): ?>
                                            <div class="mt-1">
                                                <small><strong>Chi phí:</strong> 
                                                    <?= number_format($detail['parts_cost'] + $detail['labor_cost']) ?> VNĐ
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($detail['time_spent'] > 0): ?>
                                            <div class="mt-1">
                                                <small><strong>Thời gian:</strong> <?= $detail['time_spent'] ?> phút</small>
                                            </div>
                                        <?php endif; ?>
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
         ORDER BY r.updated_at DESC",
        [current_user()['id']]
    );
    ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Danh sách đơn đang sửa chữa
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
                                <th>Chi phí hiện tại</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inProgressRequests as $req): ?>
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
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-wrench fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không có đơn nào đang sửa chữa</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -35px;
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
}

.timeline-content {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline::before {
    content: '';
    position: absolute;
    left: -26px;
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
