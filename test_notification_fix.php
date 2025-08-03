<?php
/**
 * TEST SCRIPT: Kiểm tra fix notification và dashboard 
 * Chạy để đảm bảo chỉ phòng ban trong workflow mới nhận thông báo
 */

require_once __DIR__ . '/config/config.php';

echo "🔧 **KIỂM TRA FIX NOTIFICATION VÀ DASHBOARD**\n\n";

$db = Database::getInstance();

try {
    echo "1️⃣ **Kiểm tra Dashboard Stats theo Phòng Ban:**\n";
    
    // Lấy tất cả phòng kỹ thuật
    $techDepts = $db->fetchAll(
        "SELECT id, code, name FROM departments 
         WHERE code LIKE 'TECH_%' 
         ORDER BY code"
    );
    
    foreach ($techDepts as $dept) {
        echo "   🏢 **{$dept['code']} ({$dept['name']}):**\n";
        
        // Đếm workflow tasks cho phòng này
        $workflowCount = $db->fetch(
            "SELECT COUNT(DISTINCT rws.request_id) as count
             FROM repair_workflow_steps rws
             WHERE rws.assigned_department_id = ?
             AND rws.status IN ('pending', 'in_progress')",
            [$dept['id']]
        )['count'];
        
        echo "      📊 Dashboard sẽ hiển thị: {$workflowCount} đơn\n";
        
        // Liệt kê các đơn cụ thể
        $tasks = $db->fetchAll(
            "SELECT DISTINCT r.request_code, rws.status as step_status
             FROM repair_workflow_steps rws
             INNER JOIN repair_requests r ON rws.request_id = r.id
             WHERE rws.assigned_department_id = ?
             AND rws.status IN ('pending', 'in_progress')
             ORDER BY r.request_code",
            [$dept['id']]
        );
        
        if (!empty($tasks)) {
            foreach ($tasks as $task) {
                echo "         📋 {$task['request_code']} ({$task['step_status']})\n";
            }
        } else {
            echo "         ✅ **ĐÚNG**: Không có đơn nào → không hiển thị thông báo\n";
        }
        echo "\n";
    }
    
    echo "\n2️⃣ **Kiểm tra Notification Logic:**\n";
    
    // Kiểm tra các đơn có workflow
    $requestsWithWorkflow = $db->fetchAll(
        "SELECT DISTINCT r.id, r.request_code
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
         ORDER BY r.request_code"
    );
    
    foreach ($requestsWithWorkflow as $request) {
        echo "   📋 **{$request['request_code']}:**\n";
        
        // Lấy workflow departments
        $workflowDepts = $db->fetchAll(
            "SELECT DISTINCT d.code, d.name
             FROM repair_workflow_steps rws
             INNER JOIN departments d ON rws.assigned_department_id = d.id
             WHERE rws.request_id = ?
             ORDER BY d.code",
            [$request['id']]
        );
        
        echo "      🔄 Workflow: ";
        $deptCodes = array_column($workflowDepts, 'code');
        echo implode(' → ', $deptCodes) . "\n";
        
        // Kiểm tra thông báo đã gửi
        $notifications = $db->fetchAll(
            "SELECT DISTINCT u.username, d.code as user_dept
             FROM notifications n
             INNER JOIN users u ON n.user_id = u.id
             INNER JOIN departments d ON u.department_id = d.id
             WHERE n.related_type = 'repair_request'
             AND n.related_id = ?
             AND n.title LIKE '%sửa chữa mới%'
             ORDER BY d.code",
            [$request['id']]
        );
        
        $notifiedDepts = array_unique(array_column($notifications, 'user_dept'));
        
        echo "      📢 Thông báo gửi cho: " . implode(', ', $notifiedDepts) . "\n";
        
        // So sánh
        $extraNotified = array_diff($notifiedDepts, $deptCodes);
        $missingNotified = array_diff($deptCodes, $notifiedDepts);
        
        if (empty($extraNotified) && empty($missingNotified)) {
            echo "      ✅ **ĐÚNG**: Thông báo chính xác theo workflow\n";
        } else {
            echo "      ❌ **SAI**: Có vấn đề!\n";
            if (!empty($extraNotified)) {
                echo "         🚫 Gửi thừa cho: " . implode(', ', $extraNotified) . "\n";
            }
            if (!empty($missingNotified)) {
                echo "         ⚠️ Thiếu thông báo cho: " . implode(', ', $missingNotified) . "\n";
            }
        }
        echo "\n";
    }
    
    echo "\n3️⃣ **Test Scenario - Tạo đơn chỉ có A và D:**\n";
    
    // Tìm đơn có workflow A → D
    $adRequest = $db->fetch(
        "SELECT r.id, r.request_code
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws1 ON r.id = rws1.request_id
         INNER JOIN departments d1 ON rws1.assigned_department_id = d1.id
         WHERE d1.code = 'TECH_A'
         AND EXISTS (
             SELECT 1 FROM repair_workflow_steps rws2
             INNER JOIN departments d2 ON rws2.assigned_department_id = d2.id
             WHERE rws2.request_id = r.id AND d2.code = 'TECH_D'
         )
         AND NOT EXISTS (
             SELECT 1 FROM repair_workflow_steps rws3
             INNER JOIN departments d3 ON rws3.assigned_department_id = d3.id
             WHERE rws3.request_id = r.id AND d3.code IN ('TECH_B', 'TECH_C')
         )
         LIMIT 1"
    );
    
    if ($adRequest) {
        echo "   📋 Đơn A→D: {$adRequest['request_code']}\n";
        
        // Kiểm tra technician C có nhận thông báo không
        $cNotifications = $db->fetchAll(
            "SELECT u.username
             FROM notifications n
             INNER JOIN users u ON n.user_id = u.id
             INNER JOIN departments d ON u.department_id = d.id
             WHERE n.related_id = ? AND d.code = 'TECH_C'
             AND n.title LIKE '%sửa chữa mới%'",
            [$adRequest['id']]
        );
        
        if (empty($cNotifications)) {
            echo "   ✅ **ĐÚNG**: Technician C KHÔNG nhận thông báo\n";
        } else {
            echo "   ❌ **SAI**: Technician C vẫn nhận thông báo: " . implode(', ', array_column($cNotifications, 'username')) . "\n";
        }
    } else {
        echo "   ⚠️ Không tìm thấy đơn A→D để test\n";
    }
    
    echo "\n4️⃣ **Khuyến nghị:**\n";
    echo "   • Nếu thấy ❌ SAI → cần clear cache browser và logout/login lại\n";
    echo "   • Dashboard chỉ hiển thị đúng số đơn của phòng ban\n";
    echo "   • Thông báo chỉ gửi cho phòng ban trong workflow\n";
    echo "   • Test bằng cách tạo đơn mới với workflow cụ thể\n";
    
    echo "\n✅ **KIỂM TRA HOÀN TẤT!**\n";

} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}
?>
