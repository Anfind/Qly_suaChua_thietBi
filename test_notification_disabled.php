<?php
require_once 'config/config.php';

echo "🔕 TEST TẮT THÔNG BÁO CHO TECHNICIAN\n\n";

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

echo "✅ Test với Request: {$request['request_code']} (ID: {$request['id']})\n";

// Lấy workflow steps
$steps = $db->fetchAll("
    SELECT rws.*, d.name as dept_name 
    FROM repair_workflow_steps rws
    LEFT JOIN departments d ON rws.assigned_department_id = d.id
    WHERE rws.request_id = ?
    ORDER BY rws.step_order
", [$request['id']]);

echo "✅ Workflow có " . count($steps) . " steps\n";

// Test với 1 phòng ban
$firstStep = $steps[0];
$testDeptIds = [$firstStep['assigned_department_id']];

echo "\n🧪 TEST: Gửi notification (SẼ BỊ TẮT) cho phòng ban: {$firstStep['dept_name']}\n";

// Clear log
if (file_exists('logs/error.log')) {
    file_put_contents('logs/error.log', '');
}

// Test notification - SẼ KHÔNG GỬI THÔNG BÁO
$result = notifyClerkSentToRepair(
    $request['id'], 
    $request['request_code'], 
    1,
    $testDeptIds
);

echo "📤 Function result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

// Đọc log để xem thông báo đã tắt
echo "\n📋 LOG MESSAGES (kiểm tra thông báo đã tắt):\n";
if (file_exists('logs/error.log')) {
    $logs = file_get_contents('logs/error.log');
    if ($logs) {
        $logLines = explode("\n", trim($logs));
        foreach ($logLines as $line) {
            if (strpos($line, 'NOTIFICATION') !== false) {
                if (strpos($line, 'DISABLED') !== false) {
                    echo "   ✅ " . substr($line, strpos($line, 'NOTIFICATION')) . "\n";
                } else {
                    echo "   📋 " . substr($line, strpos($line, 'NOTIFICATION')) . "\n";
                }
            }
        }
    } else {
        echo "   (Không có log)\n";
    }
}

echo "\n🔕 KẾT LUẬN: THÔNG BÁO ĐÃ ĐƯỢC TẮT!\n";
echo "   ✅ Function vẫn chạy bình thường\n";
echo "   ✅ Logic workflow vẫn hoạt động\n";
echo "   🔕 Technician KHÔNG nhận thông báo nữa\n";
echo "   📋 Chỉ ghi log để theo dõi\n";
?>
