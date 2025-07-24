<?php
require_once 'config/config.php';

echo "=== DEBUG LOGISTICS ROLE ===\n\n";

// Check if logged in
if (!is_logged_in()) {
    echo "KHÔNG ĐĂNG NHẬP\n";
    echo "Session: " . print_r($_SESSION, true) . "\n";
    exit;
}

$user = current_user();
echo "Current User:\n";
echo "ID: " . $user['id'] . "\n";
echo "Username: " . $user['username'] . "\n"; 
echo "Full Name: " . $user['full_name'] . "\n";
echo "Role Name: " . $user['role_name'] . "\n";
echo "Role ID: " . $user['role_id'] . "\n\n";

// Test has_any_role
$roles_to_test = ['logistics', 'admin'];
echo "Testing has_any_role(['logistics', 'admin']):\n";
$result = has_any_role($roles_to_test);
echo "Result: " . ($result ? 'TRUE' : 'FALSE') . "\n\n";

// Test individual role
echo "Testing individual roles:\n";
echo "has_role('logistics'): " . (has_role('logistics') ? 'TRUE' : 'FALSE') . "\n";
echo "has_role('admin'): " . (has_role('admin') ? 'TRUE' : 'FALSE') . "\n\n";

// Check in_array logic
echo "in_array check:\n";
echo "user role_name: '" . $user['role_name'] . "'\n";
echo "search array: " . print_r($roles_to_test, true) . "\n";
echo "in_array result: " . (in_array($user['role_name'], $roles_to_test) ? 'TRUE' : 'FALSE') . "\n\n";

// Show all users with logistics role
$db = Database::getInstance();
$logistics_users = $db->fetchAll(
    "SELECT u.*, r.name as role_name 
     FROM users u 
     LEFT JOIN roles r ON u.role_id = r.id 
     WHERE r.name = 'logistics'"
);

echo "All logistics users:\n";
foreach ($logistics_users as $lu) {
    echo "- ID: {$lu['id']}, Username: {$lu['username']}, Role: {$lu['role_name']}\n";
}
?>
