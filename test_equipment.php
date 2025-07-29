<?php
require_once __DIR__ . '/config/config.php';

echo "<h2>🧪 TEST THÊM THIẾT BỊ</h2>";

try {
    $db = Database::getInstance();
    $equipment = new Equipment();
    
    // Test case 1: Thêm thiết bị với type_id hợp lệ
    echo "<h3>✅ Test 1: Thêm thiết bị với type_id hợp lệ</h3>";
    $data1 = [
        'name' => 'Test Equipment 1',
        'code' => 'TEST001',
        'type_id' => 1, // Máy tính
        'model' => 'Test Model',
        'brand' => 'Test Brand',
        'status' => 'active'
    ];
    
    $id1 = $equipment->create($data1);
    echo "✅ Thành công! ID: $id1<br>";
    
    // Xóa test data
    $equipment->delete($id1);
    echo "🗑️ Đã xóa test data<br><br>";
    
    // Test case 2: Thêm thiết bị với type_id null
    echo "<h3>❌ Test 2: Thêm thiết bị với type_id = null</h3>";
    try {
        $data2 = [
            'name' => 'Test Equipment 2',
            'code' => 'TEST002',
            'type_id' => null,
            'model' => 'Test Model',
            'brand' => 'Test Brand',
            'status' => 'active'
        ];
        
        $id2 = $equipment->create($data2);
        echo "⚠️ Không nên thành công với type_id = null! ID: $id2<br>";
        $equipment->delete($id2);
    } catch (Exception $e) {
        echo "✅ Lỗi như mong đợi: " . $e->getMessage() . "<br>";
    }
    
    // Test case 3: Thêm thiết bị với type_id = ''
    echo "<h3>❌ Test 3: Thêm thiết bị với type_id = '' (empty string)</h3>";
    try {
        $data3 = [
            'name' => 'Test Equipment 3',
            'code' => 'TEST003',
            'type_id' => '',
            'model' => 'Test Model',
            'brand' => 'Test Brand',
            'status' => 'active'
        ];
        
        $id3 = $equipment->create($data3);
        echo "⚠️ Không nên thành công với type_id = ''! ID: $id3<br>";
        $equipment->delete($id3);
    } catch (Exception $e) {
        echo "✅ Lỗi như mong đợi: " . $e->getMessage() . "<br>";
    }
    
    echo "<br><h3>📋 Equipment Types hiện có:</h3>";
    $types = $db->fetchAll("SELECT id, name FROM equipment_types ORDER BY id");
    foreach ($types as $type) {
        echo "ID: {$type['id']} - {$type['name']}<br>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
}

echo "<br><p><a href='admin/equipments.php'>👉 Quay lại quản lý thiết bị</a></p>";
?>
