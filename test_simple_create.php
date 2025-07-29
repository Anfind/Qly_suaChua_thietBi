<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';

echo "<h1>Test Simple Repair Request Creation</h1>";

try {
    $controller = new RepairController();
    
    // Test data đơn giản
    $_POST = [
        'equipment_id' => 1,
        'problem_description' => 'Test simple creation',
        'urgency_level' => 'low'
    ];
    $_SESSION['user_id'] = 1;
    $_SESSION['csrf_token'] = 'test';
    $_POST['csrf_token'] = 'test';
    
    echo "<p>Attempting to create repair request...</p>";
    
    $result = $controller->create();
    
    if (isset($result['success'])) {
        echo "<p style='color: green;'>✓ Request created successfully: " . $result['request_code'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Creation failed</p>";
        if (isset($result['error'])) {
            echo "<p>Error: " . $result['error'] . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}
?>
