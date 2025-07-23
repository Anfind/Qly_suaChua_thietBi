<?php
require_once 'config/config.php';

echo "=== TEST LOGIN FUNCTION ===\n";

$userModel = new User();

// Test các user
$testUsers = [
    ['logistics1', 'user123'],
    ['user1', 'user123'],
    ['admin', 'admin123'],
    ['clerk1', 'user123'],
    ['tech1', 'user123']
];

foreach($testUsers as $test) {
    $username = $test[0];
    $password = $test[1];
    
    echo "\n--- Testing: $username / $password ---\n";
    
    $result = $userModel->login($username, $password);
    
    if ($result) {
        echo "✅ Login SUCCESS\n";
        echo "ID: {$result['id']}\n";
        echo "Username: {$result['username']}\n";
        echo "Full Name: {$result['full_name']}\n";
        echo "Role ID: {$result['role_id']}\n";
        echo "Role Name: {$result['role_name']}\n";
    } else {
        echo "❌ Login FAILED\n";
    }
}
?>
