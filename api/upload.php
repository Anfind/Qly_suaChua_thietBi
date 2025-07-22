<?php
/**
 * Ajax handler cho file uploads
 */
require_once '../config/config.php';
require_login();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }
    
    $type = $_GET['type'] ?? 'general';
    $subfolder = '';
    $allowedTypes = null;
    
    // Xác định thư mục và loại file cho phép
    switch ($type) {
        case 'equipment':
            $subfolder = 'equipments';
            $allowedTypes = ALLOWED_IMAGE_TYPES;
            break;
        case 'request':
            $subfolder = 'requests';
            $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES);
            break;
        case 'avatar':
            $subfolder = 'avatars';
            $allowedTypes = ALLOWED_IMAGE_TYPES;
            break;
        case 'document':
            $subfolder = 'documents';
            $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES);
            break;
        default:
            $subfolder = 'general';
            $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES, ALLOWED_DOCUMENT_TYPES);
    }
    
    $fileUpload = new FileUpload(UPLOAD_PATH, $allowedTypes, MAX_FILE_SIZE);
    
    // Upload multiple files
    if (isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
        $results = $fileUpload->uploadMultiple($_FILES['files'], $subfolder);
    } else {
        // Single file
        $results = $fileUpload->uploadSingle($_FILES['files'], $subfolder);
        if ($results) {
            $results = [$results];
        }
    }
    
    if ($results === false) {
        throw new Exception(implode(', ', $fileUpload->getErrors()));
    }
    
    echo json_encode([
        'success' => true,
        'files' => $results,
        'message' => 'Upload thành công ' . count($results) . ' file(s)'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
