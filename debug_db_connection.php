<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Database Connection</h2>";

try {
    // Test 1: Direct PDO connection
    echo "<h3>Test 1: Direct PDO Connection</h3>";
    $dsn = "mysql:host=localhost;dbname=equipment_repair_management;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ];
    
    $pdo = new PDO($dsn, 'root', '210506', $options);
    echo "✓ Direct PDO connection: SUCCESS<br>";
    
    // Test 2: Simple query
    echo "<h3>Test 2: Simple Query</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✓ User count: " . $result['count'] . "<br>";
    
    // Test 3: Database class
    echo "<h3>Test 3: Database Class</h3>";
    require_once 'config/Database.php';
    $db = Database::getInstance();
    echo "✓ Database singleton: SUCCESS<br>";
    
    // Test 4: Test login query multiple times
    echo "<h3>Test 4: Login Query (5 lần liên tiếp)</h3>";
    $sql = "SELECT u.*, r.name as role_name, r.display_name as role_display_name, 
                   d.name as department_name, d.code as department_code
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.username = ? AND u.status = 'active'";
    
    for ($i = 1; $i <= 5; $i++) {
        echo "Lần {$i}: ";
        try {
            $user = $db->fetch($sql, ['admin']);
            if ($user) {
                echo "✓ Found user: " . $user['full_name'] . "<br>";
            } else {
                echo "✗ No user found<br>";
            }
        } catch (Exception $e) {
            echo "✗ ERROR: " . $e->getMessage() . "<br>";
        }
        
        // Delay nhỏ giữa các query
        usleep(100000); // 0.1 seconds
    }
    
    // Test 5: Test với multiple connections
    echo "<h3>Test 5: Multiple Database Instances</h3>";
    for ($i = 1; $i <= 3; $i++) {
        echo "Instance {$i}: ";
        try {
            $db_new = Database::getInstance();
            $count = $db_new->fetch("SELECT COUNT(*) as count FROM users");
            echo "✓ Count: " . $count['count'] . "<br>";
        } catch (Exception $e) {
            echo "✗ ERROR: " . $e->getMessage() . "<br>";
        }
    }
    
    // Test 6: Session simulation
    echo "<h3>Test 6: Session Simulation</h3>";
    session_start();
    echo "Session started<br>";
    
    // Clear session như trong login
    $old_csrf = $_SESSION['csrf_token'] ?? null;
    session_unset();
    session_regenerate_id(true);
    echo "Session cleared and regenerated<br>";
    
    // Test query sau khi clear session
    try {
        $user = $db->fetch($sql, ['admin']);
        if ($user) {
            echo "✓ Query after session clear: SUCCESS - " . $user['full_name'] . "<br>";
        } else {
            echo "✗ Query after session clear: No user found<br>";
        }
    } catch (Exception $e) {
        echo "✗ Query after session clear ERROR: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage();
}
?>
