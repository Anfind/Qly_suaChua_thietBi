<?php
session_start();
require_once 'config/config.php';
require_once 'config/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<h2>Database Connection Test</h2>";
    echo "Database connected successfully!<br><br>";
    
    // Kiểm tra các bảng
    $tables = ['users', 'departments', 'equipments', 'repair_requests'];
    
    foreach ($tables as $table) {
        echo "<h3>Table: $table</h3>";
        try {
            $stmt = $conn->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table><br>";
            
            // Đếm records
            $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
            $count_stmt->execute();
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC);
            echo "Total records: " . $count['count'] . "<br><br>";
            
        } catch (Exception $e) {
            echo "Error with table $table: " . $e->getMessage() . "<br><br>";
        }
    }
    
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
