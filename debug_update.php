<?php
require_once 'config/config.php';

try {
    echo "=== DEBUG DATABASE UPDATE METHOD ===" . PHP_EOL;
    
    $db = Database::getInstance();
    echo "✓ Database instance tạo thành công" . PHP_EOL;
    
    // Test hàm update của Database class
    echo "Test Database->update() method..." . PHP_EOL;
    
    $updateData = ['last_login' => date('Y-m-d H:i:s')];
    $where = 'id = ?';
    $whereParams = [1];
    
    echo "Update data: " . print_r($updateData, true);
    echo "Where: $where" . PHP_EOL;
    echo "Where params: " . print_r($whereParams, true);
    
    $result = $db->update('users', $updateData, $where, $whereParams);
    echo "✓ Database update thành công" . PHP_EOL;
    
    // Bây giờ test toàn bộ quá trình login không có update
    echo PHP_EOL . "=== TEST LOGIN KHÔNG UPDATE LAST_LOGIN ===" . PHP_EOL;
    
    // Tạo một version User model tạm không update last_login
    $sql = "SELECT u.*, r.name as role_name, r.display_name as role_display_name, 
                   d.name as department_name, d.code as department_code
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.username = ? AND u.status = 'active'";
    
    $user = $db->fetch($sql, ['admin']);
    
    if ($user && 'admin123' === $user['password']) {
        echo "✓ Login logic hoạt động tốt" . PHP_EOL;
        echo "- Username: " . $user['username'] . PHP_EOL;
        echo "- Role: " . $user['role_name'] . PHP_EOL;
        
        // Test update sau đó
        echo "Thử update last_login..." . PHP_EOL;
        $updateResult = $db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$user['id']]
        );
        echo "✓ Update last_login thành công" . PHP_EOL;
        
        unset($user['password']);
        echo "✓ User data sạch sẽ" . PHP_EOL;
        
    } else {
        echo "✗ Login logic thất bại" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "TRACE: " . $e->getTraceAsString() . PHP_EOL;
}
?>
