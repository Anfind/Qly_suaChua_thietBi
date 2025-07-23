<?php
/**
 * Cron job để gửi email tự động
 * Chạy hàng ngày vào 8:00 sáng
 */

require_once __DIR__ . '/config/config.php';

// Chỉ cho phép chạy từ command line
if (php_sapi_name() !== 'cli') {
    die('Script này chỉ có thể chạy từ command line');
}

try {
    echo "=== Bắt đầu cron job email ===" . PHP_EOL;
    echo "Thời gian: " . date('Y-m-d H:i:s') . PHP_EOL;
    
    // Gửi thông báo đơn quá hạn
    echo "Kiểm tra đơn quá hạn..." . PHP_EOL;
    require_once 'utils/email.php';
    $overdueCount = send_overdue_notifications();
    echo "Đã gửi thông báo cho {$overdueCount} đơn quá hạn" . PHP_EOL;
    
    // Gửi báo cáo hàng ngày cho admin
    echo "Gửi báo cáo hàng ngày..." . PHP_EOL;
    $db = Database::getInstance();
    $admins = $db->fetchAll(
        "SELECT email FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE r.name = 'admin' AND u.email IS NOT NULL AND u.status = 'active'"
    );
    
    $sentCount = 0;
    foreach ($admins as $admin) {
        if (send_daily_summary($admin['email'])) {
            $sentCount++;
        }
    }
    echo "Đã gửi báo cáo cho {$sentCount} admin" . PHP_EOL;
    
    // Cleanup old export files (7 ngày)
    echo "Dọn dẹp file export cũ..." . PHP_EOL;
    require_once 'utils/ExportService.php';
    $exportService = new ExportService();
    $deletedCount = $exportService->cleanupOldExports(7);
    echo "Đã xóa {$deletedCount} file export cũ" . PHP_EOL;
    
    // Cleanup old uploaded files (30 ngày)
    echo "Dọn dẹp file upload cũ..." . PHP_EOL;
    $uploadDirs = [
        UPLOAD_PATH . 'requests/',
        UPLOAD_PATH . 'equipments/',
        UPLOAD_PATH . 'avatars/',
        UPLOAD_PATH . 'documents/'
    ];
    
    $totalDeleted = 0;
    foreach ($uploadDirs as $dir) {
        if (function_exists('cleanup_old_files')) {
            $deleted = cleanup_old_files($dir, 30);
            $totalDeleted += $deleted;
        }
    }
    echo "Đã xóa {$totalDeleted} file upload cũ" . PHP_EOL;
    
    echo "=== Hoàn thành cron job ===" . PHP_EOL;
    
} catch (Exception $e) {
    echo "LỖI: " . $e->getMessage() . PHP_EOL;
    error_log("Cron job error: " . $e->getMessage());
}
?>
