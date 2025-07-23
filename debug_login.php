<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=equipment_repair_management;charset=utf8mb4', 'root', '210506', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo 'Kết nối database: OK' . PHP_EOL;
    
    // Kiểm tra bảng users
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $result = $stmt->fetch();
    echo 'Số lượng users: ' . $result['count'] . PHP_EOL;
    
    // Kiểm tra cấu trúc bảng users
    $stmt = $pdo->query('DESCRIBE users');
    $columns = $stmt->fetchAll();
    echo 'Cấu trúc bảng users:' . PHP_EOL;
    foreach($columns as $col) {
        echo '- ' . $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
    }
    
    echo PHP_EOL . 'Danh sách tất cả users:' . PHP_EOL;
    $stmt = $pdo->query('SELECT username, password, full_name, status FROM users');
    $users = $stmt->fetchAll();
    foreach($users as $user) {
        echo '- ' . $user['username'] . ' / ' . $user['password'] . ' (' . $user['status'] . ')' . PHP_EOL;
    }
    
    // Thử query login cho admin
    echo PHP_EOL . 'Test query login cho admin:' . PHP_EOL;
    $stmt = $pdo->prepare('SELECT u.*, r.name as role_name, r.display_name as role_display_name, 
                       d.name as department_name, d.code as department_code
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.username = ? AND u.status = ?');
    $stmt->execute(['admin', 'active']);
    $user = $stmt->fetch();
    
    if($user) {
        echo 'User admin tìm thấy:' . PHP_EOL;
        echo '- Username: ' . $user['username'] . PHP_EOL;
        echo '- Password: ' . $user['password'] . PHP_EOL;
        echo '- Role: ' . ($user['role_name'] ?? 'NULL') . PHP_EOL;
        echo '- Status: ' . $user['status'] . PHP_EOL;
        echo '- Department: ' . ($user['department_name'] ?? 'NULL') . PHP_EOL;
    } else {
        echo 'Không tìm thấy user admin!' . PHP_EOL;
    }
    
    // Kiểm tra bảng roles
    echo PHP_EOL . 'Kiểm tra bảng roles:' . PHP_EOL;
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM roles');
    $result = $stmt->fetch();
    echo 'Số lượng roles: ' . $result['count'] . PHP_EOL;
    
    if($result['count'] > 0) {
        $stmt = $pdo->query('SELECT id, name, display_name FROM roles');
        $roles = $stmt->fetchAll();
        foreach($roles as $role) {
            echo '- ID: ' . $role['id'] . ', Name: ' . $role['name'] . ', Display: ' . $role['display_name'] . PHP_EOL;
        }
    }
    
} catch(Exception $e) {
    echo 'Lỗi: ' . $e->getMessage() . PHP_EOL;
    echo 'Stack trace: ' . $e->getTraceAsString() . PHP_EOL;
}
?>
