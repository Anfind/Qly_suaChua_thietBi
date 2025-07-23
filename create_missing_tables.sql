-- Tạo table repair_contents để quản lý nội dung sửa chữa
CREATE TABLE IF NOT EXISTS `repair_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `estimated_cost` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive','deleted') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu
INSERT INTO `repair_contents` (`name`, `description`, `category`, `estimated_cost`, `created_by`) VALUES
('Thay thế màn hình LCD', 'Thay thế màn hình LCD bị vỡ hoặc không hiển thị', 'hardware', 500000, 1),
('Sửa chữa bàn phím', 'Sửa chữa hoặc thay thế bàn phím không hoạt động', 'hardware', 200000, 1),
('Cài đặt lại hệ điều hành', 'Format và cài đặt lại Windows/Linux', 'software', 100000, 1),
('Diệt virus', 'Quét và diệt virus, malware', 'software', 50000, 1),
('Sửa chữa card mạng', 'Sửa chữa kết nối mạng không ổn định', 'network', 150000, 1),
('Thay thế ổ cứng', 'Thay thế ổ cứng bị hỏng', 'hardware', 800000, 1),
('Vệ sinh máy tính', 'Vệ sinh bụi bẩn, thay keo tản nhiệt', 'maintenance', 80000, 1),
('Nâng cấp RAM', 'Thêm hoặc thay thế RAM', 'hardware', 300000, 1);

-- Tạo table system_settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm settings mặc định
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `category`) VALUES
('app_name', 'Hệ thống quản lý sửa chữa thiết bị', 'string', 'Tên ứng dụng', 'general'),
('app_version', '1.0.0', 'string', 'Phiên bản ứng dụng', 'general'),
('max_file_size', '10485760', 'number', 'Kích thước file tối đa (bytes)', 'upload'),
('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx', 'string', 'Các loại file được phép upload', 'upload'),
('email_notifications', '1', 'boolean', 'Bật thông báo email', 'notifications'),
('maintenance_mode', '0', 'boolean', 'Chế độ bảo trì', 'general'),
('session_timeout', '3600', 'number', 'Thời gian timeout session (giây)', 'security'),
('password_min_length', '6', 'number', 'Độ dài mật khẩu tối thiểu', 'security');
