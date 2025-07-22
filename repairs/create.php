<?php
require_once '../config/config.php';
require_role('requester');

$controller = new RepairController();
$data = $controller->create();

$title = 'Tạo đơn sửa chữa mới';
$error = $data['error'] ?? '';
$equipments = $data['equipments'] ?? [];

$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Đơn sửa chữa', 'url' => url('repairs/')],
    ['title' => 'Tạo đơn mới', 'url' => '']
];

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Tạo đơn sửa chữa mới
                </h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="createRequestForm">
                    <?= csrf_field() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="equipment_id" class="form-label">
                                    <i class="fas fa-desktop me-1"></i>
                                    Thiết bị cần sửa chữa <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="equipment_id" name="equipment_id" required>
                                    <option value="">-- Chọn thiết bị --</option>
                                    <?php foreach ($equipments as $equipment): ?>
                                        <option value="<?= $equipment['id'] ?>" 
                                                data-model="<?= e($equipment['model']) ?>"
                                                data-location="<?= e($equipment['location']) ?>"
                                                <?= ($_POST['equipment_id'] ?? '') == $equipment['id'] ? 'selected' : '' ?>>
                                            <?= e($equipment['name']) ?> (<?= e($equipment['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="equipmentInfo"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="urgency_level" class="form-label">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    Mức độ khẩn cấp
                                </label>
                                <select class="form-select" id="urgency_level" name="urgency_level">
                                    <option value="low" <?= ($_POST['urgency_level'] ?? 'medium') === 'low' ? 'selected' : '' ?>>
                                        🟢 Thấp - Không ảnh hưởng công việc
                                    </option>
                                    <option value="medium" <?= ($_POST['urgency_level'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>
                                        🟡 Trung bình - Ảnh hưởng một phần
                                    </option>
                                    <option value="high" <?= ($_POST['urgency_level'] ?? 'medium') === 'high' ? 'selected' : '' ?>>
                                        🟠 Cao - Ảnh hưởng nghiêm trọng
                                    </option>
                                    <option value="critical" <?= ($_POST['urgency_level'] ?? 'medium') === 'critical' ? 'selected' : '' ?>>
                                        🔴 Khẩn cấp - Dừng hoàn toàn
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="problem_description" class="form-label">
                            <i class="fas fa-clipboard-list me-1"></i>
                            Mô tả tình trạng lỗi <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="problem_description" name="problem_description" 
                                  rows="4" required placeholder="Mô tả chi tiết hiện tượng, lỗi gặp phải..."><?= e($_POST['problem_description'] ?? '') ?></textarea>
                        <div class="form-text">
                            Hãy mô tả cụ thể: triệu chứng, thời điểm xảy ra, tần suất, những gì bạn đã thử...
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="images" class="form-label">
                                    <i class="fas fa-images me-1"></i>
                                    Hình ảnh minh họa
                                </label>
                                <input type="file" class="form-control" id="images" name="images[]" 
                                       multiple accept="image/*" onchange="previewImages(this)">
                                <div class="form-text">
                                    Chọn nhiều hình ảnh để minh họa tình trạng lỗi (tối đa 10MB/file)
                                </div>
                                <div id="imagePreview" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="videos" class="form-label">
                                    <i class="fas fa-video me-1"></i>
                                    Video minh họa
                                </label>
                                <input type="file" class="form-control" id="videos" name="videos[]" 
                                       multiple accept="video/*" onchange="previewVideos(this)">
                                <div class="form-text">
                                    Video ghi lại hiện tượng lỗi (tối đa 10MB/file)
                                </div>
                                <div id="videoPreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview card for request -->
                    <div class="card bg-light mb-3" style="display: none;" id="requestPreview">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-eye me-1"></i>
                                Xem trước đơn sửa chữa
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Thiết bị:</strong> <span id="previewEquipment">-</span></p>
                                    <p><strong>Mức độ:</strong> <span id="previewUrgency">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Người đề xuất:</strong> <?= e(current_user()['full_name']) ?></p>
                                    <p><strong>Đơn vị:</strong> <?= e(current_user()['department_name']) ?></p>
                                </div>
                            </div>
                            <p><strong>Mô tả lỗi:</strong></p>
                            <div class="border rounded p-2 bg-white" id="previewDescription">-</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('repairs/') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </a>
                        
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="togglePreview()">
                                <i class="fas fa-eye me-2"></i>Xem trước
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Tạo đơn sửa chữa
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom CSS
$custom_css = "
<style>
    .image-preview, .video-preview {
        display: inline-block;
        margin: 5px;
        position: relative;
    }
    
    .image-preview img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 5px;
        border: 2px solid #ddd;
    }
    
    .video-preview video {
        width: 150px;
        height: 100px;
        border-radius: 5px;
        border: 2px solid #ddd;
    }
    
    .preview-remove {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 20px;
        height: 20px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .urgency-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }
    
    .equipment-card {
        border: 1px solid #e3e6f0;
        border-radius: 5px;
        padding: 10px;
        margin-top: 10px;
        background: #f8f9fc;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        transform: translateY(-1px);
    }
</style>
";

// Custom JS
$custom_js = "
<script>
    // Equipment selection handler
    document.getElementById('equipment_id').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const infoDiv = document.getElementById('equipmentInfo');
        
        if (option.value) {
            const model = option.dataset.model || 'Không có';
            const location = option.dataset.location || 'Không có';
            
            infoDiv.innerHTML = `
                <div class='equipment-card'>
                    <small><strong>Model:</strong> \${model}</small><br>
                    <small><strong>Vị trí:</strong> \${location}</small>
                </div>
            `;
        } else {
            infoDiv.innerHTML = '';
        }
        
        updatePreview();
    });
    
    // Problem description handler
    document.getElementById('problem_description').addEventListener('input', updatePreview);
    document.getElementById('urgency_level').addEventListener('change', updatePreview);
    
    // Image preview
    function previewImages(input) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        
        if (input.files) {
            Array.from(input.files).forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'image-preview';
                        div.innerHTML = `
                            <img src='\${e.target.result}' alt='Preview'>
                            <button type='button' class='preview-remove' onclick='removeFile(this, \"images\", \${index})'>×</button>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }
    
    // Video preview  
    function previewVideos(input) {
        const preview = document.getElementById('videoPreview');
        preview.innerHTML = '';
        
        if (input.files) {
            Array.from(input.files).forEach((file, index) => {
                if (file.type.startsWith('video/')) {
                    const div = document.createElement('div');
                    div.className = 'video-preview';
                    div.innerHTML = `
                        <video controls>
                            <source src='\${URL.createObjectURL(file)}' type='\${file.type}'>
                        </video>
                        <button type='button' class='preview-remove' onclick='removeFile(this, \"videos\", \${index})'>×</button>
                    `;
                    preview.appendChild(div);
                }
            });
        }
    }
    
    // Remove file from preview
    function removeFile(button, inputName, index) {
        const input = document.querySelector(`input[name=\"\${inputName}[]\"]`);
        const dt = new DataTransfer();
        
        Array.from(input.files).forEach((file, i) => {
            if (i !== index) dt.items.add(file);
        });
        
        input.files = dt.files;
        button.parentElement.remove();
    }
    
    // Toggle preview
    function togglePreview() {
        const preview = document.getElementById('requestPreview');
        if (preview.style.display === 'none') {
            updatePreview();
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }
    
    // Update preview content
    function updatePreview() {
        const equipmentSelect = document.getElementById('equipment_id');
        const urgencySelect = document.getElementById('urgency_level');
        const description = document.getElementById('problem_description').value;
        
        // Equipment
        const equipmentText = equipmentSelect.options[equipmentSelect.selectedIndex].text;
        document.getElementById('previewEquipment').textContent = equipmentText === '-- Chọn thiết bị --' ? '-' : equipmentText;
        
        // Urgency
        const urgencyText = urgencySelect.options[urgencySelect.selectedIndex].text;
        document.getElementById('previewUrgency').innerHTML = urgencyText;
        
        // Description
        document.getElementById('previewDescription').textContent = description || '-';
    }
    
    // Form validation
    document.getElementById('createRequestForm').addEventListener('submit', function(e) {
        const submitBtn = e.target.querySelector('button[type=\"submit\"]');
        submitBtn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span>Đang tạo đơn...';
        submitBtn.disabled = true;
    });
    
    // Auto-resize textarea
    const textarea = document.getElementById('problem_description');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
</script>
";

include '../layouts/app.php';
?>
