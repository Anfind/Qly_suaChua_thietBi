<?php
require_once 'config/config.php';

echo "Test Helper Function format_status_display:\n";
echo "========================================\n";
echo "Input: 'Đã bàn giao cho văn thư' -> Output: '" . format_status_display('Đã bàn giao cho văn thư') . "'\n";
echo "Input: 'Chờ bàn giao' -> Output: '" . format_status_display('Chờ bàn giao') . "'\n";
echo "Input: 'Đang sửa chữa' -> Output: '" . format_status_display('Đang sửa chữa') . "'\n";
echo "\nTest completed successfully!\n";
?>
