<?php
require_once __DIR__ . '/../config/config.php';

// Debug user role trước khi require_role
$current_user = current_user();
error_log("=== CLERK SEND PAGE LOAD DEBUG ===");
error_log("Current user: " . print_r($current_user, true));
error_log("User role_name: " . ($current_user['role_name'] ?? 'NULL'));
error_log("===================================");

require_role('clerk');

$db = Database::getInstance();
$error = '';
$success = '';

// Lấy danh sách phòng ban để làm dropdown đơn vị sửa chữa
$departments = $db->fetchAll("SELECT id, code, name FROM departments WHERE status = 'active' ORDER BY name");

// Lấy danh sách phòng kỹ thuật để tạo workflow
$tech_departments = $db->fetchAll("SELECT id, code, name FROM departments WHERE status = 'active' AND code LIKE 'TECH_%' ORDER BY code");

// Lấy workflow templates để hỗ trợ tự động
$workflow_templates = $db->fetchAll("SELECT id, name, description, equipment_type_id, departments_sequence FROM workflow_templates WHERE status = 'active' ORDER BY is_default DESC, name");

// Xử lý chuyển thiết bị đến sửa chữa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $request_id = (int)$_POST['request_id'];
        $notes = $_POST['notes'] ?? '';
        $repair_company = $_POST['repair_company'] ?? '';
        $repair_company_other = $_POST['repair_company_other'] ?? '';
        $estimated_completion = $_POST['estimated_completion'] ?? '';
        $estimated_cost = $_POST['estimated_cost'] ?? '';
        $workflow_departments = $_POST['workflow_departments'] ?? [];
        $use_workflow_template = $_POST['use_workflow_template'] ?? '';
        
        // Nếu chọn "other", sử dụng tên nhập tự do
        if ($repair_company === 'other' && !empty($repair_company_other)) {
            $repair_company = $repair_company_other;
        } elseif ($repair_company !== 'other') {
            // Lấy tên phòng ban từ database
            $dept = $db->fetch("SELECT name FROM departments WHERE id = ?", [$repair_company]);
            $repair_company = $dept ? $dept['name'] : $repair_company;
        }
        
        // Xử lý workflow departments
        $workflow_dept_ids = [];
        if ($use_workflow_template && is_numeric($use_workflow_template)) {
            // Sử dụng template
            $template = $db->fetch("SELECT departments_sequence FROM workflow_templates WHERE id = ?", [$use_workflow_template]);
            if ($template) {
                $workflow_dept_ids = json_decode($template['departments_sequence'], true) ?: [];
            }
        } elseif (!empty($workflow_departments)) {
            // Sử dụng danh sách phòng ban được chọn thủ công
            $workflow_dept_ids = array_filter(array_map('intval', $workflow_departments));
        }
        
        // Kiểm tra quyền user hiện tại
        $user = current_user();
        
        // Debug log
        error_log("=== CLERK SEND DEBUG ===");
        error_log("User data: " . print_r($user, true));
        error_log("User role_name: " . ($user['role_name'] ?? 'NULL'));
        error_log("Is clerk: " . (in_array($user['role_name'] ?? '', ['clerk']) ? 'YES' : 'NO'));
        error_log("Is admin: " . (in_array($user['role_name'] ?? '', ['admin']) ? 'YES' : 'NO'));
        error_log("========================");
        
        if (!$user || !in_array($user['role_name'], ['clerk', 'admin'])) {
            $error_detail = sprintf(
                'Bạn không có quyền thực hiện hành động này. User: %s, Role: %s', 
                $user['username'] ?? 'NULL',
                $user['role_name'] ?? 'NULL'
            );
            error_log("Permission denied: " . $error_detail);
            throw new Exception($error_detail);
        }
        
        // Thêm thông tin vào notes
        $full_notes = $notes;
        if ($repair_company) {
            $full_notes = "Đơn vị sửa chữa: " . $repair_company . "\n" . $notes;
        }
        if ($estimated_completion) {
            $full_notes .= "\nDự kiến hoàn thành: " . $estimated_completion;
        }
        if ($estimated_cost) {
            $full_notes .= "\nDự kiến chi phí: " . number_format($estimated_cost) . " VNĐ";
        }
        
        // Cập nhật trạng thái thành "SENT_TO_REPAIR"
        $result = $db->query(
            "UPDATE repair_requests SET 
                current_status_id = (SELECT id FROM repair_statuses WHERE code = 'SENT_TO_REPAIR'), 
                assigned_clerk_id = ?,
                estimated_completion = ?,
                updated_at = NOW() 
             WHERE id = ?",
            [$user['id'], $estimated_completion ?: null, $request_id]
        );
        
        // Cập nhật chi phí ước tính nếu có
        if ($estimated_cost) {
            $db->query(
                "UPDATE repair_requests SET total_cost = ? WHERE id = ?",
                [$estimated_cost, $request_id]
            );
        }
        
        // Thêm vào lịch sử trạng thái
        $db->insert('repair_status_history', [
            'request_id' => $request_id,
            'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'SENT_TO_REPAIR'")['id'],
            'user_id' => $user['id'],
            'notes' => $full_notes,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Tạo workflow steps nếu có
        if (!empty($workflow_dept_ids)) {
            try {
                $departments_json = json_encode($workflow_dept_ids);
                $db->query("CALL CreateWorkflowSteps(?, ?, ?)", [$request_id, $departments_json, $user['id']]);
                
                // Cập nhật trạng thái thành IN_PROGRESS cho step đầu tiên
                $db->query(
                    "UPDATE repair_requests SET current_status_id = (SELECT id FROM repair_statuses WHERE code = 'IN_PROGRESS') WHERE id = ?",
                    [$request_id]
                );
                
                // Cập nhật step đầu tiên thành in_progress
                $db->query(
                    "UPDATE repair_workflow_steps 
                     SET status = 'in_progress', started_at = NOW() 
                     WHERE request_id = ? AND step_order = 1",
                    [$request_id]
                );
                
                // Thêm log cho workflow
                $workflow_log = "Tạo quy trình sửa chữa đa phòng ban:\n";
                foreach ($workflow_dept_ids as $index => $dept_id) {
                    $dept_info = $db->fetch("SELECT name FROM departments WHERE id = ?", [$dept_id]);
                    $workflow_log .= "Bước " . ($index + 1) . ": " . ($dept_info['name'] ?? 'N/A') . "\n";
                }
                
                $db->insert('repair_status_history', [
                    'request_id' => $request_id,
                    'status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'IN_PROGRESS'")['id'],
                    'user_id' => $user['id'],
                    'notes' => $workflow_log,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $success = 'Đã tạo quy trình sửa chữa đa phòng ban thành công';
            } catch (Exception $e) {
                error_log("Workflow creation error: " . $e->getMessage());
                $success = 'Đã chuyển thiết bị đến sửa chữa thành công (chưa tạo được workflow)';
            }
        } else {
            $success = 'Đã chuyển thiết bị đến sửa chữa thành công';
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
                d.name as department_name, s.name as status_name, s.code as status_code
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN departments d ON u.department_id = d.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.id = ? AND s.code = 'HANDED_TO_CLERK'",
        [$request_id]
    );
    
    if (!$request) {
        redirect('index.php', 'Không tìm thấy đơn hoặc đơn không ở trạng thái chờ chuyển', 'error');
    }
}

$title = 'Chuyển thiết bị đến sửa chữa';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Văn thư', 'url' => url('clerk/')],
    ['title' => 'Chuyển sửa chữa', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-shipping-fast me-2"></i>
            Chuyển thiết bị đến sửa chữa
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
    <!-- Form chuyển sửa chữa cụ thể -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Chuyển đơn #<?= e($request['request_code']) ?> đến sửa chữa
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
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="repair_company" class="form-label">Đơn vị sửa chữa <span class="text-danger">*</span></label>
                            <select name="repair_company" id="repair_company" class="form-select" required>
                                <option value="">-- Chọn đơn vị sửa chữa --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                                <?php endforeach; ?>
                                <option value="other">🔧 Đơn vị khác (nhập tự do)</option>
                            </select>
                        </div>
                        <div class="mb-3" id="other_company_div" style="display: none;">
                            <label for="repair_company_other" class="form-label">Tên đơn vị sửa chữa khác</label>
                            <input type="text" name="repair_company_other" id="repair_company_other" class="form-control" 
                                   placeholder="Nhập tên đơn vị/công ty sửa chữa...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="estimated_completion" class="form-label">Dự kiến hoàn thành</label>
                            <input type="date" name="estimated_completion" id="estimated_completion" 
                                   class="form-control" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Workflow Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-project-diagram me-2"></i>
                                    Quy trình sửa chữa đa phòng ban (Tùy chọn)
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-magic me-1"></i>Chọn template quy trình có sẵn
                                    </label>
                                    <select name="use_workflow_template" id="use_workflow_template" class="form-select">
                                        <option value="">-- Chọn template hoặc tự tạo --</option>
                                        <?php foreach ($workflow_templates as $template): ?>
                                            <option value="<?= $template['id'] ?>" data-sequence="<?= e($template['departments_sequence']) ?>">
                                                <?= e($template['name']) ?> - <?= e($template['description']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        Template sẽ tự động tạo quy trình theo loại thiết bị phù hợp
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tools me-1"></i>Hoặc chọn thủ công các phòng kỹ thuật
                                    </label>
                                    <div class="row">
                                        <?php foreach ($tech_departments as $tech_dept): ?>
                                            <div class="col-md-6 col-lg-4 mb-2">
                                                <div class="form-check">
                                                    <input type="checkbox" name="workflow_departments[]" 
                                                           value="<?= $tech_dept['id'] ?>" 
                                                           id="dept_<?= $tech_dept['id'] ?>" 
                                                           class="form-check-input workflow-dept-checkbox">
                                                    <label class="form-check-label" for="dept_<?= $tech_dept['id'] ?>">
                                                        <strong><?= e($tech_dept['code']) ?></strong><br>
                                                        <small class="text-muted"><?= e($tech_dept['name']) ?></small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="form-text">
                                        Chọn các phòng kỹ thuật sẽ tham gia sửa chữa theo thứ tự từ trên xuống dưới
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Lưu ý:</strong> Nếu chọn quy trình đa phòng ban, mỗi phòng sẽ lần lượt thực hiện phần việc của mình. 
                                    Phòng cuối cùng hoàn thành sẽ chuyển đơn sang trạng thái "Đã sửa xong".
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="estimated_cost" class="form-label">Dự kiến chi phí (VNĐ)</label>
                            <input type="number" name="estimated_cost" id="estimated_cost" class="form-control" 
                                   placeholder="0" min="0" step="1000">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Ghi chú chuyển sửa chữa</label>
                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                              placeholder="Ghi chú về việc chuyển sửa chữa, yêu cầu đặc biệt..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-shipping-fast me-1"></i>Xác nhận chuyển sửa chữa
                    </button>
                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Hiển thị thông báo nếu không có đơn -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
            <h5>Không tìm thấy đơn sửa chữa</h5>
            <p class="text-muted">Đơn không tồn tại hoặc không ở trạng thái chờ chuyển sửa chữa</p>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();

// Custom JavaScript
$custom_js = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    const repairCompanySelect = document.getElementById('repair_company');
    const otherCompanyDiv = document.getElementById('other_company_div');
    const otherCompanyInput = document.getElementById('repair_company_other');
    const workflowTemplateSelect = document.getElementById('use_workflow_template');
    const workflowCheckboxes = document.querySelectorAll('.workflow-dept-checkbox');
    
    // Hiển thị/ẩn trường nhập tự do
    repairCompanySelect.addEventListener('change', function() {
        if (this.value === 'other') {
            otherCompanyDiv.style.display = 'block';
            otherCompanyInput.setAttribute('required', '');
            otherCompanyInput.focus();
        } else {
            otherCompanyDiv.style.display = 'none';
            otherCompanyInput.removeAttribute('required');
            otherCompanyInput.value = '';
        }
    });
    
    // Xử lý workflow template
    workflowTemplateSelect.addEventListener('change', function() {
        // Clear all workflow checkboxes first
        workflowCheckboxes.forEach(cb => cb.checked = false);
        
        if (this.value) {
            try {
                const selectedOption = this.options[this.selectedIndex];
                const sequence = selectedOption.getAttribute('data-sequence');
                if (sequence) {
                    const deptIds = JSON.parse(sequence);
                    deptIds.forEach(deptId => {
                        const checkbox = document.getElementById('dept_' + deptId);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                }
            } catch (e) {
                console.error('Error parsing workflow template:', e);
            }
        }
    });
    
    // Khi chọn workflow departments thủ công, clear template
    workflowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                workflowTemplateSelect.value = '';
            }
        });
    });
    
    // Validation trước khi submit
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const repairCompany = repairCompanySelect.value;
        const otherCompanyValue = otherCompanyInput.value.trim();
        
        if (repairCompany === 'other' && !otherCompanyValue) {
            e.preventDefault();
            alert('Vui lòng nhập tên đơn vị sửa chữa khác');
            otherCompanyInput.focus();
            return false;
        }
        
        if (!repairCompany) {
            e.preventDefault();
            alert('Vui lòng chọn đơn vị sửa chữa');
            repairCompanySelect.focus();
            return false;
        }
        
        // Check if workflow is selected
        const hasWorkflow = workflowTemplateSelect.value || 
            Array.from(workflowCheckboxes).some(cb => cb.checked);
        
        let confirmMessage = 'Xác nhận chuyển thiết bị đến đơn vị sửa chữa?';
        if (hasWorkflow) {
            confirmMessage = 'Xác nhận tạo quy trình sửa chữa đa phòng ban và chuyển thiết bị?';
        }
        
        return confirm(confirmMessage);
    });
});
</script>
";

include '../layouts/app.php';
?>
