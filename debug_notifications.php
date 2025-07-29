<?php
require_once 'config/config.php';
require_login();

$title = 'Debug Notifications';
$user = current_user();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Notifications</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; }
        .error { border-left-color: #dc3545; }
        .success { border-left-color: #28a745; }
    </style>
</head>
<body>
    <h1>Notification System Debug</h1>
    
    <div class="debug">
        <h3>User Information:</h3>
        <ul>
            <li>ID: <?= $user['id'] ?></li>
            <li>Name: <?= $user['full_name'] ?></li>
            <li>Role: <?= $user['role'] ?></li>
        </ul>
    </div>
    
    <div class="debug">
        <h3>Function Tests:</h3>
        <ul>
            <li>getUnreadNotificationCount exists: <?= function_exists('getUnreadNotificationCount') ? 'YES' : 'NO' ?></li>
            <li>getRecentNotifications exists: <?= function_exists('getRecentNotifications') ? 'YES' : 'NO' ?></li>
            <li>createNotification exists: <?= function_exists('createNotification') ? 'YES' : 'NO' ?></li>
        </ul>
    </div>
    
    <?php
    $notification_count = 0;
    $recent_notifications = [];
    $error = '';
    
    try {
        if (function_exists('getUnreadNotificationCount')) {
            $notification_count = getUnreadNotificationCount($user['id']);
        } else {
            $error = 'getUnreadNotificationCount function not found';
        }
        
        if (function_exists('getRecentNotifications')) {
            $recent_notifications = getRecentNotifications($user['id'], 10);
        } else {
            $error = 'getRecentNotifications function not found';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    ?>
    
    <?php if ($error): ?>
        <div class="debug error">
            <h3>Error:</h3>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php else: ?>
        <div class="debug success">
            <h3>Notification Data:</h3>
            <ul>
                <li>Unread Count: <?= $notification_count ?></li>
                <li>Recent Notifications: <?= count($recent_notifications) ?></li>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($recent_notifications)): ?>
        <div class="debug">
            <h3>Recent Notifications:</h3>
            <ol>
                <?php foreach ($recent_notifications as $notif): ?>
                    <li>
                        <strong><?= htmlspecialchars($notif['title']) ?></strong><br>
                        <?= htmlspecialchars($notif['message']) ?><br>
                        <small>Created: <?= $notif['created_at'] ?> | Read: <?= $notif['is_read'] ? 'Yes' : 'No' ?></small>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php endif; ?>
    
    <div class="debug">
        <h3>Test Create Notification:</h3>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
            try {
                $result = createNotification(
                    $user['id'],
                    'Debug Test',
                    'Test notification created at ' . date('Y-m-d H:i:s'),
                    'info'
                );
                
                if ($result) {
                    echo '<p style="color: green;">✓ Test notification created successfully!</p>';
                    // Refresh page to see new data
                    echo '<script>setTimeout(() => window.location.reload(), 1000);</script>';
                } else {
                    echo '<p style="color: red;">✗ Failed to create test notification</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
        ?>
        
        <form method="POST">
            <button type="submit" name="create_test" value="1">Create Test Notification</button>
        </form>
    </div>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>
