<?php
require_once 'config/config.php';

echo "=== TEST WORKFLOW NOTIFICATION DETAIL ===\n";

$db = Database::getInstance();

// Lấy 1 request có workflow
$request = $db->fetch("
    SELECT rr.*, rwt.template_name 
    FROM repair_requests rr
    LEFT JOIN repair_workflow_templates rwt ON rr.workflow_template_id = rwt.id
    WHERE rr.workflow_template_id IS NOT NULL
    LIMIT 1
");

if (!$request) {
    echo "Không tìm thấy request có workflow\n";
    exit;
}

echo "Request ID: {$request['id']}\n";
echo "Request Code: {$request['request_code']}\n";
echo "Workflow Template: {$request['template_name']}\n\n";

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
        }
    }
    echo "\n";
}

// Test notification với phòng ban cụ thể
echo "=== TEST NOTIFICATION ===\n";

// Lấy department ID đầu tiên
$firstStep = $steps[0];
$testDeptIds = [$firstStep['assigned_department_id']];

echo "Test gửi notification cho phòng ban: {$firstStep['dept_name']} (ID: {$firstStep['assigned_department_id']})\n";

// Clear log cũ
file_put_contents('logs/error.log', '');

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
$logs = file_get_contents('logs/error.log');
if ($logs) {
    echo $logs;
} else {
    echo "No logs found\n";
}

// Kiểm tra notifications đã tạo
echo "\n=== NOTIFICATIONS ĐÃ TẠO ===\n";
$notifications = $db->fetchAll("
    SELECT n.*, u.full_name as recipient_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.reference_type = 'repair_request' 
    AND n.reference_id = ?
    ORDER BY n.created_at DESC
    LIMIT 10
", [$request['id']]);

foreach ($notifications as $notif) {
    echo "- {$notif['recipient_name']}: {$notif['title']} ({$notif['created_at']})\n";
}

echo "\nTotal notifications: " . count($notifications) . "\n";
?>
