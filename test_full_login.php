<?php
// Test login hoàn chỉnh - simulation chính xác web interface

session_start();
require_once 'config/config.php';
require_once 'utils/helpers.php';

echo "=== SIMULATION WEB LOGIN PROCESS ===\n";

// Clear session để bắt đầu fresh
$_SESSION = array();
if (function_exists('session_regenerate_id')) {
    @session_regenerate_id(true);
}

echo "1. Bắt đầu với session trống (như lần đầu vào trang login):\n";
echo "Initial session: ";
print_r($_SESSION);

// Giả lập hiển thị form login (csrf_field() được gọi)
echo "\n2. Hiển thị form login - gọi csrf_field():\n";
$csrf_html = csrf_field();
echo "CSRF field HTML: $csrf_html\n";
echo "Session sau csrf_field(): ";
print_r($_SESSION);

// Giả lập user submit form lần đầu
echo "\n3. User submit form lần đầu:\n";
$_POST = [
    'username' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $_SESSION['csrf_token'] // Form đã có token từ csrf_field()
];

echo "POST data: ";
print_r($_POST);

// Bắt đầu logic trong index.php
echo "\n4. Xử lý login trong index.php:\n";

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Lấy CSRF token trước khi clear session
$submitted_csrf = $_POST['csrf_token'] ?? '';
$session_csrf = $_SESSION['csrf_token'] ?? '';

echo "Username: $username\n";
echo "Password: $password\n";
echo "submitted_csrf: $submitted_csrf\n";
echo "session_csrf: $session_csrf\n";

if (empty($username) || empty($password)) {
    echo "❌ Lỗi: Thiếu username/password\n";
    exit;
}

echo "\n5. Bắt đầu try-catch block:\n";

try {
    // CLEAR TOÀN BỘ SESSION TRƯỚC KHI LOGIN
    echo "Clearing session...\n";
    $_SESSION = array();
    if (function_exists('session_regenerate_id')) {
        @session_regenerate_id(true);
    }
    
    echo "Session sau khi clear: ";
    print_r($_SESSION);
    
    // Kiểm tra CSRF token (chỉ khi có cả 2)
    echo "\n6. Kiểm tra CSRF logic:\n";
    if (!empty($session_csrf) && !empty($submitted_csrf)) {
        echo "Có cả session_csrf và submitted_csrf, kiểm tra hash_equals...\n";
        if (!hash_equals($session_csrf, $submitted_csrf)) {
            echo "❌ CSRF token không hợp lệ\n";
            $error = 'CSRF token không hợp lệ';
            // Tạo lại CSRF token sau khi clear session
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            echo "✅ CSRF token hợp lệ\n";
            $process_login = true;
        }
    } else {
        echo "✅ Lần đầu login hoặc không có CSRF, cho phép login\n";
        $process_login = true;
    }
    
    if (empty($error) && isset($process_login)) {
        echo "\n7. Thực hiện login với User model:\n";
        
        $userModel = new User();
        $user = $userModel->login($username, $password);
        
        if ($user) {
            echo "✅ Login thành công!\n";
            echo "User data: ";
            print_r($user);
            
            // Lưu thông tin user vào session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['login_time'] = time();
            
            echo "Session sau khi login: ";
            print_r($_SESSION);
            
            echo "\n🎉 LOGIN THÀNH CÔNG HOÀN TOÀN!\n";
            echo "Redirect tới dashboard sẽ xảy ra...\n";
            
        } else {
            echo "❌ Sai username/password\n";
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception xảy ra: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    $error = 'Có lỗi xảy ra: ' . $e->getMessage();
}

echo "\n=== KẾT QUẢ CUỐI CÙNG ===\n";
if (isset($error)) {
    echo "❌ LỖI: $error\n";
} else if (isset($process_login) && $process_login) {
    echo "✅ THÀNH CÔNG: Login hoàn tất!\n";
} else {
    echo "❓ KHÔNG RÕ: Không có kết quả rõ ràng\n";
}

echo "\nFinal session state: ";
print_r($_SESSION);
?>
