<?php
require_once __DIR__ . '/config/config.php';

try {
    echo "Testing Notification System...\n";
    
    // Test notification functions
    if (function_exists('notifyNewRepairRequest')) {
        echo "✓ notifyNewRepairRequest function exists\n";
        
        // Test tạo notification
        $result = notifyNewRepairRequest(999, 'TEST001', 1);
        echo "✓ Notification created successfully\n";
        
        // Kiểm tra notification vừa tạo
        $db = new Database();
        $notifications = $db->fetchAll("SELECT * FROM notifications WHERE title LIKE '%TEST001%' ORDER BY id DESC LIMIT 1");
        
        if (!empty($notifications)) {
            $notif = $notifications[0];
            echo "✓ Notification found in database:\n";
            echo "  - Title: " . $notif['title'] . "\n";
            echo "  - Message: " . $notif['message'] . "\n";
            echo "  - Target: " . $notif['target_type'] . " - " . $notif['target_id'] . "\n";
        } else {
            echo "✗ No notification found in database\n";
        }
        
    } else {
        echo "✗ notifyNewRepairRequest function not found\n";
    }
    
    // Test get notifications
    if (function_exists('getNotificationsForUser')) {
        echo "\n✓ getNotificationsForUser function exists\n";
        
        $notifications = getNotificationsForUser(1, 5);
        echo "✓ Found " . count($notifications) . " notifications for user 1\n";
        
        foreach($notifications as $n) {
            echo "  - " . $n['title'] . " (" . $n['created_at'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
