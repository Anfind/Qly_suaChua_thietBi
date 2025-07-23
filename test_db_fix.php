<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Database Update Fix</h2>";

require_once 'config/config.php';

try {
    echo "<h3>Test 1: Database Connection</h3>";
    $db = Database::getInstance();
    echo "✓ Database connection: SUCCESS<br>";
    
    echo "<h3>Test 2: Simple Query</h3>";
    $users = $db->fetchAll("SELECT id, username, full_name FROM users LIMIT 3");
    echo "✓ Found " . count($users) . " users<br>";
    foreach ($users as $user) {
        echo "- {$user['username']}: {$user['full_name']}<br>";
    }
    
    echo "<h3>Test 3: Update Method Test</h3>";
    // Test update method với user admin
    $admin = $db->fetch("SELECT * FROM users WHERE username = ?", ['admin']);
    if ($admin) {
        echo "✓ Found admin user: {$admin['full_name']}<br>";
        
        // Test update last_login
        $current_time = date('Y-m-d H:i:s');
        echo "Updating last_login to: {$current_time}<br>";
        
        $result = $db->update('users', 
            ['last_login' => $current_time], 
            'id = ?', 
            [$admin['id']]
        );
        
        echo "✓ Update result: SUCCESS<br>";
        
        // Verify update
        $updated = $db->fetch("SELECT last_login FROM users WHERE id = ?", [$admin['id']]);
        echo "✓ Verified last_login: {$updated['last_login']}<br>";
    } else {
        echo "✗ Admin user not found<br>";
    }
    
    echo "<h3>Test 4: User Login Method</h3>";
    $userModel = new User();
    $loginResult = $userModel->login('admin', 'admin123');
    
    if ($loginResult) {
        echo "✓ Login SUCCESS: {$loginResult['full_name']} - {$loginResult['role_name']}<br>";
    } else {
        echo "✗ Login FAILED<br>";
    }
    
    echo "<h3>Test 5: Multiple Login Attempts</h3>";
    for ($i = 1; $i <= 3; $i++) {
        echo "Attempt {$i}: ";
        $result = $userModel->login('admin', 'admin123');
        if ($result) {
            echo "✓ SUCCESS<br>";
        } else {
            echo "✗ FAILED<br>";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}
?>
