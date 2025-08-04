<?php
require_once 'config/config.php';
require_login();

$user = current_user();
$error = '';
$success = '';

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password)) {
            throw new Exception('Vui lòng nhập mật khẩu hiện tại');
        }
        
        if (empty($new_password)) {
            throw new Exception('Vui lòng nhập mật khẩu mới');
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception('Mật khẩu mới phải có ít nhất 6 ký tự');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('Xác nhận mật khẩu không khớp');
        }
        
        if ($current_password === $new_password) {
            throw new Exception('Mật khẩu mới phải khác mật khẩu hiện tại');
        }
        
        // Kiểm tra mật khẩu hiện tại
        $db = Database::getInstance();
        $userFromDb = $db->fetch("SELECT password FROM users WHERE id = ?", [$user['id']]);
        
        if (!$userFromDb) {
            throw new Exception('Không tìm thấy thông tin người dùng');
        }
        
        $password_valid = false;
        // Kiểm tra password (hỗ trợ cả plain text và hash)
        if (strpos($userFromDb['password'], '$') !== 0) {
            // Plain text password
            $password_valid = ($current_password === $userFromDb['password']);
        } else {
            // Hashed password
            $password_valid = password_verify($current_password, $userFromDb['password']);
        }
        
        if (!$password_valid) {
            throw new Exception('Mật khẩu hiện tại không đúng');
        }
        
        // Cập nhật mật khẩu mới (hash)
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $result = $db->update(
            'users', 
            ['password' => $hashed_new_password, 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$user['id']]
        );
        
        if ($result) {
            $success = 'Đổi mật khẩu thành công!';
            
            // Log hoạt động
            log_activity('Đổi mật khẩu', [
                'user_id' => $user['id'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            throw new Exception('Có lỗi xảy ra khi cập nhật mật khẩu');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$title = 'Đổi mật khẩu';
$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Đổi mật khẩu', 'url' => '']
];

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-key me-2"></i>
                    Đổi mật khẩu
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
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Tài khoản:</strong> <?= e($user['full_name']) ?> (<?= e($user['username']) ?>)
                </div>
                
                <form method="POST" id="changePasswordForm" novalidate>
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label" for="current_password">
                            <i class="fas fa-lock me-1"></i>
                            Mật khẩu hiện tại <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="current_password" 
                                   name="current_password" 
                                   required
                                   autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye" id="current_password_icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="new_password">
                            <i class="fas fa-key me-1"></i>
                            Mật khẩu mới <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password" 
                                   required
                                   minlength="6"
                                   autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye" id="new_password_icon"></i>
                            </button>
                        </div>
                        <div class="form-text">Mật khẩu phải có ít nhất 6 ký tự</div>
                        <div class="password-strength mt-2" id="password_strength">
                            <div class="strength-bar" id="strength_bar"></div>
                        </div>
                        <div class="form-text" id="strength_text"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">
                            <i class="fas fa-check me-1"></i>
                            Xác nhận mật khẩu mới <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password_icon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback" id="confirm_feedback">
                            Xác nhận mật khẩu không khớp
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i>
                            Quay lại
                        </a>
                        <button type="submit" class="btn btn-primary" id="submit_btn">
                            <i class="fas fa-save me-1"></i>
                            Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.password-strength {
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-weak {
    background: #ef4444;
}

.strength-fair {
    background: #f59e0b;
}

.strength-good {
    background: #06b6d4;
}

.strength-strong {
    background: #10b981;
}
</style>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const toggleIcon = document.getElementById(fieldId + '_icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Password strength checker
function checkPasswordStrength(password) {
    let score = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) score += 25;
    else if (password.length >= 6) score += 10;
    else feedback.push('Ít nhất 6 ký tự');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) {
        score += 25;
    } else {
        feedback.push('Chữ hoa');
    }
    
    // Lowercase check
    if (/[a-z]/.test(password)) {
        score += 25;
    } else {
        feedback.push('Chữ thường');
    }
    
    // Number check
    if (/[0-9]/.test(password)) {
        score += 25;
    } else {
        feedback.push('Số');
    }
    
    // Special character check
    if (/[^A-Za-z0-9]/.test(password)) {
        score += 10;
    }
    
    return { score, feedback };
}

// Update password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strength_bar');
    const strengthText = document.getElementById('strength_text');
    
    if (password.length === 0) {
        strengthBar.style.width = '0%';
        strengthBar.className = 'strength-bar';
        strengthText.textContent = '';
        return;
    }
    
    const { score, feedback } = checkPasswordStrength(password);
    
    strengthBar.style.width = Math.min(score, 100) + '%';
    
    if (score < 25) {
        strengthBar.className = 'strength-bar strength-weak';
        strengthText.textContent = 'Yếu - Cần: ' + feedback.join(', ');
        strengthText.className = 'form-text text-danger';
    } else if (score < 50) {
        strengthBar.className = 'strength-bar strength-fair';
        strengthText.textContent = 'Trung bình - Nên thêm: ' + feedback.join(', ');
        strengthText.className = 'form-text text-warning';
    } else if (score < 75) {
        strengthBar.className = 'strength-bar strength-good';
        strengthText.textContent = 'Tốt';
        strengthText.className = 'form-text text-info';
    } else {
        strengthBar.className = 'strength-bar strength-strong';
        strengthText.textContent = 'Mạnh';
        strengthText.className = 'form-text text-success';
    }
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const feedback = document.getElementById('confirm_feedback');
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.classList.add('is-invalid');
        feedback.style.display = 'block';
    } else {
        this.classList.remove('is-invalid');
        feedback.style.display = 'none';
    }
});

// Form validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const currentPassword = document.getElementById('current_password').value;
    
    if (!currentPassword) {
        e.preventDefault();
        alert('Vui lòng nhập mật khẩu hiện tại');
        return;
    }
    
    if (!newPassword) {
        e.preventDefault();
        alert('Vui lòng nhập mật khẩu mới');
        return;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Mật khẩu mới phải có ít nhất 6 ký tự');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Xác nhận mật khẩu không khớp');
        return;
    }
    
    if (currentPassword === newPassword) {
        e.preventDefault();
        alert('Mật khẩu mới phải khác mật khẩu hiện tại');
        return;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submit_btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
});

// Auto focus on first field
document.getElementById('current_password').focus();
</script>

<?php
$content = ob_get_clean();

include 'layouts/app.php';

