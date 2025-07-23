<?php
require_once '../config/config.php';
require_login();
require_role('admin');

$title = 'Quản lý nội dung sửa chữa';
$db = Database::getInstance();

// Xử lý thêm/sửa/xóa nội dung sửa chữa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $category = trim($_POST['category'] ?? 'general');
            $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
            
            if (empty($name)) {
                throw new Exception('Tên nội dung sửa chữa không được để trống');
            }
            
            $db->insert('repair_contents', [
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'estimated_cost' => $estimated_cost,
                'status' => 'active',
                'created_by' => current_user()['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            set_flash_message('Thêm nội dung sửa chữa thành công!', 'success');
            
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $category = trim($_POST['category'] ?? 'general');
            $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
            
            if (empty($name)) {
                throw new Exception('Tên nội dung sửa chữa không được để trống');
            }
            
            $db->update('repair_contents', [
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'estimated_cost' => $estimated_cost,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            set_flash_message('Cập nhật nội dung sửa chữa thành công!', 'success');
            
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            
            $db->update('repair_contents', [
                'status' => 'deleted',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            
            set_flash_message('Xóa nội dung sửa chữa thành công!', 'success');
        }
        
        redirect('repair-contents.php');
        
    } catch (Exception $e) {
        set_flash_message('Có lỗi xảy ra: ' . $e->getMessage(), 'error');
    }
}

// Lấy danh sách nội dung sửa chữa
$repair_contents = $db->fetchAll(
    "SELECT * FROM repair_contents 
     WHERE status != 'deleted' 
     ORDER BY category, name"
);

// Lấy danh mục để group
$categories = [];
foreach ($repair_contents as $content) {
    $categories[$content['category']][] = $content;
}

$breadcrumbs = [
    ['title' => 'Admin', 'url' => url('admin/')],
    ['title' => 'Nội dung sửa chữa', 'url' => '']
];

ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-tools"></i> Quản lý nội dung sửa chữa</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Thêm nội dung
        </button>
    </div>

    <?php flash_messages(); ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list-alt fa-2x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small text-muted">Tổng nội dung</div>
                            <div class="h5 mb-0"><?= count($repair_contents) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-tags fa-2x text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small text-muted">Danh mục</div>
                            <div class="h5 mb-0"><?= count($categories) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content by Categories -->
    <?php foreach ($categories as $category => $contents): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="fas fa-folder"></i> 
                <?= ucfirst(e($category)) ?> 
                <span class="badge bg-secondary"><?= count($contents) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tên nội dung</th>
                            <th>Mô tả</th>
                            <th>Chi phí ước tính</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contents as $content): ?>
                        <tr>
                            <td>
                                <strong><?= e($content['name']) ?></strong>
                            </td>
                            <td><?= e($content['description']) ?></td>
                            <td>
                                <?php if ($content['estimated_cost'] > 0): ?>
                                    <span class="text-success fw-bold">
                                        <?= number_format($content['estimated_cost'], 0, ',', '.') ?> VNĐ
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Chưa định</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($content['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-content='<?= json_encode($content) ?>'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-id="<?= $content['id'] ?>" 
                                        data-name="<?= e($content['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($repair_contents)): ?>
    <div class="text-center py-5">
        <i class="fas fa-tools fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Chưa có nội dung sửa chữa nào</h5>
        <p class="text-muted">Hãy thêm nội dung sửa chữa đầu tiên!</p>
    </div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm nội dung sửa chữa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Tên nội dung *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Danh mục</label>
                        <select class="form-select" name="category">
                            <option value="general">Chung</option>
                            <option value="hardware">Phần cứng</option>
                            <option value="software">Phần mềm</option>
                            <option value="network">Mạng</option>
                            <option value="maintenance">Bảo trì</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Chi phí ước tính (VNĐ)</label>
                        <input type="number" class="form-control" name="estimated_cost" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Sửa nội dung sửa chữa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <?= csrf_field() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Tên nội dung *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Danh mục</label>
                        <select class="form-select" name="category" id="edit_category">
                            <option value="general">Chung</option>
                            <option value="hardware">Phần cứng</option>
                            <option value="software">Phần mềm</option>
                            <option value="network">Mạng</option>
                            <option value="maintenance">Bảo trì</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Chi phí ước tính (VNĐ)</label>
                        <input type="number" class="form-control" name="estimated_cost" id="edit_estimated_cost" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận xóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <?= csrf_field() ?>
                    
                    <p>Bạn có chắc chắn muốn xóa nội dung sửa chữa <strong id="delete_name"></strong>?</p>
                    <p class="text-muted">Hành động này không thể hoàn tác.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit button handler
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const content = JSON.parse(this.dataset.content);
            
            document.getElementById('edit_id').value = content.id;
            document.getElementById('edit_name').value = content.name;
            document.getElementById('edit_category').value = content.category;
            document.getElementById('edit_description').value = content.description || '';
            document.getElementById('edit_estimated_cost').value = content.estimated_cost || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    });
    
    // Delete button handler
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.dataset.id;
            document.getElementById('delete_name').textContent = this.dataset.name;
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../layouts/app.php';
?>
