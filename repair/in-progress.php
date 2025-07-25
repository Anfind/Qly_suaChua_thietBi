<?php
require_once __DIR__ . '/../config/config.php';
require_role('technician');

$controller = new RepairController();
$db = Database::getInstance();

// Lấy danh sách đơn đang sửa chữa và chờ tiếp nhận
$user = current_user();
$inProgressRequests = $db->fetchAll(
    "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.model as equipment_model,
            u.full_name as requester_name, u.phone as requester_phone,
            d.name as department_name, s.name as status_name, s.code as status_code
     FROM repair_requests r
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     WHERE s.code IN ('SENT_TO_REPAIR', 'IN_PROGRESS') 
     AND (r.assigned_technician_id = ? OR r.assigned_technician_id IS NULL)
     ORDER BY r.updated_at DESC",
    [$user['id']]
);

$title = 'Đang sửa chữa';

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Kỹ thuật viên', 'url' => url('technician/')],
    ['title' => 'Đang sửa chữa', 'url' => '']
];

ob_start();
?>

<style>
.stats-card {
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: none;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
}

.badge-status {
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

.progress-custom {
    height: 10px;
    border-radius: 20px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.action-buttons .btn {
    margin: 0 2px;
    border-radius: 8px;
    font-weight: 500;
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
                <i class="fas fa-tasks me-2"></i>
                Đơn cần xử lý
            </h2>
            <p class="text-muted">Quản lý các đơn chờ tiếp nhận và đang sửa chữa</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= url('technician/') ?>" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
            </a>
        </div>
    </div>

    <!-- Quick stats với thiết kế đẹp -->
    <div class="row mb-5">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-warning text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-tasks fa-2x"></i>
                    </div>
                    <h4 class="text-warning mb-2"><?= count($inProgressRequests) ?></h4>
                    <p class="text-muted mb-0 fw-bold">Cần xử lý</p>
                    <small class="text-muted">Đơn chờ tiếp nhận & đang sửa</small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-info text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                    <h4 class="text-info mb-2">
                        <?php
                        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
                        $completedThisWeek = $db->fetch(
                            "SELECT COUNT(*) as count FROM repair_requests r
                             JOIN repair_statuses s ON r.current_status_id = s.id
                             WHERE s.code IN ('COMPLETED', 'RETURNED') 
                             AND r.assigned_technician_id = ?
                             AND r.updated_at >= ?",
                            [$user['id'], $thisWeekStart]
                        )['count'] ?? 0;
                        echo $completedThisWeek;
                        ?>
                    </h4>
                    <p class="text-muted mb-0 fw-bold">Hoàn thành tuần này</p>
                    <small class="text-muted">Thành tích trong tuần</small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="circle-icon bg-success text-white mb-3 mx-auto" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-trophy fa-2x"></i>
                    </div>
                    <h4 class="text-success mb-2">
                        <?php
                        $totalCompleted = $db->fetch(
                            "SELECT COUNT(*) as count FROM repair_requests r
                             JOIN repair_statuses s ON r.current_status_id = s.id
                             WHERE s.code IN ('COMPLETED', 'RETURNED') 
                             AND r.assigned_technician_id = ?",
                            [$user['id']]
                        )['count'] ?? 0;
                        echo $totalCompleted;
                        ?>
                    </h4>
                    <p class="text-muted mb-0 fw-bold">Tổng hoàn thành</p>
                    <small class="text-muted">Tổng thành tích</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng đơn cần xử lý với thiết kế modern -->
    <div class="card table-modern">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
            <h5 class="mb-0">
                <i class="fas fa-list-ul me-2"></i>
                Danh sách đơn cần xử lý (<?= count($inProgressRequests) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($inProgressRequests)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list text-muted"></i>
                    <h4 class="mt-3 mb-2">Không có đơn cần xử lý</h4>
                    <p class="text-muted">Tất cả đơn của bạn đã được hoàn thành hoặc chưa có đơn mới được giao.</p>
                    <a href="<?= url('technician/') ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-home me-2"></i>Về Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th width="12%">Mã đơn</th>
                                <th width="18%">Thiết bị</th>
                                <th width="15%">Người gửi</th>
                                <th width="12%">Trạng thái</th>
                                <th width="15%">Tiến độ</th>
                                <th width="12%">Ngày tạo</th>
                                <th width="16%">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inProgressRequests as $request): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary">#<?= $request['id'] ?></strong>
                                        <br><small class="text-muted"><?= $request['request_code'] ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($request['equipment_image'])): ?>
                                                <img src="<?= asset('uploads/equipments/' . $request['equipment_image']) ?>" 
                                                     alt="Equipment" class="rounded me-2" width="40" height="40"
                                                     style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-laptop text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= e($request['equipment_name']) ?></strong>
                                                <br><small class="text-muted"><?= e($request['equipment_code']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                 style="width: 35px; height: 35px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <div>
                                                <strong><?= e($request['requester_name']) ?></strong>
                                                <?php if ($request['requester_phone']): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i><?= e($request['requester_phone']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusInfo = [
                                            'SENT_TO_REPAIR' => ['class' => 'warning', 'text' => 'Chờ tiếp nhận', 'icon' => 'clock'],
                                            'IN_PROGRESS' => ['class' => 'info', 'text' => 'Đang sửa chữa', 'icon' => 'cog']
                                        ][$request['status_code']] ?? ['class' => 'secondary', 'text' => $request['status_name'], 'icon' => 'info'];
                                        ?>
                                        <span class="badge badge-status bg-<?= $statusInfo['class'] ?>">
                                            <i class="fas fa-<?= $statusInfo['icon'] ?> me-1"></i>
                                            <?= $statusInfo['text'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        // Tính toán tiến độ dựa trên số lượng chi tiết sửa chữa
                                        $repairDetailsCount = $db->fetch(
                                            "SELECT COUNT(*) as count FROM repair_details WHERE request_id = ?",
                                            [$request['id']]
                                        )['count'] ?? 0;
                                        
                                        if ($repairDetailsCount == 0) {
                                            $progress = 10; // Vừa bắt đầu
                                            $progressText = "Vừa bắt đầu";
                                            $progressClass = "warning";
                                        } elseif ($repairDetailsCount < 3) {
                                            $progress = 40;
                                            $progressText = "Đang tiến hành";
                                            $progressClass = "info";
                                        } else {
                                            $progress = 80;
                                            $progressText = "Gần hoàn thành";
                                            $progressClass = "success";
                                        }
                                        ?>
                                        <div class="progress progress-custom mb-1" style="height: 8px;">
                                            <div class="progress-bar bg-<?= $progressClass ?>" style="width: <?= $progress ?>%"></div>
                                        </div>
                                        <small class="text-muted fw-bold"><?= $progressText ?></small>
                                        <br><small class="text-muted">(<?= $repairDetailsCount ?> bước)</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold"><?= date('d/m/Y', strtotime($request['updated_at'])) ?></span>
                                        <br><small class="text-muted"><?= date('H:i', strtotime($request['updated_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-outline-primary btn-sm mb-1" title="Xem chi tiết">
                                                <i class="fas fa-eye me-1"></i>Chi tiết
                                            </a>
                                            <?php if ($request['status_code'] === 'SENT_TO_REPAIR'): ?>
                                                <br><a href="<?= url('technician/start-repair.php?id=' . $request['id']) ?>" 
                                                   class="btn btn-success btn-sm" title="Bắt đầu sửa chữa">
                                                    <i class="fas fa-play me-1"></i>Nhận
                                                </a>
                                            <?php else: ?>
                                                <br><a href="<?= url('technician/update-progress.php?id=' . $request['id']) ?>" 
                                                   class="btn btn-warning btn-sm me-1" title="Cập nhật tiến độ">
                                                    <i class="fas fa-edit me-1"></i>Cập nhật
                                                </a>
                                                <a href="<?= url('technician/complete-repair.php?id=' . $request['id']) ?>" 
                                                   class="btn btn-info btn-sm" title="Hoàn thành sửa chữa">
                                                    <i class="fas fa-check me-1"></i>Hoàn thành
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
