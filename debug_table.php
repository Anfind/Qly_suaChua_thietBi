<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=equipment_repair_management;charset=utf8mb4', 'root', '210506', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "=== KIỂM TRA CẤU TRÚC BẢNG USERS ===" . PHP_EOL;
    
    // Kiểm tra cấu trúc bảng users chi tiết
    $stmt = $pdo->query('SHOW CREATE TABLE users');
    $result = $stmt->fetch();
    echo "CREATE TABLE users:" . PHP_EOL;
    echo $result['Create Table'] . PHP_EOL . PHP_EOL;
    
    // Test UPDATE thử
    echo "=== TEST UPDATE LAST_LOGIN ===" . PHP_EOL;
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
        $stmt->execute([date('Y-m-d H:i:s'), 1]);
        echo "✓ Update last_login thành công" . PHP_EOL;
    } catch (Exception $e) {
        echo "✗ Lỗi update: " . $e->getMessage() . PHP_EOL;
    }
    
} catch(Exception $e) {
    echo 'Lỗi: ' . $e->getMessage() . PHP_EOL;
}
?>
