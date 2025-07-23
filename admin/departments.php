<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$departmentModel = new Department();
$title = 'Quản lý đơn vị';

// Xử lý các actions
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        switch ($action) {
            case 'create':
                $data = [
                    'code' => $_POST['code'],
                    'name' => $_POST['name'],
                    'address' => $_POST['address'],
                    'phone' => $_POST['phone'],
                    'email' => $_POST['email'],
                    'manager_name' => $_POST['manager_name']
                ];
                
                $departmentModel->create($data);
                $success = 'Tạo đơn vị thành công!';
                $action = 'list';
                break;
                
            case 'update':
                $id = $_POST['id'];
                $data = [
                    'code' => $_POST['code'],
                    'name' => $_POST['name'],
                    'address' => $_POST['address'],
                    'phone' => $_POST['phone'],
                    'email' => $_POST['email'],
                    'manager_name' => $_POST['manager_name'],
                    'status' => $_POST['status']
                ];
                
                $departmentModel->update($id, $data);
                $success = 'Cập nhật đơn vị thành công!';
                $action = 'list';
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $departmentModel->delete($id);
                $success = 'Xóa đơn vị thành công!';
                $action = 'list';
                break;
                
            case 'transfer':
                $fromId = $_POST['from_department_id'];
                $toId = $_POST['to_department_id'];
                $transferType = $_POST['transfer_type'];
                
                if ($transferType === 'users' || $transferType === 'both') {
                    $departmentModel->transferUsers($fromId, $toId);
                }
                
                if ($transferType === 'equipments' || $transferType === 'both') {
                    $departmentModel->transferEquipments($fromId, $toId);
                }
                
                $success = 'Chuyển đổi thành công!';
                $action = 'list';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy danh sách departments
$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$departments = $departmentModel->getAll($filters);

// Lấy department để edit
$editDepartment = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editDepartment = $departmentModel->getById($_GET['id']);
    if (!$editDepartment) {
        $error = 'Không tìm thấy đơn vị!';
        $action = 'list';
    }
}

// Lấy department để view details
$viewDepartment = null;
$departmentUsers = [];
$departmentEquipments = [];
if ($action === 'view' && isset($_GET['id'])) {
    $viewDepartment = $departmentModel->getById($_GET['id']);
    if ($viewDepartment) {
        $departmentUsers = $departmentModel->getUsers($_GET['id']);
        $departmentEquipments = $departmentModel->getEquipments($_GET['id']);
    } else {
        $error = 'Không tìm thấy đơn vị!';
        $action = 'list';
    }
}

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Quản trị', 'url' => url('admin/')],
    ['title' => 'Đơn vị', 'url' => '']
];

if ($action === 'view' && $viewDepartment) {
    $breadcrumbs[] = ['title' => $viewDepartment['name'], 'url' => ''];
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-building me-2"></i>
            <?php if ($action === 'view' && $viewDepartment): ?>
                Chi tiết đơn vị: <?= e($viewDepartment['name']) ?>
            <?php else: ?>
                Quản lý đơn vị
            <?php endif; ?>
        </h2>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($action === 'list'): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">
                <i class="fas fa-plus me-2"></i>Thêm đơn vị
            </button>
        <?php elseif (in_array($action, ['edit', 'view'])): ?>
            <a href="<?= url('admin/departments.php') ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        <?php endif; ?>
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

<?php if ($action === 'list'): ?>
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Tạm khóa</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm tên, mã, người quản lý..." 
                               value="<?= e($filters['search']) ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <a href="<?= url('admin/departments.php') ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-redo me-1"></i>Làm mới
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Departments Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($departments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Chưa có đơn vị nào</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">
                        <i class="fas fa-plus me-2"></i>Thêm đơn vị đầu tiên
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã đơn vị</th>
                                <th>Tên đơn vị</th>
                                <th>Người quản lý</th>
                                <th>Liên hệ</th>
                                <th>Số người</th>
                                <th>Số thiết bị</th>
                                <th>Trạng thái</th>
                                <th class="text-center" width="150">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><strong><?= e($dept['code']) ?></strong></td>
                                    <td>
                                        <a href="<?= url('admin/departments.php?action=view&id=' . $dept['id']) ?>" 
                                           class="text-decoration-none">
                                            <?= e($dept['name']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($dept['manager_name'] ?: 'Chưa phân công') ?></td>
                                    <td>
                                        <?php if ($dept['phone']): ?>
                                            <i class="fas fa-phone me-1"></i><?= e($dept['phone']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($dept['email']): ?>
                                            <i class="fas fa-envelope me-1"></i><?= e($dept['email']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= number_format($dept['user_count']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info rounded-pill">
                                            <?= number_format($dept['equipment_count']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($dept['status'] === 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tạm khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= url('admin/departments.php?action=view&id=' . $dept['id']) ?>" 
                                               class="btn btn-sm btn-outline-info" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= url('admin/departments.php?action=edit&id=' . $dept['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= e($dept['name']) ?>', <?= $dept['user_count'] ?>, <?= $dept['equipment_count'] ?>)" 
                                                    title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'edit' && $editDepartment): ?>
    <!-- Edit Department Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Chỉnh sửa đơn vị: <?= e($editDepartment['name']) ?></h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $editDepartment['id'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mã đơn vị <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required 
                               value="<?= e($editDepartment['code']) ?>" style="text-transform: uppercase;">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tên đơn vị <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required 
                               value="<?= e($editDepartment['name']) ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Người quản lý</label>
                        <input type="text" name="manager_name" class="form-control" 
                               value="<?= e($editDepartment['manager_name']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?= e($editDepartment['phone']) ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= e($editDepartment['email']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?= $editDepartment['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="inactive" <?= $editDepartment['status'] === 'inactive' ? 'selected' : '' ?>>Tạm khóa</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <textarea name="address" class="form-control" rows="3"><?= e($editDepartment['address']) ?></textarea>
                </div>
                
                <div class="text-end">
                    <a href="<?= url('admin/departments.php') ?>" class="btn btn-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $viewDepartment): ?>
    <!-- Department Details -->
    <div class="row">
        <!-- Department Info -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thông tin đơn vị</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Mã đơn vị:</strong></td>
                            <td><?= e($viewDepartment['code']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tên đơn vị:</strong></td>
                            <td><?= e($viewDepartment['name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Người quản lý:</strong></td>
                            <td><?= e($viewDepartment['manager_name'] ?: 'Chưa phân công') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Điện thoại:</strong></td>
                            <td><?= e($viewDepartment['phone'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?= e($viewDepartment['email'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Địa chỉ:</strong></td>
                            <td><?= e($viewDepartment['address'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Trạng thái:</strong></td>
                            <td>
                                <?php if ($viewDepartment['status'] === 'active'): ?>
                                    <span class="badge bg-success">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Tạm khóa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Ngày tạo:</strong></td>
                            <td><?= format_datetime($viewDepartment['created_at']) ?></td>
                        </tr>
                    </table>
                    
                    <div class="d-grid gap-2">
                        <a href="<?= url('admin/departments.php?action=edit&id=' . $viewDepartment['id']) ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Chỉnh sửa
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Người dùng (<?= count($departmentUsers) ?>)</h5>
                    <a href="<?= url('admin/users.php?department_id=' . $viewDepartment['id']) ?>" 
                       class="btn btn-sm btn-outline-primary">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($departmentUsers)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Chưa có người dùng</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($departmentUsers, 0, 5) as $user): ?>
                                <div class="list-group-item border-0 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= e($user['full_name']) ?></h6>
                                            <small class="text-muted"><?= e($user['role_name']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($departmentUsers) > 5): ?>
                                <div class="list-group-item border-0 text-center">
                                    <small class="text-muted">và <?= count($departmentUsers) - 5 ?> người khác...</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Equipments -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Thiết bị (<?= count($departmentEquipments) ?>)</h5>
                    <a href="<?= url('admin/equipments.php?department_id=' . $viewDepartment['id']) ?>" 
                       class="btn btn-sm btn-outline-primary">
                        Xem tất cả
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($departmentEquipments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-desktop fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Chưa có thiết bị</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($departmentEquipments, 0, 5) as $equipment): ?>
                                <div class="list-group-item border-0 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= e($equipment['name']) ?></h6>
                                            <small class="text-muted"><?= e($equipment['code']) ?> - <?= e($equipment['type_name']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($departmentEquipments) > 5): ?>
                                <div class="list-group-item border-0 text-center">
                                    <small class="text-muted">và <?= count($departmentEquipments) - 5 ?> thiết bị khác...</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Create Department Modal -->
<div class="modal fade" id="createDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm đơn vị mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã đơn vị <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required style="text-transform: uppercase;" 
                                   placeholder="Ví dụ: IT, HR, ACC">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên đơn vị <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="Ví dụ: Phòng Công nghệ thông tin">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Người quản lý</label>
                            <input type="text" name="manager_name" class="form-control" 
                                   placeholder="Họ tên người quản lý">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="Số điện thoại liên hệ">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="Email liên hệ">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Địa chỉ</label>
                        <textarea name="address" class="form-control" rows="3" 
                                  placeholder="Địa chỉ văn phòng"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Tạo đơn vị
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa đơn vị <strong id="deleteDepartmentName"></strong>?</p>
                <div id="deleteWarning" class="alert alert-warning" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <span id="warningText"></span>
                </div>
                <p class="text-danger">
                    <i class="fas fa-warning me-1"></i>
                    Hành động này không thể hoàn tác!
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteDepartmentId">
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-2"></i>Xóa
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chuyển đổi trước khi xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="transfer">
                <input type="hidden" name="from_department_id" id="transferFromId">
                
                <div class="modal-body">
                    <p>Đơn vị <strong id="transferDepartmentName"></strong> có:</p>
                    <ul id="transferItems"></ul>
                    
                    <div class="mb-3">
                        <label class="form-label">Chuyển đến đơn vị:</label>
                        <select name="to_department_id" class="form-select" required>
                            <option value="">Chọn đơn vị đích</option>
                            <?php foreach ($departments as $dept): ?>
                                <?php if ($dept['status'] === 'active'): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?> (<?= e($dept['code']) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Chuyển:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="transfer_type" value="users" id="transferUsers">
                            <label class="form-check-label" for="transferUsers">Chỉ người dùng</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="transfer_type" value="equipments" id="transferEquipments">
                            <label class="form-check-label" for="transferEquipments">Chỉ thiết bị</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="transfer_type" value="both" id="transferBoth" checked>
                            <label class="form-check-label" for="transferBoth">Cả hai</label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-exchange-alt me-2"></i>Chuyển đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom JS
$custom_js = "
<script>
    function deleteDepartment(id, name, userCount, equipmentCount) {
        document.getElementById('deleteDepartmentId').value = id;
        document.getElementById('deleteDepartmentName').textContent = name;
        
        const warningDiv = document.getElementById('deleteWarning');
        const warningText = document.getElementById('warningText');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        
        if (userCount > 0 || equipmentCount > 0) {
            let warning = 'Đơn vị này có ';
            let items = [];
            if (userCount > 0) items.push(userCount + ' người dùng');
            if (equipmentCount > 0) items.push(equipmentCount + ' thiết bị');
            warning += items.join(' và ') + '. Bạn cần chuyển chúng sang đơn vị khác trước.';
            
            warningText.textContent = warning;
            warningDiv.style.display = 'block';
            confirmBtn.disabled = true;
            
            // Show transfer option
            confirmBtn.textContent = 'Chuyển đổi';
            confirmBtn.onclick = function() {
                showTransferModal(id, name, userCount, equipmentCount);
            };
        } else {
            warningDiv.style.display = 'none';
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Xóa';
            confirmBtn.onclick = null;
        }
        
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    
    function showTransferModal(id, name, userCount, equipmentCount) {
        document.getElementById('transferFromId').value = id;
        document.getElementById('transferDepartmentName').textContent = name;
        
        const itemsList = document.getElementById('transferItems');
        itemsList.innerHTML = '';
        if (userCount > 0) {
            itemsList.innerHTML += '<li>' + userCount + ' người dùng</li>';
        }
        if (equipmentCount > 0) {
            itemsList.innerHTML += '<li>' + equipmentCount + ' thiết bị</li>';
        }
        
        // Hide delete modal and show transfer modal
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        new bootstrap.Modal(document.getElementById('transferModal')).show();
    }
    
    // Auto-focus on modal show
    document.getElementById('createDepartmentModal').addEventListener('shown.bs.modal', function() {
        this.querySelector('input[name=\"code\"]').focus();
    });
    
    // Auto uppercase for code input
    document.addEventListener('input', function(e) {
        if (e.target.name === 'code') {
            e.target.value = e.target.value.toUpperCase();
        }
    });
</script>
";

include '../layouts/app.php';
?>
