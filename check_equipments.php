<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h3>📋 Thiết bị mới nhất:</h3>";
    $equipments = $db->fetchAll('SELECT id, name, code, status, type_id, department_id, created_at FROM equipments ORDER BY created_at DESC LIMIT 5');
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Tên</th><th>Mã</th><th>Status</th><th>Type ID</th><th>Dept ID</th><th>Created</th></tr>";
    foreach($equipments as $eq) {
        echo "<tr>";
        echo "<td>{$eq['id']}</td>";
        echo "<td>{$eq['name']}</td>";
        echo "<td>{$eq['code']}</td>";
        echo "<td><strong>{$eq['status']}</strong></td>";
        echo "<td>{$eq['type_id']}</td>";
        echo "<td>{$eq['department_id']}</td>";
        echo "<td>{$eq['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>🔍 Test Equipment Model getAll:</h3>";
    $equipment = new Equipment();
    $activeEquipments = $equipment->getAll(['status' => 'active']);
    
    echo "<p>Số thiết bị active: " . count($activeEquipments) . "</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Tên</th><th>Mã</th><th>Status</th><th>Type</th><th>Department</th></tr>";
    foreach($activeEquipments as $eq) {
        echo "<tr>";
        echo "<td>{$eq['id']}</td>";
        echo "<td>{$eq['name']}</td>";
        echo "<td>{$eq['code']}</td>";
        echo "<td><strong>{$eq['status']}</strong></td>";
        echo "<td>{$eq['type_name']}</td>";
        echo "<td>{$eq['department_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</p>";
}
?>
