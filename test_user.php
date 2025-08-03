<?php
require_once 'config/config.php';

echo "Testing User model...\n";

try {
    $db = Database::getInstance();
    echo "Database connected\n";
    
    // Test roles
    $roles = $db->fetchAll("SELECT * FROM roles");
    echo "Roles found: " . count($roles) . "\n";
    foreach ($roles as $role) {
        echo "- " . $role['display_name'] . "\n";
    }
    
    // Test departments
    $departments = $db->fetchAll("SELECT * FROM departments WHERE status = 'active'");
    echo "Departments found: " . count($departments) . "\n";
    foreach ($departments as $dept) {
        echo "- " . $dept['name'] . "\n";
    }
    
    // Test User model
    $userModel = new User();
    echo "User model created successfully\n";
    
    // Test create user
    $testData = [
        'username' => 'testuser' . time(),
        'password' => password_hash('123456', PASSWORD_DEFAULT),
        'full_name' => 'Test User',
        'email' => 'test@example.com',
        'role_id' => $roles[0]['id'] ?? 1
    ];
    
    $userId = $userModel->create($testData);
    echo "User created with ID: $userId\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
