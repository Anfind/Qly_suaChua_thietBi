<?php
require_once '../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('repairs/', 'Phương thức không được hỗ trợ', 'error');
}

try {
    verify_csrf();
    
    $controller = new RepairController();
    $controller->updateStatus();
    
} catch (Exception $e) {
    redirect($_SERVER['HTTP_REFERER'] ?? 'repairs/', 
        'Lỗi: ' . $e->getMessage(), 'error');
}
?>
