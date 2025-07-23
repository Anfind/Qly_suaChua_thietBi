<?php
require_once 'config/config.php';

echo "<h2>🧪 Test Login Function</h2>";

try {
    // Test kết nối database
    $db = new Database();
    $conn = $db->getConnection();
    echo "✅ Database connected<br><br>";
    
    // Kiểm tra users
    $stmt = $conn->query("SELECT id, username, password, full_name, role_id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Users in database:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Full Name</th><th>Role ID</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['password']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role_id']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Test login function
    echo "<h3>Testing login function:</h3>";
    $userModel = new User();
    
    $testCredentials = [
        ['admin', 'admin123'],
        ['user1', 'user123'],
        ['logistics1', 'user123']
    ];
    
    foreach ($testCredentials as $cred) {
        echo "<strong>Testing: {$cred[0]} / {$cred[1]}</strong><br>";
        
        try {
            $result = $userModel->login($cred[0], $cred[1]);
            if ($result) {
                echo "✅ SUCCESS - Role: {$result['role_name']}<br>";
            } else {
                echo "❌ FAILED - Invalid credentials<br>";
            }
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "<br>";
        }
        echo "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<hr>";
echo '<a href="index.php">Back to Login</a>';
?>
