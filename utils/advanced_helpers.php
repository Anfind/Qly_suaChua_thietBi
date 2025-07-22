<?php
/**
 * Helper functions để tối ưu hiệu suất và bổ sung chức năng
 */

/**
 * Generate unique filename cho uploads
 */
function generate_unique_filename($originalName, $directory) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    
    // Slugify basename
    $baseName = slugify($baseName);
    
    $counter = 0;
    do {
        $filename = $baseName . ($counter > 0 ? '-' . $counter : '') . '.' . $extension;
        $counter++;
    } while (file_exists($directory . $filename));
    
    return $filename;
}

/**
 * Resize image if needed
 */
function resize_image($sourcePath, $destinationPath, $maxWidth = 1200, $maxHeight = 800, $quality = 85) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    list($originalWidth, $originalHeight, $imageType) = $imageInfo;
    
    // Nếu ảnh đã nhỏ hơn kích thước tối đa thì copy trực tiếp
    if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
        return copy($sourcePath, $destinationPath);
    }
    
    // Tính toán kích thước mới
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);
    
    // Tạo image từ source
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Tạo image mới
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Giữ trong suốt cho PNG và GIF
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Save
    $result = false;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $destinationPath, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $destinationPath, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $destinationPath);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($newImage, $destinationPath, $quality);
            break;
    }
    
    // Cleanup
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

/**
 * Create thumbnail
 */
function create_thumbnail($sourcePath, $destinationPath, $width = 150, $height = 150) {
    return resize_image($sourcePath, $destinationPath, $width, $height, 85);
}

/**
 * Send email notification (basic implementation)
 */
function send_notification($to, $subject, $message, $data = []) {
    if (!SMTP_HOST || !SMTP_USERNAME) {
        // Log instead of sending if email not configured
        error_log("Email notification: To: $to, Subject: $subject, Message: $message");
        return true;
    }
    
    // TODO: Implement actual email sending using PHPMailer or similar
    // For now, just log the notification
    error_log("Email notification queued: To: $to, Subject: $subject");
    return true;
}

/**
 * Send repair status notification
 */
function notify_status_change($requestId, $newStatus, $userId) {
    try {
        $db = Database::getInstance();
        
        // Lấy thông tin đơn sửa chữa
        $request = $db->fetch(
            "SELECT r.*, e.name as equipment_name, u.full_name as requester_name, u.email as requester_email,
                    s.name as status_name
             FROM repair_requests r
             LEFT JOIN equipments e ON r.equipment_id = e.id
             LEFT JOIN users u ON r.requester_id = u.id
             LEFT JOIN repair_statuses s ON r.current_status_id = s.id
             WHERE r.id = ?",
            [$requestId]
        );
        
        if (!$request || !$request['requester_email']) {
            return false;
        }
        
        $statusMessages = [
            'HANDED_TO_CLERK' => 'Đơn sửa chữa đã được bàn giao cho văn thư',
            'SENT_TO_REPAIR' => 'Thiết bị đã được chuyển đến đơn vị sửa chữa',
            'IN_PROGRESS' => 'Đơn vị sửa chữa đã bắt đầu xử lý',
            'REPAIR_COMPLETED' => 'Việc sửa chữa đã hoàn thành',
            'RETRIEVED' => 'Thiết bị đã được thu hồi',
            'COMPLETED' => 'Đơn sửa chữa đã hoàn tất - thiết bị đã được trả lại'
        ];
        
        $subject = "Cập nhật trạng thái đơn sửa chữa #{$request['request_code']}";
        $message = "Xin chào {$request['requester_name']},\n\n";
        $message .= "Đơn sửa chữa thiết bị {$request['equipment_name']} (#{$request['request_code']}) ";
        $message .= "đã được cập nhật trạng thái: {$request['status_name']}\n\n";
        
        if (isset($statusMessages[$newStatus])) {
            $message .= $statusMessages[$newStatus] . "\n\n";
        }
        
        $message .= "Vui lòng truy cập hệ thống để xem chi tiết.\n\n";
        $message .= "Trân trọng,\n";
        $message .= APP_NAME;
        
        return send_notification($request['requester_email'], $subject, $message);
        
    } catch (Exception $e) {
        error_log("Error sending status notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR code for request
 */
function generate_qr_code($text, $size = 200) {
    // Simple QR code URL (using external service)
    $encoded = urlencode($text);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}";
}

/**
 * Calculate repair statistics
 */
function calculate_repair_stats($requestId) {
    try {
        $db = Database::getInstance();
        
        // Lấy thông tin đơn
        $request = $db->fetch(
            "SELECT *, 
                    DATEDIFF(COALESCE(actual_completion, NOW()), created_at) as days_elapsed,
                    CASE 
                        WHEN actual_completion IS NOT NULL THEN DATEDIFF(actual_completion, created_at)
                        ELSE NULL 
                    END as completion_days
             FROM repair_requests 
             WHERE id = ?",
            [$requestId]
        );
        
        if (!$request) {
            return null;
        }
        
        // Tính tổng chi phí từ repair_details
        $totalCost = $db->fetch(
            "SELECT COALESCE(SUM(parts_cost + labor_cost), 0) as total
             FROM repair_details 
             WHERE request_id = ?",
            [$requestId]
        )['total'];
        
        // Tính tổng thời gian làm việc
        $totalHours = $db->fetch(
            "SELECT COALESCE(SUM(time_spent), 0) as total
             FROM repair_details 
             WHERE request_id = ?",
            [$requestId]
        )['total'];
        
        // Cập nhật tổng chi phí vào bảng repair_requests
        $db->update('repair_requests', 
            ['total_cost' => $totalCost], 
            'id = ?', 
            [$requestId]
        );
        
        return [
            'days_elapsed' => $request['days_elapsed'],
            'completion_days' => $request['completion_days'],
            'total_cost' => $totalCost,
            'total_hours' => $totalHours,
            'urgency_level' => $request['urgency_level']
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating repair stats: " . $e->getMessage());
        return null;
    }
}

/**
 * Get equipment maintenance history
 */
function get_equipment_history($equipmentId, $limit = 10) {
    try {
        $db = Database::getInstance();
        
        return $db->fetchAll(
            "SELECT r.*, s.name as status_name, s.color as status_color,
                    u.full_name as requester_name
             FROM repair_requests r
             LEFT JOIN repair_statuses s ON r.current_status_id = s.id
             LEFT JOIN users u ON r.requester_id = u.id
             WHERE r.equipment_id = ?
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$equipmentId, $limit]
        );
        
    } catch (Exception $e) {
        error_log("Error getting equipment history: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if equipment needs maintenance
 */
function check_maintenance_due($equipmentId) {
    try {
        $db = Database::getInstance();
        
        // Lấy thông tin thiết bị và lần sửa chữa gần nhất
        $equipment = $db->fetch(
            "SELECT e.*, 
                    MAX(r.actual_completion) as last_repair,
                    COUNT(r.id) as repair_count
             FROM equipments e
             LEFT JOIN repair_requests r ON e.id = r.equipment_id AND r.current_status_id = (SELECT id FROM repair_statuses WHERE code = 'COMPLETED')
             WHERE e.id = ?
             GROUP BY e.id",
            [$equipmentId]
        );
        
        if (!$equipment) {
            return false;
        }
        
        $alerts = [];
        
        // Kiểm tra bảo hành
        if ($equipment['warranty_date'] && strtotime($equipment['warranty_date']) < time()) {
            $alerts[] = [
                'type' => 'warranty_expired',
                'message' => 'Thiết bị đã hết bảo hành',
                'severity' => 'warning'
            ];
        }
        
        // Kiểm tra số lần sửa chữa
        if ($equipment['repair_count'] > 5) {
            $alerts[] = [
                'type' => 'frequent_repairs',
                'message' => 'Thiết bị có nhiều lần sửa chữa (' . $equipment['repair_count'] . ' lần)',
                'severity' => 'danger'
            ];
        }
        
        // Kiểm tra thời gian từ lần sửa cuối
        if ($equipment['last_repair']) {
            $daysSinceRepair = (time() - strtotime($equipment['last_repair'])) / (60 * 60 * 24);
            if ($daysSinceRepair > 365) { // 1 năm
                $alerts[] = [
                    'type' => 'maintenance_due',
                    'message' => 'Đã hơn 1 năm kể từ lần bảo trì cuối',
                    'severity' => 'info'
                ];
            }
        }
        
        return $alerts;
        
    } catch (Exception $e) {
        error_log("Error checking maintenance due: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate equipment barcode
 */
function generate_barcode($code, $width = 300, $height = 50) {
    // Simple barcode using external service
    $encoded = urlencode($code);
    return "https://barcode.tec-it.com/barcode.ashx?data={$encoded}&code=Code128&width={$width}&height={$height}";
}

/**
 * Validate file upload
 */
function validate_upload($file, $allowedTypes = null, $maxSize = null) {
    if (!$allowedTypes) {
        $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOCUMENT_TYPES);
    }
    
    if (!$maxSize) {
        $maxSize = MAX_FILE_SIZE;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Lỗi upload file: ' . $file['error']);
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File quá lớn. Kích thước tối đa: ' . ($maxSize / 1024 / 1024) . 'MB');
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        throw new Exception('Định dạng file không được hỗ trợ. Chỉ chấp nhận: ' . implode(', ', $allowedTypes));
    }
    
    return true;
}

/**
 * Secure file upload
 */
function secure_upload($file, $directory, $allowedTypes = null) {
    validate_upload($file, $allowedTypes);
    
    $filename = generate_unique_filename($file['name'], $directory);
    $destination = $directory . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Không thể lưu file');
    }
    
    // Resize image nếu cần
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $resized = $directory . 'resized_' . $filename;
        if (resize_image($destination, $resized)) {
            unlink($destination);
            rename($resized, $destination);
        }
        
        // Tạo thumbnail
        $thumbnailDir = $directory . 'thumbnails/';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }
        create_thumbnail($destination, $thumbnailDir . $filename);
    }
    
    return $filename;
}

/**
 * Clean old files
 */
function cleanup_old_files($directory, $daysOld = 30) {
    if (!is_dir($directory)) {
        return false;
    }
    
    $files = glob($directory . '*');
    $cutoff = time() - ($daysOld * 24 * 60 * 60);
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

/**
 * Cache management
 */
function cache_set($key, $value, $expiration = 3600) {
    $cacheDir = BASE_PATH . 'cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $data = [
        'value' => $value,
        'expires' => time() + $expiration
    ];
    
    return file_put_contents($cacheDir . md5($key) . '.cache', serialize($data));
}

function cache_get($key) {
    $cacheFile = BASE_PATH . 'cache/' . md5($key) . '.cache';
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $data = unserialize(file_get_contents($cacheFile));
    
    if ($data['expires'] < time()) {
        unlink($cacheFile);
        return null;
    }
    
    return $data['value'];
}

function cache_delete($key) {
    $cacheFile = BASE_PATH . 'cache/' . md5($key) . '.cache';
    return file_exists($cacheFile) ? unlink($cacheFile) : true;
}

/**
 * System health check
 */
function system_health_check() {
    $checks = [];
    
    // Database connection
    try {
        $db = Database::getInstance();
        $db->query("SELECT 1");
        $checks['database'] = ['status' => 'ok', 'message' => 'Kết nối database thành công'];
    } catch (Exception $e) {
        $checks['database'] = ['status' => 'error', 'message' => 'Lỗi kết nối database: ' . $e->getMessage()];
    }
    
    // Upload directories writable
    $uploadDirs = [UPLOAD_PATH, UPLOAD_EQUIPMENT_PATH, UPLOAD_REQUEST_PATH, UPLOAD_AVATAR_PATH];
    foreach ($uploadDirs as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            $checks['upload_' . basename($dir)] = ['status' => 'ok', 'message' => 'Thư mục upload ' . basename($dir) . ' OK'];
        } else {
            $checks['upload_' . basename($dir)] = ['status' => 'error', 'message' => 'Thư mục upload ' . basename($dir) . ' không ghi được'];
        }
    }
    
    // PHP extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'json'];
    foreach ($requiredExtensions as $ext) {
        if (extension_loaded($ext)) {
            $checks['ext_' . $ext] = ['status' => 'ok', 'message' => 'Extension ' . $ext . ' đã cài đặt'];
        } else {
            $checks['ext_' . $ext] = ['status' => 'error', 'message' => 'Extension ' . $ext . ' chưa cài đặt'];
        }
    }
    
    // Disk space
    $freeSpace = disk_free_space(BASE_PATH);
    $totalSpace = disk_total_space(BASE_PATH);
    $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
    
    if ($usedPercent > 90) {
        $checks['disk_space'] = ['status' => 'error', 'message' => 'Dung lượng đĩa sắp hết (' . round($usedPercent, 1) . '% đã sử dụng)'];
    } elseif ($usedPercent > 80) {
        $checks['disk_space'] = ['status' => 'warning', 'message' => 'Dung lượng đĩa cao (' . round($usedPercent, 1) . '% đã sử dụng)'];
    } else {
        $checks['disk_space'] = ['status' => 'ok', 'message' => 'Dung lượng đĩa OK (' . round($usedPercent, 1) . '% đã sử dụng)'];
    }
    
    return $checks;
}
?>
