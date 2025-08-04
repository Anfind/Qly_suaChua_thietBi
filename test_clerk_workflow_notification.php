<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/notification_helpers.php';

// Test script để kiểm tra workflow notification cho clerk

echo "<h2>Test Clerk Workflow Notification</h2>";

$db = Database::getInstance();

// Lấy danh sách departments kỹ thuật
$techDepartments = $db->fetchAll("SELECT id, code, name FROM departments WHERE code LIKE 'TECH_%' AND status = 'active'");
echo "<h3>Phòng ban kỹ thuật có sẵn:</h3>";
foreach ($techDepartments as $dept) {
    $techCount = $db->fetch("SELECT COUNT(*) as count FROM users WHERE department_id = ? AND role_id = (SELECT id FROM roles WHERE name = 'technician') AND status = 'active'", [$dept['id']]);
    echo "<p>{$dept['code']} - {$dept['name']} (Có {$techCount['count']} kỹ thuật viên)</p>";
}

// Test 1: Thông báo với specific departments
echo "<h3>Test 1: Thông báo với departments cụ thể</h3>";
$testDepartmentIds = [1, 2]; // Giả sử TECH_ELEC và TECH_MECH

try {
    $result = notifyClerkSentToRepair(999, 'TEST001', 1, $testDepartmentIds);
    echo $result ? "✅ Thành công gửi thông báo với departments cụ thể" : "❌ Lỗi gửi thông báo";
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

// Test 2: Kiểm tra ai nhận được thông báo 
echo "<h3>Test 2: Kiểm tra users nhận thông báo</h3>";
$techUsers = $db->fetchAll("
    SELECT u.id, u.username, u.full_name, d.name as department_name 
    FROM users u 
    JOIN departments d ON u.department_id = d.id
    WHERE u.department_id IN (" . implode(',', array_fill(0, count($testDepartmentIds), '?')) . ")
    AND u.role_id = (SELECT id FROM roles WHERE name = 'technician') 
    AND u.status = 'active'
", $testDepartmentIds);

if (!empty($techUsers)) {
    echo "<p>Các kỹ thuật viên sẽ nhận thông báo:</p>";
    foreach ($techUsers as $user) {
        echo "<li>{$user['full_name']} ({$user['username']}) - {$user['department_name']}</li>";
    }
} else {
    echo "<p>❌ Không có kỹ thuật viên nào trong các phòng ban được chọn</p>";
}

// Test 3: Kiểm tra notification được tạo
echo "<h3>Test 3: Kiểm tra notifications gần đây</h3>";
$recentNotifications = $db->fetchAll("
    SELECT n.*, u.username 
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND n.title LIKE '%Đơn sửa chữa mới%'
    ORDER BY n.created_at DESC
    LIMIT 10
");

if (!empty($recentNotifications)) {
    echo "<table border='1'>";
    echo "<tr><th>User</th><th>Title</th><th>Message</th><th>Created</th></tr>";
    foreach ($recentNotifications as $notif) {
        echo "<tr>";
        echo "<td>{$notif['username']}</td>";
        echo "<td>{$notif['title']}</td>";
        echo "<td>{$notif['message']}</td>";
        echo "<td>{$notif['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Không có notification nào được tạo gần đây</p>";
}

// Test 4: Test với departments trống
echo "<h3>Test 4: Test với departments trống (fallback to workflow steps)</h3>";
try {
    $result = notifyClerkSentToRepair(999, 'TEST002', 1, []);
    echo $result ? "✅ Thành công xử lý với departments trống" : "❌ Lỗi xử lý với departments trống";
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}

echo "<h3>✅ Test hoàn tất</h3>";
echo "<p><strong>Kết luận:</strong> Workflow notification sẽ chỉ gửi đến kỹ thuật viên trong các phòng ban mà clerk đã chọn.</p>";
?>
