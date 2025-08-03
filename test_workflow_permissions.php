<?php
/**
 * SCRIPT TEST PHÂN QUYỀN WORKFLOW NOTIFICATION
 * Kiểm tra xem thông báo có đúng chỉ gửi cho phòng ban tham gia workflow không
 */

require_once __DIR__ . '/config/config.php';

echo "🔍 **KIỂM TRA PHÂN QUYỀN THÔNG BÁO WORKFLOW**\n\n";

$db = Database::getInstance();

try {
    // 1. Lấy tất cả repair requests có workflow
    echo "1️⃣ **Danh sách các đơn có workflow:**\n";
    $requests = $db->fetchAll(
        "SELECT DISTINCT r.id, r.request_code, r.problem_description
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
         ORDER BY r.request_code"
    );
    
    foreach ($requests as $request) {
        echo "   📋 {$request['request_code']}: {$request['problem_description']}\n";
        
        // Lấy workflow steps cho đơn này
        $steps = $db->fetchAll(
            "SELECT rws.step_order, d.name as department_name, d.code as department_code
             FROM repair_workflow_steps rws
             LEFT JOIN departments d ON rws.assigned_department_id = d.id
             WHERE rws.request_id = ?
             ORDER BY rws.step_order",
            [$request['id']]
        );
        
        echo "      🔄 Workflow: ";
        $workflow_depts = [];
        foreach ($steps as $step) {
            $workflow_depts[] = $step['department_code'];
            echo $step['department_code'];
            if ($step['step_order'] < count($steps)) echo " → ";
        }
        echo "\n";
        
        // Kiểm tra ai nhận thông báo cho đơn này
        $notifications = $db->fetchAll(
            "SELECT n.title, n.message, u.username, u.full_name, d.code as user_dept
             FROM notifications n
             INNER JOIN users u ON n.user_id = u.id
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE n.related_type = 'repair_request' 
             AND n.related_id = ?
             AND n.title LIKE '%sửa chữa mới%'
             ORDER BY n.created_at DESC",
            [$request['id']]
        );
        
        echo "      📢 Thông báo gửi cho:\n";
        $notified_depts = [];
        foreach ($notifications as $notif) {
            $notified_depts[] = $notif['user_dept'];
            echo "         👤 {$notif['username']} ({$notif['user_dept']}) - {$notif['full_name']}\n";
        }
        
        // So sánh workflow vs notification
        $workflow_depts = array_unique($workflow_depts);
        $notified_depts = array_unique($notified_depts);
        
        $should_notify = $workflow_depts;
        $actually_notified = $notified_depts;
        
        $extra_notified = array_diff($actually_notified, $should_notify);
        $missing_notified = array_diff($should_notify, $actually_notified);
        
        if (empty($extra_notified) && empty($missing_notified)) {
            echo "      ✅ **ĐÚNG**: Chỉ thông báo cho phòng ban trong workflow\n";
        } else {
            echo "      ❌ **SAI**: Có vấn đề phân quyền!\n";
            if (!empty($extra_notified)) {
                echo "         🚫 Thông báo thừa cho: " . implode(', ', $extra_notified) . "\n";
            }
            if (!empty($missing_notified)) {
                echo "         ⚠️ Thiếu thông báo cho: " . implode(', ', $missing_notified) . "\n";
            }
        }
        
        echo "\n";
    }
    
    // 2. Kiểm tra dashboard stats cho từng phòng ban
    echo "\n2️⃣ **Kiểm tra Dashboard Stats:**\n";
    $tech_depts = $db->fetchAll(
        "SELECT DISTINCT d.id, d.code, d.name
         FROM departments d
         WHERE d.code LIKE 'TECH_%'
         ORDER BY d.code"
    );
    
    foreach ($tech_depts as $dept) {
        echo "   🏢 **{$dept['code']} - {$dept['name']}:**\n";
        
        // Đếm đơn theo workflow
        $workflow_count = $db->fetch(
            "SELECT COUNT(DISTINCT rws.request_id) as count
             FROM repair_workflow_steps rws
             INNER JOIN repair_requests r ON rws.request_id = r.id
             WHERE rws.assigned_department_id = ?
             AND rws.status IN ('pending', 'in_progress')",
            [$dept['id']]
        )['count'];
        
        echo "      📊 Workflow tasks: {$workflow_count}\n";
        
        // Lấy danh sách cụ thể
        $tasks = $db->fetchAll(
            "SELECT DISTINCT r.request_code, rws.status as step_status
             FROM repair_workflow_steps rws
             INNER JOIN repair_requests r ON rws.request_id = r.id
             WHERE rws.assigned_department_id = ?
             AND rws.status IN ('pending', 'in_progress')
             ORDER BY r.request_code",
            [$dept['id']]
        );
        
        foreach ($tasks as $task) {
            echo "         📋 {$task['request_code']} ({$task['step_status']})\n";
        }
        
        if (empty($tasks)) {
            echo "         ✅ Không có đơn nào (đúng nếu phòng ban không tham gia workflow)\n";
        }
        
        echo "\n";
    }
    
    // 3. Test function notification helpers
    echo "\n3️⃣ **Test Notification Helper Functions:**\n";
    
    if (function_exists('notifyClerkSentToRepair')) {
        echo "   ✅ notifyClerkSentToRepair() exists\n";
    } else {
        echo "   ❌ notifyClerkSentToRepair() missing\n";
    }
    
    if (function_exists('getUnreadNotificationCount')) {
        echo "   ✅ getUnreadNotificationCount() exists\n";
    } else {
        echo "   ❌ getUnreadNotificationCount() missing\n";
    }
    
    echo "\n🎯 **KẾT LUẬN:**\n";
    echo "1. Kiểm tra các đơn workflow xem thông báo có đúng không\n";
    echo "2. Kiểm tra dashboard stats có chính xác không\n";
    echo "3. Nếu vẫn có vấn đề, có thể do cache browser hoặc session cũ\n";
    echo "\n✅ **HOÀN TẤT KIỂM TRA**\n";
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}
?>
