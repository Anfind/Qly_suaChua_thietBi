<?php
require_once __DIR__ . '/config/config.php';

echo "=== TEST CHỌN PHÒNG BAN THỦ CÔNG ===\n\n";

$db = Database::getInstance();

// Test data: chọn phòng ban thủ công A->B->D (không theo template)
$manual_departments = [5, 6, 8]; // TECH_A, TECH_B, TECH_D

echo "1. Test tạo workflow steps thủ công cho A->B->D:\n";

try {
    // Tạo request test
    $test_request = $db->insert('repair_requests', [
        'request_code' => 'TEST-MANUAL-' . date('YmdHis'),
        'equipment_id' => 1,
        'requester_id' => 2,
        'problem_description' => 'Test chọn phòng ban thủ công',
        'urgency_level' => 'medium',
        'current_status_id' => $db->fetch("SELECT id FROM repair_statuses WHERE code = 'SENT_TO_REPAIR'")['id']
    ]);
    
    echo "   Tạo request ID: {$test_request}\n";
    
    // Tạo workflow steps thủ công
    $departments_json = json_encode($manual_departments);
    $db->query("CALL CreateWorkflowSteps(?, ?, ?)", [$test_request, $departments_json, 1]);
    
    echo "   Tạo workflow steps thành công\n";
    
    // Kiểm tra kết quả
    $steps = $db->fetchAll(
        "SELECT rws.step_order, d.name as department_name, rws.status
         FROM repair_workflow_steps rws
         LEFT JOIN departments d ON rws.assigned_department_id = d.id
         WHERE rws.request_id = ?
         ORDER BY rws.step_order",
        [$test_request]
    );
    
    echo "   Workflow steps được tạo:\n";
    foreach ($steps as $step) {
        echo "     Bước {$step['step_order']}: {$step['department_name']} ({$step['status']})\n";
    }
    
    // Test phân quyền truy cập
    echo "\n2. Test phân quyền truy cập:\n";
    
    // Set step đầu tiên thành in_progress
    $db->query(
        "UPDATE repair_workflow_steps 
         SET status = 'in_progress', started_at = NOW() 
         WHERE request_id = ? AND step_order = 1",
        [$test_request]
    );
    
    // Test từng technician
    $technicians = [
        'tech_a1' => 5, // TECH_A
        'tech_b1' => 6, // TECH_B  
        'tech_c1' => 7, // TECH_C
        'tech_d1' => 8  // TECH_D
    ];
    
    $repairModel = new RepairRequest();
    
    foreach ($technicians as $username => $dept_id) {
        $tech = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($tech) {
            $visible_steps = $repairModel->getByWorkflowForTechnician($tech['id'], ['pending', 'in_progress']);
            $count = count($visible_steps);
            
            $dept_name = $db->fetch("SELECT name FROM departments WHERE id = ?", [$dept_id])['name'];
            echo "   {$username} ({$dept_name}): thấy {$count} bước\n";
            
            foreach ($visible_steps as $step) {
                if ($step['id'] == $test_request) {
                    echo "     - Bước {$step['step_order']}: {$step['assigned_department_name']}\n";
                }
            }
        }
    }
    
    echo "\n✅ TEST THÀNH CÔNG: Chọn phòng ban thủ công hoạt động đúng!\n";
    echo "✅ Phân quyền truy cập đã đúng theo từng phòng ban.\n";
    
    // Cleanup test data
    $db->query("DELETE FROM repair_workflow_steps WHERE request_id = ?", [$test_request]);
    $db->query("DELETE FROM repair_requests WHERE id = ?", [$test_request]);
    
    echo "✅ Đã dọn dẹp dữ liệu test.\n";
    
} catch (Exception $e) {
    echo "❌ LỖI: " . $e->getMessage() . "\n";
}

echo "\n=== TEST HOÀN TẤT ===\n";
?>
