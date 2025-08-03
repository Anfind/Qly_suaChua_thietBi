<?php
/**
 * CLEANUP SCRIPT: Xóa thông báo sai và cập nhật dữ liệu
 * Chạy script này để dọn dẹp thông báo đã gửi sai
 */

require_once __DIR__ . '/config/config.php';

echo "🧹 **CLEANUP NOTIFICATIONS - XÓA THÔNG BÁO SAI**\n\n";

$db = Database::getInstance();

try {
    echo "1️⃣ **Tìm và xóa thông báo gửi sai:**\n";
    
    // Lấy tất cả đơn có workflow
    $requestsWithWorkflow = $db->fetchAll(
        "SELECT DISTINCT r.id, r.request_code
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id"
    );
    
    $cleanedCount = 0;
    
    foreach ($requestsWithWorkflow as $request) {
        // Lấy danh sách phòng ban trong workflow
        $workflowDepts = $db->fetchAll(
            "SELECT DISTINCT d.id
             FROM repair_workflow_steps rws
             INNER JOIN departments d ON rws.assigned_department_id = d.id
             WHERE rws.request_id = ?",
            [$request['id']]
        );
        $workflowDeptIds = array_column($workflowDepts, 'id');
        
        if (empty($workflowDeptIds)) continue;
        
        // Tìm thông báo gửi cho user KHÔNG thuộc workflow departments
        $wrongNotifications = $db->fetchAll(
            "SELECT n.id, n.title, u.username, d.code as dept_code
             FROM notifications n
             INNER JOIN users u ON n.user_id = u.id
             INNER JOIN departments d ON u.department_id = d.id
             WHERE n.related_type = 'repair_request'
             AND n.related_id = ?
             AND n.title LIKE '%sửa chữa mới%'
             AND u.department_id NOT IN (" . implode(',', array_fill(0, count($workflowDeptIds), '?')) . ")",
            array_merge([$request['id']], $workflowDeptIds)
        );
        
        if (!empty($wrongNotifications)) {
            echo "   📋 {$request['request_code']}: Tìm thấy " . count($wrongNotifications) . " thông báo sai\n";
            
            foreach ($wrongNotifications as $notif) {
                echo "      🗑️ Xóa thông báo cho {$notif['username']} ({$notif['dept_code']})\n";
                
                // Xóa thông báo sai
                $db->query("DELETE FROM notifications WHERE id = ?", [$notif['id']]);
                $cleanedCount++;
            }
        }
    }
    
    echo "\n2️⃣ **Kết quả:**\n";
    echo "   🧹 Đã xóa {$cleanedCount} thông báo sai\n";
    
    echo "\n3️⃣ **Tạo thông báo mới đúng (nếu cần):**\n";
    
    // Tìm các đơn còn thiếu thông báo đúng
    foreach ($requestsWithWorkflow as $request) {
        $workflowDepts = $db->fetchAll(
            "SELECT DISTINCT d.id, d.code
             FROM repair_workflow_steps rws
             INNER JOIN departments d ON rws.assigned_department_id = d.id
             WHERE rws.request_id = ?",
            [$request['id']]
        );
        
        foreach ($workflowDepts as $dept) {
            // Kiểm tra đã có thông báo cho phòng này chưa
            $existingNotif = $db->fetch(
                "SELECT COUNT(*) as count
                 FROM notifications n
                 INNER JOIN users u ON n.user_id = u.id
                 WHERE n.related_id = ? AND u.department_id = ?
                 AND n.title LIKE '%sửa chữa mới%'",
                [$request['id'], $dept['id']]
            )['count'];
            
            if ($existingNotif == 0) {
                // Tạo thông báo mới cho phòng này
                $technicianUsers = $db->fetchAll(
                    "SELECT id FROM users 
                     WHERE department_id = ?
                     AND role_id = (SELECT id FROM roles WHERE name = 'technician')
                     AND status = 'active'",
                    [$dept['id']]
                );
                
                if (!empty($technicianUsers)) {
                    foreach ($technicianUsers as $user) {
                        $db->insert('notifications', [
                            'user_id' => $user['id'],
                            'title' => '🔧 Đơn sửa chữa mới',
                            'message' => "Đơn {$request['request_code']} đã được giao cho kỹ thuật, cần xử lý",
                            'type' => 'info',
                            'related_type' => 'repair_request',
                            'related_id' => $request['id'],
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    echo "   ✅ Tạo thông báo mới cho phòng {$dept['code']}\n";
                }
            }
        }
    }
    
    echo "\n4️⃣ **Kiểm tra lại sau cleanup:**\n";
    
    // Kiểm tra lại
    foreach ($requestsWithWorkflow as $request) {
        $workflowDepts = $db->fetchAll(
            "SELECT DISTINCT d.code
             FROM repair_workflow_steps rws
             INNER JOIN departments d ON rws.assigned_department_id = d.id
             WHERE rws.request_id = ?
             ORDER BY d.code",
            [$request['id']]
        );
        $workflowCodes = array_column($workflowDepts, 'code');
        
        $notifiedDepts = $db->fetchAll(
            "SELECT DISTINCT d.code
             FROM notifications n
             INNER JOIN users u ON n.user_id = u.id
             INNER JOIN departments d ON u.department_id = d.id
             WHERE n.related_id = ? AND n.title LIKE '%sửa chữa mới%'
             ORDER BY d.code",
            [$request['id']]
        );
        $notifiedCodes = array_column($notifiedDepts, 'code');
        
        $extraNotified = array_diff($notifiedCodes, $workflowCodes);
        
        if (empty($extraNotified)) {
            echo "   ✅ {$request['request_code']}: Thông báo đã đúng\n";
        } else {
            echo "   ❌ {$request['request_code']}: Vẫn còn thông báo thừa cho " . implode(', ', $extraNotified) . "\n";
        }
    }
    
    echo "\n🎉 **CLEANUP HOÀN TẤT!**\n";
    echo "   • Xóa thông báo sai: {$cleanedCount}\n";
    echo "   • Hệ thống thông báo đã chuẩn hóa theo workflow\n";
    echo "   • Logout/login lại để thấy kết quả\n";

} catch (Exception $e) {
    echo "❌ Lỗi cleanup: " . $e->getMessage() . "\n";
}
?>
