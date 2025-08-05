<?php
require_once 'config/config.php';

echo "=== TEST WORKFLOW NOTIFICATION SIMPLE ===\n";

$db = Database::getInstance();

// Lấy 1 request có workflow steps
$request = $db->fetch("
    SELECT DISTINCT rr.* 
    FROM repair_requests rr
    INNER JOIN repair_workflow_steps rws ON rr.id = rws.request_id
    LIMIT 1
");

if (!$request) {
    echo "Không tìm thấy request có workflow\n";
    exit;
}

echo "Request ID: {$request['id']}\n";
echo "Request Code: {$request['request_code']}\n\n";

// Lấy workflow steps
$steps = $db->fetchAll("
    SELECT rws.*, d.name as dept_name 
    FROM repair_workflow_steps rws
    LEFT JOIN departments d ON rws.assigned_department_id = d.id
    WHERE rws.request_id = ?
    ORDER BY rws.step_order
", [$request['id']]);

echo "=== WORKFLOW STEPS ===\n";
foreach ($steps as $step) {
    echo "Step {$step['step_order']}: {$step['step_name']} -> Phòng ban: {$step['dept_name']} (ID: {$step['assigned_department_id']})\n";
}
echo "\n";

// Lấy danh sách technician trong từng phòng ban
echo "=== TECHNICIAN TRONG TỪNG PHÒNG BAN ===\n";
$allTechIds = [];
foreach ($steps as $step) {
    $techs = $db->fetchAll("
        SELECT u.id, u.full_name, u.username
        FROM users u
        WHERE u.department_id = ?
        AND u.role_id = (SELECT id FROM roles WHERE name = 'technician')
        AND u.status = 'active'
    ", [$step['assigned_department_id']]);
    
    echo "Phòng ban {$step['dept_name']} (ID: {$step['assigned_department_id']}):\n";
    if (empty($techs)) {
        echo "  - Không có technician nào\n";
    } else {
        foreach ($techs as $tech) {
            echo "  - {$tech['full_name']} ({$tech['username']}) - ID: {$tech['id']}\n";
            $allTechIds[] = $tech['id'];
        }
    }
    echo "\n";
}

// Test notification với phòng ban cụ thể (chỉ phòng ban đầu tiên)
echo "=== TEST NOTIFICATION CHO 1 PHÒNG BAN ===\n";

$firstStep = $steps[0];
$testDeptIds = [$firstStep['assigned_department_id']];

echo "Test gửi notification cho ONLY phòng ban: {$firstStep['dept_name']} (ID: {$firstStep['assigned_department_id']})\n";

// Clear log cũ
if (file_exists('logs/error.log')) {
    file_put_contents('logs/error.log', '');
}

// Test notification
$result = notifyClerkSentToRepair(
    $request['id'], 
    $request['request_code'], 
    1, // clerk user id
    $testDeptIds
);

echo "Kết quả: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";

// Đọc log mới
echo "=== LOG MESSAGES ===\n";
if (file_exists('logs/error.log')) {
    $logs = file_get_contents('logs/error.log');
    if ($logs) {
        echo $logs;
    } else {
        echo "No logs found\n";
    }
}

// Kiểm tra notifications mới nhất
echo "\n=== NOTIFICATIONS MỚI NHẤT ===\n";
$notifications = $db->fetchAll("
    SELECT n.*, u.full_name as recipient_name, u.department_id, d.name as dept_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE n.created_at >= NOW() - INTERVAL 5 MINUTE
    ORDER BY n.created_at DESC
    LIMIT 10
", []);

if (empty($notifications)) {
    echo "Không có notifications mới nào\n";
} else {
    foreach ($notifications as $notif) {
        echo "- {$notif['recipient_name']} (Phòng: {$notif['dept_name']}): {$notif['title']} ({$notif['created_at']})\n";
    }
    echo "\nTotal notifications mới: " . count($notifications) . "\n";
    
    // Kiểm tra xem có gửi đúng phòng ban không
    $sentDepts = array_unique(array_column($notifications, 'department_id'));
    echo "Phòng ban nhận notification: " . implode(', ', $sentDepts) . "\n";
    echo "Phòng ban được chọn: " . implode(', ', $testDeptIds) . "\n";
    
    if (count($sentDepts) == 1 && $sentDepts[0] == $testDeptIds[0]) {
        echo "✅ ĐÚNG: Chỉ gửi cho phòng ban được chọn!\n";
    } else {
        echo "❌ SAI: Gửi cho nhiều phòng ban hoặc sai phòng ban!\n";
    }
}
?>
