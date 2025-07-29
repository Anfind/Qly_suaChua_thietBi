<?php
/**
 * Script sửa lỗi equipment types
 * Đảm bảo database có đủ dữ liệu loại thiết bị
 */

require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>🔧 FIXING EQUIPMENT TYPES...</h2>";
    
    // 1. Kiểm tra bảng equipment_types
    $existingTypes = $db->fetchAll("SELECT id, name FROM equipment_types ORDER BY id");
    echo "<h3>📋 Equipment Types hiện có:</h3>";
    
    if (empty($existingTypes)) {
        echo "<p>❌ Không có loại thiết bị nào! Đang thêm dữ liệu mẫu...</p>";
        
        // Thêm dữ liệu mẫu
        $sampleTypes = [
            ['name' => 'Máy tính', 'description' => 'Máy tính để bàn và laptop', 'icon' => 'fas fa-desktop'],
            ['name' => 'Máy in', 'description' => 'Máy in laser, phun, đa chức năng', 'icon' => 'fas fa-print'],
            ['name' => 'Điều hòa', 'description' => 'Máy lạnh, điều hòa không khí', 'icon' => 'fas fa-snowflake'],
            ['name' => 'Thiết bị mạng', 'description' => 'Router, Switch, Access Point', 'icon' => 'fas fa-network-wired'],
            ['name' => 'Thiết bị văn phòng', 'description' => 'Máy photocopy, máy scan, máy fax', 'icon' => 'fas fa-copy'],
            ['name' => 'Thiết bị âm thanh', 'description' => 'Loa, micro, amply', 'icon' => 'fas fa-volume-up'],
        ];
        
        foreach ($sampleTypes as $type) {
            $db->insert('equipment_types', $type);
            echo "✅ Đã thêm: {$type['name']}<br>";
        }
        
        $existingTypes = $db->fetchAll("SELECT id, name FROM equipment_types ORDER BY id");
    }
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    foreach ($existingTypes as $type) {
        echo "<tr><td>{$type['id']}</td><td>{$type['name']}</td></tr>";
    }
    echo "</table>";
    
    // 2. Kiểm tra thiết bị có type_id không đúng
    echo "<h3>🔍 Kiểm tra thiết bị có type_id không hợp lệ:</h3>";
    $invalidEquipments = $db->fetchAll(
        "SELECT e.id, e.code, e.name, e.type_id 
         FROM equipments e 
         LEFT JOIN equipment_types et ON e.type_id = et.id 
         WHERE e.type_id IS NOT NULL AND et.id IS NULL"
    );
    
    if (empty($invalidEquipments)) {
        echo "<p>✅ Tất cả thiết bị đều có type_id hợp lệ!</p>";
    } else {
        echo "<p>❌ Tìm thấy " . count($invalidEquipments) . " thiết bị có type_id không hợp lệ:</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Invalid Type ID</th></tr>";
        foreach ($invalidEquipments as $eq) {
            echo "<tr><td>{$eq['id']}</td><td>{$eq['code']}</td><td>{$eq['name']}</td><td>{$eq['type_id']}</td></tr>";
        }
        echo "</table>";
        
        // Cập nhật thành NULL hoặc type_id mặc định
        echo "<p>🔧 Đang cập nhật type_id thành NULL cho các thiết bị này...</p>";
        $db->query("UPDATE equipments SET type_id = NULL WHERE type_id NOT IN (SELECT id FROM equipment_types)");
        echo "<p>✅ Đã cập nhật xong!</p>";
    }
    
    // 3. Test form submission
    echo "<h3>📝 Test Form:</h3>";
    echo "<form method='POST' action='admin/equipments.php'>";
    echo "<select name='type_id'>";
    echo "<option value=''>-- Chọn loại thiết bị --</option>";
    foreach ($existingTypes as $type) {
        echo "<option value='{$type['id']}'>{$type['name']}</option>";
    }
    echo "</select>";
    echo "<input type='text' name='code' placeholder='Mã thiết bị' required>";
    echo "<input type='text' name='name' placeholder='Tên thiết bị' required>";
    echo "<input type='hidden' name='action' value='create'>";
    echo csrf_field();
    echo "<button type='submit'>Test Thêm Thiết Bị</button>";
    echo "</form>";
    
    echo "<h3>🎉 HOÀN TẤT!</h3>";
    echo "<p>✅ Database đã sẵn sàng. Bạn có thể thêm thiết bị mới bình thường.</p>";
    echo "<p><a href='admin/equipments.php'>🔗 Đi đến trang quản lý thiết bị</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ LỖI:</h3>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
