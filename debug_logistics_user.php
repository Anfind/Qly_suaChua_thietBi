<?php
require_once __DIR__ . '/config/config.php';

echo "<h2>Debug Logistics User</h2>";

if (!is_logged_in()) {
    echo "<p>Chưa đăng nhập</p>";
    echo "<a href='index.php'>Đăng nhập</a>";
    exit;
}

$user = current_user();
echo "<h3>Thông tin user hiện tại:</h3>";
echo "<pre>";
var_dump($user);
echo "</pre>";

echo "<h3>Session:</h3>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

echo "<h3>Kiểm tra quyền:</h3>";
echo "has_role('logistics'): " . (has_role('logistics') ? 'TRUE' : 'FALSE') . "<br>";
echo "has_role('admin'): " . (has_role('admin') ? 'TRUE' : 'FALSE') . "<br>";
echo "role_name: " . ($user['role_name'] ?? 'NULL') . "<br>";

$allowed_roles = ['logistics', 'admin'];
echo "in_array(role_name, allowed_roles): " . (in_array($user['role_name'], $allowed_roles) ? 'TRUE' : 'FALSE') . "<br>";
?>
