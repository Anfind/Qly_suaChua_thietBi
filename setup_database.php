<?php
/**
 * Script tạo database và import dữ liệu
 */

try {
    // Thử các cách kết nối khác nhau
    $connections = [
        ['root', '210506'],
        ['root', ''],
        ['root', 'root'],
        ['root', 'mysql'],
        ['', '']
    ];
    
    $pdo = null;
    foreach ($connections as $conn) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;charset=utf8mb4", 
                $conn[0], 
                $conn[1], 
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
                ]
            );
            echo "✅ Kết nối MySQL thành công với user: {$conn[0]}\n";
            break;
        } catch (PDOException $e) {
            echo "❌ Không kết nối được với user: {$conn[0]}\n";
        }
    }
    
    if (!$pdo) {
        throw new PDOException("Không thể kết nối với bất kỳ user nào");
    }
    
    // Tạo database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS equipment_repair_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database 'equipment_repair_management' đã được tạo!\n";
    
    // Chọn database
    $pdo->exec("USE equipment_repair_management");
    
    // Đọc và thực thi file SQL
    $sqlFile = __DIR__ . '/database.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Tách các câu lệnh SQL
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );
        
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "⚠️ Lỗi SQL: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "✅ Import database thành công!\n";
    } else {
        echo "❌ Không tìm thấy file database.sql\n";
    }
    
    // Kiểm tra bảng
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Các bảng đã tạo: " . implode(', ', $tables) . "\n";
    
    // Kiểm tra user admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $adminExists = $stmt->fetchColumn();
    
    if ($adminExists == 0) {
        // Tạo user admin với password không hash
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role_id, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin123', 'Administrator', 1, 'active']);
        echo "✅ Tài khoản admin đã được tạo (password không hash)!\n";
    } else {
        // Cập nhật password admin về dạng không hash
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute(['admin123']);
        echo "✅ Password admin đã được cập nhật (không hash)!\n";
    }
    
    echo "\n🎉 Setup database hoàn tất!\n";
    echo "👤 Username: admin\n";
    echo "🔑 Password: admin123\n";
    
} catch (PDOException $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
    echo "💡 Hướng dẫn khắc phục:\n";
    echo "1. Kiểm tra XAMPP MySQL đang chạy\n";
    echo "2. Kiểm tra port 3306 không bị chặn\n";
    echo "3. Thử đăng nhập phpMyAdmin\n";
}
?>
