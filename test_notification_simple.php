<?php
/**
 * TEST NOTIFICATION SIMPLE
 * Test notification mà không cần login
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/notification_helpers.php';

try {
    echo "<h1>Test Notification System (Simple)</h1>";
    
    $db = Database::getInstance();
    
    echo "<h3>1. Kiểm tra database connection:</h3>";
    if ($db->isConnected()) {
        echo "✓ Database connected<br>";
    } else {
        echo "❌ Database connection failed<br>";
        exit;
    }
    
    echo "<h3>2. Test transaction methods:</h3>";
    echo "inTransaction(): " . ($db->inTransaction() ? 'true' : 'false') . "<br>";
    
    echo "<h3>3. Test createNotification function:</h3>";
    // Get first user
    $user = $db->fetch("SELECT id FROM users LIMIT 1");
    if ($user) {
        $result = createNotification(
            $user['id'],
            'Test Notification',
            'This is a test notification',
            'info',
            'system',
            null,
            'dashboard.php'
        );
        
        if ($result) {
            echo "✓ Single notification created successfully (ID: {$result})<br>";
        } else {
            echo "❌ Failed to create single notification<br>";
        }
    } else {
        echo "❌ No users found in database<br>";
    }
    
    echo "<h3>4. Test createBulkNotifications function:</h3>";
    $users = $db->fetchAll("SELECT id FROM users LIMIT 3");
    if (!empty($users)) {
        $userIds = array_column($users, 'id');
        $result = createBulkNotifications(
            $userIds,
            'Test Bulk Notification',
            'This is a bulk test notification',
            'info',
            'system',
            null,
            'dashboard.php'
        );
        
        if ($result > 0) {
            echo "✓ Bulk notifications created successfully (Count: {$result})<br>";
        } else {
            echo "❌ Failed to create bulk notifications<br>";
        }
    } else {
        echo "❌ No users found for bulk notification test<br>";
    }
    
    echo "<h3>5. Test notification retrieval:</h3>";
    if ($user) {
        $count = getUnreadNotificationCount($user['id']);
        echo "Unread notifications for user {$user['id']}: {$count}<br>";
        
        $recent = getRecentNotifications($user['id'], 3);
        echo "Recent notifications count: " . count($recent) . "<br>";
        
        if (!empty($recent)) {
            echo "<ul>";
            foreach ($recent as $notif) {
                echo "<li>{$notif['title']} - {$notif['message']} ({$notif['time_ago']})</li>";
            }
            echo "</ul>";
        }
    }
    
    echo "<h3>✅ All tests completed successfully!</h3>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<a href='logistics/handover.php'>Test Logistics Handover</a> | ";
echo "<a href='dashboard.php'>Dashboard</a>";

?>
