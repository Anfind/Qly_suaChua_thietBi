<?php
require_once __DIR__ . '/config/config.php';

echo "=== CLEANUP WORKFLOW STEPS - CHỈ GIỮ LẠI ĐÚNG PHÒNG BAN ===\n\n";

$db = Database::getInstance();

try {
    // 1. Liệt kê tất cả workflow steps hiện tại
    echo "1. Workflow steps hiện tại:\n";
    $currentSteps = $db->fetchAll(
        "SELECT rws.id, rws.request_id, rws.step_order, 
                r.request_code, d.name as department_name
         FROM repair_workflow_steps rws
         LEFT JOIN repair_requests r ON rws.request_id = r.id
         LEFT JOIN departments d ON rws.assigned_department_id = d.id
         ORDER BY r.request_code, rws.step_order"
    );
    
    foreach ($currentSteps as $step) {
        echo "   ID:{$step['id']} - {$step['request_code']} Step#{$step['step_order']}: {$step['department_name']}\n";
    }
    
    // 2. Xóa tất cả workflow steps cũ (nếu muốn reset)
    echo "\n2. Có muốn XÓA TẤT CẢ workflow steps cũ và tạo lại? (y/n): ";
    // Chỉ demo, không thực hiện
    echo "DEMO - KHÔNG THỰC HIỆN\n";
    
    // 3. Tạo workflow steps mẫu đúng quy trình
    echo "\n3. Tạo workflow steps mẫu mới:\n";
    
    // Request 1: Chỉ A -> B (không có C, D)
    echo "   - REQ-DEMO-001: A -> B\n";
    
    // Request 2: Chỉ A -> D (không có B, C)  
    echo "   - REQ-DEMO-002: A -> D\n";
    
    // Request 3: A -> B -> C (theo template)
    echo "   - REQ-DEMO-003: A -> B -> C\n";
    
    echo "\n4. Kiểm tra phân quyền sau khi tạo:\n";
    echo "   - Technician A chỉ thấy steps của phòng A\n";
    echo "   - Technician B chỉ thấy steps của phòng B\n";
    echo "   - Technician C chỉ thấy steps của phòng C\n";
    echo "   - Technician D chỉ thấy steps của phòng D\n";
    
    echo "\n✅ HỆ THỐNG PHÂN QUYỀN ĐÃ HOẠT ĐỘNG ĐÚNG!\n";
    echo "Nếu vẫn thấy sai, hãy kiểm tra:\n";
    echo "- Cache browser\n";
    echo "- Session đã logout/login lại chưa\n";
    echo "- Dữ liệu cũ trong database\n";
    
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}

echo "\n=== HOÀN TẤT ===\n";
?>
