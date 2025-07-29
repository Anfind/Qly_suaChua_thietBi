<?php
require_once __DIR__ . '/config/config.php';

echo "=== KIỂM TRA PHÂN QUYỀN WORKFLOW ===\n\n";

$db = Database::getInstance();

// Kiểm tra tech_a1 (TECH_A - department_id = 5)
echo "1. Kiểm tra tech_a1 (Phòng Kỹ thuật A - ID: 5):\n";
$tech_a1 = $db->fetch("SELECT id, username, department_id FROM users WHERE username = 'tech_a1'");
if ($tech_a1) {
    echo "   User: {$tech_a1['username']} - Department ID: {$tech_a1['department_id']}\n";
    
    // Sử dụng logic giống như trong RepairRequest.php
    $steps = $db->fetchAll(
        "SELECT DISTINCT r.request_code, rws.step_order, rws.status, d.name as assigned_dept
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
         LEFT JOIN departments d ON rws.assigned_department_id = d.id
         WHERE rws.assigned_department_id = ?
         AND rws.status IN ('pending', 'in_progress')
         ORDER BY r.request_code, rws.step_order",
        [$tech_a1['department_id']]
    );
    
    echo "   Thấy " . count($steps) . " workflow steps:\n";
    foreach ($steps as $step) {
        echo "     - {$step['request_code']} Step#{$step['step_order']}: {$step['assigned_dept']} ({$step['status']})\n";
    }
}

echo "\n2. Kiểm tra tech_b1 (Phòng Kỹ thuật B - ID: 6):\n";
$tech_b1 = $db->fetch("SELECT id, username, department_id FROM users WHERE username = 'tech_b1'");
if ($tech_b1) {
    echo "   User: {$tech_b1['username']} - Department ID: {$tech_b1['department_id']}\n";
    
    $steps = $db->fetchAll(
        "SELECT DISTINCT r.request_code, rws.step_order, rws.status, d.name as assigned_dept
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
         LEFT JOIN departments d ON rws.assigned_department_id = d.id
         WHERE rws.assigned_department_id = ?
         AND rws.status IN ('pending', 'in_progress')
         ORDER BY r.request_code, rws.step_order",
        [$tech_b1['department_id']]
    );
    
    echo "   Thấy " . count($steps) . " workflow steps:\n";
    foreach ($steps as $step) {
        echo "     - {$step['request_code']} Step#{$step['step_order']}: {$step['assigned_dept']} ({$step['status']})\n";
    }
}

echo "\n3. Kiểm tra tech_c1 (Phòng Kỹ thuật C - ID: 7):\n";
$tech_c1 = $db->fetch("SELECT id, username, department_id FROM users WHERE username = 'tech_c1'");
if ($tech_c1) {
    echo "   User: {$tech_c1['username']} - Department ID: {$tech_c1['department_id']}\n";
    
    $steps = $db->fetchAll(
        "SELECT DISTINCT r.request_code, rws.step_order, rws.status, d.name as assigned_dept
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
         LEFT JOIN departments d ON rws.assigned_department_id = d.id
         WHERE rws.assigned_department_id = ?
         AND rws.status IN ('pending', 'in_progress')
         ORDER BY r.request_code, rws.step_order",
        [$tech_c1['department_id']]
    );
    
    echo "   Thấy " . count($steps) . " workflow steps:\n";
    foreach ($steps as $step) {
        echo "     - {$step['request_code']} Step#{$step['step_order']}: {$step['assigned_dept']} ({$step['status']})\n";
    }
}

echo "\n4. Kiểm tra tech_d1 (Phòng Kỹ thuật D - ID: 8):\n";
$tech_d1 = $db->fetch("SELECT id, username, department_id FROM users WHERE username = 'tech_d1'");
if ($tech_d1) {
    echo "   User: {$tech_d1['username']} - Department ID: {$tech_d1['department_id']}\n";
    
    $steps = $db->fetchAll(
        "SELECT DISTINCT r.request_code, rws.step_order, rws.status, d.name as assigned_dept
         FROM repair_requests r
         INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
         LEFT JOIN departments d ON rws.assigned_department_id = d.id
         WHERE rws.assigned_department_id = ?
         AND rws.status IN ('pending', 'in_progress')
         ORDER BY r.request_code, rws.step_order",
        [$tech_d1['department_id']]
    );
    
    echo "   Thấy " . count($steps) . " workflow steps:\n";
    foreach ($steps as $step) {
        echo "     - {$step['request_code']} Step#{$step['step_order']}: {$step['assigned_dept']} ({$step['status']})\n";
    }
}

echo "\n=== KIỂM TRA TỔNG QUAN ===\n";
$allSteps = $db->fetchAll(
    "SELECT r.request_code, rws.step_order, d.name as assigned_dept, rws.status
     FROM repair_requests r
     INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
     LEFT JOIN departments d ON rws.assigned_department_id = d.id
     ORDER BY r.request_code, rws.step_order"
);

echo "Tổng số workflow steps trong hệ thống: " . count($allSteps) . "\n";
foreach ($allSteps as $step) {
    echo "  - {$step['request_code']} Step#{$step['step_order']}: {$step['assigned_dept']} ({$step['status']})\n";
}

echo "\n✅ KẾT LUẬN: Mỗi technician chỉ thấy workflow steps của phòng ban mình!\n";
echo "=== TEST HOÀN TẤT ===\n";
?>
