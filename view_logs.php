<?php
// Đọc error log
$log_file = __DIR__ . '/logs/error.log';
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $lines = explode("\n", $logs);
    
    // Lấy 50 dòng cuối
    $recent_lines = array_slice($lines, -50);
    
    echo "<h2>Recent Error Logs</h2>";
    echo "<pre style='background: #f5f5f5; padding: 15px; overflow-x: auto;'>";
    echo htmlspecialchars(implode("\n", $recent_lines));
    echo "</pre>";
} else {
    echo "<p>Error log file not found: $log_file</p>";
}
?>
