<?php
require_once 'config/config.php';

echo "=== KIỂM TRA DỮ LIỆU USERS ===\n";

$db = Database::getInstance();
$users = $db->fetchAll('SELECT id, username, password, full_name, role_id FROM users ORDER BY id');

foreach($users as $user) {
    echo "ID: {$user['id']}, Username: {$user['username']}, Password: {$user['password']}, Name: {$user['full_name']}, Role: {$user['role_id']}\n";
}

echo "\n=== KIỂM TRA ROLES ===\n";
$roles = $db->fetchAll('SELECT id, name, display_name FROM roles ORDER BY id');

foreach($roles as $role) {
    echo "ID: {$role['id']}, Name: {$role['name']}, Display: {$role['display_name']}\n";
}
?>
