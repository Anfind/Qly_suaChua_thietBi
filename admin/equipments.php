<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$title = 'Quản lý thiết bị';
$db = Database::getInstance();

$equipment = new Equipment();
$error = '';
$success = '';

// Xử lý action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                // Validate dữ liệu đầu vào
                if (empty($_POST['name']) || empty($_POST['code'])) {
                    throw new Exception('Tên thiết bị và mã thiết bị là bắt buộc');
                }
                
                if (empty($_POST['type_id'])) {
                    throw new Exception('Vui lòng chọn loại thiết bị');
                }
                
                $data = [
                    'name' => trim($_POST['name']),
                    'code' => trim($_POST['code']),
                    'type_id' => (int)$_POST['type_id'],
                    'model' => trim($_POST['model'] ?? ''),
                    'brand' => trim($_POST['brand'] ?? ''),
                    'purchase_date' => $_POST['purchase_date'] ?? null,
                    'warranty_date' => $_POST['warranty_date'] ?? null,
                    'status' => $_POST['status'] ?? 'active',
                    'department_id' => $_POST['department_id'] ?? null,
                    'location' => $_POST['location'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'specifications' => $_POST['specifications'] ?? '',
                    'purchase_price' => $_POST['purchase_price'] ?? null
                ];
                
                $id = $equipment->create($data);
                
                // Xử lý upload ảnh
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handle_file_upload($_FILES['image'], UPLOAD_EQUIPMENT_PATH);
                    if ($uploadResult['success']) {
                        $db->update('equipments', 
                            ['image' => $uploadResult['filename']], 
                            'id = ?', 
                            [$id]
                        );
                    }
                }
                
                $success = 'Thêm thiết bị thành công!';
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                
                // Validate dữ liệu đầu vào
                if (empty($_POST['name']) || empty($_POST['code'])) {
                    throw new Exception('Tên thiết bị và mã thiết bị là bắt buộc');
                }
                
                if (empty($_POST['type_id'])) {
                    throw new Exception('Vui lòng chọn loại thiết bị');
                }
                
                $data = [
                    'name' => trim($_POST['name']),
                    'code' => trim($_POST['code']),
                    'type_id' => (int)$_POST['type_id'],
                    'model' => trim($_POST['model'] ?? ''),
                    'brand' => trim($_POST['brand'] ?? ''),
                    'purchase_date' => $_POST['purchase_date'] ?? null,
                    'warranty_date' => $_POST['warranty_date'] ?? null,
                    'status' => $_POST['status'] ?? 'active',
                    'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
                    'location' => $_POST['location'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'specifications' => $_POST['specifications'] ?? '',
                    'purchase_price' => $_POST['purchase_price'] ?? null,
                    'supplier' => $_POST['supplier'] ?? ''
                ];
                
                $equipment->update($id, $data);
                
                // Xử lý upload ảnh mới
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handle_file_upload($_FILES['image'], UPLOAD_EQUIPMENT_PATH);
                    if ($uploadResult['success']) {
                        // Xóa ảnh cũ
                        $oldEquipment = $equipment->findById($id);
                        if ($oldEquipment && $oldEquipment['image']) {
                            $oldImagePath = UPLOAD_EQUIPMENT_PATH . $oldEquipment['image'];
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        
                        $db->update('equipments', 
                            ['image' => $uploadResult['filename']], 
                            'id = ?', 
                            [$id]
                        );
                    }
                }
                
                $success = 'Cập nhật thiết bị thành công!';
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Kiểm tra xem có đơn sửa chữa nào đang xử lý không
                $activeRequests = $db->fetch(
                    "SELECT COUNT(*) as count FROM repair_requests 
                     WHERE equipment_id = ? AND status NOT IN ('completed', 'cancelled')", 
                    [$id]
                );
                
                if ($activeRequests['count'] > 0) {
                    throw new Exception('Không thể xóa thiết bị đang có đơn sửa chữa!');
                }
                
                // Lấy thông tin ảnh để xóa
                $equipmentData = $equipment->findById($id);
                if ($equipmentData && $equipmentData['image']) {
                    $imagePath = UPLOAD_EQUIPMENT_PATH . $equipmentData['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $equipment->delete($id);
                $success = 'Xóa thiết bị thành công!';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Phân trang và tìm kiếm
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(e.name LIKE ? OR e.serial_number LIKE ? OR e.brand LIKE ? OR e.model LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($departmentFilter) {
    $whereConditions[] = "e.department_id = ?";
    $params[] = $departmentFilter;
}

if ($statusFilter) {
    $whereConditions[] = "e.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter) {
    $whereConditions[] = "e.type_id = ?";
    $params[] = $typeFilter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count total
$totalQuery = "SELECT COUNT(*) as count FROM equipments e $whereClause";
$total = $db->fetch($totalQuery, $params)['count'];
$totalPages = ceil($total / $limit);

// Get equipments
$query = "SELECT e.*, d.name as department_name 
          FROM equipments e 
          LEFT JOIN departments d ON e.department_id = d.id 
          $whereClause 
          ORDER BY e.created_at DESC 
          LIMIT $limit OFFSET $offset";

$equipments = $db->fetchAll($query, $params);

// Get departments for filter
$departments = $db->fetchAll("SELECT id, name FROM departments ORDER BY name");

// Get equipment types for filter
$equipmentTypes = $db->fetchAll("SELECT id, name FROM equipment_types ORDER BY name");

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Quản trị', 'url' => url('admin/')],
    ['title' => 'Quản lý thiết bị', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="page-title">
            <i class="fas fa-desktop me-2"></i>
            Quản lý thiết bị
        </h2>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#equipmentModal">
            <i class="fas fa-plus me-2"></i>Thêm thiết bị
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Tìm kiếm..." 
                       value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select">
                    <option value="">Tất cả phòng ban</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $departmentFilter == $dept['id'] ? 'selected' : '' ?>>
                            <?= e($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                    <option value="maintenance" <?= $statusFilter === 'maintenance' ? 'selected' : '' ?>>Bảo trì</option>
                    <option value="broken" <?= $statusFilter === 'broken' ? 'selected' : '' ?>>Hỏng</option>
                    <option value="decommissioned" <?= $statusFilter === 'decommissioned' ? 'selected' : '' ?>>Ngừng sử dụng</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="">Tất cả loại</option>
                    <?php foreach ($equipmentTypes as $type): ?>
                        <option value="<?= $type['id'] ?>" <?= $typeFilter == $type['id'] ? 'selected' : '' ?>>
                            <?= e($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>Tìm kiếm
                </button>
                <a href="equipments.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Xóa bộ lọc
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Equipments Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tên thiết bị</th>
                        <th>Mã thiết bị</th>
                        <th>Loại</th>
                        <th>Phòng ban</th>
                        <th>Trạng thái</th>
                        <th>Ngày mua</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($equipments)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Không có thiết bị nào</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipments as $eq): ?>
                            <tr>
                                <td>
                                    <?php if ($eq['image']): ?>
                                        <img src="<?= url('uploads/equipments/' . $eq['image']) ?>" 
                                             class="equipment-thumb" alt="<?= e($eq['name']) ?>"
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="equipment-thumb-placeholder">
                                            <i class="fas fa-desktop"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e($eq['name']) ?></strong><br>
                                    <small class="text-muted"><?= e($eq['brand']) ?> <?= e($eq['model']) ?></small>
                                </td>
                                <td><?= e($eq['code']) ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= e($eq['type_name'] ?? 'Chưa phân loại') ?>
                                    </span>
                                </td>
                                <td><?= e($eq['department_name'] ?? 'Chưa phân bổ') ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'active' => 'success',
                                        'maintenance' => 'warning',
                                        'broken' => 'danger',
                                        'decommissioned' => 'secondary'
                                    ];
                                    $statusLabels = [
                                        'active' => 'Hoạt động',
                                        'maintenance' => 'Bảo trì',
                                        'broken' => 'Hỏng',
                                        'decommissioned' => 'Ngừng sử dụng'
                                    ];
                                    $statusColor = $statusColors[$eq['status']] ?? 'secondary';
                                    $statusLabel = $statusLabels[$eq['status']] ?? $eq['status'];
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span>
                                </td>
                                <td><?= $eq['purchase_date'] ? format_date($eq['purchase_date']) : '-' ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="viewEquipment(<?= $eq['id'] ?>)" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="editEquipment(<?= $eq['id'] ?>)" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteEquipment(<?= $eq['id'] ?>)" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Phân trang thiết bị">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&department=<?= urlencode($departmentFilter) ?>&status=<?= urlencode($statusFilter) ?>&type=<?= urlencode($typeFilter) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Equipment Modal -->
<div class="modal fade" id="equipmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="equipmentModalTitle">Thêm thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="equipmentForm" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="id" id="equipmentId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên thiết bị <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="equipmentName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã thiết bị</label>
                            <input type="text" name="code" id="equipmentCode" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loại thiết bị</label>
                            <select name="type_id" id="equipmentType" class="form-select" required>
                                <option value="">-- Chọn loại thiết bị --</option>
                                <?php foreach ($equipmentTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>"><?= $type['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hãng</label>
                            <input type="text" name="brand" id="equipmentBrand" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" id="equipmentModel" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phòng ban</label>
                            <select name="department_id" id="equipmentDepartment" class="form-select">
                                <option value="">Chưa phân bổ</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="equipmentStatus" class="form-select">
                                <option value="active">Hoạt động</option>
                                <option value="maintenance">Bảo trì</option>
                                <option value="broken">Hỏng</option>
                                <option value="decommissioned">Ngừng sử dụng</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày mua</label>
                            <input type="date" name="purchase_date" id="equipmentPurchaseDate" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hết bảo hành</label>
                            <input type="date" name="warranty_date" id="equipmentWarranty" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vị trí</label>
                            <input type="text" name="location" id="equipmentLocation" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Giá trị (VNĐ)</label>
                            <input type="number" name="purchase_price" id="equipmentValue" class="form-control" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nhà cung cấp</label>
                        <input type="text" name="supplier" id="equipmentSupplier" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="equipmentDescription" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Thông số kỹ thuật</label>
                        <textarea name="specifications" id="equipmentSpecs" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ảnh thiết bị</label>
                        <input type="file" name="image" id="equipmentImage" class="form-control" accept="image/*">
                        <div id="currentImage" class="mt-2" style="display: none;">
                            <img id="currentImagePreview" src="" class="img-thumbnail" style="max-width: 200px;">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Equipment Modal -->
<div class="modal fade" id="viewEquipmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewEquipmentContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa thiết bị này không?</p>
                <p class="text-danger"><small>Hành động này không thể hoàn tác!</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" class="d-inline" id="deleteForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom CSS và JS
$custom_css = "
<style>
    .equipment-thumb {
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    
    .equipment-thumb-placeholder {
        width: 40px;
        height: 40px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
    }
    
    .table th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
    }
</style>
";

$custom_js = "
<script>
    function editEquipment(id) {
        fetch('get-equipment.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const eq = data.equipment;
                    
                    document.getElementById('equipmentModalTitle').textContent = 'Sửa thiết bị';
                    document.querySelector('input[name=\"action\"]').value = 'update';
                    document.getElementById('equipmentId').value = eq.id;
                    
                    document.getElementById('equipmentName').value = eq.name || '';
                    document.getElementById('equipmentCode').value = eq.code || '';
                    document.getElementById('equipmentType').value = eq.type_id || '';
                    document.getElementById('equipmentBrand').value = eq.brand || '';
                    document.getElementById('equipmentModel').value = eq.model || '';
                    document.getElementById('equipmentDepartment').value = eq.department_id || '';
                    document.getElementById('equipmentStatus').value = eq.status || '';
                    document.getElementById('equipmentPurchaseDate').value = eq.purchase_date || '';
                    document.getElementById('equipmentWarranty').value = eq.warranty_date || '';
                    document.getElementById('equipmentLocation').value = eq.location || '';
                    document.getElementById('equipmentValue').value = eq.purchase_price || '';
                    document.getElementById('equipmentDescription').value = eq.description || '';
                    document.getElementById('equipmentSpecs').value = eq.specifications || '';
                    
                    if (eq.image) {
                        document.getElementById('currentImage').style.display = 'block';
                        document.getElementById('currentImagePreview').src = '../uploads/equipments/' + eq.image;
                    } else {
                        document.getElementById('currentImage').style.display = 'none';
                    }
                    
                    new bootstrap.Modal(document.getElementById('equipmentModal')).show();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Lỗi: ' + error);
            });
    }
    
    function viewEquipment(id) {
        fetch('get-equipment.php?id=' + id + '&view=1')
            .then(response => response.text())
            .then(html => {
                document.getElementById('viewEquipmentContent').innerHTML = html;
                new bootstrap.Modal(document.getElementById('viewEquipmentModal')).show();
            })
            .catch(error => {
                alert('Lỗi: ' + error);
            });
    }
    
    function deleteEquipment(id) {
        document.getElementById('deleteId').value = id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    
    // Reset form khi mở modal thêm mới
    document.getElementById('equipmentModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('equipmentForm').reset();
        document.getElementById('equipmentModalTitle').textContent = 'Thêm thiết bị';
        document.querySelector('input[name=\"action\"]').value = 'create';
        document.getElementById('equipmentId').value = '';
        document.getElementById('currentImage').style.display = 'none';
    });
    
    // Form validation trước khi submit
    document.getElementById('equipmentForm').addEventListener('submit', function(e) {
        const name = document.getElementById('equipmentName').value.trim();
        const code = document.getElementById('equipmentCode').value.trim();
        const typeId = document.getElementById('equipmentType').value;
        
        if (!name) {
            e.preventDefault();
            alert('Vui lòng nhập tên thiết bị');
            document.getElementById('equipmentName').focus();
            return false;
        }
        
        if (!code) {
            e.preventDefault();
            alert('Vui lòng nhập mã thiết bị');
            document.getElementById('equipmentCode').focus();
            return false;
        }
        
        if (!typeId) {
            e.preventDefault();
            alert('Vui lòng chọn loại thiết bị');
            document.getElementById('equipmentType').focus();
            return false;
        }
        
        return true;
    });
</script>
";

include '../layouts/app.php';
?>
