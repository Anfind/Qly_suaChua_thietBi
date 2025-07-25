<?php
require_once __DIR__ . '/../config/config.php';
require_role('logistics');

$controller = new RepairController();
$db = Database::getInstance();

$error = '';
$success = '';

// Xử lý trả lại thiết bị
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $notes = $_POST['notes'] ?? '';
        $return_condition = $_POST['return_condition'] ?? '';
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        if (!$user || !in_array($user['role_name'], ['logistics', 'admin'])) {
            throw new Exception('Chỉ giao liên và admin mới có quyền trả lại thiết bị');
        }
        
        // Thêm thông tin về tình trạng trả lại vào notes
        $full_notes = $notes;
        if ($return_condition) {
            $condition_text = [
                'excellent' => 'Tình trạng xuất sắc',
                'good' => 'Tình trạng tốt',
                'acceptable' => 'Tình trạng chấp nhận được',
                'damaged' => 'Có hư hỏng'
            ][$return_condition] ?? $return_condition;
            
            $full_notes = "Tình trạng khi trả lại: " . $condition_text . 
                         ($notes ? "\nGhi chú: " . $notes : "");
        }
        
        // Cập nhật trạng thái thành "COMPLETED"
        $result = $db->query(
            "UPDATE repair_requests SET 
                current_status_id = (SELECT id FROM repair_statuses WHERE code = 'COMPLETED'), 
                updated_at = NOW(), 
                actual_completion = NOW()
             WHERE id = ?",
            [$request_id]
        );
        
        // Thêm vào lịch sử trạng thái
        $db->insert('repair_status_history', [
            'request_id' => $request_id,
            'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'COMPLETED'")['id'],
            'user_id' => $user['id'],
            'notes' => $full_notes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        log_activity('return_equipment', [
            'request_id' => $request_id,
            'return_condition' => $return_condition,
            'notes' => $notes
        ]);
        
        $request = $db->fetch("SELECT request_code FROM repair_requests WHERE id = ?", [$request_id]);
        redirect('index.php', 'Đã trả lại thiết bị đơn ' . $request['request_code'] . ' thành công', 'success');
        
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
         WHERE r.id = ? AND s.code = 'RETRIEVED'",
        [$request_id]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái chờ trả lại', 'error');
    }
}

// Lấy danh sách đơn sẵn sàng trả lại
$readyRequests = $db->fetchAll(
    "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
            u.full_name as requester_name, d.name as department_name
     FROM repair_requests r
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     WHERE s.code = 'RETRIEVED'
     ORDER BY r.updated_at ASC"
);

$title = 'Trả lại thiết bị';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Giao liên', 'url' => url('logistics/')],
    ['title' => 'Trả lại thiết bị', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-undo me-2"></i>
            Trả lại thiết bị
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

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($request): ?>
    <!-- Form trả lại cụ thể -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Trả lại đơn #<?= e($request['request_code']) ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Thông tin thiết bị</h6>
                    <p><strong>Tên:</strong> <?= e($request['equipment_name']) ?></p>
                    <p><strong>Mã:</strong> <?= e($request['equipment_code']) ?></p>
                    <?php if ($request['equipment_model']): ?>
                        <p><strong>Model:</strong> <?= e($request['equipment_model']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h6>Thông tin người đề xuất</h6>
                    <p><strong>Họ tên:</strong> <?= e($request['requester_name']) ?></p>
                    <p><strong>Phòng ban:</strong> <?= e($request['department_name']) ?></p>
                    <?php if ($request['requester_phone']): ?>
                        <p><strong>Điện thoại:</strong> <?= e($request['requester_phone']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-12">
                    <h6>Kết quả sửa chữa</h6>
                    <?php
                    // Lấy chi tiết sửa chữa
                    $repairDetails = $db->fetchAll(
                        "SELECT d.*, u.full_name as technician_name, c.name as content_name
                         FROM repair_details d
                         LEFT JOIN users u ON d.technician_id = u.id
                         LEFT JOIN repair_contents c ON d.content_id = c.id
                         WHERE d.request_id = ?
                         ORDER BY d.created_at DESC",
                        [$request['id']]
                    );
                    ?>
                    
                    <?php if ($repairDetails): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nội dung</th>
                                        <th>Kỹ thuật viên</th>
                                        <th>Ngày thực hiện</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($repairDetails as $detail): ?>
                                        <tr>
                                            <td><?= e($detail['content_name'] ?? $detail['description']) ?></td>
                                            <td><?= e($detail['technician_name']) ?></td>
                                            <td><?= format_date($detail['created_at']) ?></td>
                                            <td><?= e($detail['notes']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Chưa có thông tin chi tiết sửa chữa.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                <input type="hidden" name="new_status" value="COMPLETED">
                
                <div class="mb-3">
                    <label class="form-label">Tình trạng thiết bị khi trả lại <span class="text-danger">*</span></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="return_condition" id="excellent" value="excellent" required>
                        <label class="form-check-label" for="excellent">
                            <i class="fas fa-star text-warning me-1"></i>Xuất sắc - Như mới
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="return_condition" id="good" value="good">
                        <label class="form-check-label" for="good">
                            <i class="fas fa-check-circle text-success me-1"></i>Tốt - Hoạt động bình thường
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="return_condition" id="acceptable" value="acceptable">
                        <label class="form-check-label" for="acceptable">
                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>Chấp nhận được - Còn vấn đề nhỏ
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="return_condition" id="damaged" value="damaged">
                        <label class="form-check-label" for="damaged">
                            <i class="fas fa-times-circle text-danger me-1"></i>Có hư hỏng - Không thể sửa được
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Ghi chú trả lại</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                              placeholder="Ghi chú về tình trạng thiết bị sau sửa chữa, hướng dẫn sử dụng..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success" id="submit-btn">
                        <i class="fas fa-check me-1"></i>Xác nhận trả lại & Hoàn tất
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submit-btn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const returnCondition = document.querySelector('input[name="return_condition"]:checked');
            
            if (!returnCondition) {
                alert('Vui lòng chọn tình trạng thiết bị khi trả lại');
                e.preventDefault();
                return false;
            }
            
            // Confirm before submit
            const conditionText = returnCondition.nextElementSibling.textContent.trim();
            const confirmMsg = `Xác nhận trả lại thiết bị với tình trạng:\n\n${conditionText}\n\nSau khi xác nhận, đơn sẽ được hoàn tất. Bạn có chắc chắn muốn tiếp tục?`;
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang xử lý...';
        });
    }
});
</script>
<?php else: ?>
    <!-- Danh sách đơn sẵn sàng trả lại -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Danh sách đơn sẵn sàng trả lại (<?= count($readyRequests) ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($readyRequests)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không có đơn nào cần trả lại</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Quay lại Dashboard
                    </a>
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
                            <?php foreach ($readyRequests as $req): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('repairs/view.php?code=' . $req['request_code']) ?>" 
                                           class="text-decoration-none">
                                            <strong><?= e($req['request_code']) ?></strong>
                                        </a>
                                    </td>
                                    <td>
                                        <strong><?= e($req['equipment_name']) ?></strong><br>
                                        <small class="text-muted"><?= e($req['equipment_code']) ?></small>
                                    </td>
                                    <td><?= e($req['requester_name']) ?></td>
                                    <td><?= e($req['department_name']) ?></td>
                                    <td><?= format_date($req['updated_at']) ?></td>
                                    <td>
                                        <a href="?id=<?= $req['id'] ?>" class="btn btn-sm btn-success">
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
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../layouts/app.php';
?>
