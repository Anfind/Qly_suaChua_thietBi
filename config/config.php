<?php
/**
 * File cấu hình chính của ứng dụng
 */

// Bắt đầu session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cấu hình múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình ứng dụng
define('APP_NAME', 'Hệ thống quản lý sửa chữa thiết bị');
define('APP_VERSION', '1.0.0');
define('APP_DESCRIPTION', 'Quản lý toàn trình sửa chữa thiết bị');

// Cấu hình đường dẫn
define('BASE_PATH', __DIR__ . '/../');
define('BASE_URL', 'http://localhost/Qly_suaChua_thietBi/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOAD_PATH', BASE_PATH . 'uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Cấu hình thư mục upload
define('UPLOAD_EQUIPMENT_PATH', UPLOAD_PATH . 'equipments/');
define('UPLOAD_REQUEST_PATH', UPLOAD_PATH . 'requests/');
define('UPLOAD_AVATAR_PATH', UPLOAD_PATH . 'avatars/');

// Cấu hình file upload
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov', 'wmv']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Cấu hình bảo mật
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 giờ

// Cấu hình phân trang
define('ITEMS_PER_PAGE', 20);

// Cấu hình debug
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);

// Thiết lập error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Cấu hình logging
if (LOG_ERRORS) {
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . 'logs/error.log');
}

// Autoload classes
spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . 'models/',
        BASE_PATH . 'controllers/',
        BASE_PATH . 'config/',
        BASE_PATH . 'utils/',
        BASE_PATH . 'middleware/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Tạo thư mục upload nếu chưa tồn tại
$uploadDirs = [
    UPLOAD_PATH,
    UPLOAD_EQUIPMENT_PATH,
    UPLOAD_REQUEST_PATH,
    UPLOAD_AVATAR_PATH,
    BASE_PATH . 'logs/'
];

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Hàm helper global
require_once BASE_PATH . 'utils/helpers.php';
require_once BASE_PATH . 'utils/advanced_helpers.php';

// Xử lý CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kiểm tra session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();
?>
