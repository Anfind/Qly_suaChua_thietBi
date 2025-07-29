<?php
require_once __DIR__ . '/../config/config.php';
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
                        <div class="col-md-12">
                            <div class="card mb-3 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-desktop me-1"></i>
                                        Thông tin thiết bị cần sửa chữa <span class="text-warning">*</span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="equipment_name" class="form-label">
                                                    Tên thiết bị <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="equipment_name" name="equipment_name" 
                                                       required placeholder="VD: Máy tính để bàn, Máy in, Máy chiếu..."
                                                       value="<?= e($_POST['equipment_name'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="equipment_code" class="form-label">
                                                    Mã thiết bị (nếu có)
                                                </label>
                                                <input type="text" class="form-control" id="equipment_code" name="equipment_code" 
                                                       placeholder="VD: PC001, PRINT002..."
                                                       value="<?= e($_POST['equipment_code'] ?? '') ?>">
                                                <div class="form-text">Để trống nếu chưa có mã thiết bị</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="equipment_model" class="form-label">
                                                    Model/Phiên bản
                                                </label>
                                                <input type="text" class="form-control" id="equipment_model" name="equipment_model" 
                                                       placeholder="VD: Dell OptiPlex 7090, HP LaserJet Pro..."
                                                       value="<?= e($_POST['equipment_model'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="equipment_brand" class="form-label">
                                                    Hãng sản xuất
                                                </label>
                                                <input type="text" class="form-control" id="equipment_brand" name="equipment_brand" 
                                                       placeholder="VD: Dell, HP, Canon, Epson..."
                                                       value="<?= e($_POST['equipment_brand'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="equipment_location" class="form-label">
                                                    Vị trí đặt thiết bị <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="equipment_location" name="equipment_location" 
                                                       required placeholder="VD: Phòng 101, Tầng 2, Khoa CNTT..."
                                                       value="<?= e($_POST['equipment_location'] ?? '') ?>">
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
                                </div>
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
                                    <p><strong>Thiết bị:</strong> <span id="previewEquipmentName">-</span></p>
                                    <p><strong>Mã thiết bị:</strong> <span id="previewEquipmentCode">-</span></p>
                                    <p><strong>Model:</strong> <span id="previewEquipmentModel">-</span></p>
                                    <p><strong>Vị trí:</strong> <span id="previewEquipmentLocation">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Mức độ:</strong> <span id="previewUrgency">-</span></p>
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
    
    .card.border-primary {
        border-color: #007bff !important;
        box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.25);
    }
    
    .card-header.bg-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
    }
    
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: all 0.15s ease-in-out;
    }
    
    .form-control:hover, .form-select:hover {
        border-color: #80bdff;
    }
    
    .text-warning {
        color: #ffc107 !important;
    }
    
    .equipment-info-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        padding: 1rem;
        margin-top: 0.5rem;
    }
    
    /* Animation for form inputs */
    .form-control, .form-select {
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, transform 0.15s ease-in-out;
    }
    
    .form-control:focus, .form-select:focus {
        transform: translateY(-1px);
    }
    
    /* Improved card styling */
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: box-shadow 0.15s ease-in-out;
    }
    
    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
</style>
";

// Custom JS
$custom_js = "
<script>
    // Form input handlers for preview
    document.getElementById('equipment_name').addEventListener('input', updatePreview);
    document.getElementById('equipment_code').addEventListener('input', updatePreview);
    document.getElementById('equipment_model').addEventListener('input', updatePreview);
    document.getElementById('equipment_location').addEventListener('input', updatePreview);
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
        const equipmentName = document.getElementById('equipment_name').value;
        const equipmentCode = document.getElementById('equipment_code').value;
        const equipmentModel = document.getElementById('equipment_model').value;
        const equipmentLocation = document.getElementById('equipment_location').value;
        const urgencySelect = document.getElementById('urgency_level');
        const description = document.getElementById('problem_description').value;
        
        // Equipment info
        document.getElementById('previewEquipmentName').textContent = equipmentName || '-';
        document.getElementById('previewEquipmentCode').textContent = equipmentCode || 'Chưa có mã';
        document.getElementById('previewEquipmentModel').textContent = equipmentModel || '-';
        document.getElementById('previewEquipmentLocation').textContent = equipmentLocation || '-';
        
        // Urgency
        const urgencyText = urgencySelect.options[urgencySelect.selectedIndex].text;
        document.getElementById('previewUrgency').innerHTML = urgencyText;
        
        // Description
        document.getElementById('previewDescription').textContent = description || '-';
    }
    
    // Auto-generate equipment code suggestion
    function suggestEquipmentCode() {
        const equipmentName = document.getElementById('equipment_name').value;
        const codeInput = document.getElementById('equipment_code');
        
        if (equipmentName && !codeInput.value) {
            // Generate code from equipment name
            let code = equipmentName
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g, 'd')
                .replace(/[^a-z0-9]/g, '')
                .slice(0, 6)
                .toUpperCase();
            
            if (code.length >= 2) {
                // Add random number
                const randomNum = Math.floor(Math.random() * 999) + 1;
                code += randomNum.toString().padStart(3, '0');
                codeInput.value = code;
            }
        }
    }
    
    // Auto-suggest equipment code when name is entered
    document.getElementById('equipment_name').addEventListener('blur', suggestEquipmentCode);
    
    // Form validation before submit
    function validateForm() {
        const equipmentName = document.getElementById('equipment_name').value.trim();
        const equipmentLocation = document.getElementById('equipment_location').value.trim();
        const description = document.getElementById('problem_description').value.trim();
        
        if (!equipmentName) {
            alert('Vui lòng nhập tên thiết bị');
            document.getElementById('equipment_name').focus();
            return false;
        }
        
        if (!equipmentLocation) {
            alert('Vui lòng nhập vị trí thiết bị');
            document.getElementById('equipment_location').focus();
            return false;
        }
        
        if (!description) {
            alert('Vui lòng mô tả tình trạng lỗi');
            document.getElementById('problem_description').focus();
            return false;
        }
        
        return true;
    }
    
    // Form submit handler
    document.getElementById('createRequestForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        
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
    
    // Initialize preview on page load
    document.addEventListener('DOMContentLoaded', function() {
        updatePreview();
    });
</script>
";

include '../layouts/app.php';
?>
