<?php
require_once __DIR__ . '/config/config.php';

try {
    echo "Testing Real Notification with Request...\n";
    
    $repairRequest = new RepairRequest();
    
    $testData = [
        'equipment_id' => 1,
        'requester_id' => 1,
        'problem_description' => 'Test notification creation',
        'urgency_level' => 'high'
    ];
    
    echo "Creating repair request with notification...\n";
    $requestCode = $repairRequest->create($testData);
    echo "✓ Request created: $requestCode\n";
    
    // Lấy request_id
    $db = new Database();
    $request = $db->fetch("SELECT id FROM repair_requests WHERE request_code = ?", [$requestCode]);
    
    if ($request) {
        $requestId = $request['id'];
        echo "✓ Request ID: $requestId\n";
        
        // Kiểm tra notifications trong database
        $notifications = $db->fetchAll("
            SELECT n.*, u.full_name 
            FROM notifications n 
            JOIN users u ON n.user_id = u.id 
            WHERE n.related_id = ? AND n.related_type = 'repair_request'
            ORDER BY n.id DESC
        ", [$requestId]);
        
        echo "✓ Found " . count($notifications) . " notifications\n";
        
        foreach($notifications as $n) {
            echo "  - User: " . $n['full_name'] . "\n";
            echo "    Title: " . $n['title'] . "\n";
            echo "    Message: " . $n['message'] . "\n";
            echo "    Created: " . $n['created_at'] . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
