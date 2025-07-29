<?php
require_once __DIR__ . '/../config/config.php';

// Debug user role trước khi require_role
$current_user = current_user();
error_log("=== HANDOVER PAGE LOAD DEBUG ===");
error_log("Current user: " . print_r($current_user, true));
error_log("User role_name: " . ($current_user['role_name'] ?? 'NULL'));
error_log("================================");

require_role('logistics');

$controller = new RepairController();
$db = Database::getInstance();

$error = '';
$success = '';

// Xử lý hành động của giao liên
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $notes = $_POST['notes'] ?? '';
        $condition_check = $_POST['condition_check'] ?? '';
        $action = $_POST['action'] ?? 'receive'; // 'receive' hoặc 'handover'
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        // Debug log
        error_log("=== HANDOVER DEBUG ===");
        error_log("User data: " . print_r($user, true));
        error_log("User role_name: " . ($user['role_name'] ?? 'NULL'));
        error_log("Action: " . $action);
        error_log("=====================");
        
        if (!$user || !in_array($user['role_name'], ['logistics', 'admin'])) {
            $error_detail = sprintf(
                'Bạn không có quyền thực hiện hành động này. User: %s, Role: %s', 
                $user['username'] ?? 'NULL',
                $user['role_name'] ?? 'NULL'
            );
            error_log("Permission denied: " . $error_detail);
            throw new Exception($error_detail);
        }
        
        // Kiểm tra xem đơn có tồn tại và có phải trạng thái hợp lệ không
        $request = $db->fetch("SELECT r.*, s.code as status_code FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE r.id = ?", [$request_id]);
        
        if (!$request) {
            throw new Exception('Không tìm thấy đơn sửa chữa');
        }
        
        // Thêm ghi chú về condition_check vào notes
        $full_notes = $notes;
        if ($condition_check) {
            $condition_text = [
                'good' => 'Tình trạng tốt, đúng như mô tả',
                'different' => 'Có khác biệt so với mô tả', 
                'problem' => 'Có vấn đề nghiêm trọng'
            ][$condition_check] ?? $condition_check;
            
            $full_notes = "Kiểm tra thiết bị: " . $condition_text . 
                         ($notes ? "\nGhi chú: " . $notes : "");
        }
        
        if ($action === 'receive') {
            // Hành động: Nhận đề xuất từ người đề xuất
            if ($request['status_code'] !== 'PENDING_HANDOVER') {
                throw new Exception('Đơn không ở trạng thái chờ bàn giao');
            }
            
            // Cập nhật trạng thái thành "LOGISTICS_RECEIVED" và lưu timestamp
            $result = $db->query(
                "UPDATE repair_requests SET 
                    current_status_id = (SELECT id FROM repair_statuses WHERE code = 'LOGISTICS_RECEIVED'), 
                    logistics_received_at = NOW(),
                    updated_at = NOW() 
                 WHERE id = ?",
                [$request_id]
            );
            
            // Thêm vào lịch sử trạng thái
            $db->insert('repair_status_history', [
                'request_id' => $request_id,
                'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'LOGISTICS_RECEIVED'")['id'],
                'user_id' => $user['id'],
                'notes' => $full_notes,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = 'Đã xác nhận nhận đề xuất thành công';
            
        } elseif ($action === 'handover') {
            // Hành động: Bàn giao cho văn thư
            if ($request['status_code'] !== 'LOGISTICS_RECEIVED') {
                throw new Exception('Đơn phải ở trạng thái đã nhận đề xuất mới có thể bàn giao');
            }
            
            // Cập nhật trạng thái thành "LOGISTICS_HANDOVER" và lưu timestamp
            $result = $db->query(
                "UPDATE repair_requests SET 
                    current_status_id = (SELECT id FROM repair_statuses WHERE code = 'LOGISTICS_HANDOVER'), 
                    logistics_handover_at = NOW(),
                    updated_at = NOW() 
                 WHERE id = ?",
                [$request_id]
            );
            
            // Thêm vào lịch sử trạng thái
            $db->insert('repair_status_history', [
                'request_id' => $request_id,
                'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'LOGISTICS_HANDOVER'")['id'],
                'user_id' => $user['id'],
                'notes' => $full_notes,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = 'Đã xác nhận bàn giao cho văn thư thành công';
        }
        
        // Log activity
        log_activity('logistics_' . $action, [
            'request_id' => $request_id,
            'condition_check' => $condition_check,
            'notes' => $notes,
            'action' => $action
        ]);
        
        // Thêm ghi chú về condition_check vào notes
        $full_notes = $notes;
        if ($condition_check) {
            $condition_text = [
                'good' => 'Tình trạng tốt, đúng như mô tả',
                'different' => 'Có khác biệt so với mô tả', 
                'problem' => 'Có vấn đề nghiêm trọng'
            ][$condition_check] ?? $condition_check;
            
            $full_notes = "Kiểm tra thiết bị: " . $condition_text . 
                         ($notes ? "\nGhi chú: " . $notes : "");
        }
        
        // Cập nhật trạng thái thành "HANDED_TO_CLERK"
        $result = $db->query(
            "UPDATE repair_requests SET current_status_id = (SELECT id FROM repair_statuses WHERE code = 'HANDED_TO_CLERK'), updated_at = NOW() WHERE id = ?",
            [$request_id]
        );
        
        // Thêm vào lịch sử trạng thái
        $db->insert('repair_status_history', [
            'request_id' => $request_id,
            'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'HANDED_TO_CLERK'")['id'],
            'user_id' => $user['id'],
            'notes' => $full_notes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        log_activity('handover_equipment', [
            'request_id' => $request_id,
            'condition_check' => $condition_check,
            'notes' => $notes
        ]);
        
        $success = 'Đã xác nhận nhận đề xuất thành công';
        
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
         WHERE r.id = ? AND s.code = 'PENDING_HANDOVER'",
        [$request_id]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái chờ nhận đề xuất', 'error');
    }
}

// Lấy danh sách đơn chờ nhận đề xuất
$pendingRequests = $db->fetchAll(
    "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
            u.full_name as requester_name, d.name as department_name
     FROM repair_requests r
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     WHERE s.code = 'PENDING_HANDOVER'
     ORDER BY r.created_at ASC"
);

$title = 'Nhận đề xuất';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Giao liên', 'url' => url('logistics/')],
    ['title' => 'Nhận đề xuất', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-handshake me-2"></i>
            Nhận đề xuất
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
    <!-- Form nhận đề xuất cụ thể -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Nhận đề xuất #<?= e($request['request_code']) ?>
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
                    <h6>Mô tả sự cố</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(e($request['problem_description'] ?? '')) ?>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Kiểm tra tình trạng thiết bị</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_check" id="condition_good" value="good" required>
                        <label class="form-check-label" for="condition_good">
                            <i class="fas fa-check-circle text-success me-1"></i>Tình trạng tốt, đúng như mô tả
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_check" id="condition_different" value="different" required>
                        <label class="form-check-label" for="condition_different">
                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>Có khác biệt so với mô tả
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="condition_check" id="condition_problem" value="problem" required>
                        <label class="form-check-label" for="condition_problem">
                            <i class="fas fa-times-circle text-danger me-1"></i>Có vấn đề nghiêm trọng
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Ghi chú nhận đề xuất</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                              placeholder="Ghi chú về tình trạng thiết bị, thông tin bổ sung..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-handshake me-1"></i>Xác nhận nhận đề xuất
                    </button>
                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Danh sách đơn chờ nhận đề xuất -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Danh sách đơn chờ nhận đề xuất (<?= count($pendingRequests) ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($pendingRequests)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không có đơn nào cần nhận đề xuất</p>
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
                                <th>Mức độ</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $req): ?>
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
                                    <td>
                                        <?php
                                        $urgencyClass = [
                                            'low' => 'success',
                                            'medium' => 'warning', 
                                            'high' => 'danger',
                                            'critical' => 'dark'
                                        ][$req['urgency_level']] ?? 'secondary';
                                        
                                        $urgencyText = [
                                            'low' => 'Thấp',
                                            'medium' => 'Trung bình',
                                            'high' => 'Cao',
                                            'critical' => 'Khẩn cấp'
                                        ][$req['urgency_level']] ?? 'Không xác định';
                                        ?>
                                        <span class="badge bg-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                                    </td>
                                    <td><?= format_date($req['created_at']) ?></td>
                                    <td>
                                        <a href="?id=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
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
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../layouts/app.php';
?>
