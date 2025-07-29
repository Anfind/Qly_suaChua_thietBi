<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/notification_helpers.php';

// Debug user role trước khi require_role
$current_user = current_user();
error_log("=== CLERK RETRIEVE PAGE LOAD DEBUG ===");
error_log("Current user: " . print_r($current_user, true));
error_log("User role_name: " . ($current_user['role_name'] ?? 'NULL'));
error_log("======================================");

require_role('clerk');

$db = Database::getInstance();
$error = '';
$success = '';

// Xử lý thu hồi thiết bị
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $notes = $_POST['notes'] ?? '';
        $actual_cost = $_POST['actual_cost'] ?? '';
        $repair_result = $_POST['repair_result'] ?? '';
        $condition_after_repair = $_POST['condition_after_repair'] ?? '';
        
        // Validation các trường bắt buộc
        if (empty($repair_result)) {
            throw new Exception('Vui lòng chọn kết quả sửa chữa');
        }
        
        if (empty($condition_after_repair)) {
            throw new Exception('Vui lòng chọn tình trạng thiết bị khi trả lại');
        }
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        // Debug log
        error_log("=== CLERK RETRIEVE DEBUG ===");
        error_log("User data: " . print_r($user, true));
        error_log("User role_name: " . ($user['role_name'] ?? 'NULL'));
        error_log("Is clerk: " . (in_array($user['role_name'] ?? '', ['clerk']) ? 'YES' : 'NO'));
        error_log("Is admin: " . (in_array($user['role_name'] ?? '', ['admin']) ? 'YES' : 'NO'));
        error_log("============================");
        
        // Cho phép clerk và admin thực hiện thu hồi
        if (!$user || !in_array($user['role_name'], ['clerk', 'admin'])) {
            $error_detail = sprintf(
                'Chỉ văn thư và admin mới có quyền thu hồi thiết bị. User: %s, Role: %s', 
                $user['username'] ?? 'NULL',
                $user['role_name'] ?? 'NULL'
            );
            error_log("Permission denied: " . $error_detail);
            throw new Exception($error_detail);
        }
        
        // Thêm thông tin vào notes
        $full_notes = $notes;
        if ($repair_result) {
            $repair_text = [
                'fully_repaired' => 'Đã sửa chữa hoàn toàn',
                'partially_repaired' => 'Sửa chữa một phần', 
                'cannot_repair' => 'Không thể sửa chữa',
                'replaced' => 'Đã thay thế linh kiện'
            ][$repair_result] ?? $repair_result;
            
            $full_notes = "Kết quả sửa chữa: " . $repair_text . "\n" . $notes;
        }
        if ($condition_after_repair) {
            $condition_text = [
                'like_new' => 'Như mới',
                'good' => 'Tốt', 
                'acceptable' => 'Chấp nhận được',
                'poor' => 'Kém'
            ][$condition_after_repair] ?? $condition_after_repair;
            
            $full_notes .= "\nTình trạng sau sửa chữa: " . $condition_text;
        }
        if ($actual_cost) {
            $full_notes .= "\nChi phí thực tế: " . number_format($actual_cost) . " VNĐ";
        }
        
        // Cập nhật trạng thái thành "RETRIEVED"
        $result = $db->query(
            "UPDATE repair_requests SET 
                current_status_id = (SELECT id FROM repair_statuses WHERE code = 'RETRIEVED'), 
                updated_at = NOW() 
             WHERE id = ?",
            [$request_id]
        );
        
        // Cập nhật chi phí thực tế nếu có
        if ($actual_cost) {
            $db->query(
                "UPDATE repair_requests SET total_cost = ? WHERE id = ?",
                [$actual_cost, $request_id]
            );
        }
        
        // Thêm vào lịch sử trạng thái
        $db->insert('repair_status_history', [
            'request_id' => $request_id,
            'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'RETRIEVED'")['id'],
            'user_id' => $user['id'],
            'notes' => $full_notes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        log_activity('retrieve_equipment', [
            'request_id' => $request_id,
            'repair_result' => $repair_result,
            'condition_after_repair' => $condition_after_repair,
            'actual_cost' => $actual_cost,
            'notes' => $notes
        ]);
        
        // Gửi thông báo bằng hệ thống notification mới
        $request_info = $db->fetch("SELECT request_code FROM repair_requests WHERE id = ?", [$request_id]);
        notifyEquipmentRetrieved($request_id, $request_info['request_code'], $user['id']);
        
        $success = 'Đã thu hồi thiết bị thành công';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy thông tin đơn nếu có ID
$request = null;
if (isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    // Debug query trước
    error_log("=== CLERK RETRIEVE QUERY DEBUG ===");
    error_log("Looking for request ID: " . $request_id);
    
    // Kiểm tra đơn có tồn tại không
    $requestExists = $db->fetch(
        "SELECT r.id, r.request_code, s.code as status_code, s.name as status_name
         FROM repair_requests r
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.id = ?",
        [$request_id]
    );
    
    error_log("Request exists check: " . print_r($requestExists, true));
    
    if (!$requestExists) {
        redirect('index.php', 'Không tìm thấy đơn với ID: ' . $request_id, 'error');
    }
    
    // Kiểm tra trạng thái có phải REPAIR_COMPLETED không
    if ($requestExists['status_code'] !== 'REPAIR_COMPLETED') {
        error_log("Wrong status: " . $requestExists['status_code'] . " (expected: REPAIR_COMPLETED)");
        redirect('index.php', 'Đơn ' . $requestExists['request_code'] . ' không ở trạng thái "Đã sửa xong". Trạng thái hiện tại: ' . $requestExists['status_name'], 'error');
    }
    
    // Lấy thông tin đầy đủ của đơn
    $request = $db->fetch(
        "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.model as equipment_model,
                u.full_name as requester_name, u.phone as requester_phone,
                d.name as department_name, s.name as status_name, s.code as status_code
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.id = ?",
        [$request_id]
    );
    
    error_log("Full request data: " . print_r($request, true));
    error_log("==================================");
}

$title = 'Thu hồi thiết bị đã sửa xong';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Văn thư', 'url' => url('clerk/')],
    ['title' => 'Thu hồi thiết bị', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-undo me-2"></i>
            Thu hồi thiết bị đã sửa xong
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
    <!-- Form thu hồi cụ thể -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Thu hồi đơn #<?= e($request['request_code']) ?>
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
                    <h6>Mô tả sự cố ban đầu</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(e($request['problem_description'] ?? '')) ?>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Kết quả sửa chữa <span class="text-danger">*</span></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="repair_result" id="fully_repaired" value="fully_repaired" required>
                        <label class="form-check-label" for="fully_repaired">
                            <i class="fas fa-check-circle text-success me-1"></i>Đã sửa chữa hoàn toàn
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="repair_result" id="partially_repaired" value="partially_repaired">
                        <label class="form-check-label" for="partially_repaired">
                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>Sửa chữa một phần
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="repair_result" id="replaced" value="replaced">
                        <label class="form-check-label" for="replaced">
                            <i class="fas fa-exchange-alt text-info me-1"></i>Đã thay thế linh kiện
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="repair_result" id="cannot_repair" value="cannot_repair">
                        <label class="form-check-label" for="cannot_repair">
                            <i class="fas fa-times-circle text-danger me-1"></i>Không thể sửa chữa
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Tình trạng thiết bị khi trả lại <span class="text-danger">*</span></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_after_repair" id="like_new" value="like_new" required>
                        <label class="form-check-label" for="like_new">
                            <i class="fas fa-star text-warning me-1"></i>Như mới
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_after_repair" id="good" value="good">
                        <label class="form-check-label" for="good">
                            <i class="fas fa-thumbs-up text-success me-1"></i>Tốt
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_after_repair" id="acceptable" value="acceptable">
                        <label class="form-check-label" for="acceptable">
                            <i class="fas fa-check text-info me-1"></i>Chấp nhận được
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_after_repair" id="poor" value="poor">
                        <label class="form-check-label" for="poor">
                            <i class="fas fa-thumbs-down text-danger me-1"></i>Kém
                        </label>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="actual_cost" class="form-label">Chi phí thực tế (VNĐ)</label>
                            <input type="number" name="actual_cost" id="actual_cost" class="form-control" 
                                   placeholder="0" min="0" step="1000">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Ghi chú thu hồi</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                              placeholder="Ghi chú về kết quả sửa chữa, tình trạng thiết bị, thông tin bổ sung..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-undo me-1"></i>Xác nhận thu hồi
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
    
    // Validation function
    function validateForm() {
        const repairResult = document.querySelector('input[name="repair_result"]:checked');
        const condition = document.querySelector('input[name="condition_after_repair"]:checked');
        
        let isValid = true;
        let errorMsg = '';
        
        if (!repairResult) {
            isValid = false;
            errorMsg += '• Vui lòng chọn kết quả sửa chữa\n';
        }
        
        if (!condition) {
            isValid = false;
            errorMsg += '• Vui lòng chọn tình trạng thiết bị khi trả lại\n';
        }
        
        if (!isValid) {
            alert('Vui lòng điền đầy đủ thông tin:\n\n' + errorMsg);
            return false;
        }
        
        return true;
    }
    
    // Form submission
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Confirm before submit
            const repairResult = document.querySelector('input[name="repair_result"]:checked');
            const condition = document.querySelector('input[name="condition_after_repair"]:checked');
            
            const repairText = repairResult ? repairResult.nextElementSibling.textContent.trim() : '';
            const conditionText = condition ? condition.nextElementSibling.textContent.trim() : '';
            
            const confirmMsg = `Xác nhận thu hồi thiết bị với thông tin:\n\n` +
                             `• Kết quả sửa chữa: ${repairText}\n` +
                             `• Tình trạng thiết bị: ${conditionText}\n\n` +
                             `Bạn có chắc chắn muốn tiếp tục?`;
            
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
    <!-- Hiển thị thông báo nếu không có đơn -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
            <h5>Không tìm thấy đơn sửa chữa</h5>
            <p class="text-muted mb-3">
                Có thể đơn không tồn tại, đã được thu hồi, hoặc chưa hoàn thành sửa chữa.<br>
                Vui lòng kiểm tra danh sách đơn cần thu hồi.
            </p>
            
            <!-- Debug info cho admin -->
            <?php if ($user && $user['role_name'] === 'admin'): ?>
                <div class="alert alert-info text-start mt-3">
                    <strong>Debug Info (Admin only):</strong><br>
                    - Request ID: <?= $_GET['id'] ?? 'NULL' ?><br>
                    - Cần kiểm tra đơn ở trạng thái "REPAIR_COMPLETED"<br>
                    - <a href="<?= url('debug_clerk.php') ?>" target="_blank">🔍 Debug Clerk Data</a>
                </div>
            <?php endif; ?>
            
            <div class="d-flex gap-2 justify-content-center">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
                <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../layouts/app.php';
?>
