<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = Database::getInstance();
    $result = $db->query("DELETE FROM equipments WHERE code LIKE 'TEST%'");
    echo "✅ Đã xóa các thiết bị test\n";
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}
?>
