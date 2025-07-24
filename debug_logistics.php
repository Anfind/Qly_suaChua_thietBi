<?php
error_reporting(E_ERROR | E_PARSE);

require_once 'config/config.php';

echo "=== KIỂM TRA USER LOGISTICS ===\n";

$db = Database::getInstance();

// Kiểm tra user logistics
$sql = "SELECT u.*, r.name as role_name FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.username = 'logistics'";
$user = $db->fetch($sql);

if ($user) {
    echo "✓ User logistics tồn tại:\n";
    echo "  - ID: " . $user['id'] . "\n";
    echo "  - Username: " . $user['username'] . "\n";
    echo "  - Full Name: " . $user['full_name'] . "\n";
    echo "  - Role: " . $user['role_name'] . "\n";
    echo "  - Active: " . ($user['status'] === 'active' ? 'Yes' : 'No') . "\n";
} else {
    echo "✗ User logistics không tồn tại\n";
}

// Kiểm tra repair requests để test
echo "\n=== KIỂM TRA ĐƠN SỬA CHỮA ===\n";
$sql = "SELECT r.id, r.request_code, s.code as status_code, s.name as status_name 
        FROM repair_requests r 
        LEFT JOIN repair_statuses s ON r.current_status_id = s.id 
        WHERE s.code IN ('PENDING_HANDOVER', 'RETRIEVED')
        LIMIT 3";
$requests = $db->fetchAll($sql);

if ($requests) {
    echo "✓ Có " . count($requests) . " đơn để test logistics:\n";
    foreach ($requests as $req) {
        echo "  - Đơn #{$req['id']}: {$req['request_code']} - {$req['status_name']}\n";
    }
} else {
    echo "✗ Không có đơn nào để test logistics\n";
}

echo "\n=== DONE ===\n";
?>
