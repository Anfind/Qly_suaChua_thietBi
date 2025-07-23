<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$userModel = new User();
$title = 'Quản lý người dùng';

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
                    'username' => trim($_POST['username']),
                    'password' => $_POST['password'],
                    'full_name' => trim($_POST['full_name']),
                    'email' => trim($_POST['email']),
                    'phone' => trim($_POST['phone']),
                    'department_id' => $_POST['department_id'] ?: null,
                    'role_id' => $_POST['role_id']
                ];
                
                $userModel->create($data);
                $success = 'Tạo người dùng thành công!';
                $action = 'list';
                break;
                
            case 'update':
                $id = $_POST['id'];
                $data = [
                    'username' => trim($_POST['username']),
                    'full_name' => trim($_POST['full_name']),
                    'email' => trim($_POST['email']),
                    'phone' => trim($_POST['phone']),
                    'department_id' => $_POST['department_id'] ?: null,
                    'role_id' => $_POST['role_id'],
                    'status' => $_POST['status']
                ];
                
                if (!empty($_POST['password'])) {
                    $data['password'] = $_POST['password'];
                }
                
                $userModel->update($id, $data);
                $success = 'Cập nhật người dùng thành công!';
                $action = 'list';
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $userModel->delete($id);
                $success = 'Xóa người dùng thành công!';
                $action = 'list';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy dữ liệu cho form
$db = Database::getInstance();
$roles = $db->fetchAll("SELECT * FROM roles ORDER BY display_name");
$departments = $db->fetchAll("SELECT * FROM departments WHERE status = 'active' ORDER BY name");

// Lấy danh sách users
$filters = [
    'role_id' => $_GET['role_id'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$users = $userModel->getAll($filters);

// Lấy user để edit
$editUser = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editUser = $userModel->getById($_GET['id']);
    if (!$editUser) {
        $error = 'Không tìm thấy người dùng!';
        $action = 'list';
    }
}

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Quản trị', 'url' => url('admin/')],
    ['title' => 'Người dùng', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-users me-2"></i>
            Quản lý người dùng
        </h2>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($action === 'list'): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fas fa-plus me-2"></i>Thêm người dùng
            </button>
        <?php elseif ($action === 'edit'): ?>
            <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary">
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
                    <select name="role_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả vai trò</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= $filters['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                <?= e($role['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="department_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả đơn vị</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $filters['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm tên, username, email..." 
                               value="<?= e($filters['search']) ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-redo me-1"></i>Làm mới
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Không có người dùng nào</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i>Thêm người dùng đầu tiên
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Đơn vị</th>
                                <th>Trạng thái</th>
                                <th>Đăng nhập cuối</th>
                                <th class="text-center" width="120">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($user['username']) ?></strong>
                                        <?php if ($user['id'] == current_user()['id']): ?>
                                            <span class="badge bg-info ms-1">Bạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($user['full_name']) ?></td>
                                    <td><?= e($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= e($user['role_name']) ?></span>
                                    </td>
                                    <td><?= e($user['department_name'] ?? 'Chưa phân công') ?></td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tạm khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $user['last_login'] ? format_datetime($user['last_login']) : 'Chưa đăng nhập' ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= url('admin/users.php?action=edit&id=' . $user['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != current_user()['id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteUser(<?= $user['id'] ?>, '<?= e($user['username']) ?>')" title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
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

<?php elseif ($action === 'edit' && $editUser): ?>
    <!-- Edit User Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Chỉnh sửa người dùng: <?= e($editUser['full_name']) ?></h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required 
                               value="<?= e($editUser['username']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Để trống nếu không đổi">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required 
                               value="<?= e($editUser['full_name']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= e($editUser['email']) ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?= e($editUser['phone']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= $editUser['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                    <?= e($role['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Đơn vị</label>
                        <select name="department_id" class="form-select">
                            <option value="">Chọn đơn vị</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= $editUser['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Tạm khóa</option>
                        </select>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm người dùng mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <option value="">Chọn vai trò</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= e($role['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Đơn vị</label>
                            <select name="department_id" class="form-select">
                                <option value="">Chọn đơn vị</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Tạo người dùng
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
                <p>Bạn có chắc chắn muốn xóa người dùng <strong id="deleteUsername"></strong>?</p>
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
                    <input type="hidden" name="id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom JS
$custom_js = "
<script>
    function deleteUser(id, username) {
        document.getElementById('deleteUserId').value = id;
        document.getElementById('deleteUsername').textContent = username;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    
    // Auto-focus on modal show
    document.getElementById('createUserModal').addEventListener('shown.bs.modal', function() {
        this.querySelector('input[name=\"username\"]').focus();
    });
</script>
";

include '../layouts/app.php';
?>
