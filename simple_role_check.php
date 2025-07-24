<?php
require_once 'config/config.php';

echo "<h2>Simple Role Check</h2>";

if (!is_logged_in()) {
    echo "<p>Please login first</p>";
    exit;
}

$user = current_user();
echo "<p>Username: " . ($user['username'] ?? 'N/A') . "</p>";
echo "<p>Role Name: " . ($user['role_name'] ?? 'N/A') . "</p>";
echo "<p>Role ID: " . ($user['role_id'] ?? 'N/A') . "</p>";

$allowed = in_array($user['role_name'] ?? '', ['logistics', 'admin']);
echo "<p>Has logistics permission: " . ($allowed ? 'YES' : 'NO') . "</p>";

// Check actual roles in database
$db = Database::getInstance();
$roles = $db->fetchAll("SELECT id, name, display_name FROM roles");
echo "<h3>Available roles:</h3>";
foreach ($roles as $role) {
    echo "<p>ID: {$role['id']}, Name: {$role['name']}, Display: {$role['display_name']}</p>";
}
?>
