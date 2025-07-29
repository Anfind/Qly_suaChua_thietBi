<?php
require_once __DIR__ . '/../config/config.php';

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
        
        if (!$user || !in_array($user['role_name'], ['logistics', 'admin'])) {
            throw new Exception('Bạn không có quyền thực hiện hành động này');
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
                d.name as department_name, s.name as status_name, s.code as status_code
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.id = ? AND s.code IN ('PENDING_HANDOVER', 'LOGISTICS_RECEIVED')",
        [$request_id]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái phù hợp', 'error');
    }
}

// Lấy danh sách đơn chờ nhận đề xuất hoặc chờ bàn giao
$pendingRequests = $db->fetchAll(
    "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
            u.full_name as requester_name, d.name as department_name,
            s.name as status_name, s.code as status_code
     FROM repair_requests r
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     WHERE s.code IN ('PENDING_HANDOVER', 'LOGISTICS_RECEIVED')
     ORDER BY r.created_at ASC"
);

$pageTitle = 'Quản lý bàn giao thiết bị';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">
        <i class="fas fa-handshake me-2"></i>
        Quản lý bàn giao thiết bị
    </h2>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?= e($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    <?= e($success) ?>
</div>
<?php endif; ?>

<?php if ($request): ?>
<!-- Form xử lý đơn cụ thể -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-edit me-2"></i>
            Xử lý đơn #<?= e($request['request_code']) ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Thông tin thiết bị:</h6>
                <p><strong>Tên:</strong> <?= e($request['equipment_name']) ?></p>
                <p><strong>Mã:</strong> <?= e($request['equipment_code']) ?></p>
                <?php if ($request['equipment_model']): ?>
                <p><strong>Model:</strong> <?= e($request['equipment_model']) ?></p>
                <?php endif; ?>
                
                <h6 class="mt-3">Người đề xuất:</h6>
                <p><strong>Tên:</strong> <?= e($request['requester_name']) ?></p>
                <p><strong>Phòng ban:</strong> <?= e($request['department_name']) ?></p>
                <?php if ($request['requester_phone']): ?>
                <p><strong>Điện thoại:</strong> <?= e($request['requester_phone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6>Mô tả vấn đề:</h6>
                <p><?= nl2br(e($request['problem_description'])) ?></p>
                
                <h6 class="mt-3">Trạng thái hiện tại:</h6>
                <p><span class="badge bg-primary"><?= e($request['status_name']) ?></span></p>
            </div>
        </div>

        <?php if ($request['status_code'] === 'PENDING_HANDOVER'): ?>
        <!-- Form nhận đề xuất -->
        <form method="POST" class="mt-4">
            <?= csrf_token() ?>
            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
            <input type="hidden" name="action" value="receive">
            
            <h6>Nhận đề xuất:</h6>
            
            <div class="mb-3">
                <label class="form-label">Kiểm tra tình trạng thiết bị:</label>
                <select name="condition_check" class="form-select">
                    <option value="">-- Chọn tình trạng --</option>
                    <option value="good">Tình trạng tốt, đúng như mô tả</option>
                    <option value="different">Có khác biệt so với mô tả</option>
                    <option value="problem">Có vấn đề nghiêm trọng</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Ghi chú thêm:</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Ghi chú về tình trạng thiết bị, vấn đề phát hiện..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-success">
                <i class="fas fa-check me-2"></i>
                Xác nhận nhận đề xuất
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Quay lại
            </a>
        </form>
        
        <?php elseif ($request['status_code'] === 'LOGISTICS_RECEIVED'): ?>
        <!-- Form bàn giao cho văn thư -->
        <form method="POST" class="mt-4">
            <?= csrf_token() ?>
            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
            <input type="hidden" name="action" value="handover">
            
            <h6>Bàn giao cho văn thư:</h6>
            
            <div class="mb-3">
                <label class="form-label">Kiểm tra lại thiết bị trước khi bàn giao:</label>
                <select name="condition_check" class="form-select">
                    <option value="">-- Chọn tình trạng --</option>
                    <option value="good">Tình trạng tốt, sẵn sàng bàn giao</option>
                    <option value="different">Có thay đổi so với lúc nhận</option>
                    <option value="problem">Có vấn đề mới phát sinh</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Ghi chú bàn giao:</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Ghi chú về việc bàn giao cho văn thư..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-hand-holding me-2"></i>
                Xác nhận bàn giao cho văn thư
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Quay lại
            </a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Danh sách đơn chờ xử lý -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Danh sách đơn chờ xử lý
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($pendingRequests)): ?>
        <div class="text-center py-4">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Không có đơn nào cần xử lý</h5>
            <p class="text-muted">Tất cả đơn đã được xử lý hoặc chuyển cho bộ phận khác.</p>
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
                        <th>Trạng thái</th>
                        <th>Thời gian tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequests as $req): ?>
                    <tr>
                        <td>
                            <strong><?= e($req['request_code']) ?></strong>
                        </td>
                        <td>
                            <?= e($req['equipment_name']) ?>
                            <br><small class="text-muted"><?= e($req['equipment_code']) ?></small>
                        </td>
                        <td><?= e($req['requester_name']) ?></td>
                        <td><?= e($req['department_name']) ?></td>
                        <td>
                            <?php
                            $statusClass = $req['status_code'] === 'PENDING_HANDOVER' ? 'warning' : 'info';
                            $statusText = $req['status_code'] === 'PENDING_HANDOVER' ? 'Chờ nhận' : 'Đã nhận - chờ bàn giao';
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                        </td>
                        <td>
                            <small><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></small>
                        </td>
                        <td>
                            <a href="?id=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit me-1"></i>
                                Xử lý
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
require_once __DIR__ . '/../layouts/app.php';
?>
