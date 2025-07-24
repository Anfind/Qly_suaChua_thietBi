<?php
error_reporting(E_ERROR | E_PARSE);

require_once 'config/config.php';

echo "=== TẠO USER LOGISTICS ===\n";

$db = Database::getInstance();

// Lấy role logistics
$sql = "SELECT id FROM roles WHERE name = 'logistics'";
$role = $db->fetch($sql);

if (!$role) {
    echo "✗ Role logistics không tồn tại\n";
    exit;
}

$role_id = $role['id'];
echo "✓ Role logistics ID: $role_id\n";

// Lấy department (sử dụng department đầu tiên)
$sql = "SELECT id FROM departments LIMIT 1";
$dept = $db->fetch($sql);
$dept_id = $dept['id'];

// Tạo user logistics
$userData = [
    'username' => 'logistics',
    'password' => 'logistics123', // plain text, sẽ được hash
    'full_name' => 'Nhân viên Giao liên',
    'email' => 'logistics@company.com',
    'phone' => '0987654321',
    'role_id' => $role_id,
    'department_id' => $dept_id,
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s')
];

$result = $db->insert('users', $userData);

if ($result) {
    echo "✓ Đã tạo user logistics thành công!\n";
    echo "  - Username: logistics\n";
    echo "  - Password: logistics123\n";
    echo "  - Role ID: $role_id\n";
    echo "  - Department ID: $dept_id\n";
} else {
    echo "✗ Lỗi tạo user logistics\n";
}

echo "\n=== DONE ===\n";
?>
