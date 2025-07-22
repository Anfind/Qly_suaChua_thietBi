<?php
require_once '../config/config.php';
require_role('admin');

header('Content-Type: application/json');

$equipment = new Equipment();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

$id = (int)$_GET['id'];
$equipmentData = $equipment->findById($id);

if (!$equipmentData) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy thiết bị']);
    exit;
}

if (isset($_GET['view'])) {
    // Return HTML for view modal
    $db = Database::getInstance();
    $department = null;
    if ($equipmentData['department_id']) {
        $department = $db->fetch("SELECT name FROM departments WHERE id = ?", [$equipmentData['department_id']]);
    }
    
    $equipmentTypes = [
        'computer' => 'Máy tính',
        'printer' => 'Máy in',
        'scanner' => 'Máy quét',
        'projector' => 'Máy chiếu',
        'air_conditioner' => 'Điều hòa',
        'other' => 'Khác'
    ];
    
    $statusLabels = [
        'active' => 'Hoạt động',
        'maintenance' => 'Bảo trì',
        'broken' => 'Hỏng',
        'decommissioned' => 'Ngừng sử dụng'
    ];
    
    ob_start();
    ?>
    
    <div class="row">
        <?php if ($equipmentData['image']): ?>
            <div class="col-md-4 mb-3">
                <img src="<?= url('uploads/equipments/' . $equipmentData['image']) ?>" 
                     class="img-fluid rounded" alt="<?= e($equipmentData['name']) ?>">
            </div>
        <?php endif; ?>
        
        <div class="col-md-<?= $equipmentData['image'] ? '8' : '12' ?>">
            <h4><?= e($equipmentData['name']) ?></h4>
            
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Số serial:</strong></td>
                            <td><?= e($equipmentData['serial_number'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Loại:</strong></td>
                            <td><?= $equipmentTypes[$equipmentData['equipment_type']] ?? $equipmentData['equipment_type'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Hãng:</strong></td>
                            <td><?= e($equipmentData['brand'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Model:</strong></td>
                            <td><?= e($equipmentData['model'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Trạng thái:</strong></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'active' => 'success',
                                    'maintenance' => 'warning', 
                                    'broken' => 'danger',
                                    'decommissioned' => 'secondary'
                                ];
                                $statusColor = $statusColors[$equipmentData['status']] ?? 'secondary';
                                $statusLabel = $statusLabels[$equipmentData['status']] ?? $equipmentData['status'];
                                ?>
                                <span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Phòng ban:</strong></td>
                            <td><?= e($department['name'] ?? 'Chưa phân bổ') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Vị trí:</strong></td>
                            <td><?= e($equipmentData['location'] ?: 'Chưa có') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Ngày mua:</strong></td>
                            <td><?= $equipmentData['purchase_date'] ? format_date($equipmentData['purchase_date']) : 'Chưa có' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Hết bảo hành:</strong></td>
                            <td><?= $equipmentData['warranty_expires'] ? format_date($equipmentData['warranty_expires']) : 'Chưa có' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Giá trị:</strong></td>
                            <td><?= $equipmentData['value'] ? number_format($equipmentData['value']) . ' VNĐ' : 'Chưa có' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($equipmentData['supplier']): ?>
        <div class="mb-3">
            <strong>Nhà cung cấp:</strong>
            <p><?= e($equipmentData['supplier']) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($equipmentData['description']): ?>
        <div class="mb-3">
            <strong>Mô tả:</strong>
            <p><?= nl2br(e($equipmentData['description'])) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($equipmentData['specifications']): ?>
        <div class="mb-3">
            <strong>Thông số kỹ thuật:</strong>
            <p><?= nl2br(e($equipmentData['specifications'])) ?></p>
        </div>
    <?php endif; ?>
    
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <small class="text-muted">
                Tạo lúc: <?= format_datetime($equipmentData['created_at']) ?>
            </small>
        </div>
        <div class="col-md-6 text-end">
            <small class="text-muted">
                Cập nhật: <?= format_datetime($equipmentData['updated_at']) ?>
            </small>
        </div>
    </div>
    
    <?php
    echo ob_get_clean();
    exit;
}

// Return JSON for edit modal
echo json_encode([
    'success' => true,
    'equipment' => $equipmentData
]);
?>
