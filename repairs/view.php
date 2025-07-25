<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$controller = new RepairController();
$data = $controller->view();

$request = $data['request'];
$statusHistory = $data['statusHistory'];
$repairDetails = $data['repairDetails'];

$title = 'Chi tiết đơn ' . $request['request_code'];
$user = current_user();

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Đơn sửa chữa', 'url' => url('repairs/')],
    ['title' => $request['request_code'], 'url' => '']
];

// Decode JSON fields
$images = json_decode($request['images'] ?? '[]', true);
$videos = json_decode($request['videos'] ?? '[]', true);

// Đếm tổng số file đính kèm
$attachmentSummary = get_request_attachments_summary($request['id']);
$totalAttachments = $attachmentSummary['total'];

// Lấy thông tin workflow steps nếu có
$db = Database::getInstance();
$workflowSteps = $db->fetchAll(
    "SELECT rws.*, d.name as department_name, d.code as department_code,
            u.full_name as technician_name, u.username as technician_username
     FROM repair_workflow_steps rws
     LEFT JOIN departments d ON rws.assigned_department_id = d.id
     LEFT JOIN users u ON rws.assigned_technician_id = u.id
     WHERE rws.request_id = ?
     ORDER BY rws.step_order",
    [$request['id']]
);

// Check permissions for actions
$canUpdateStatus = false;
$allowedNextStatuses = [];

$currentStatus = $request['status_code'];
$userRole = $user['role_name'];

// Define allowed transitions
$statusTransitions = [
    'PENDING_HANDOVER' => ['logistics' => 'HANDED_TO_CLERK'],
    'HANDED_TO_CLERK' => ['clerk' => 'SENT_TO_REPAIR'],
    'SENT_TO_REPAIR' => ['technician' => 'IN_PROGRESS'],
    'IN_PROGRESS' => ['technician' => 'REPAIR_COMPLETED'],
    'REPAIR_COMPLETED' => ['clerk' => 'RETRIEVED'],
    'RETRIEVED' => ['logistics' => 'COMPLETED']
];

if (isset($statusTransitions[$currentStatus][$userRole])) {
    $canUpdateStatus = true;
    $allowedNextStatuses[] = $statusTransitions[$currentStatus][$userRole];
}

// Admin can always cancel
if (has_role('admin') && !in_array($currentStatus, ['COMPLETED', 'CANCELLED'])) {
    $canUpdateStatus = true;
    $allowedNextStatuses[] = 'CANCELLED';
}

ob_start();
?>

<!-- Request header -->
<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title mb-2">
            <i class="fas fa-file-alt me-2"></i>
            Đơn sửa chữa #<?= e($request['request_code']) ?>
        </h2>
        <div class="d-flex align-items-center gap-3">
            <span class="status-badge fs-6" style="background-color: <?= e($request['status_color']) ?>15; color: <?= e($request['status_color']) ?>;">
                <i class="<?= e($request['status_icon']) ?> me-1"></i>
                <?= e($request['status_name']) ?>
            </span>
            
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
            <span class="badge bg-<?= $urgencyClass ?> fs-6"><?= $urgencyText ?></span>
            
            <small class="text-muted">
                <i class="fas fa-calendar me-1"></i>
                Tạo <?= time_ago($request['created_at']) ?>
            </small>
        </div>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($canUpdateStatus): ?>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                <i class="fas fa-edit me-2"></i>Cập nhật trạng thái
            </button>
        <?php endif; ?>
        
        <!-- Xem file đính kèm -->
        <a href="<?= url('repairs/attachments.php?code=' . $request['request_code']) ?>" class="btn btn-outline-info me-2">
            <i class="fas fa-paperclip me-2"></i>File đính kèm 
            <?php if ($totalAttachments > 0): ?>
                <span class="badge bg-info ms-1"><?= $totalAttachments ?></span>
            <?php endif; ?>
        </a>
        
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-h"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= url('repairs/attachments.php?code=' . $request['request_code']) ?>">
                    <i class="fas fa-paperclip me-2"></i>Xem tất cả file đính kèm
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>In đơn
                </a></li>
                <li><a class="dropdown-item" href="<?= url('repairs/export.php?id=' . $request['id'] . '&format=pdf') ?>">
                    <i class="fas fa-file-pdf me-2"></i>Xuất PDF
                </a></li>
                <?php if (has_role('admin')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteRequest()">
                        <i class="fas fa-trash me-2"></i>Xóa đơn
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main content -->
    <div class="col-lg-8">
        <!-- Request details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông tin đơn sửa chữa
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-desktop me-1"></i>Thiết bị
                        </h6>
                        <p class="mb-1"><strong><?= e($request['equipment_name']) ?></strong></p>
                        <p class="text-muted mb-1">Mã: <?= e($request['equipment_code']) ?></p>
                        <?php if ($request['equipment_model']): ?>
                            <p class="text-muted mb-1">Model: <?= e($request['equipment_model']) ?></p>
                        <?php endif; ?>
                        <?php if ($request['equipment_location']): ?>
                            <p class="text-muted mb-3">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?= e($request['equipment_location']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-user me-1"></i>Người đề xuất
                        </h6>
                        <p class="mb-1"><strong><?= e($request['requester_name']) ?></strong></p>
                        <p class="text-muted mb-1"><?= e($request['department_name']) ?></p>
                        <?php if ($request['requester_phone']): ?>
                            <p class="text-muted mb-1">
                                <i class="fas fa-phone me-1"></i>
                                <?= e($request['requester_phone']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($request['requester_email']): ?>
                            <p class="text-muted mb-3">
                                <i class="fas fa-envelope me-1"></i>
                                <?= e($request['requester_email']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <hr>
                
                <h6 class="text-primary mb-2">
                    <i class="fas fa-clipboard-list me-1"></i>Mô tả tình trạng lỗi
                </h6>
                <div class="border rounded p-3 bg-light">
                    <?= nl2br(e($request['problem_description'])) ?>
                </div>
                
                <!-- Images and videos -->
                <?php if (!empty($images) || !empty($videos)): ?>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-primary mb-0">
                            <i class="fas fa-paperclip me-1"></i>Tệp đính kèm 
                            <span class="badge bg-primary ms-1"><?= count($images) + count($videos) ?></span>
                        </h6>
                        <a href="<?= url('repairs/attachments.php?code=' . $request['request_code']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i>Xem tất cả 
                            <?php if ($totalAttachments > 0): ?>
                                <span class="badge bg-primary ms-1"><?= $totalAttachments ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <?php if (!empty($images)): ?>
                        <h6 class="mb-2">Hình ảnh:</h6>
                        <div class="row mb-3">
                            <?php foreach ($images as $image): ?>
                                <div class="col-md-3 mb-2">
                                    <a href="<?= upload_url('requests/' . $image) ?>" target="_blank">
                                        <img src="<?= upload_url('requests/' . $image) ?>" 
                                             class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($videos)): ?>
                        <h6 class="mb-2">Video:</h6>
                        <div class="row">
                            <?php foreach ($videos as $video): ?>
                                <div class="col-md-6 mb-2">
                                    <video controls class="w-100" style="max-height: 200px;">
                                        <source src="<?= upload_url('requests/' . $video) ?>" type="video/mp4">
                                        Trình duyệt không hỗ trợ video.
                                    </video>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Workflow steps (if exists) -->
        <?php if (!empty($workflowSteps)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-project-diagram me-2"></i>
                        Quy trình sửa chữa đa phòng ban
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($workflowSteps as $index => $step): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-<?= $step['status'] === 'completed' ? 'success' : ($step['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary">Bước <?= $step['step_order'] ?></span>
                                            <span class="badge bg-<?= $step['status'] === 'completed' ? 'success' : ($step['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                                <?php
                                                $statusText = [
                                                    'pending' => 'Chờ thực hiện',
                                                    'in_progress' => 'Đang thực hiện', 
                                                    'completed' => 'Đã hoàn thành',
                                                    'skipped' => 'Bỏ qua'
                                                ][$step['status']] ?? 'Không xác định';
                                                echo $statusText;
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <h6 class="card-title mb-2"><?= e($step['department_name']) ?></h6>
                                        <p class="card-text text-muted mb-2">
                                            <small><?= e($step['department_code']) ?></small>
                                        </p>
                                        
                                        <?php if ($step['technician_name']): ?>
                                            <p class="card-text mb-2">
                                                <i class="fas fa-user me-1"></i>
                                                <small><?= e($step['technician_name']) ?></small>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($step['started_at']): ?>
                                            <p class="card-text mb-1">
                                                <i class="fas fa-play me-1"></i>
                                                <small>Bắt đầu: <?= date('d/m/Y H:i', strtotime($step['started_at'])) ?></small>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($step['completed_at']): ?>
                                            <p class="card-text mb-1">
                                                <i class="fas fa-check me-1"></i>
                                                <small>Hoàn thành: <?= date('d/m/Y H:i', strtotime($step['completed_at'])) ?></small>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($step['notes']): ?>
                                            <p class="card-text">
                                                <i class="fas fa-comment me-1"></i>
                                                <small><?= nl2br(e($step['notes'])) ?></small>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Progress indicator -->
                    <div class="mt-3">
                        <h6>Tiến độ tổng thể:</h6>
                        <div class="progress">
                            <?php
                            $completedSteps = array_filter($workflowSteps, function($step) {
                                return $step['status'] === 'completed';
                            });
                            $totalSteps = count($workflowSteps);
                            $progressPercent = $totalSteps > 0 ? (count($completedSteps) / $totalSteps) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $progressPercent ?>%" 
                                 aria-valuenow="<?= $progressPercent ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                                <?= round($progressPercent) ?>%
                            </div>
                        </div>
                        <small class="text-muted">
                            <?= count($completedSteps) ?>/<?= $totalSteps ?> bước đã hoàn thành
                        </small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Repair details (for technicians) -->
        <?php if (!empty($repairDetails) || has_any_role(['technician', 'admin'])): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Chi tiết sửa chữa
                    </h5>
                    
                    <?php if (has_any_role(['technician', 'admin']) && in_array($currentStatus, ['IN_PROGRESS', 'REPAIR_COMPLETED'])): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRepairDetailModal">
                            <i class="fas fa-plus me-1"></i>Thêm chi tiết
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($repairDetails)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Chưa có chi tiết sửa chữa nào</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($repairDetails as $detail): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-1"></i>
                                        <?= e($detail['technician_name']) ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= format_datetime($detail['created_at']) ?>
                                    </small>
                                </div>
                                
                                <p class="mb-2"><?= nl2br(e($detail['description'])) ?></p>
                                
                                <?php if ($detail['parts_replaced']): ?>
                                    <p class="mb-2">
                                        <strong>Linh kiện thay thế:</strong> <?= e($detail['parts_replaced']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <?php if ($detail['parts_cost'] > 0): ?>
                                        <div class="col-md-4">
                                            <small class="text-muted">Chi phí linh kiện:</small><br>
                                            <strong><?= format_money($detail['parts_cost']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($detail['labor_cost'] > 0): ?>
                                        <div class="col-md-4">
                                            <small class="text-muted">Chi phí nhân công:</small><br>
                                            <strong><?= format_money($detail['labor_cost']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($detail['time_spent'] > 0): ?>
                                        <div class="col-md-4">
                                            <small class="text-muted">Thời gian:</small><br>
                                            <strong><?= $detail['time_spent'] ?> giờ</strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($detail['images']): ?>
                                    <?php $detailImages = json_decode($detail['images'], true); ?>
                                    <?php if (!empty($detailImages)): ?>
                                        <div class="mt-2">
                                            <small class="text-muted d-block mb-1">Hình ảnh:</small>
                                            <div class="d-flex gap-2">
                                                <?php foreach ($detailImages as $image): ?>
                                                    <a href="<?= upload_url('requests/' . $image) ?>" target="_blank">
                                                        <img src="<?= upload_url('requests/' . $image) ?>" 
                                                             class="img-thumbnail" style="width: 80px; height: 80px; object-fit: cover;">
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Total cost -->
                        <?php if ($request['total_cost'] > 0): ?>
                            <div class="alert alert-info">
                                <strong>Tổng chi phí sửa chữa: <?= format_money($request['total_cost']) ?></strong>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Status timeline -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>
                    Lịch sử trạng thái
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($statusHistory as $index => $history): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker" style="background-color: <?= e($history['status_color']) ?>;">
                                <i class="<?= e($history['status_icon']) ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <h6 class="mb-1"><?= e($history['status_name']) ?></h6>
                                <small class="text-muted d-block">
                                    <?= e($history['user_name']) ?> - <?= e($history['role_name']) ?>
                                </small>
                                <small class="text-muted d-block">
                                    <?= format_datetime($history['created_at']) ?>
                                </small>
                                <?php if ($history['notes']): ?>
                                    <p class="mt-2 mb-0"><?= nl2br(e($history['notes'])) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($history['attachments']): ?>
                                    <?php $attachments = json_decode($history['attachments'], true); ?>
                                    <?php if (!empty($attachments)): ?>
                                        <div class="mt-2">
                                            <?php foreach ($attachments as $attachment): ?>
                                                <a href="<?= upload_url('requests/' . $attachment) ?>" 
                                                   class="btn btn-sm btn-outline-primary me-1" target="_blank">
                                                    <i class="fas fa-download me-1"></i>
                                                    <?= e(basename($attachment)) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Assignment info -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>
                    Phân công xử lý
                </h5>
            </div>
            <div class="card-body">
                <?php if ($request['logistics_name']): ?>
                    <div class="mb-3">
                        <small class="text-muted">Giao liên:</small><br>
                        <strong><?= e($request['logistics_name']) ?></strong>
                    </div>
                <?php endif; ?>
                
                <?php if ($request['clerk_name']): ?>
                    <div class="mb-3">
                        <small class="text-muted">Văn thư:</small><br>
                        <strong><?= e($request['clerk_name']) ?></strong>
                    </div>
                <?php endif; ?>
                
                <?php if ($request['technician_name']): ?>
                    <div class="mb-3">
                        <small class="text-muted">Kỹ thuật viên:</small><br>
                        <strong><?= e($request['technician_name']) ?></strong>
                    </div>
                <?php endif; ?>
                
                <?php if (!$request['logistics_name'] && !$request['clerk_name'] && !$request['technician_name']): ?>
                    <p class="text-muted mb-0">Chưa có phân công</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<?php if ($canUpdateStatus): ?>
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Cập nhật trạng thái
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('repairs/update-status.php') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Trạng thái mới:</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <?php foreach ($allowedNextStatuses as $status): ?>
                                <?php
                                $statusNames = [
                                    'HANDED_TO_CLERK' => 'Đã bàn giao cho văn thư',
                                    'SENT_TO_REPAIR' => 'Đã chuyển đơn vị sửa chữa',
                                    'IN_PROGRESS' => 'Đang sửa chữa',
                                    'REPAIR_COMPLETED' => 'Đã sửa xong - chờ thu hồi',
                                    'RETRIEVED' => 'Đã thu hồi - chờ trả lại',
                                    'COMPLETED' => 'Hoàn tất',
                                    'CANCELLED' => 'Hủy bỏ'
                                ];
                                ?>
                                <option value="<?= $status ?>"><?= $statusNames[$status] ?? $status ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Ghi chú:</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Ghi chú về việc chuyển trạng thái..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachments" class="form-label">Tệp đính kèm:</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                        <div class="form-text">Có thể đính kèm hình ảnh, tài liệu...</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Repair Detail Modal -->
<?php if (has_any_role(['technician', 'admin']) && in_array($currentStatus, ['IN_PROGRESS', 'REPAIR_COMPLETED'])): ?>
<div class="modal fade" id="addRepairDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Thêm chi tiết sửa chữa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('repairs/add-detail.php') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả công việc <span class="text-danger">*</span>:</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required
                                  placeholder="Mô tả chi tiết công việc đã thực hiện..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parts_replaced" class="form-label">Linh kiện thay thế:</label>
                                <textarea class="form-control" id="parts_replaced" name="parts_replaced" rows="2"
                                          placeholder="Danh sách linh kiện đã thay thế..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="time_spent" class="form-label">Thời gian (giờ):</label>
                                <input type="number" class="form-control" id="time_spent" name="time_spent" 
                                       min="0" step="0.5" placeholder="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parts_cost" class="form-label">Chi phí linh kiện (VNĐ):</label>
                                <input type="number" class="form-control" id="parts_cost" name="parts_cost" 
                                       min="0" placeholder="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="labor_cost" class="form-label">Chi phí nhân công (VNĐ):</label>
                                <input type="number" class="form-control" id="labor_cost" name="labor_cost" 
                                       min="0" placeholder="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="repair_images" class="form-label">Hình ảnh minh họa:</label>
                        <input type="file" class="form-control" id="repair_images" name="images[]" 
                               multiple accept="image/*">
                        <div class="form-text">Hình ảnh trước/sau sửa chữa, linh kiện thay thế...</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="repair_notes" class="form-label">Ghi chú thêm:</label>
                        <textarea class="form-control" id="repair_notes" name="notes" rows="2"
                                  placeholder="Ghi chú thêm về quá trình sửa chữa..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu chi tiết
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();

// Custom CSS
$custom_css = "
<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e3e6f0;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    
    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 5px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        border: 3px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .timeline-content {
        background: white;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
    }
    
    .img-thumbnail {
        transition: transform 0.2s;
        cursor: pointer;
    }
    
    .img-thumbnail:hover {
        transform: scale(1.05);
    }
    
    @media print {
        .btn, .dropdown, .modal { display: none !important; }
        .card { border: 1px solid #000 !important; }
    }
</style>
";

// Custom JS
$custom_js = "
<script>
    function deleteRequest() {
        if (confirm('Bạn có chắc chắn muốn xóa đơn sửa chữa này?')) {
            // Implementation for delete
            alert('Chức năng xóa sẽ được triển khai');
        }
    }
    
    // Image lightbox
    document.addEventListener('click', function(e) {
        if (e.target.matches('.img-thumbnail')) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class='modal-dialog modal-lg'>
                    <div class='modal-content'>
                        <div class='modal-body p-0'>
                            <img src='\${e.target.src}' class='w-100'>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
    });
    
    // Auto-scroll timeline to current status
    document.addEventListener('DOMContentLoaded', function() {
        const timeline = document.querySelector('.timeline');
        if (timeline) {
            const items = timeline.querySelectorAll('.timeline-item');
            if (items.length > 0) {
                const lastItem = items[items.length - 1];
                lastItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
</script>
";

include '../layouts/app.php';
?>
