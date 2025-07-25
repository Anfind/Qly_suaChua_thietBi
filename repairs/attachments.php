<?php
/**
 * Trang xem tất cả file đính kèm của đơn sửa chữa
 * Tất cả role có thể xem
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$db = Database::getInstance();

$code = $_GET['code'] ?? '';
if (empty($code)) {
    redirect('repairs/', 'Không tìm thấy đơn sửa chữa', 'error');
}

// Lấy thông tin đơn
$request = $db->fetch(
    "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
            u.full_name as requester_name, d.name as department_name,
            s.name as status_name, s.color as status_color, s.icon as status_icon
     FROM repair_requests r
     LEFT JOIN equipments e ON r.equipment_id = e.id
     LEFT JOIN users u ON r.requester_id = u.id
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN repair_statuses s ON r.current_status_id = s.id
     WHERE r.request_code = ?",
    [$code]
);

if (!$request) {
    redirect('repairs/', 'Không tìm thấy đơn sửa chữa', 'error');
}

// Lấy chi tiết sửa chữa với ảnh
$repairDetails = $db->fetchAll(
    "SELECT d.*, u.full_name as technician_name
     FROM repair_details d
     LEFT JOIN users u ON d.technician_id = u.id
     WHERE d.request_id = ?
     ORDER BY d.created_at ASC",
    [$request['id']]
);

// Lấy lịch sử trạng thái với attachments
$statusHistory = $db->fetchAll(
    "SELECT h.*, u.full_name as user_name, u_role.name as role_name,
            s.name as status_name, s.color as status_color, s.icon as status_icon
     FROM repair_status_history h
     LEFT JOIN users u ON h.user_id = u.id
     LEFT JOIN roles u_role ON u.role_id = u_role.id
     LEFT JOIN repair_statuses s ON h.status_id = s.id
     WHERE h.request_id = ?
     ORDER BY h.created_at ASC",
    [$request['id']]
);

// Decode JSON fields
$requestImages = json_decode($request['images'] ?? '[]', true);
$requestVideos = json_decode($request['videos'] ?? '[]', true);

$title = 'File đính kèm - Đơn ' . $request['request_code'];

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Đơn sửa chữa', 'url' => url('repairs/')],
    ['title' => $request['request_code'], 'url' => url('repairs/view.php?code=' . $request['request_code'])],
    ['title' => 'File đính kèm', 'url' => '']
];

ob_start();
?>

<style>
.attachment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.attachment-item {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}

.attachment-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.attachment-preview {
    width: 100%;
    height: 150px;
    object-fit: cover;
    cursor: pointer;
}

.attachment-info {
    padding: 10px;
}

.attachment-name {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.attachment-meta {
    font-size: 11px;
    color: #999;
}

.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.lightbox {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
    justify-content: center;
    align-items: center;
}

.lightbox-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 30px;
    cursor: pointer;
    z-index: 10000;
}

.video-player {
    width: 100%;
    height: 150px;
    background: #000;
    border-radius: 4px;
}

.file-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 150px;
    background: #f8f9fa;
    color: #6c757d;
    font-size: 48px;
}

.download-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    border: none;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.attachment-item:hover .download-btn {
    opacity: 1;
}

.no-attachments {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}
</style>

<!-- Request header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="page-title mb-2">
            <i class="fas fa-paperclip me-2"></i>
            File đính kèm - Đơn #<?= e($request['request_code']) ?>
        </h2>
        <div class="d-flex align-items-center gap-3">
            <span class="status-badge" style="background-color: <?= e($request['status_color']) ?>15; color: <?= e($request['status_color']) ?>;">
                <i class="<?= e($request['status_icon']) ?> me-1"></i>
                <?= e($request['status_name']) ?>
            </span>
            <small class="text-muted">
                <i class="fas fa-building me-1"></i><?= e($request['department_name']) ?>
            </small>
        </div>
    </div>
    <div>
        <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Quay lại chi tiết đơn
        </a>
    </div>
</div>

<!-- Equipment info -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h5><?= e($request['equipment_name']) ?></h5>
                <p class="text-muted mb-2">Mã: <?= e($request['equipment_code']) ?></p>
                <p class="mb-0"><strong>Người đề xuất:</strong> <?= e($request['requester_name']) ?></p>
            </div>
            <div class="col-md-4 text-end">
                <small class="text-muted">Tạo lúc: <?= format_datetime($request['created_at']) ?></small>
            </div>
        </div>
    </div>
</div>

<!-- File đính kèm ban đầu của đơn -->
<?php if (!empty($requestImages) || !empty($requestVideos)): ?>
    <div class="section-header">
        <div class="d-flex align-items-center">
            <i class="fas fa-file-image me-2"></i>
            <div>
                <h5 class="mb-0">File đính kèm ban đầu</h5>
                <small class="opacity-75">Được đính kèm khi tạo đơn</small>
            </div>
        </div>
    </div>

    <?php if (!empty($requestImages)): ?>
        <h6 class="mb-3"><i class="fas fa-image me-2"></i>Hình ảnh (<?= count($requestImages) ?>)</h6>
        <div class="attachment-grid">
            <?php foreach ($requestImages as $index => $image): ?>
                <div class="attachment-item position-relative">
                    <img src="<?= upload_url('requests/' . $image) ?>" 
                         class="attachment-preview" 
                         onclick="openLightbox('<?= upload_url('requests/' . $image) ?>', 'image')"
                         alt="Hình ảnh đính kèm">
                    <button class="download-btn" onclick="downloadFile('<?= upload_url('requests/' . $image) ?>', '<?= basename($image) ?>')">
                        <i class="fas fa-download"></i>
                    </button>
                    <div class="attachment-info">
                        <div class="attachment-name"><?= e(basename($image)) ?></div>
                        <div class="attachment-meta">Hình ảnh • Đính kèm ban đầu</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($requestVideos)): ?>
        <h6 class="mb-3 mt-4"><i class="fas fa-video me-2"></i>Video (<?= count($requestVideos) ?>)</h6>
        <div class="attachment-grid">
            <?php foreach ($requestVideos as $index => $video): ?>
                <div class="attachment-item position-relative">
                    <video class="video-player" controls>
                        <source src="<?= upload_url('requests/' . $video) ?>" type="video/mp4">
                        Trình duyệt không hỗ trợ video.
                    </video>
                    <button class="download-btn" onclick="downloadFile('<?= upload_url('requests/' . $video) ?>', '<?= basename($video) ?>')">
                        <i class="fas fa-download"></i>
                    </button>
                    <div class="attachment-info">
                        <div class="attachment-name"><?= e(basename($video)) ?></div>
                        <div class="attachment-meta">Video • Đính kèm ban đầu</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- File từ chi tiết sửa chữa -->
<?php if (!empty($repairDetails)): ?>
    <?php 
    $hasRepairImages = false;
    foreach ($repairDetails as $detail) {
        $detailImages = json_decode($detail['images'] ?? '[]', true);
        if (!empty($detailImages)) {
            $hasRepairImages = true;
            break;
        }
    }
    ?>
    
    <?php if ($hasRepairImages): ?>
        <div class="section-header mt-5">
            <div class="d-flex align-items-center">
                <i class="fas fa-tools me-2"></i>
                <div>
                    <h5 class="mb-0">Hình ảnh từ quá trình sửa chữa</h5>
                    <small class="opacity-75">Được chụp bởi kỹ thuật viên trong quá trình sửa chữa</small>
                </div>
            </div>
        </div>

        <?php foreach ($repairDetails as $detail): ?>
            <?php 
            $detailImages = json_decode($detail['images'] ?? '[]', true);
            if (empty($detailImages)) continue;
            ?>
            
            <div class="mb-4">
                <h6 class="mb-3">
                    <i class="fas fa-user me-2"></i><?= e($detail['technician_name']) ?>
                    <small class="text-muted ms-2"><?= format_datetime($detail['created_at']) ?></small>
                </h6>
                
                <?php if ($detail['description']): ?>
                    <div class="alert alert-light mb-3">
                        <i class="fas fa-comment me-2"></i><?= nl2br(e($detail['description'])) ?>
                    </div>
                <?php endif; ?>
                
                <div class="attachment-grid">
                    <?php foreach ($detailImages as $image): ?>
                        <div class="attachment-item position-relative">
                            <img src="<?= upload_url('requests/' . $image) ?>" 
                                 class="attachment-preview" 
                                 onclick="openLightbox('<?= upload_url('requests/' . $image) ?>', 'image')"
                                 alt="Hình ảnh sửa chữa">
                            <button class="download-btn" onclick="downloadFile('<?= upload_url('requests/' . $image) ?>', '<?= basename($image) ?>')">
                                <i class="fas fa-download"></i>
                            </button>
                            <div class="attachment-info">
                                <div class="attachment-name"><?= e(basename($image)) ?></div>
                                <div class="attachment-meta">Sửa chữa • <?= e($detail['technician_name']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- File từ lịch sử trạng thái -->
<?php 
$hasStatusAttachments = false;
foreach ($statusHistory as $history) {
    $attachments = json_decode($history['attachments'] ?? '[]', true);
    if (!empty($attachments)) {
        $hasStatusAttachments = true;
        break;
    }
}
?>

<?php if ($hasStatusAttachments): ?>
    <div class="section-header mt-5">
        <div class="d-flex align-items-center">
            <i class="fas fa-history me-2"></i>
            <div>
                <h5 class="mb-0">File đính kèm từ cập nhật trạng thái</h5>
                <small class="opacity-75">Được đính kèm khi cập nhật trạng thái đơn</small>
            </div>
        </div>
    </div>

    <?php foreach ($statusHistory as $history): ?>
        <?php 
        $attachments = json_decode($history['attachments'] ?? '[]', true);
        if (empty($attachments)) continue;
        ?>
        
        <div class="mb-4">
            <h6 class="mb-3">
                <span class="status-badge" style="background-color: <?= e($history['status_color']) ?>15; color: <?= e($history['status_color']) ?>;">
                    <i class="<?= e($history['status_icon']) ?> me-1"></i>
                    <?= e($history['status_name']) ?>
                </span>
                <span class="ms-2"><?= e($history['user_name']) ?> (<?= e($history['role_name']) ?>)</span>
                <small class="text-muted ms-2"><?= format_datetime($history['created_at']) ?></small>
            </h6>
            
            <?php if ($history['notes']): ?>
                <div class="alert alert-light mb-3">
                    <i class="fas fa-comment me-2"></i><?= nl2br(e($history['notes'])) ?>
                </div>
            <?php endif; ?>
            
            <div class="attachment-grid">
                <?php foreach ($attachments as $attachment): ?>
                    <?php 
                    $fileExt = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
                    $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $isVideo = in_array($fileExt, ['mp4', 'avi', 'mov', 'wmv']);
                    ?>
                    
                    <div class="attachment-item position-relative">
                        <?php if ($isImage): ?>
                            <img src="<?= upload_url('requests/' . $attachment) ?>" 
                                 class="attachment-preview" 
                                 onclick="openLightbox('<?= upload_url('requests/' . $attachment) ?>', 'image')"
                                 alt="File đính kèm">
                        <?php elseif ($isVideo): ?>
                            <video class="video-player" controls>
                                <source src="<?= upload_url('requests/' . $attachment) ?>" type="video/mp4">
                                Trình duyệt không hỗ trợ video.
                            </video>
                        <?php else: ?>
                            <div class="file-icon">
                                <i class="fas fa-file"></i>
                            </div>
                        <?php endif; ?>
                        
                        <button class="download-btn" onclick="downloadFile('<?= upload_url('requests/' . $attachment) ?>', '<?= basename($attachment) ?>')">
                            <i class="fas fa-download"></i>
                        </button>
                        
                        <div class="attachment-info">
                            <div class="attachment-name"><?= e(basename($attachment)) ?></div>
                            <div class="attachment-meta">
                                <?= ucfirst($fileExt) ?> • <?= e($history['status_name']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Không có file nào -->
<?php if (empty($requestImages) && empty($requestVideos) && !$hasRepairImages && !$hasStatusAttachments): ?>
    <div class="no-attachments">
        <div class="empty-icon">
            <i class="fas fa-folder-open"></i>
        </div>
        <h5>Không có file đính kèm</h5>
        <p class="text-muted">Đơn này chưa có file hoặc hình ảnh nào được đính kèm.</p>
    </div>
<?php endif; ?>

<!-- Lightbox for images -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightbox-img" class="lightbox-content">
</div>

<script>
function openLightbox(src, type) {
    if (type === 'image') {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').style.display = 'flex';
    }
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}

function downloadFile(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Close lightbox on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});
</script>

<?php 
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
