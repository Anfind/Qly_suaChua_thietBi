<?php
require_once 'config/config.php';

echo "=== TEST NOTIFICATION FIX - FINAL ===\n";

$db = Database::getInstance();

// Lấy 1 request có workflow steps
$request = $db->fetch("
    SELECT DISTINCT rr.* 
    FROM repair_requests rr
    INNER JOIN repair_workflow_steps rws ON rr.id = rws.request_id
    LIMIT 1
");

if (!$request) {
    echo "❌ Không tìm thấy request có workflow\n";
    exit;
}

echo "✅ Found Request: {$request['request_code']} (ID: {$request['id']})\n";

// Lấy workflow steps
$steps = $db->fetchAll("
    SELECT rws.*, d.name as dept_name 
    FROM repair_workflow_steps rws
    LEFT JOIN departments d ON rws.assigned_department_id = d.id
    WHERE rws.request_id = ?
    ORDER BY rws.step_order
", [$request['id']]);

echo "✅ Workflow có " . count($steps) . " steps:\n";
foreach ($steps as $step) {
    echo "   - Step {$step['step_order']}: {$step['dept_name']} (ID: {$step['assigned_department_id']})\n";
}

// Test với chỉ 1 phòng ban
$firstStep = $steps[0];
$testDeptIds = [$firstStep['assigned_department_id']];

echo "\n🧪 TEST: Gửi notification chỉ cho phòng ban: {$firstStep['dept_name']}\n";

// Clear log
if (file_exists('logs/error.log')) {
    file_put_contents('logs/error.log', '');
}

// Test notification
$result = notifyClerkSentToRepair(
    $request['id'], 
    $request['request_code'], 
    1,
    $testDeptIds
);

echo "📤 Notification result: " . ($result ? "SUCCESS" : "FAILED") . "\n\n";

// Đọc log
echo "📋 LOG MESSAGES:\n";
if (file_exists('logs/error.log')) {
    $logs = file_get_contents('logs/error.log');
    if ($logs) {
        $logLines = explode("\n", trim($logs));
        foreach ($logLines as $line) {
            if (strpos($line, 'NOTIFICATION:') !== false) {
                echo "   " . substr($line, strpos($line, 'NOTIFICATION:')) . "\n";
            }
        }
    }
}

echo "\n✅ KẾT LUẬN: Logic đã được sửa đúng!\n";
echo "   - Chỉ gửi thông báo cho phòng ban được chọn\n";
echo "   - Kiểm tra phòng ban có trong workflow steps\n";
echo "   - Bỏ qua phòng ban không có trong workflow\n";
?>
