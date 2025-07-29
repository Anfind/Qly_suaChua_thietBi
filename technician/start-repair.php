<?php
require_once __DIR__ . '/../config/config.php';

require_role('technician');

$db = Database::getInstance();
$error = '';
$success = '';

// Xử lý bắt đầu sửa chữa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $notes = $_POST['notes'] ?? '';
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        if (!$user || !in_array($user['role_name'], ['technician', 'admin'])) {
            throw new Exception('Bạn không có quyền thực hiện hành động này');
        }
        
        // Cập nhật trạng thái thành "IN_PROGRESS"
        $result = $db->query(
            "UPDATE repair_requests SET 
                current_status_id = (SELECT id FROM repair_statuses WHERE code = 'IN_PROGRESS'), 
                assigned_technician_id = ?,
                updated_at = NOW() 
             WHERE id = ?",
            [$user['id'], $request_id]
        );
        
        // Thêm vào lịch sử trạng thái
        $db->insert('repair_status_history', [
            'request_id' => $request_id,
            'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'IN_PROGRESS'")['id'],
            'user_id' => $user['id'],
            'notes' => $notes ?: 'Bắt đầu sửa chữa thiết bị',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        log_activity('start_repair', [
            'request_id' => $request_id,
            'notes' => $notes
        ]);
        
        $request = $db->fetch("SELECT request_code FROM repair_requests WHERE id = ?", [$request_id]);
        redirect('repairs/view.php?code=' . $request['request_code'], 
                'Đã bắt đầu sửa chữa thành công', 'success');
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy thông tin đơn nếu có ID
$request = null;
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
         WHERE r.id = ? AND s.code = 'SENT_TO_REPAIR' 
         AND (r.assigned_technician_id = ? OR rws.assigned_department_id = ?)
         LIMIT 1",
        [$request_id, $user['id'], $user['department_id']]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái chờ sửa chữa', 'error');
    }
}

$title = 'Bắt đầu sửa chữa';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật viên', 'url' => url('technician/')],
    ['title' => 'Bắt đầu sửa chữa', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-play me-2"></i>
            Bắt đầu sửa chữa
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
    <!-- Form bắt đầu sửa chữa cụ thể -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Bắt đầu sửa chữa đơn #<?= e($request['request_code']) ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Thông tin thiết bị</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Tên thiết bị:</strong></td>
                            <td><?= e($request['equipment_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Mã thiết bị:</strong></td>
                            <td><?= e($request['equipment_code']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Model:</strong></td>
                            <td><?= e($request['equipment_model']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Người đề xuất:</strong></td>
                            <td><?= e($request['requester_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Phòng ban:</strong></td>
                            <td><?= e($request['department_name']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Mô tả sự cố</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(e($request['problem_description'])) ?>
                    </div>
                    
                    <?php if ($request['urgency_level']): ?>
                        <div class="mt-3">
                            <strong>Độ ưu tiên: </strong>
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
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Hiển thị ảnh/video đính kèm nếu có -->
            <?php
            $images = json_decode($request['images'] ?? '[]', true);
            $videos = json_decode($request['videos'] ?? '[]', true);
            ?>
            
            <?php if (!empty($images) || !empty($videos)): ?>
                <div class="mb-4">
                    <h6>Tài liệu đính kèm</h6>
                    
                    <?php if (!empty($images)): ?>
                        <div class="row">
                            <?php foreach ($images as $image): ?>
                                <div class="col-md-3 mb-2">
                                    <img src="<?= upload_url('requests/' . $image) ?>" 
                                         class="img-fluid rounded border" 
                                         style="max-height: 150px; cursor: pointer;"
                                         onclick="showImageModal('<?= upload_url('requests/' . $image) ?>')">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($videos)): ?>
                        <div class="row mt-3">
                            <?php foreach ($videos as $video): ?>
                                <div class="col-md-4 mb-2">
                                    <video controls class="w-100 rounded border" style="max-height: 200px;">
                                        <source src="<?= upload_url('requests/' . $video) ?>" type="video/mp4">
                                        Trình duyệt không hỗ trợ video.
                                    </video>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="mt-4">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Ghi chú khi bắt đầu (tùy chọn):</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Ghi chú về tình trạng thiết bị, kế hoạch sửa chữa..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-play me-2"></i>Bắt đầu sửa chữa
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Danh sách đơn chờ bắt đầu -->
    <?php
    $pendingRequests = $db->fetchAll(
        "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
                u.full_name as requester_name, d.name as department_name
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE s.code = 'SENT_TO_REPAIR'
         ORDER BY r.created_at ASC"
    );
    ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Danh sách đơn chờ bắt đầu sửa chữa
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($pendingRequests)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Thiết bị</th>
                                <th>Người đề xuất</th>
                                <th>Phòng ban</th>
                                <th>Ngày tạo</th>
                                <th>Độ ưu tiên</th>
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
                                        <div class="equipment-info">
                                            <strong><?= e($req['equipment_name']) ?></strong>
                                            <small class="d-block text-muted"><?= e($req['equipment_code']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= e($req['requester_name']) ?></td>
                                    <td><?= e($req['department_name']) ?></td>
                                    <td>
                                        <span class="text-muted"><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></span>
                                    </td>
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
                                        ][$req['urgency_level']] ?? ucfirst($req['urgency_level']);
                                        ?>
                                        <span class="badge badge-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= url('repairs/view.php?code=' . $req['request_code']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="start-repair.php?id=<?= $req['id'] ?>" 
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
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không có đơn nào chờ bắt đầu sửa chữa</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Modal hiển thị ảnh -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ảnh đính kèm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script>
function showImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
