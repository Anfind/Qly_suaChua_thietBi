<?php
// Debug login process
require_once 'config/config.php';

try {
    echo "=== DEBUG LOGIN PROCESS ===" . PHP_EOL;
    
    // Test tạo User model
    echo "1. Tạo User model..." . PHP_EOL;
    $userModel = new User();
    echo "   ✓ User model tạo thành công" . PHP_EOL;
    
    // Test login với admin
    echo "2. Test login với admin/admin123..." . PHP_EOL;
    $username = 'admin';
    $password = 'admin123';
    
    echo "   - Username: $username" . PHP_EOL;
    echo "   - Password: $password" . PHP_EOL;
    
    $result = $userModel->login($username, $password);
    
    if ($result) {
        echo "   ✓ Login thành công!" . PHP_EOL;
        echo "   - User ID: " . $result['id'] . PHP_EOL;
        echo "   - Full name: " . $result['full_name'] . PHP_EOL;
        echo "   - Role: " . $result['role_name'] . PHP_EOL;
        echo "   - Department: " . ($result['department_name'] ?? 'NULL') . PHP_EOL;
    } else {
        echo "   ✗ Login thất bại!" . PHP_EOL;
    }
    
    // Test với user1
    echo PHP_EOL . "3. Test login với user1/user123..." . PHP_EOL;
    $result2 = $userModel->login('user1', 'user123');
    
    if ($result2) {
        echo "   ✓ Login user1 thành công!" . PHP_EOL;
        echo "   - Role: " . $result2['role_name'] . PHP_EOL;
    } else {
        echo "   ✗ Login user1 thất bại!" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "TRACE: " . $e->getTraceAsString() . PHP_EOL;
}
?>
