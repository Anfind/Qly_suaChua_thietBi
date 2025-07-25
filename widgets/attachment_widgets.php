<?php
/**
 * Widget hiển thị file đính kèm gần đây
 */

if (!function_exists('render_recent_attachments_widget')) {
    function render_recent_attachments_widget($limit = 5) {
        $db = Database::getInstance();
        $user = current_user();
        
        // Lấy các đơn gần đây có file đính kèm dựa theo role
        $whereClause = "";
        $params = [];
        
        if (has_role('requester')) {
            $whereClause = "WHERE r.requester_id = ?";
            $params[] = $user['id'];
        } elseif (has_role('technician')) {
            $whereClause = "WHERE r.assigned_technician_id = ?";
            $params[] = $user['id'];
        } elseif (has_role('clerk')) {
            $whereClause = "WHERE s.code IN ('HANDED_TO_CLERK', 'REPAIR_COMPLETED')";
        } elseif (has_role('logistics')) {
            $whereClause = "WHERE s.code IN ('PENDING_HANDOVER', 'RETRIEVED')";
        } else {
            // Admin xem tất cả
            $whereClause = "";
        }
        
        $sql = "SELECT r.id, r.request_code, r.images, r.videos, r.created_at,
                       e.name as equipment_name, u.full_name as requester_name,
                       s.name as status_name, s.color as status_color, s.icon as status_icon
                FROM repair_requests r
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                $whereClause
                AND (r.images IS NOT NULL AND r.images != '[]' AND r.images != '')
                ORDER BY r.created_at DESC
                LIMIT $limit";
        
        $requests = $db->fetchAll($sql, $params);
        
        if (empty($requests)) {
            return "";
        }
        
        ob_start();
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-paperclip me-2"></i>
                    Đơn có file đính kèm gần đây
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($requests as $request): ?>
                        <?php 
                        $images = json_decode($request['images'] ?? '[]', true);
                        $videos = json_decode($request['videos'] ?? '[]', true);
                        $attachmentCount = count($images) + count($videos);
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                           class="text-decoration-none fw-bold">
                                            #<?= e($request['request_code']) ?>
                                        </a>
                                        <span class="badge ms-2" style="background-color: <?= e($request['status_color']) ?>;">
                                            <i class="<?= e($request['status_icon']) ?> me-1"></i>
                                            <?= e($request['status_name']) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <i class="fas fa-laptop me-1"></i><?= e($request['equipment_name']) ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-user me-1"></i><?= e($request['requester_name']) ?>
                                        <span class="ms-2">
                                            <i class="fas fa-clock me-1"></i><?= time_ago($request['created_at']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="mb-1">
                                        <a href="<?= url('repairs/attachments.php?code=' . $request['request_code']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-paperclip me-1"></i>
                                            <?= $attachmentCount ?> file
                                        </a>
                                    </div>
                                    <?php if (!empty($images)): ?>
                                        <div class="d-flex gap-1">
                                            <?php foreach (array_slice($images, 0, 3) as $image): ?>
                                                <img src="<?= upload_url('requests/' . $image) ?>" 
                                                     class="rounded" 
                                                     style="width: 30px; height: 30px; object-fit: cover;"
                                                     title="<?= e(basename($image)) ?>">
                                            <?php endforeach; ?>
                                            <?php if (count($images) > 3): ?>
                                                <span class="badge bg-secondary d-flex align-items-center justify-content-center" 
                                                      style="width: 30px; height: 30px; font-size: 10px;">
                                                    +<?= count($images) - 3 ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="<?= url('repairs/') ?>" class="text-decoration-none">
                        <i class="fas fa-list me-1"></i>Xem tất cả đơn sửa chữa
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('render_attachment_stats_widget')) {
    function render_attachment_stats_widget() {
        $db = Database::getInstance();
        $user = current_user();
        
        // Thống kê file đính kèm
        $whereClause = "";
        $params = [];
        
        if (has_role('requester')) {
            $whereClause = "WHERE r.requester_id = ?";
            $params[] = $user['id'];
        } elseif (has_role('technician')) {
            $whereClause = "WHERE r.assigned_technician_id = ?";
            $params[] = $user['id'];
        }
        
        $stats = [
            'requests_with_images' => $db->fetch("
                SELECT COUNT(*) as count 
                FROM repair_requests r 
                $whereClause 
                AND r.images IS NOT NULL AND r.images != '[]' AND r.images != ''
            ", $params)['count'],
            
            'requests_with_videos' => $db->fetch("
                SELECT COUNT(*) as count 
                FROM repair_requests r 
                $whereClause 
                AND r.videos IS NOT NULL AND r.videos != '[]' AND r.videos != ''
            ", $params)['count'],
            
            'repair_details_with_images' => $db->fetch("
                SELECT COUNT(DISTINCT rd.request_id) as count 
                FROM repair_details rd
                LEFT JOIN repair_requests r ON rd.request_id = r.id
                $whereClause 
                AND rd.images IS NOT NULL AND rd.images != '[]' AND rd.images != ''
            ", $params)['count']
        ];
        
        ob_start();
        ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Thống kê file đính kèm
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <h4 class="text-primary mb-1"><?= $stats['requests_with_images'] ?></h4>
                            <small class="text-muted">Đơn có hình ảnh</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <h4 class="text-success mb-1"><?= $stats['requests_with_videos'] ?></h4>
                            <small class="text-muted">Đơn có video</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <h4 class="text-warning mb-1"><?= $stats['repair_details_with_images'] ?></h4>
                        <small class="text-muted">Có ảnh sửa chữa</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>
