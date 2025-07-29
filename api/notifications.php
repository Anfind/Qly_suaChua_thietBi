<?php
/**
 * NOTIFICATION API ENDPOINT
 * Xử lý các yêu cầu notification qua AJAX
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/notification_helpers.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = current_user();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'count':
            // Lấy số lượng thông báo chưa đọc
            $count = getUnreadNotificationCount($user['id']);
            echo json_encode(['count' => $count]);
            break;
            
        case 'recent':
            // Lấy thông báo gần đây
            $limit = (int)($_GET['limit'] ?? 10);
            $notifications = getRecentNotifications($user['id'], $limit);
            echo json_encode(['notifications' => $notifications]);
            break;
            
        case 'mark_read':
            // Đánh dấu một thông báo đã đọc
            $input = json_decode(file_get_contents('php://input'), true);
            $notificationId = $input['notification_id'] ?? 0;
            
            if ($notificationId) {
                $success = markNotificationAsRead($notificationId);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            // Đánh dấu tất cả thông báo đã đọc
            $success = markAllNotificationsAsRead($user['id']);
            echo json_encode(['success' => $success]);
            break;
            
        case 'full':
            // Lấy thông tin đầy đủ cho notification dropdown
            $count = getUnreadNotificationCount($user['id']);
            $notifications = getRecentNotifications($user['id'], 10);
            echo json_encode([
                'count' => $count,
                'notifications' => $notifications,
                'timestamp' => time()
            ]);
            break;
            
        case 'create_test':
            // Tạo thông báo test (chỉ để development)
            if (is_development_mode()) {
                $success = createNotification(
                    $user['id'],
                    'Thông báo test',
                    'Đây là thông báo test từ hệ thống',
                    'info',
                    'system',
                    null,
                    'dashboard.php'
                );
                echo json_encode(['success' => $success, 'message' => 'Test notification created']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Test mode not available']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Notification API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Check if in development mode
 */
function is_development_mode() {
    return defined('APP_ENV') && APP_ENV === 'development';
}
?>
