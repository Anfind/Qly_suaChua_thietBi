<?php
require_once 'config/config.php';
require_once 'utils/helpers.php';

echo "<h2>Debug CSRF Logic - Updated</h2>";

// Test 1: Kiểm tra session hiện tại
echo "<h3>1. Session hiện tại:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test 2: Test csrf_field()
echo "<h3>2. Test csrf_field():</h3>";
echo "CSRF Field HTML: " . csrf_field() . "<br>";
echo "Session sau khi gọi csrf_field():<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test 3: Giả lập POST request như trong index.php
echo "<h3>3. Giả lập POST login như trong index.php:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<strong>POST Request received:</strong><br>";
    echo "Username: " . ($_POST['username'] ?? 'not set') . "<br>";
    echo "Password: " . ($_POST['password'] ?? 'not set') . "<br>";
    echo "CSRF Token từ form: " . ($_POST['csrf_token'] ?? 'not set') . "<br>";
    echo "CSRF Token từ session trước khi xử lý: " . ($_SESSION['csrf_token'] ?? 'not set') . "<br>";
    
    // Lấy CSRF token trước khi clear session (như trong index.php mới)
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    $session_csrf = $_SESSION['csrf_token'] ?? '';
    
    echo "<br><strong>Logic test (như trong index.php mới):</strong><br>";
    echo "submitted_csrf: " . $submitted_csrf . "<br>";
    echo "session_csrf: " . $session_csrf . "<br>";
    echo "empty(session_csrf): " . (empty($session_csrf) ? 'true' : 'false') . "<br>";
    echo "empty(submitted_csrf): " . (empty($submitted_csrf) ? 'true' : 'false') . "<br>";
    
    $process_login = false;
    
    if (!empty($session_csrf) && !empty($submitted_csrf)) {
        echo "<br>Có cả 2 CSRF token, kiểm tra hash_equals...<br>";
        if (!hash_equals($session_csrf, $submitted_csrf)) {
            echo "❌ CSRF token không hợp lệ<br>";
            $error = 'CSRF token không hợp lệ';
        } else {
            echo "✅ CSRF token hợp lệ<br>";
            $process_login = true;
        }
    } else {
        echo "<br>✅ Lần đầu login hoặc không có CSRF, cho phép login<br>";
        $process_login = true;
    }
    
    // Clear session như trong logic thật
    echo "<br><strong>Clearing session...</strong><br>";
    $_SESSION = array();
    session_regenerate_id(true);
    echo "Session sau khi clear:<br>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<strong>Kết quả:</strong><br>";
    echo "process_login: " . ($process_login ? 'true' : 'false') . "<br>";
    if (isset($error)) {
        echo "error: " . $error . "<br>";
    }
    
    // Tạo lại CSRF token cho lần login tiếp theo
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo "Tạo lại CSRF token mới: " . $_SESSION['csrf_token'] . "<br>";
    }
}

// Form test
echo '<h3>4. Form Test (giống hệt form trong index.php):</h3>';
echo '<form method="POST" action="debug_csrf.php">';
echo csrf_field();
echo '<div>';
echo '<label>Username:</label>';
echo '<input type="text" name="username" value="admin" required>';
echo '</div>';
echo '<div>';
echo '<label>Password:</label>';
echo '<input type="password" name="password" value="admin123" required>';
echo '</div>';
echo '<input type="submit" value="Test Login">';
echo '</form>';

echo "<hr>";
echo '<a href="debug_csrf.php">Reset Page</a> | ';
echo '<a href="force_clear_session.php">Clear Session</a> | ';
echo '<a href="index.php">Back to Login</a>';
?>
