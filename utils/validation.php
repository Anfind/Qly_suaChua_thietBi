<?php
/**
 * Validation helpers
 */

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Vietnam format)
 */
function validate_phone($phone) {
    return preg_match('/^(0|\+84)[0-9]{9,10}$/', $phone);
}

/**
 * Validate strong password
 */
function validate_strong_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

/**
 * Validate equipment code format
 */
function validate_equipment_code($code) {
    // Format: DEPT-TYPE-0001
    return preg_match('/^[A-Z]{2,5}-[A-Z]{2,5}-\d{4}$/', $code);
}

/**
 * Sanitize text input
 */
function sanitize_text($text) {
    return trim(filter_var($text, FILTER_SANITIZE_STRING));
}

/**
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types = [], $max_size = MAX_FILE_SIZE) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Lỗi upload file';
        return $errors;
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = 'File quá lớn (tối đa ' . ($max_size / 1024 / 1024) . 'MB)';
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowed_types) && !in_array($extension, $allowed_types)) {
        $errors[] = 'Loại file không được phép';
    }
    
    // Check for malicious files
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4'
    ];
    
    if (!empty($allowed_types) && !in_array($mime_type, array_values($allowed_mimes))) {
        $errors[] = 'File không đúng định dạng';
    }
    
    return $errors;
}

/**
 * Validate date format
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Clean and validate JSON data
 */
function validate_json_array($json_string) {
    $data = json_decode($json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    return is_array($data) ? $data : false;
}
?>
