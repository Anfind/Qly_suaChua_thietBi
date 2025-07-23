<?php
session_start();
require_once 'config/config.php';

echo "=== SESSION DEBUG ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session data:\n";
print_r($_SESSION);

if (isset($_SESSION['user_id'])) {
    echo "\n=== CURRENT USER FROM DB ===\n";
    $db = Database::getInstance();
    $user = $db->fetch(
        "SELECT u.*, r.name as role_name, r.display_name as role_display_name, 
                d.name as department_name 
         FROM users u 
         LEFT JOIN roles r ON u.role_id = r.id 
         LEFT JOIN departments d ON u.department_id = d.id 
         WHERE u.id = ?",
        [$_SESSION['user_id']]
    );
    
    if ($user) {
        echo "ID: {$user['id']}\n";
        echo "Username: {$user['username']}\n";
        echo "Full Name: {$user['full_name']}\n";
        echo "Role Name: {$user['role_name']}\n";
        echo "Role Display: {$user['role_display_name']}\n";
        echo "Department: {$user['department_name']}\n";
    } else {
        echo "❌ No user found for session user_id: {$_SESSION['user_id']}\n";
    }
} else {
    echo "❌ No user_id in session\n";
}

echo "\n=== TEST CURRENT_USER FUNCTION ===\n";
if (function_exists('current_user')) {
    $currentUser = current_user();
    if ($currentUser) {
        echo "✅ current_user() returned:\n";
        echo "ID: {$currentUser['id']}\n";
        echo "Username: {$currentUser['username']}\n";
        echo "Full Name: {$currentUser['full_name']}\n";
        echo "Role Name: {$currentUser['role_name']}\n";
    } else {
        echo "❌ current_user() returned null\n";
    }
} else {
    echo "❌ current_user() function not found\n";
}
?>
