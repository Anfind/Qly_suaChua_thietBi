<?php
/**
 * File Upload Handler - Xử lý upload file an toàn
 */
class FileUpload {
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;
    private $errors = [];
    
    public function __construct($uploadPath = UPLOAD_PATH, $allowedTypes = null, $maxSize = MAX_FILE_SIZE) {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
        $this->allowedTypes = $allowedTypes;
        $this->maxSize = $maxSize;
        
        // Tạo thư mục nếu chưa tồn tại
        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload single file
     */
    public function uploadSingle($file, $subfolder = '') {
        $this->errors = [];
        
        if (!$this->validateFile($file)) {
            return false;
        }
        
        $uploadDir = $this->uploadPath . ltrim($subfolder, '/');
        if ($subfolder && !file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = $this->generateUniqueFilename($file['name'], $uploadDir);
        $destination = $uploadDir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Resize image nếu cần
            if ($this->isImage($file)) {
                $this->resizeImage($destination);
                $this->createThumbnail($destination);
            }
            
            return [
                'filename' => $filename,
                'path' => $destination,
                'url' => upload_url(ltrim($subfolder, '/') . '/' . $filename),
                'size' => filesize($destination),
                'type' => mime_content_type($destination)
            ];
        }
        
        $this->errors[] = 'Không thể upload file';
        return false;
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultiple($files, $subfolder = '') {
        $results = [];
        $uploadedFiles = [];
        
        // Normalize files array
        $normalizedFiles = $this->normalizeFilesArray($files);
        
        foreach ($normalizedFiles as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            
            $result = $this->uploadSingle($file, $subfolder);
            if ($result) {
                $uploadedFiles[] = $result;
            } else {
                // Rollback nếu có lỗi
                foreach ($uploadedFiles as $uploaded) {
                    unlink($uploaded['path']);
                }
                return false;
            }
        }
        
        return $uploadedFiles;
    }
    
    /**
     * Validate file
     */
    private function validateFile($file) {
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }
        
        // Check file size
        if ($file['size'] > $this->maxSize) {
            $this->errors[] = 'File quá lớn. Kích thước tối đa: ' . $this->formatBytes($this->maxSize);
            return false;
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($this->allowedTypes && !in_array($extension, $this->allowedTypes)) {
            $this->errors[] = 'Loại file không được phép. Chỉ chấp nhận: ' . implode(', ', $this->allowedTypes);
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!$this->isValidMimeType($mimeType, $extension)) {
            $this->errors[] = 'File không hợp lệ hoặc bị hỏng';
            return false;
        }
        
        // Check for malicious content
        if ($this->containsMaliciousContent($file['tmp_name'])) {
            $this->errors[] = 'File chứa nội dung nguy hiểm';
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName, $directory) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize filename
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $baseName = trim($baseName, '_');
        
        if (empty($baseName)) {
            $baseName = 'file_' . time();
        }
        
        $filename = $baseName . '.' . $extension;
        $counter = 0;
        
        while (file_exists($directory . '/' . $filename)) {
            $counter++;
            $filename = $baseName . '_' . $counter . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Resize image if too large
     */
    private function resizeImage($imagePath, $maxWidth = 1200, $maxHeight = 800, $quality = 85) {
        if (!extension_loaded('gd')) return false;
        
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) return false;
        
        list($originalWidth, $originalHeight, $imageType) = $imageInfo;
        
        // Không resize nếu ảnh đã nhỏ hơn max
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return true;
        }
        
        // Tính tỷ lệ
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
        
        // Tạo image resource
        $sourceImage = $this->createImageResource($imagePath, $imageType);
        if (!$sourceImage) return false;
        
        // Tạo ảnh mới
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Save
        $this->saveImageResource($newImage, $imagePath, $imageType, $quality);
        
        // Cleanup
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return true;
    }
    
    /**
     * Create thumbnail
     */
    private function createThumbnail($imagePath, $thumbnailSize = 150) {
        if (!extension_loaded('gd')) return false;
        
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) return false;
        
        list($originalWidth, $originalHeight, $imageType) = $imageInfo;
        
        $sourceImage = $this->createImageResource($imagePath, $imageType);
        if (!$sourceImage) return false;
        
        // Tạo thumbnail vuông
        $size = min($originalWidth, $originalHeight);
        $x = ($originalWidth - $size) / 2;
        $y = ($originalHeight - $size) / 2;
        
        $thumbnail = imagecreatetruecolor($thumbnailSize, $thumbnailSize);
        
        // Preserve transparency
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbnailSize, $thumbnailSize, $transparent);
        }
        
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, $x, $y, $thumbnailSize, $thumbnailSize, $size, $size);
        
        // Save thumbnail
        $pathInfo = pathinfo($imagePath);
        $thumbnailPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
        $this->saveImageResource($thumbnail, $thumbnailPath, $imageType);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $thumbnailPath;
    }
    
    /**
     * Create image resource from file
     */
    private function createImageResource($imagePath, $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($imagePath);
            default:
                return false;
        }
    }
    
    /**
     * Save image resource to file
     */
    private function saveImageResource($imageResource, $path, $imageType, $quality = 85) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($imageResource, $path, $quality);
            case IMAGETYPE_PNG:
                return imagepng($imageResource, $path, 9);
            case IMAGETYPE_GIF:
                return imagegif($imageResource, $path);
            case IMAGETYPE_WEBP:
                return imagewebp($imageResource, $path, $quality);
            default:
                return false;
        }
    }
    
    /**
     * Check if file is image
     */
    private function isImage($file) {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($extension, $imageTypes);
    }
    
    /**
     * Validate MIME type
     */
    private function isValidMimeType($mimeType, $extension) {
        $validMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        return isset($validMimes[$extension]) && $validMimes[$extension] === $mimeType;
    }
    
    /**
     * Check for malicious content
     */
    private function containsMaliciousContent($filePath) {
        // Check for PHP tags in uploaded files
        $content = file_get_contents($filePath, false, null, 0, 1024); // Read first 1KB
        $maliciousPatterns = [
            '/<\?php/',
            '/<\?=/',
            '/<script/',
            '/javascript:/',
            '/eval\s*\(/',
            '/base64_decode\s*\(/'
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Normalize files array for multiple uploads
     */
    private function normalizeFilesArray($files) {
        $normalizedFiles = [];
        
        if (isset($files['name']) && is_array($files['name'])) {
            // Multiple files from same input
            for ($i = 0; $i < count($files['name']); $i++) {
                $normalizedFiles[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
            }
        } else {
            // Single file
            $normalizedFiles[] = $files;
        }
        
        return $normalizedFiles;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File quá lớn (vượt quá upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File quá lớn (vượt quá MAX_FILE_SIZE trong form)',
            UPLOAD_ERR_PARTIAL => 'File được upload không hoàn chỉnh',
            UPLOAD_ERR_NO_FILE => 'Không có file được upload',
            UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm',
            UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file vào disk',
            UPLOAD_ERR_EXTENSION => 'File upload bị dừng bởi extension'
        ];
        
        return $messages[$errorCode] ?? 'Lỗi upload không xác định';
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Delete uploaded file
     */
    public function deleteFile($filePath) {
        if (file_exists($filePath)) {
            unlink($filePath);
            
            // Delete thumbnail if exists
            $pathInfo = pathinfo($filePath);
            $thumbnailPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            return true;
        }
        return false;
    }
    
    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        return end($this->errors);
    }
}
?>
