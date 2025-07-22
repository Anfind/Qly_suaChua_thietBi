<?php
/**
 * File chứa các hàm helper toàn cục
 */

/**
 * Escape HTML để tránh XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Tạo URL
 */
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Tạo URL cho assets
 */
function asset($path) {
    return ASSETS_URL . ltrim($path, '/');
}

/**
 * Tạo URL cho upload
 */
function upload_url($path) {
    return UPLOAD_URL . ltrim($path, '/');
}

/**
 * Redirect
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        flash($message, $type);
    }
    
    if (!str_starts_with($url, 'http')) {
        $url = url($url);
    }
    
    header("Location: $url");
    exit;
}

/**
 * Flash message
 */
function flash($message, $type = 'info') {
    $_SESSION['flash'][] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Lấy và xóa flash messages
 */
function get_flash_messages() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Kiểm tra user đã đăng nhập
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Lấy thông tin user hiện tại
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    static $user = null;
    if ($user === null) {
        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT u.*, r.name as role_name, r.display_name as role_display_name, 
                    d.name as department_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             LEFT JOIN departments d ON u.department_id = d.id 
             WHERE u.id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    return $user;
}

/**
 * Kiểm tra quyền của user
 */
function has_role($role) {
    $user = current_user();
    return $user && $user['role_name'] === $role;
}

/**
 * Kiểm tra user có một trong các roles
 */
function has_any_role($roles) {
    $user = current_user();
    if (!$user) return false;
    
    return in_array($user['role_name'], $roles);
}

/**
 * Require login
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('auth/login.php', 'Vui lòng đăng nhập để tiếp tục', 'warning');
    }
}

/**
 * Require role
 */
function require_role($role) {
    require_login();
    if (!has_role($role)) {
        redirect('dashboard.php', 'Bạn không có quyền truy cập trang này', 'error');
    }
}

/**
 * Require any role
 */
function require_any_role($roles) {
    require_login();
    if (!has_any_role($roles)) {
        redirect('dashboard.php', 'Bạn không có quyền truy cập trang này', 'error');
    }
}

/**
 * Tạo CSRF token field
 */
function csrf_field() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $_SESSION['csrf_token'] . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf() {
    if (!isset($_POST[CSRF_TOKEN_NAME]) || 
        !hash_equals($_SESSION['csrf_token'], $_POST[CSRF_TOKEN_NAME])) {
        die('CSRF token mismatch');
    }
}

/**
 * Format ngày tháng
 */
function format_date($date, $format = 'd/m/Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Format ngày giờ
 */
function format_datetime($datetime, $format = 'd/m/Y H:i') {
    if (!$datetime) return '';
    return date($format, strtotime($datetime));
}

/**
 * Format số tiền
 */
function format_money($amount, $currency = 'VNĐ') {
    if ($amount == 0) return '0 ' . $currency;
    return number_format($amount, 0, ',', '.') . ' ' . $currency;
}

/**
 * Format thời gian relative
 */
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'vừa xong';
    if ($time < 3600) return floor($time/60) . ' phút trước';
    if ($time < 86400) return floor($time/3600) . ' giờ trước';
    if ($time < 2592000) return floor($time/86400) . ' ngày trước';
    if ($time < 31104000) return floor($time/2592000) . ' tháng trước';
    return floor($time/31104000) . ' năm trước';
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Generate slug từ tiếng Việt
 */
function slugify($text) {
    // Chuyển đổi tiếng Việt
    $vietnamese = [
        'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
        'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
        'ì', 'í', 'ị', 'ỉ', 'ĩ',
        'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
        'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
        'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
        'đ',
        'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
        'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
        'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
        'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
        'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
        'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
        'Đ'
    ];
    
    $latin = [
        'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
        'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
        'i', 'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
        'y', 'y', 'y', 'y', 'y',
        'd',
        'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
        'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
        'I', 'I', 'I', 'I', 'I',
        'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
        'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
        'Y', 'Y', 'Y', 'Y', 'Y',
        'D'
    ];
    
    $text = str_replace($vietnamese, $latin, $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    
    return $text;
}

/**
 * Upload file
 */
function upload_file($file, $directory, $allowed_types = null) {
    if (!isset($file['tmp_name']) || !$file['tmp_name']) {
        return false;
    }
    
    $allowed_types = $allowed_types ?: array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOCUMENT_TYPES);
    
    // Kiểm tra kích thước file
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File quá lớn. Kích thước tối đa: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    
    // Kiểm tra định dạng file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('Định dạng file không được hỗ trợ: ' . $extension);
    }
    
    // Tạo tên file mới
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $directory . $filename;
    
    // Tạo thư mục nếu chưa tồn tại
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    throw new Exception('Không thể upload file');
}

/**
 * Xóa file
 */
function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return true;
}

/**
 * Tạo breadcrumb
 */
function breadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $count = count($items);
    foreach ($items as $index => $item) {
        if ($index === $count - 1) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . e($item['title']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . e($item['url']) . '">' . e($item['title']) . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Log activity
 */
function log_activity($action, $details = null) {
    $user = current_user();
    if (!$user) return;
    
    $db = Database::getInstance();
    $db->insert('activity_logs', [
        'user_id' => $user['id'],
        'action' => $action,
        'details' => $details ? json_encode($details) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}
?>
