<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/notification_helpers.php';

require_login();

// Chặn technician truy cập trang thông báo
if (has_role('technician')) {
    redirect('dashboard.php', 'Bạn không có quyền truy cập trang thông báo', 'warning');
    exit;
}

$title = 'Thông báo';
$user = current_user();

// Xử lý mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read' && isset($_POST['notification_id'])) {
        markNotificationAsRead($_POST['notification_id']);
        redirect('notifications.php', 'Đã đánh dấu thông báo đã đọc', 'success');
    }
    
    if ($action === 'mark_all_read') {
        markAllNotificationsAsRead($user['id']);
        redirect('notifications.php', 'Đã đánh dấu tất cả thông báo đã đọc', 'success');
    }
}

// Phân trang
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Lọc theo loại
$type_filter = $_GET['type'] ?? '';
$read_filter = $_GET['read'] ?? '';

// Build query
$where_conditions = ["user_id = ?"];
$params = [$user['id']];

if ($type_filter) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($read_filter !== '') {
    $where_conditions[] = "is_read = ?";
    $params[] = $read_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Lấy tổng số notifications
$total_query = "SELECT COUNT(*) as count FROM notifications WHERE $where_clause";
$total_result = $db->fetch($total_query, $params);
$total_notifications = $total_result['count'];
$total_pages = ceil($total_notifications / $limit);

// Lấy notifications
$notifications_query = "
    SELECT 
        id,
        title,
        message,
        type,
        action_url,
        is_read,
        created_at,
        CASE 
            WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 1 THEN 'Vừa xong'
            WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' phút trước')
            WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), ' giờ trước')
            WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) < 7 THEN CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), ' ngày trước')
            ELSE DATE_FORMAT(created_at, '%d/%m/%Y %H:%i')
        END as time_ago
    FROM notifications 
    WHERE $where_clause
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
";

$notifications = $db->fetchAll($notifications_query, $params);

// Thống kê
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?", [$user['id']])['count'],
    'unread' => $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$user['id']])['count'],
    'today' => $db->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$user['id']])['count']
];

ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-bell me-2"></i>Thông báo</h1>
                <div class="d-flex gap-2">
                    <?php if ($stats['unread'] > 0): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-check-double me-1"></i>
                                Đánh dấu tất cả đã đọc
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Thống kê -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-bell fa-2x text-primary mb-2"></i>
                            <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                            <small class="text-muted">Tổng thông báo</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                            <h3 class="mb-0"><?= number_format($stats['unread']) ?></h3>
                            <small class="text-muted">Chưa đọc</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day fa-2x text-success mb-2"></i>
                            <h3 class="mb-0"><?= number_format($stats['today']) ?></h3>
                            <small class="text-muted">Hôm nay</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bộ lọc -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Loại thông báo</label>
                            <select name="type" class="form-select">
                                <option value="">Tất cả</option>
                                <option value="info" <?= $type_filter === 'info' ? 'selected' : '' ?>>Thông tin</option>
                                <option value="success" <?= $type_filter === 'success' ? 'selected' : '' ?>>Thành công</option>
                                <option value="warning" <?= $type_filter === 'warning' ? 'selected' : '' ?>>Cảnh báo</option>
                                <option value="danger" <?= $type_filter === 'danger' ? 'selected' : '' ?>>Lỗi</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="read" class="form-select">
                                <option value="">Tất cả</option>
                                <option value="0" <?= $read_filter === '0' ? 'selected' : '' ?>>Chưa đọc</option>
                                <option value="1" <?= $read_filter === '1' ? 'selected' : '' ?>>Đã đọc</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i>Lọc
                                </button>
                                <a href="notifications.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Danh sách thông báo -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Không có thông báo nào</h5>
                            <p class="text-muted">Bạn chưa có thông báo nào hoặc tất cả đã được xóa.</p>
                        </div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item-page <?= !$notification['is_read'] ? 'unread' : '' ?>" 
                                     data-id="<?= $notification['id'] ?>">
                                    <div class="d-flex">
                                        <div class="notification-icon me-3">
                                            <?php
                                            $icon_class = 'fas fa-info-circle text-info';
                                            switch ($notification['type']) {
                                                case 'success':
                                                    $icon_class = 'fas fa-check-circle text-success';
                                                    break;
                                                case 'warning':
                                                    $icon_class = 'fas fa-exclamation-triangle text-warning';
                                                    break;
                                                case 'danger':
                                                    $icon_class = 'fas fa-times-circle text-danger';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?= $icon_class ?> fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="notification-content">
                                                    <h6 class="notification-title mb-1">
                                                        <?= e($notification['title']) ?>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">Mới</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="notification-message mb-2"><?= e($notification['message']) ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= $notification['time_ago'] ?>
                                                    </small>
                                                </div>
                                                <div class="notification-actions">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Đánh dấu đã đọc">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($notification['action_url']): ?>
                                                        <a href="<?= url($notification['action_url']) ?>" 
                                                           class="btn btn-sm btn-primary ms-1" 
                                                           title="Đi đến">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Phân trang -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Phân trang thông báo" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?><?= $type_filter ? "&type={$type_filter}" : '' ?><?= $read_filter !== '' ? "&read={$read_filter}" : '' ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= $type_filter ? "&type={$type_filter}" : '' ?><?= $read_filter !== '' ? "&read={$read_filter}" : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?><?= $type_filter ? "&type={$type_filter}" : '' ?><?= $read_filter !== '' ? "&read={$read_filter}" : '' ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notification-item-page {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.notification-item-page:hover {
    background-color: #f8f9fa;
}

.notification-item-page.unread {
    background-color: #f0f9ff;
    border-left: 4px solid #3b82f6;
}

.notification-item-page:last-child {
    border-bottom: none;
}

.notification-title {
    font-weight: 600;
    color: #1f2937;
}

.notification-message {
    color: #6b7280;
    line-height: 1.5;
}

.notification-icon {
    flex-shrink: 0;
    width: 40px;
    text-align: center;
}

.notification-actions {
    flex-shrink: 0;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.2s;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/layouts/app.php';
?>
