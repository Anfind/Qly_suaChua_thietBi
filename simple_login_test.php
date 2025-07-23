<?php
require_once 'config/config.php';
require_once 'utils/helpers.php';

echo "<h2>🔧 SIMPLE LOGIN TEST</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "<h3>📝 POST Data:</h3>";
    echo "Username: " . htmlspecialchars($username) . "<br>";
    echo "Password: " . htmlspecialchars($password) . "<br><br>";
    
    if (!empty($username) && !empty($password)) {
        try {
            echo "<h3>🔍 Testing Login:</h3>";
            
            // Test 1: Direct database query
            echo "<strong>Test 1: Direct Database Query</strong><br>";
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id, username, password, full_name, role_id FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user_db = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_db) {
                echo "✅ User found in database<br>";
                echo "ID: {$user_db['id']}<br>";
                echo "Username: {$user_db['username']}<br>";
                echo "Password in DB: {$user_db['password']}<br>";
                echo "Password match: " . ($password === $user_db['password'] ? '✅ YES' : '❌ NO') . "<br><br>";
            } else {
                echo "❌ User NOT found in database<br><br>";
            }
            
            // Test 2: Using User model
            echo "<strong>Test 2: Using User Model</strong><br>";
            $userModel = new User();
            $user = $userModel->login($username, $password);
            
            if ($user) {
                echo "✅ Login SUCCESS via User model<br>";
                echo "ID: {$user['id']}<br>";
                echo "Username: {$user['username']}<br>";
                echo "Full Name: {$user['full_name']}<br>";
                echo "Role: {$user['role_name']}<br>";
            } else {
                echo "❌ Login FAILED via User model<br>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠️ Please enter both username and password</div>";
    }
    
    echo "<hr>";
}

echo "<h3>🧪 Test Login Form:</h3>";
echo '<form method="POST">';
echo '<div style="margin: 10px 0;">';
echo '<label>Username:</label><br>';
echo '<input type="text" name="username" value="admin" style="padding: 5px; width: 200px;">';
echo '</div>';
echo '<div style="margin: 10px 0;">';
echo '<label>Password:</label><br>';
echo '<input type="password" name="password" value="admin123" style="padding: 5px; width: 200px;">';
echo '</div>';
echo '<input type="submit" value="🔐 Test Login" style="padding: 8px 15px; background: #007bff; color: white; border: none; cursor: pointer;">';
echo '</form>';

echo "<hr>";
echo '<a href="index.php">🏠 Back to Main Login</a> | ';
echo '<a href="debug_csrf.php">🔍 CSRF Debug</a> | ';
echo '<a href="force_clear_session.php">🗑️ Clear Session</a>';
?>
