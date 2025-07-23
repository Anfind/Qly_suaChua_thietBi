<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$title = 'Cài đặt hệ thống';
$db = Database::getInstance();

$error = '';
$success = '';

// Xử lý cập nhật cài đặt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'system_settings':
                // Cập nhật cài đặt hệ thống
                $settings = [
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                    'auto_notifications' => isset($_POST['auto_notifications']) ? 1 : 0,
                    'cleanup_old_files' => isset($_POST['cleanup_old_files']) ? 1 : 0,
                    'session_timeout' => (int)$_POST['session_timeout'],
                    'max_file_size' => (int)$_POST['max_file_size'],
                    'items_per_page' => (int)$_POST['items_per_page']
                ];
                
                foreach ($settings as $key => $value) {
                    $existing = $db->fetch("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
                    if ($existing) {
                        $db->update('system_settings', 
                            ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 
                            'setting_key = ?', 
                            [$key]
                        );
                    } else {
                        $db->insert('system_settings', [
                            'setting_key' => $key,
                            'setting_value' => $value,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                $success = 'Cập nhật cài đặt hệ thống thành công!';
                break;
                
            case 'cleanup':
                $daysOld = (int)$_POST['cleanup_days'];
                
                $cleaned = 0;
                $cleaned += cleanup_old_files(UPLOAD_REQUEST_PATH, $daysOld);
                $cleaned += cleanup_old_files(BASE_PATH . 'logs/', $daysOld);
                $cleaned += cleanup_old_files(BASE_PATH . 'cache/', 7); // Cache chỉ giữ 7 ngày
                
                $success = "Đã dọn dẹp $cleaned file cũ!";
                break;
                
            case 'backup':
                // Tạo backup database
                $backupFile = create_database_backup();
                if ($backupFile) {
                    $success = "Tạo backup thành công: $backupFile";
                } else {
                    $error = "Lỗi tạo backup database";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy cài đặt hiện tại
$currentSettings = [];
$settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settingsData as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Các cài đặt mặc định
$defaultSettings = [
    'maintenance_mode' => 0,
    'auto_notifications' => 1,
    'cleanup_old_files' => 1,
    'session_timeout' => 3600,
    'max_file_size' => 10,
    'items_per_page' => 20
];

// Merge với cài đặt hiện tại
$settings = array_merge($defaultSettings, $currentSettings);

// System health check
$healthChecks = system_health_check();

// Database stats
$dbStats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests")['count'],
    'total_equipments' => $db->fetch("SELECT COUNT(*) as count FROM equipments")['count'],
    'database_size' => get_database_size()
];

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Quản trị', 'url' => url('admin/')],
    ['title' => 'Cài đặt', 'url' => '']
];

// Helper function để tạo backup
function create_database_backup() {
    try {
        $backupDir = BASE_PATH . 'backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            'localhost',
            'root', 
            '',
            'equipment_repair_management',
            $filepath
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($filepath)) {
            return $filename;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function get_database_size() {
    try {
        $db = Database::getInstance();
        $result = $db->fetch(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
             FROM information_schema.TABLES 
             WHERE table_schema = 'equipment_repair_management'"
        );
        return $result['size_mb'] . ' MB';
    } catch (Exception $e) {
        return 'N/A';
    }
}

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-cog me-2"></i>
            Cài đặt hệ thống
        </h2>
    </div>
    <div class="col-md-4 text-end">
        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#healthCheckModal">
            <i class="fas fa-heartbeat me-1"></i>Kiểm tra hệ thống
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- System Settings -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Cài đặt chung</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="system_settings">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timeout phiên (giây)</label>
                            <input type="number" name="session_timeout" class="form-control" 
                                   value="<?= $settings['session_timeout'] ?>" min="300" max="86400">
                            <small class="text-muted">Thời gian tự động đăng xuất (300 - 86400 giây)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kích thước file tối đa (MB)</label>
                            <input type="number" name="max_file_size" class="form-control" 
                                   value="<?= $settings['max_file_size'] ?>" min="1" max="100">
                            <small class="text-muted">Kích thước tối đa cho file upload</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Số mục mỗi trang</label>
                            <select name="items_per_page" class="form-select">
                                <option value="10" <?= $settings['items_per_page'] == 10 ? 'selected' : '' ?>>10</option>
                                <option value="20" <?= $settings['items_per_page'] == 20 ? 'selected' : '' ?>>20</option>
                                <option value="50" <?= $settings['items_per_page'] == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $settings['items_per_page'] == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                       <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Chế độ bảo trì</label>
                                <small class="d-block text-muted">Khóa hệ thống cho người dùng thường</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_notifications" 
                                       <?= $settings['auto_notifications'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Gửi thông báo tự động</label>
                                <small class="d-block text-muted">Email thông báo khi thay đổi trạng thái</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="cleanup_old_files" 
                                       <?= $settings['cleanup_old_files'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Tự động dọn dẹp</label>
                                <small class="d-block text-muted">Xóa file cũ định kỳ</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Lưu cài đặt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Thông tin hệ thống</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>Phiên bản PHP:</strong></td>
                        <td><?= PHP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phiên bản app:</strong></td>
                        <td><?= APP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td><strong>Người dùng:</strong></td>
                        <td><?= number_format($dbStats['total_users']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Đơn sửa chữa:</strong></td>
                        <td><?= number_format($dbStats['total_requests']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Thiết bị:</strong></td>
                        <td><?= number_format($dbStats['total_equipments']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Kích thước DB:</strong></td>
                        <td><?= $dbStats['database_size'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Múi giờ:</strong></td>
                        <td><?= date_default_timezone_get() ?></td>
                    </tr>
                    <tr>
                        <td><strong>Thời gian:</strong></td>
                        <td><?= date('d/m/Y H:i:s') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Maintenance Tools -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Công cụ bảo trì</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Cleanup -->
                    <div class="col-md-4 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-broom fa-2x text-warning mb-3"></i>
                                <h6>Dọn dẹp file cũ</h6>
                                <p class="text-muted small">Xóa file upload và log cũ</p>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cleanup">
                                    <div class="mb-2">
                                        <input type="number" name="cleanup_days" value="30" min="1" max="365" 
                                               class="form-control form-control-sm" placeholder="Số ngày">
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-sm" 
                                            onclick="return confirm('Bạn có chắc muốn dọn dẹp file cũ?')">
                                        <i class="fas fa-broom me-1"></i>Dọn dẹp
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backup -->
                    <div class="col-md-4 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-database fa-2x text-info mb-3"></i>
                                <h6>Backup database</h6>
                                <p class="text-muted small">Tạo bản sao lưu dữ liệu</p>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="backup">
                                    <button type="submit" class="btn btn-info btn-sm" 
                                            onclick="return confirm('Tạo backup database?')">
                                        <i class="fas fa-download me-1"></i>Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cache -->
                    <div class="col-md-4 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <i class="fas fa-memory fa-2x text-success mb-3"></i>
                                <h6>Xóa cache</h6>
                                <p class="text-muted small">Xóa bộ nhớ đệm hệ thống</p>
                                <button type="button" class="btn btn-success btn-sm" onclick="clearCache()">
                                    <i class="fas fa-eraser me-1"></i>Xóa cache
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Health Check Modal -->
<div class="modal fade" id="healthCheckModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kiểm tra tình trạng hệ thống</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <?php foreach ($healthChecks as $check => $result): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= ucfirst(str_replace('_', ' ', $check)) ?></strong><br>
                                <small class="text-muted"><?= e($result['message']) ?></small>
                            </div>
                            <div>
                                <?php if ($result['status'] === 'ok'): ?>
                                    <span class="badge bg-success">OK</span>
                                <?php elseif ($result['status'] === 'warning'): ?>
                                    <span class="badge bg-warning">Cảnh báo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Lỗi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>Kiểm tra lại
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Tạo bảng system_settings nếu chưa có
try {
    $db->query("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Ignore nếu bảng đã tồn tại
}

// Custom JS
$custom_js = "
<script>
    function clearCache() {
        if (confirm('Xóa tất cả cache?')) {
            fetch('clear-cache.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({action: 'clear_cache'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cache đã được xóa thành công!');
                } else {
                    alert('Lỗi xóa cache: ' + data.message);
                }
            })
            .catch(error => {
                alert('Lỗi: ' + error);
            });
        }
    }
    
    // Warning for maintenance mode
    document.querySelector('input[name=\"maintenance_mode\"]').addEventListener('change', function() {
        if (this.checked) {
            alert('Cảnh báo: Chế độ bảo trì sẽ khóa hệ thống cho tất cả người dùng không phải admin!');
        }
    });
</script>
";

include '../layouts/app.php';
?>
