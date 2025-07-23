<?php
// File này để test trên web server thực tế
require_once 'config/config.php';

$step = $_GET['step'] ?? 'start';
$debug = [];

switch($step) {
    case 'start':
        // Reset session
        $_SESSION = array();
        session_regenerate_id(true);
        
        $debug['message'] = 'Session đã được reset, click Next để bắt đầu test';
        $debug['session'] = $_SESSION;
        $debug['next_url'] = '?step=show_form';
        break;
        
    case 'show_form':
        // Hiển thị form với CSRF
        $csrf_html = csrf_field();
        $debug['message'] = 'Form login với CSRF token đã được tạo';
        $debug['csrf_html'] = $csrf_html;
        $debug['session'] = $_SESSION;
        $debug['form_action'] = '?step=process_login';
        break;
        
    case 'process_login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $debug['post_data'] = $_POST;
            $debug['session_before'] = $_SESSION;
            
            // Áp dụng logic từ index.php
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Lấy CSRF token trước khi clear session
            $submitted_csrf = $_POST['csrf_token'] ?? '';
            $session_csrf = $_SESSION['csrf_token'] ?? '';
            
            $debug['submitted_csrf'] = $submitted_csrf;
            $debug['session_csrf'] = $session_csrf;
            
            if (empty($username) || empty($password)) {
                $debug['error'] = 'Vui lòng nhập đầy đủ thông tin';
            } else {
                try {
                    // CLEAR TOÀN BỘ SESSION TRƯỚC KHI LOGIN
                    $_SESSION = array();
                    session_regenerate_id(true);
                    
                    $debug['session_after_clear'] = $_SESSION;
                    
                    // Kiểm tra CSRF token (chỉ khi có cả 2)
                    if (!empty($session_csrf) && !empty($submitted_csrf)) {
                        if (!hash_equals($session_csrf, $submitted_csrf)) {
                            $debug['error'] = 'CSRF token không hợp lệ';
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            $debug['csrf_result'] = 'CSRF token hợp lệ';
                            $process_login = true;
                        }
                    } else {
                        $debug['csrf_result'] = 'Lần đầu login hoặc không có CSRF, cho phép login';
                        $process_login = true;
                    }
                    
                    if (empty($debug['error']) && isset($process_login)) {
                        $userModel = new User();
                        $user = $userModel->login($username, $password);
                        
                        if ($user) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role_name'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['login_time'] = time();
                            
                            $debug['success'] = 'Login thành công!';
                            $debug['user_data'] = $user;
                            $debug['final_session'] = $_SESSION;
                        } else {
                            $debug['error'] = 'Tên đăng nhập hoặc mật khẩu không đúng';
                        }
                    }
                    
                } catch (Exception $e) {
                    $debug['error'] = 'Có lỗi xảy ra: ' . $e->getMessage();
                    $debug['exception'] = $e->getTraceAsString();
                }
            }
        } else {
            $debug['error'] = 'Không có POST data';
        }
        break;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Login Web</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .debug { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .button { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; margin: 5px; }
        form { margin: 20px 0; }
        input[type="text"], input[type="password"] { padding: 8px; margin: 5px 0; display: block; }
        input[type="submit"] { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 3px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Debug Login Web Interface</h1>
    
    <p><strong>Current Step:</strong> <?= $step ?></p>
    
    <?php if ($step === 'start'): ?>
        <div class="debug">
            <h3>Step 1: Reset Session</h3>
            <p class="success"><?= $debug['message'] ?></p>
            <p><strong>Session State:</strong></p>
            <pre><?= print_r($debug['session'], true) ?></pre>
            <a href="<?= $debug['next_url'] ?>" class="button">Next: Show Form</a>
        </div>
    <?php endif; ?>
    
    <?php if ($step === 'show_form'): ?>
        <div class="debug">
            <h3>Step 2: Show Login Form</h3>
            <p class="success"><?= $debug['message'] ?></p>
            <p><strong>CSRF Field HTML:</strong></p>
            <pre><?= htmlspecialchars($debug['csrf_html']) ?></pre>
            <p><strong>Session State:</strong></p>
            <pre><?= print_r($debug['session'], true) ?></pre>
        </div>
        
        <h3>Login Form:</h3>
        <form method="POST" action="<?= $debug['form_action'] ?>">
            <?= $debug['csrf_html'] ?>
            <input type="text" name="username" placeholder="Username" value="admin" required>
            <input type="password" name="password" placeholder="Password" value="admin123" required>
            <input type="submit" value="Submit Login">
        </form>
    <?php endif; ?>
    
    <?php if ($step === 'process_login'): ?>
        <div class="debug">
            <h3>Step 3: Process Login</h3>
            
            <?php if (isset($debug['error'])): ?>
                <p class="error">ERROR: <?= $debug['error'] ?></p>
            <?php endif; ?>
            
            <?php if (isset($debug['success'])): ?>
                <p class="success">SUCCESS: <?= $debug['success'] ?></p>
            <?php endif; ?>
            
            <h4>POST Data:</h4>
            <pre><?= print_r($debug['post_data'] ?? [], true) ?></pre>
            
            <h4>Session Before Clear:</h4>
            <pre><?= print_r($debug['session_before'] ?? [], true) ?></pre>
            
            <h4>CSRF Tokens:</h4>
            <p><strong>Submitted:</strong> <?= $debug['submitted_csrf'] ?? 'N/A' ?></p>
            <p><strong>Session:</strong> <?= $debug['session_csrf'] ?? 'N/A' ?></p>
            
            <h4>Session After Clear:</h4>
            <pre><?= print_r($debug['session_after_clear'] ?? [], true) ?></pre>
            
            <?php if (isset($debug['csrf_result'])): ?>
                <h4>CSRF Result:</h4>
                <p class="success"><?= $debug['csrf_result'] ?></p>
            <?php endif; ?>
            
            <?php if (isset($debug['user_data'])): ?>
                <h4>User Data:</h4>
                <pre><?= print_r($debug['user_data'], true) ?></pre>
            <?php endif; ?>
            
            <?php if (isset($debug['final_session'])): ?>
                <h4>Final Session:</h4>
                <pre><?= print_r($debug['final_session'], true) ?></pre>
            <?php endif; ?>
            
            <?php if (isset($debug['exception'])): ?>
                <h4>Exception Stack Trace:</h4>
                <pre><?= $debug['exception'] ?></pre>
            <?php endif; ?>
        </div>
        
        <a href="?step=start" class="button">Test Again</a>
        <a href="index.php" class="button">Try Real Login</a>
    <?php endif; ?>
    
    <hr>
    <p><em>Current time: <?= date('Y-m-d H:i:s') ?></em></p>
    <p><em>Session ID: <?= session_id() ?></em></p>
    
</body>
</html>
