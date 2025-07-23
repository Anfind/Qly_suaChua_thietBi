<?php
require_once __DIR__ . '/../config/config.php';
require_role('logistics');

$controller = new RepairController();
$db = Database::getInstance();

$error = '';
$success = '';

// Xử lý bàn giao
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $notes = $_POST['notes'] ?? '';
        $condition_check = $_POST['condition_check'] ?? '';
        
        // Cập nhật trạng thái
        $result = $controller->updateStatus();
        if ($result) {
            $success = 'Bàn giao thiết bị thành công!';
            
            // Ghi log hoạt động
            log_activity('logistics_handover', "Bàn giao thiết bị cho đơn ID: $request_id", $request_id);
            
            // Redirect về trang danh sách
            header('Location: index.php?success=' . urlencode($success));
            exit;
        } else {
            $error = 'Có lỗi xảy ra khi bàn giao thiết bị!';
        }
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
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái chờ bàn giao', 'error');
    }
}

// Lấy danh sách đơn chờ bàn giao
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

$title = 'Bàn giao thiết bị';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Giao liên', 'url' => url('logistics/')],
    ['title' => 'Bàn giao thiết bị', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-handshake me-2"></i>
            Bàn giao thiết bị
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
    <!-- Form bàn giao cụ thể -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Bàn giao đơn #<?= e($request['request_code']) ?>
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
                        <?= nl2br(e($request['issue_description'])) ?>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                <input type="hidden" name="new_status" value="HANDED_TO_CLERK">
                
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
                    <label for="notes" class="form-label">Ghi chú bàn giao</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                              placeholder="Ghi chú về tình trạng thiết bị, thông tin bổ sung..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-handshake me-1"></i>Xác nhận bàn giao
                    </button>
                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Danh sách đơn chờ bàn giao -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Danh sách đơn chờ bàn giao (<?= count($pendingRequests) ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($pendingRequests)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không có đơn nào cần bàn giao</p>
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
                                            <i class="fas fa-handshake me-1"></i>Bàn giao
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
