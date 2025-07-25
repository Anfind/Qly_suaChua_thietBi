<?php
require_once __DIR__ . '/../config/config.php';
require_role('technician');

$db = Database::getInstance();
$user = current_user();
$error = '';
$success = '';

// Lấy thông tin phòng ban của technician hiện tại
$current_department = $db->fetch(
    "SELECT d.* FROM departments d 
     INNER JOIN users u ON d.id = u.department_id 
     WHERE u.id = ?", 
    [$user['id']]
);

// Lấy các workflow steps được giao cho phòng ban này
$assigned_steps = $db->fetchAll(
    "SELECT rws.*, rr.request_code, rr.problem_description, rr.urgency_level,
            e.name as equipment_name, e.code as equipment_code,
            u_req.full_name as requester_name,
            rs.name as request_status_name, rs.color as request_status_color
     FROM repair_workflow_steps rws
     LEFT JOIN repair_requests rr ON rws.request_id = rr.id
     LEFT JOIN equipments e ON rr.equipment_id = e.id
     LEFT JOIN users u_req ON rr.requester_id = u_req.id
     LEFT JOIN repair_statuses rs ON rr.current_status_id = rs.id
     WHERE rws.assigned_department_id = ? 
     AND rws.status IN ('pending', 'in_progress')
     ORDER BY 
        CASE rws.status 
            WHEN 'in_progress' THEN 1 
            WHEN 'pending' THEN 2 
        END,
        rr.urgency_level = 'critical' DESC,
        rr.urgency_level = 'high' DESC,
        rr.urgency_level = 'medium' DESC,
        rr.created_at ASC",
    [$current_department['id']]
);

// Lấy các bước đã hoàn thành bởi phòng ban này
$completed_steps = $db->fetchAll(
    "SELECT rws.*, rr.request_code, rr.problem_description,
            e.name as equipment_name, e.code as equipment_code,
            u_tech.full_name as technician_name
     FROM repair_workflow_steps rws
     LEFT JOIN repair_requests rr ON rws.request_id = rr.id
     LEFT JOIN equipments e ON rr.equipment_id = e.id
     LEFT JOIN users u_tech ON rws.assigned_technician_id = u_tech.id
     WHERE rws.assigned_department_id = ? 
     AND rws.status = 'completed'
     ORDER BY rws.completed_at DESC
     LIMIT 20",
    [$current_department['id']]
);

// Xử lý bắt đầu/hoàn thành workflow step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $action = $_POST['action'] ?? '';
        $step_id = (int)($_POST['step_id'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        if ($action === 'start') {
            // Bắt đầu làm việc trên step
            $db->query(
                "UPDATE repair_workflow_steps 
                 SET status = 'in_progress', 
                     assigned_technician_id = ?, 
                     started_at = NOW(),
                     notes = ?
                 WHERE id = ? AND assigned_department_id = ? AND status = 'pending'",
                [$user['id'], $notes, $step_id, $current_department['id']]
            );
            
            $success = 'Đã bắt đầu thực hiện công việc';
            
        } elseif ($action === 'complete') {
            // Hoàn thành step và chuyển sang bước tiếp theo
            $db->query("CALL MoveToNextWorkflowStep(?, ?, ?, ?)", [
                $_POST['request_id'],
                $step_id,
                $user['id'],
                $notes
            ]);
            
            $success = 'Đã hoàn thành và chuyển sang bước tiếp theo';
            
        } elseif ($action === 'update_notes') {
            // Cập nhật ghi chú
            $db->query(
                "UPDATE repair_workflow_steps 
                 SET notes = ?, updated_at = NOW()
                 WHERE id = ? AND assigned_department_id = ?",
                [$notes, $step_id, $current_department['id']]
            );
            
            $success = 'Đã cập nhật ghi chú thành công';
        }
        
        // Reload data
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$title = 'Quy trình sửa chữa đa phòng ban - ' . ($current_department['name'] ?? 'N/A');

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật', 'url' => url('technician/')],
    ['title' => 'Quy trình đa phòng ban', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-project-diagram me-2"></i>
            Quy trình sửa chữa đa phòng ban
        </h2>
        <p class="text-muted">Phòng ban: <strong><?= e($current_department['name'] ?? 'N/A') ?></strong></p>
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

<!-- Công việc hiện tại -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-tasks me-2"></i>
            Công việc được giao (<?= count($assigned_steps) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($assigned_steps)): ?>
            <div class="text-center py-4">
                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Không có công việc nào</h5>
                <p class="text-muted">Hiện tại không có đơn sửa chữa nào được giao cho phòng ban của bạn</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Đơn</th>
                            <th>Thiết bị</th>
                            <th>Mô tả sự cố</th>
                            <th>Bước</th>
                            <th>Trạng thái</th>
                            <th>Độ ưu tiên</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_steps as $step): ?>
                            <tr class="<?= $step['status'] === 'in_progress' ? 'table-warning' : '' ?>">
                                <td>
                                    <a href="<?= url('repairs/view.php?code=' . $step['request_code']) ?>" 
                                       class="text-decoration-none">
                                        <strong>#<?= e($step['request_code']) ?></strong>
                                    </a>
                                    <br>
                                    <small class="text-muted">bởi <?= e($step['requester_name']) ?></small>
                                </td>
                                <td>
                                    <strong><?= e($step['equipment_name']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= e($step['equipment_code']) ?></small>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 200px;" 
                                          title="<?= e($step['problem_description']) ?>">
                                        <?= e($step['problem_description']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary">Bước <?= $step['step_order'] ?></span>
                                </td>
                                <td>
                                    <?php if ($step['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock me-1"></i>Chờ thực hiện
                                        </span>
                                    <?php elseif ($step['status'] === 'in_progress'): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-cog fa-spin me-1"></i>Đang thực hiện
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $urgencyClass = [
                                        'low' => 'success',
                                        'medium' => 'warning', 
                                        'high' => 'danger',
                                        'critical' => 'dark'
                                    ][$step['urgency_level']] ?? 'secondary';
                                    
                                    $urgencyText = [
                                        'low' => 'Thấp',
                                        'medium' => 'Trung bình',
                                        'high' => 'Cao',
                                        'critical' => 'Khẩn cấp'
                                    ][$step['urgency_level']] ?? 'Không xác định';
                                    ?>
                                    <span class="badge bg-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                                </td>
                                <td>
                                    <?php if ($step['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="startWorkflowStep(<?= $step['id'] ?>, <?= $step['request_id'] ?>)">
                                            <i class="fas fa-play me-1"></i>Bắt đầu
                                        </button>
                                    <?php elseif ($step['status'] === 'in_progress'): ?>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    onclick="updateWorkflowNotes(<?= $step['id'] ?>)">
                                                <i class="fas fa-edit me-1"></i>Cập nhật
                                            </button>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="completeWorkflowStep(<?= $step['id'] ?>, <?= $step['request_id'] ?>)">
                                                <i class="fas fa-check me-1"></i>Hoàn thành
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lịch sử hoàn thành -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>
            Lịch sử hoàn thành (20 gần nhất)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($completed_steps)): ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Chưa có lịch sử</h5>
                <p class="text-muted">Chưa có công việc nào được hoàn thành</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Đơn</th>
                            <th>Thiết bị</th>
                            <th>Bước</th>
                            <th>Người thực hiện</th>
                            <th>Hoàn thành</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed_steps as $step): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('repairs/view.php?code=' . $step['request_code']) ?>" 
                                       class="text-decoration-none">
                                        #<?= e($step['request_code']) ?>
                                    </a>
                                </td>
                                <td><?= e($step['equipment_name']) ?></td>
                                <td><span class="badge bg-success">Bước <?= $step['step_order'] ?></span></td>
                                <td><?= e($step['technician_name'] ?? 'N/A') ?></td>
                                <td>
                                    <small><?= date('d/m/Y H:i', strtotime($step['completed_at'])) ?></small>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 200px;" 
                                          title="<?= e($step['notes']) ?>">
                                        <?= e($step['notes'] ?: 'Không có ghi chú') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for workflow actions -->
<div class="modal fade" id="workflowActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="step_id" id="modal_step_id">
                <input type="hidden" name="request_id" id="modal_request_id">
                <input type="hidden" name="action" id="modal_action">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modal_title">Hành động</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Ghi chú</label>
                        <textarea name="notes" id="modal_notes" class="form-control" rows="4" 
                                  placeholder="Nhập ghi chú về công việc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="modal_submit">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom JavaScript
$custom_js = "
<script>
function startWorkflowStep(stepId, requestId) {
    document.getElementById('modal_step_id').value = stepId;
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('modal_action').value = 'start';
    document.getElementById('modal_title').textContent = 'Bắt đầu thực hiện';
    document.getElementById('modal_notes').placeholder = 'Ghi chú về việc bắt đầu thực hiện...';
    document.getElementById('modal_submit').textContent = 'Bắt đầu';
    document.getElementById('modal_submit').className = 'btn btn-success';
    
    new bootstrap.Modal(document.getElementById('workflowActionModal')).show();
}

function completeWorkflowStep(stepId, requestId) {
    document.getElementById('modal_step_id').value = stepId;
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('modal_action').value = 'complete';
    document.getElementById('modal_title').textContent = 'Hoàn thành công việc';
    document.getElementById('modal_notes').placeholder = 'Ghi chú về kết quả công việc...';
    document.getElementById('modal_submit').textContent = 'Hoàn thành';
    document.getElementById('modal_submit').className = 'btn btn-success';
    
    new bootstrap.Modal(document.getElementById('workflowActionModal')).show();
}

function updateWorkflowNotes(stepId) {
    document.getElementById('modal_step_id').value = stepId;
    document.getElementById('modal_request_id').value = '';
    document.getElementById('modal_action').value = 'update_notes';
    document.getElementById('modal_title').textContent = 'Cập nhật ghi chú';
    document.getElementById('modal_notes').placeholder = 'Cập nhật tiến độ công việc...';
    document.getElementById('modal_submit').textContent = 'Cập nhật';
    document.getElementById('modal_submit').className = 'btn btn-primary';
    
    new bootstrap.Modal(document.getElementById('workflowActionModal')).show();
}
</script>
";

include '../layouts/app.php';
?>
