<?php
require_once 'config/config.php';

// Kiểm tra đã đăng nhập
if (is_logged_in()) {
    redirect('dashboard.php');
}

$title = 'Đăng nhập';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        try {
            $userModel = new User();
            $user = $userModel->login($username, $password);
            
            if ($user) {
                // Lưu thông tin user vào session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role_name'];
                
                // Log hoạt động
                log_activity('Đăng nhập hệ thống');
                
                // Redirect theo role
                switch ($user['role_name']) {
                    case 'admin':
                        redirect('dashboard.php', 'Chào mừng quản trị viên!', 'success');
                        break;
                    case 'requester':
                        redirect('dashboard.php', 'Đăng nhập thành công!', 'success');
                        break;
                    case 'logistics':
                        redirect('logistics/handover.php', 'Đăng nhập thành công!', 'success');
                        break;
                    case 'clerk':
                        redirect('clerk/receive.php', 'Đăng nhập thành công!', 'success');
                        break;
                    case 'technician':
                        redirect('repair/in-progress.php', 'Đăng nhập thành công!', 'success');
                        break;
                    default:
                        redirect('dashboard.php', 'Đăng nhập thành công!', 'success');
                }
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
            }
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            display: flex;
        }
        
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="0.5" fill="white" opacity="0.1"/><circle cx="40" cy="80" r="1.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .login-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .brand-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .brand-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .brand-description {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .form-subtitle {
            color: #64748b;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #f9fafb;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        .input-group-text {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-right: none;
            color: #64748b;
            border-radius: 12px 0 0 12px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
            background: white;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }
        
        .system-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .info-item i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
                min-height: auto;
            }
            
            .login-left {
                padding: 2rem;
                min-height: 300px;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .brand-title {
                font-size: 1.5rem;
            }
            
            .brand-description {
                font-size: 1rem;
            }
        }
        
        /* Animation */
        .login-container {
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-control, .btn-login {
            animation: slideInRight 0.6s ease-out;
            animation-fill-mode: both;
        }
        
        .form-control:nth-child(1) { animation-delay: 0.1s; }
        .form-control:nth-child(2) { animation-delay: 0.2s; }
        .btn-login { animation-delay: 0.3s; }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel -->
        <div class="login-left">
            <div>
                <div class="brand-logo">
                    <i class="fas fa-tools"></i>
                </div>
                <h1 class="brand-title"><?= APP_NAME ?></h1>
                <p class="brand-description">
                    Hệ thống quản lý toàn trình sửa chữa thiết bị hiện đại, 
                    giúp theo dõi và quản lý hiệu quả từ khâu đề xuất đến hoàn tất.
                </p>
                
                <div class="system-info">
                    <div class="info-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Quản lý workflow 6 bước rõ ràng</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-users"></i>
                        <span>Phân quyền 5 vai trò chuyên biệt</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard theo dõi real-time</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Giao diện responsive, thân thiện</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="login-right">
            <form class="login-form" method="POST">
                <h2 class="form-title">Đăng nhập</h2>
                <p class="form-subtitle">Vui lòng nhập thông tin để truy cập hệ thống</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label" for="username">
                        <i class="fas fa-user me-1"></i>
                        Tên đăng nhập
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Nhập tên đăng nhập"
                               value="<?= e($_POST['username'] ?? '') ?>"
                               required
                               autocomplete="username"
                               autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock me-1"></i>
                        Mật khẩu
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Nhập mật khẩu"
                               required
                               autocomplete="current-password">
                        <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Ghi nhớ đăng nhập
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Đăng nhập
                </button>
                
                <?= csrf_field() ?>
            </form>
            
            <!-- Demo accounts info -->
            <div class="mt-4 p-3 bg-light rounded">
                <h6 class="mb-2"><i class="fas fa-info-circle me-1"></i> Tài khoản demo:</h6>
                <small class="text-muted">
                    <strong>Admin:</strong> admin / admin123<br>
                    <strong>Người đề xuất:</strong> user1 / user123<br>
                    <strong>Giao liên:</strong> logistics1 / user123<br>
                    <strong>Văn thư:</strong> clerk1 / user123<br>
                    <strong>Kỹ thuật:</strong> tech1 / user123
                </small>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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
        
        // Form validation
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Vui lòng nhập đầy đủ thông tin');
                return;
            }
            
            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang đăng nhập...';
        });
        
        // Auto focus on first empty field
        window.addEventListener('load', function() {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            if (!username.value) {
                username.focus();
            } else if (!password.value) {
                password.focus();
            }
        });
        
        // Enter key navigation
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>
