<?php
require_once 'config/config.php';
require_login();

$user = current_user();
$error = '';
$success = '';

// Xử lý cập nhật profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        if (empty($full_name)) {
            throw new Exception('Vui lòng nhập họ tên');
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email không hợp lệ');
        }
        
        if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
            throw new Exception('Số điện thoại không hợp lệ');
        }
        
        $db = Database::getInstance();
        
        // Kiểm tra email đã tồn tại (trừ user hiện tại)
        if (!empty($email) && $email !== $user['email']) {
            $existingUser = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ? AND status != 'deleted'", [$email, $user['id']]);
            if ($existingUser) {
                throw new Exception('Email đã được sử dụng bởi tài khoản khác');
            }
        }
        
        // Cập nhật thông tin
        $updateData = [
            'full_name' => $full_name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $db->update('users', $updateData, 'id = ?', [$user['id']]);
        
        if ($result) {
            $success = 'Cập nhật thông tin thành công!';
            
            // Cập nhật session
            $_SESSION['full_name'] = $full_name;
            
            // Reload user data
            $user = current_user();
            
            // Log hoạt động
            log_activity('Cập nhật hồ sơ cá nhân', [
                'user_id' => $user['id'],
                'updated_fields' => array_keys($updateData)
            ]);
        } else {
            throw new Exception('Có lỗi xảy ra khi cập nhật thông tin');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$title = 'Hồ sơ cá nhân';
$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Hồ sơ cá nhân', 'url' => '']
];

include 'layouts/app.php';

function content() {
    global $user, $error, $success;
?>

<div class="row">
    <div class="col-md-4">
        <!-- User Info Card -->
        <div class="card">
            <div class="card-body text-center">
                <div class="user-avatar-large mb-3">
                    <?php if ($user && $user['avatar']): ?>
                        <img src="<?= upload_url('avatars/' . $user['avatar']) ?>" alt="Avatar" style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 4px solid #f8f9fa;">
                    <?php else: ?>
                        <div style="width: 120px; height: 120px; background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: bold; margin: 0 auto; border: 4px solid #f8f9fa;">
                            <?= $user ? strtoupper(substr($user['full_name'], 0, 1)) : 'U' ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h5 class="card-title"><?= e($user['full_name']) ?></h5>
                <p class="text-muted"><?= e($user['role_display_name']) ?></p>
                
                <?php if ($user['department_name']): ?>
                    <div class="badge bg-primary mb-2">
                        <i class="fas fa-building me-1"></i>
                        <?= e($user['department_name']) ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-user-tag me-1"></i>
                        <?= e($user['username']) ?>
                    </small>
                </div>
                
                <?php if ($user['last_login']): ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Đăng nhập lần cuối: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Tùy chọn tài khoản
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= url('change-password.php') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-key me-2"></i>
                        Đổi mật khẩu
                    </a>
                    <a href="<?= url('dashboard.php') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Quay lại Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Profile Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Chỉnh sửa thông tin cá nhân
                </h5>
            </div>
            <div class="card-body">
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
                
                <form method="POST" novalidate>
                    <?= csrf_field() ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="username">
                                <i class="fas fa-user me-1"></i>
                                Tên đăng nhập
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   value="<?= e($user['username']) ?>" 
                                   disabled
                                   readonly>
                            <div class="form-text">Tên đăng nhập không thể thay đổi</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="role">
                                <i class="fas fa-user-tag me-1"></i>
                                Vai trò
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role" 
                                   value="<?= e($user['role_display_name']) ?>" 
                                   disabled
                                   readonly>
                            <div class="form-text">Vai trò do quản trị viên phân quyền</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="full_name">
                            <i class="fas fa-id-card me-1"></i>
                            Họ và tên <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="full_name" 
                               name="full_name" 
                               value="<?= e($user['full_name']) ?>" 
                               required
                               maxlength="100">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="email">
                                <i class="fas fa-envelope me-1"></i>
                                Email
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= e($user['email'] ?? '') ?>" 
                                   maxlength="100">
                            <div class="form-text">Email để nhận thông báo (không bắt buộc)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="phone">
                                <i class="fas fa-phone me-1"></i>
                                Số điện thoại
                            </label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= e($user['phone'] ?? '') ?>" 
                                   maxlength="15"
                                   placeholder="VD: 0901234567">
                            <div class="form-text">Số điện thoại liên hệ (không bắt buộc)</div>
                        </div>
                    </div>
                    
                    <?php if ($user['department_name']): ?>
                        <div class="mb-3">
                            <label class="form-label" for="department">
                                <i class="fas fa-building me-1"></i>
                                Đơn vị
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="department" 
                                   value="<?= e($user['department_name']) ?>" 
                                   disabled
                                   readonly>
                            <div class="form-text">Đơn vị công tác do quản trị viên phân quyền</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Lưu ý:</strong> Các thông tin như tên đăng nhập, vai trò và đơn vị do quản trị viên quản lý. 
                        Nếu cần thay đổi, vui lòng liên hệ quản trị viên hệ thống.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i>
                            Quay lại
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Cập nhật thông tin
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Thông tin tài khoản
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2">
                            <strong>Tạo tài khoản:</strong><br>
                            <small class="text-muted">
                                <?= $user['created_at'] ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'Không xác định' ?>
                            </small>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2">
                            <strong>Cập nhật lần cuối:</strong><br>
                            <small class="text-muted">
                                <?= $user['updated_at'] ? date('d/m/Y H:i', strtotime($user['updated_at'])) : 'Chưa cập nhật' ?>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const fullName = document.getElementById('full_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    if (!fullName) {
        e.preventDefault();
        alert('Vui lòng nhập họ và tên');
        document.getElementById('full_name').focus();
        return;
    }
    
    if (email && !isValidEmail(email)) {
        e.preventDefault();
        alert('Email không hợp lệ');
        document.getElementById('email').focus();
        return;
    }
    
    if (phone && !isValidPhone(phone)) {
        e.preventDefault();
        alert('Số điện thoại không hợp lệ (10-15 số)');
        document.getElementById('phone').focus();
        return;
    }
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function isValidPhone(phone) {
    const re = /^[0-9+\-\s()]{10,15}$/;
    return re.test(phone);
}

// Auto format phone number
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, ''); // Remove non-digits
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    this.value = value;
});
</script>

<?php } ?>
