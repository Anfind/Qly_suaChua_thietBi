<?php
/**
 * Script kiểm tra và sửa lỗi type_id trong equipments
 */

require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>🔧 KIỂM TRA VÀ SỬA LỖI TYPE_ID...</h2>";
    
    // 1. Kiểm tra bảng equipment_types có dữ liệu không
    $existingTypes = $db->fetchAll("SELECT id, name FROM equipment_types ORDER BY id");
    echo "<h3>📋 Equipment Types hiện có:</h3>";
    
    if (empty($existingTypes)) {
        echo "<p style='color: red;'>❌ Không có loại thiết bị nào! Đang thêm dữ liệu mẫu...</p>";
        
        // Thêm dữ liệu mẫu
        $sampleTypes = [
            ['name' => 'Máy tính', 'description' => 'Máy tính để bàn và laptop', 'icon' => 'fas fa-desktop'],
            ['name' => 'Máy in', 'description' => 'Máy in laser, phun, đa chức năng', 'icon' => 'fas fa-print'],
            ['name' => 'Điều hòa', 'description' => 'Máy lạnh, điều hòa không khí', 'icon' => 'fas fa-snowflake'],
            ['name' => 'Thiết bị mạng', 'description' => 'Router, Switch, Access Point', 'icon' => 'fas fa-network-wired'],
            ['name' => 'Thiết bị văn phòng', 'description' => 'Máy photocopy, máy scan, máy fax', 'icon' => 'fas fa-copy'],
        ];
        
        foreach ($sampleTypes as $type) {
            $db->insert('equipment_types', $type);
        }
        
        echo "<p style='color: green;'>✅ Đã thêm " . count($sampleTypes) . " loại thiết bị mẫu!</p>";
        
        // Lấy lại danh sách sau khi thêm
        $existingTypes = $db->fetchAll("SELECT id, name FROM equipment_types ORDER BY id");
    }
    
    // Hiển thị danh sách equipment types
    if (!empty($existingTypes)) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Tên loại thiết bị</th></tr>";
        foreach ($existingTypes as $type) {
            echo "<tr><td>{$type['id']}</td><td>{$type['name']}</td></tr>";
        }
        echo "</table><br>";
    }
    
    // 2. Kiểm tra thiết bị có type_id không hợp lệ
    echo "<h3>🔍 Thiết bị có type_id không hợp lệ:</h3>";
    $invalidEquipments = $db->fetchAll("
        SELECT e.id, e.code, e.name, e.type_id 
        FROM equipments e 
        WHERE e.type_id IS NOT NULL 
        AND e.type_id NOT IN (SELECT id FROM equipment_types)
    ");
    
    if (empty($invalidEquipments)) {
        echo "<p style='color: green;'>✅ Tất cả thiết bị đều có type_id hợp lệ!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Tìm thấy " . count($invalidEquipments) . " thiết bị có type_id không hợp lệ:</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Code</th><th>Name</th><th>Invalid Type ID</th></tr>";
        foreach ($invalidEquipments as $eq) {
            echo "<tr><td>{$eq['id']}</td><td>{$eq['code']}</td><td>{$eq['name']}</td><td>{$eq['type_id']}</td></tr>";
        }
        echo "</table>";
        
        // Cập nhật thành NULL
        echo "<p>🔧 Đang cập nhật type_id thành NULL cho các thiết bị này...</p>";
        $db->query("UPDATE equipments SET type_id = NULL WHERE type_id NOT IN (SELECT id FROM equipment_types)");
        echo "<p style='color: green;'>✅ Đã cập nhật xong!</p>";
    }
    
    // 3. Kiểm tra thiết bị có type_id = '' (empty string)
    echo "<h3>🔍 Thiết bị có type_id = '' (empty string):</h3>";
    $emptyTypeEquipments = $db->fetchAll("
        SELECT e.id, e.code, e.name, e.type_id 
        FROM equipments e 
        WHERE e.type_id = ''
    ");
    
    if (empty($emptyTypeEquipments)) {
        echo "<p style='color: green;'>✅ Không có thiết bị nào có type_id = ''</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Tìm thấy " . count($emptyTypeEquipments) . " thiết bị có type_id = '':</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Code</th><th>Name</th></tr>";
        foreach ($emptyTypeEquipments as $eq) {
            echo "<tr><td>{$eq['id']}</td><td>{$eq['code']}</td><td>{$eq['name']}</td></tr>";
        }
        echo "</table>";
        
        // Cập nhật thành NULL
        echo "<p>🔧 Đang cập nhật type_id = '' thành NULL...</p>";
        $db->query("UPDATE equipments SET type_id = NULL WHERE type_id = ''");
        echo "<p style='color: green;'>✅ Đã cập nhật xong!</p>";
    }
    
    // 4. Hiển thị thống kê cuối cùng
    echo "<h3>📊 Thống kê cuối cùng:</h3>";
    $stats = $db->fetchAll("
        SELECT 
            COALESCE(et.name, 'Chưa phân loại') as type_name,
            COUNT(e.id) as equipment_count
        FROM equipments e
        LEFT JOIN equipment_types et ON e.type_id = et.id
        WHERE e.status != 'disposed'
        GROUP BY e.type_id, et.name
        ORDER BY equipment_count DESC
    ");
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Loại thiết bị</th><th>Số lượng</th></tr>";
    foreach ($stats as $stat) {
        echo "<tr><td>{$stat['type_name']}</td><td>{$stat['equipment_count']}</td></tr>";
    }
    echo "</table>";
    
    echo "<br><p style='color: blue; font-weight: bold;'>🎉 Kiểm tra và sửa lỗi hoàn tất!</p>";
    echo "<p><a href='admin/equipments.php' style='color: green;'>➡️ Thử thêm thiết bị mới</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>Stack trace: " . $e->getTraceAsString() . "</p>";
}
?>
