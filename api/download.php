<?php
/**
 * Download handler cho export files
 */
require_once '../config/config.php';
require_login();

try {
    $filename = $_GET['file'] ?? '';
    if (empty($filename)) {
        throw new Exception('Thiếu tên file');
    }
    
    // Validate filename to prevent directory traversal
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        throw new Exception('Tên file không hợp lệ');
    }
    
    $exportService = new ExportService();
    $exportService->downloadFile($filename);
    
} catch (Exception $e) {
    http_response_code(404);
    echo "Error: " . $e->getMessage();
}
?>
