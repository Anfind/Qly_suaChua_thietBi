-- Tạo bảng workflow_templates nếu chưa tồn tại
CREATE TABLE IF NOT EXISTS `workflow_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Tên template',
  `description` text DEFAULT NULL COMMENT 'Mô tả template',
  `equipment_type_id` int(11) DEFAULT NULL COMMENT 'Loại thiết bị áp dụng (NULL = áp dụng cho tất cả)',
  `departments_sequence` json NOT NULL COMMENT 'Chuỗi phòng ban theo thứ tự sửa chữa',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Template mặc định',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_type` (`equipment_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_default` (`is_default`),
  FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu cho workflow templates
INSERT INTO `workflow_templates` (`name`, `description`, `equipment_type_id`, `departments_sequence`, `is_default`, `created_by`) VALUES
('Sửa chữa máy tính cơ bản', 'Quy trình sửa chữa máy tính: Phần cứng -> Phần mềm', 1,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_B')
 ), 1, 1),
('Sửa chữa máy tính phức tạp', 'Quy trình sửa chữa máy tính phức tạp: Phần cứng -> Phần mềm -> Mạng', 1,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_B'),
   (SELECT id FROM departments WHERE code = 'TECH_C')
 ), 0, 1),
('Sửa chữa máy in', 'Quy trình sửa chữa máy in: Phần cứng -> Bảo trì chuyên sâu', 2,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_D')
 ), 1, 1),
('Sửa chữa điều hòa', 'Quy trình sửa chữa điều hòa: Bảo trì chuyên sâu', 3,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_D')
 ), 1, 1),
('Sửa chữa thiết bị mạng', 'Quy trình sửa chữa thiết bị mạng: Mạng -> Phần mềm', 4,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_C'),
   (SELECT id FROM departments WHERE code = 'TECH_B')
 ), 1, 1);

-- Thêm template đa năng cho thiết bị khác
INSERT INTO `workflow_templates` (`name`, `description`, `equipment_type_id`, `departments_sequence`, `is_default`, `created_by`) VALUES
('Quy trình chuẩn (A->B->C)', 'Quy trình sửa chữa chuẩn 3 phòng: Phần cứng -> Phần mềm -> Mạng', NULL,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_B'),
   (SELECT id FROM departments WHERE code = 'TECH_C')
 ), 0, 1),
('Quy trình nhanh (A->D)', 'Quy trình sửa chữa nhanh 2 phòng: Phần cứng -> Bảo trì', NULL,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_D')
 ), 0, 1);
