<?php
require_once __DIR__ . '/config/config.php';

try {
    echo "Testing RepairRequest model directly...\n";
    
    $repairRequest = new RepairRequest();
    
    $testData = [
        'equipment_id' => 1,
        'requester_id' => 1,
        'problem_description' => 'Test direct model creation',
        'urgency_level' => 'low'
    ];
    
    echo "Creating repair request...\n";
    $result = $repairRequest->create($testData);
    
    echo "Success! Request code: $result\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
