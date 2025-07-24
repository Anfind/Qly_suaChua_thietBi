<?php
error_reporting(E_ERROR | E_PARSE);

require_once 'config/config.php';

echo "=== KIỂM TRA CẤU TRÚC BẢNG USERS ===\n";

$db = Database::getInstance();

$sql = "DESCRIBE users";
$columns = $db->fetchAll($sql);

echo "Các cột trong bảng users:\n";
foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== DONE ===\n";
?>
