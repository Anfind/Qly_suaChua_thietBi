<?php
// Script để clear session hoàn toàn
session_start();

echo "=== BEFORE CLEAR ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session data:\n";
print_r($_SESSION);

// Clear toàn bộ session
$_SESSION = array();

// Xóa session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Tạo session mới
session_start();
session_regenerate_id(true);

echo "\n=== AFTER CLEAR ===\n";
echo "New Session ID: " . session_id() . "\n";
echo "Session data:\n";
print_r($_SESSION);

echo "\n✅ Session cleared successfully!\n";
echo "You can now go to: http://localhost/Qly_suaChua_thietBi/index.php\n";
?>
