<?php
session_start();
session_unset();
session_destroy();

// Xóa cookies nếu có
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect về trang login
header('Location: ../index.php');
exit;
?>
