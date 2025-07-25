<?php
require_once __DIR__ . '/../config/config.php';
require_role('technician');

$controller = new RepairController();
$db = Database::getInstance();

// Lấy danh sách đơn đã hoàn thành
$user = current_user();
$completedRequests = $db->fetchAll(
    "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.model as equipment_model,
            u.full_name as requester_name, u.phone as requester_phone,
            d.name as department_name, s.name as status_name, s.code as status_code
     FROM repair_requests r
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     WHERE s.code IN ('REPAIR_COMPLETED', 'RETRIEVED', 'COMPLETED') 
     AND r.assigned_technician_id = ?
     ORDER BY r.updated_at DESC
     LIMIT 50",
    [$user['id']]
);

// Phân loại đơn theo trạng thái
$statusGroups = [
    'REPAIR_COMPLETED' => [],
    'RETRIEVED' => [],
    'COMPLETED' => []
];

foreach ($completedRequests as $request) {
    $status = $request['status_code'] ?? 'COMPLETED';
    if (isset($statusGroups[$status])) {
        $statusGroups[$status][] = $request;
    }
}

$title = 'Đã hoàn thành';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật viên', 'url' => url('technician/')],
    ['title' => 'Đã hoàn thành', 'url' => '']
];

ob_start();
?>

<style>
.stats-card {
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.badge-custom {
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.table-modern {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.table-modern th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px;
    font-weight: 600;
}

.table-modern td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #eee;
}

.table-modern tbody tr:hover {
    background-color: #f8f9ff;
}

.nav-tabs-custom {
    border: none;
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 5px;
}

.nav-tabs-custom .nav-link {
    border: none;
    border-radius: 8px;
    margin: 0 2px;
    color: #6c757d;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-tabs-custom .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
}

.rating-stars {
    font-size: 1.1rem;
    color: #ffc107;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="page-title text-primary">
                <i class="fas fa-medal me-2"></i>
                Lịch sử hoàn thành
            </h2>
            <p class="text-muted">Quản lý và theo dõi các đơn sửa chữa đã hoàn thành</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= url('technician/') ?>" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
            </a>
        </div>
    </div>

    <!-- Quick stats với thiết kế đẹp -->
    <div class="row mb-5">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-0 h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-success text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-tools fa-2x"></i>
                    </div>
                    <h4 class="text-success mb-2"><?= count($statusGroups['REPAIR_COMPLETED']) ?></h4>
                    <p class="text-muted mb-0 fw-bold">Đã sửa xong</p>
                    <small class="text-muted">Hoàn thành sửa chữa</small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-0 h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-info text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-box fa-2x"></i>
                    </div>
                    <h4 class="text-info mb-2"><?= count($statusGroups['RETRIEVED']) ?></h4>
                    <p class="text-muted mb-0 fw-bold">Đã thu hồi</p>
                    <small class="text-muted">Chờ trả người dùng</small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-0 h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-primary text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-flag-checkered fa-2x"></i>
                    </div>
                    <h4 class="text-primary mb-2"><?= count($statusGroups['COMPLETED']) ?></h4>
                    <p class="text-muted mb-0 fw-bold">Hoàn tất</p>
                    <small class="text-muted">Đã trả cho người dùng</small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card border-0 h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-warning text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <h4 class="text-warning mb-2"><?= count($completedRequests) ?></h4>
                    <p class="text-muted mb-0 fw-bold">Tổng cộng</p>
                    <small class="text-muted">Tất cả đơn đã xử lý</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter tabs đẹp -->
    <ul class="nav nav-tabs nav-tabs-custom mb-4" id="statusTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 py-3" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                <i class="fas fa-list me-2"></i>Tất cả <span class="badge bg-light text-dark ms-1"><?= count($completedRequests) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-3" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                <i class="fas fa-tools me-2"></i>Đã sửa xong <span class="badge bg-light text-dark ms-1"><?= count($statusGroups['REPAIR_COMPLETED']) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-3" id="handed-tab" data-bs-toggle="tab" data-bs-target="#handed" type="button" role="tab">
                <i class="fas fa-box me-2"></i>Đã thu hồi <span class="badge bg-light text-dark ms-1"><?= count($statusGroups['RETRIEVED']) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 py-3" id="returned-tab" data-bs-toggle="tab" data-bs-target="#returned" type="button" role="tab">
                <i class="fas fa-flag-checkered me-2"></i>Hoàn tất <span class="badge bg-light text-dark ms-1"><?= count($statusGroups['COMPLETED']) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="statusTabContent">
        <!-- Tất cả -->
        <div class="tab-pane fade show active" id="all" role="tabpanel">
            <?php if (!empty($completedRequests)): ?>
                <?php echo renderRequestsTable($completedRequests, $db); ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h5 class="mt-3">Chưa có đơn nào hoàn thành</h5>
                        <p class="text-muted">Khi bạn hoàn thành các đơn sửa chữa, chúng sẽ xuất hiện ở đây</p>
                        <a href="<?= url('technician/') ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Bắt đầu làm việc
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Đã sửa xong -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <?php if (!empty($statusGroups['REPAIR_COMPLETED'])): ?>
                <?php echo renderRequestsTable($statusGroups['REPAIR_COMPLETED'], $db); ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h5 class="mt-3">Chưa có đơn sửa xong</h5>
                        <p class="text-muted">Các đơn đã sửa chữa xong sẽ hiển thị ở đây</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Đã thu hồi -->
        <div class="tab-pane fade" id="handed" role="tabpanel">
            <?php if (!empty($statusGroups['RETRIEVED'])): ?>
                <?php echo renderRequestsTable($statusGroups['RETRIEVED'], $db); ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <i class="fas fa-box"></i>
                        <h5 class="mt-3">Chưa có đơn đã thu hồi</h5>
                        <p class="text-muted">Các đơn đã được thu hồi sẽ hiển thị ở đây</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Hoàn tất -->
        <div class="tab-pane fade" id="returned" role="tabpanel">
            <?php if (!empty($statusGroups['COMPLETED'])): ?>
                <?php echo renderRequestsTable($statusGroups['COMPLETED'], $db); ?>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="empty-state">
                        <i class="fas fa-flag-checkered"></i>
                        <h5 class="mt-3">Chưa có đơn hoàn tất</h5>
                        <p class="text-muted">Các đơn đã hoàn tất hoàn toàn sẽ hiển thị ở đây</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function renderRequestsTable($requests, $db) {
    ob_start();
    ?>
    <div class="card border-0 shadow-sm table-modern">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag me-2"></i>Mã đơn</th>
                            <th><i class="fas fa-desktop me-2"></i>Thiết bị</th>
                            <th><i class="fas fa-user me-2"></i>Người đề xuất</th>
                            <th><i class="fas fa-flag me-2"></i>Trạng thái</th>
                            <th><i class="fas fa-calendar me-2"></i>Ngày hoàn thành</th>
                            <th><i class="fas fa-dollar-sign me-2"></i>Chi phí</th>
                            <th><i class="fas fa-star me-2"></i>Đánh giá</th>
                            <th><i class="fas fa-cog me-2"></i>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" class="text-decoration-none">
                                        <strong class="text-primary"><?= e($request['request_code']) ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="equipment-icon me-3">
                                            <i class="fas fa-desktop text-info"></i>
                                        </div>
                                        <div>
                                            <strong><?= e($request['equipment_name']) ?></strong>
                                            <small class="d-block text-muted"><?= e($request['equipment_code']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-3">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="fw-bold"><?= e($request['requester_name']) ?></span>
                                            <?php if ($request['requester_phone']): ?>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-phone me-1"></i><?= e($request['requester_phone']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusInfo = [
                                        'REPAIR_COMPLETED' => ['class' => 'success', 'text' => 'Đã sửa xong', 'icon' => 'tools'],
                                        'RETRIEVED' => ['class' => 'info', 'text' => 'Đã thu hồi', 'icon' => 'box'],
                                        'COMPLETED' => ['class' => 'primary', 'text' => 'Hoàn tất', 'icon' => 'flag-checkered']
                                    ][$request['status_code']] ?? ['class' => 'secondary', 'text' => $request['status_name'], 'icon' => 'info'];
                                    ?>
                                    <span class="badge badge-custom bg-<?= $statusInfo['class'] ?>">
                                        <i class="fas fa-<?= $statusInfo['icon'] ?> me-1"></i>
                                        <?= $statusInfo['text'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <span class="fw-bold"><?= date('d/m/Y', strtotime($request['updated_at'])) ?></span>
                                        <small class="d-block text-muted"><?= date('H:i', strtotime($request['updated_at'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $totalCost = $request['total_cost'] ?? 0;
                                    if ($totalCost > 0) {
                                        echo '<strong class="text-success">' . number_format($totalCost) . ' VNĐ</strong>';
                                    } else {
                                        echo '<span class="badge bg-light text-success">Miễn phí</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    // Lấy đánh giá nếu có
                                    $rating = $db->fetch(
                                        "SELECT rating, feedback FROM repair_evaluations WHERE request_id = ?",
                                        [$request['id']]
                                    );
                                    
                                    if ($rating) {
                                        $stars = str_repeat('★', $rating['rating']) . str_repeat('☆', 5 - $rating['rating']);
                                        echo '<div class="rating-display text-center">';
                                        echo '<div class="rating-stars">' . $stars . '</div>';
                                        if ($rating['feedback']) {
                                            echo '<small class="text-muted" title="' . e($rating['feedback']) . '">Có phản hồi</small>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<div class="text-center">';
                                        echo '<span class="text-muted">Chưa đánh giá</span>';
                                        echo '</div>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($request['status_code'] === 'COMPLETED'): ?>
                                            <a href="<?= url('reports/repair-report.php?id=' . $request['id']) ?>" 
                                               class="btn btn-sm btn-outline-success" title="Xuất báo cáo">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<script>
// Initialize Bootstrap tabs với animation
document.addEventListener('DOMContentLoaded', function() {
    var triggerTabList = [].slice.call(document.querySelectorAll('#statusTab button'));
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Add smooth animations
    $('.stats-card').hover(
        function() { $(this).addClass('shadow-lg'); },
        function() { $(this).removeClass('shadow-lg'); }
    );
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
