-- Hệ thống quản lý sửa chữa thiết bị
-- Tạo database
CREATE DATABASE IF NOT EXISTS equipment_repair_management 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE equipment_repair_management;

-- Bảng vai trò
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng đơn vị
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    manager_name VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng người dùng
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    department_id INT,
    role_id INT NOT NULL,
    avatar VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

-- Bảng loại thiết bị
CREATE TABLE equipment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-tools',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng thiết bị
CREATE TABLE equipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    model VARCHAR(100),
    brand VARCHAR(100),
    type_id INT,
    department_id INT,
    location VARCHAR(200),
    purchase_date DATE,
    warranty_date DATE,
    purchase_price DECIMAL(15,2),
    specifications TEXT,
    image VARCHAR(255),
    status ENUM('active', 'maintenance', 'damaged', 'disposed') DEFAULT 'active',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES equipment_types(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Bảng trạng thái đơn
CREATE TABLE repair_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    step_order INT NOT NULL,
    color VARCHAR(20) DEFAULT '#007bff',
    icon VARCHAR(50) DEFAULT 'fas fa-circle',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng đơn sửa chữa
CREATE TABLE repair_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(30) NOT NULL UNIQUE,
    equipment_id INT NOT NULL,
    requester_id INT NOT NULL,
    problem_description TEXT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    images TEXT, -- JSON array chứa đường dẫn ảnh
    videos TEXT, -- JSON array chứa đường dẫn video
    current_status_id INT NOT NULL,
    assigned_logistics_id INT,
    assigned_clerk_id INT,
    assigned_technician_id INT,
    estimated_completion DATE,
    actual_completion DATETIME,
    total_cost DECIMAL(15,2) DEFAULT 0,
    logistics_received_at TIMESTAMP NULL COMMENT 'Thời gian giao liên nhận đề xuất',
    logistics_handover_at TIMESTAMP NULL COMMENT 'Thời gian giao liên bàn giao cho văn thư',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE RESTRICT,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (current_status_id) REFERENCES repair_statuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_logistics_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_clerk_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Bảng lịch sử trạng thái
CREATE TABLE repair_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    status_id INT NOT NULL,
    user_id INT NOT NULL,
    notes TEXT,
    attachments TEXT, -- JSON array
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES repair_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES repair_statuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Bảng chi tiết sửa chữa
CREATE TABLE repair_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    content_id INT,
    description TEXT NOT NULL,
    parts_replaced TEXT,
    parts_cost DECIMAL(15,2) DEFAULT 0,
    labor_cost DECIMAL(15,2) DEFAULT 0,
    time_spent INT DEFAULT 0, -- số giờ
    technician_id INT,
    notes TEXT,
    images TEXT, -- JSON array
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES repair_requests(id) ON DELETE CASCADE,
    -- FOREIGN KEY (content_id) REFERENCES repair_contents(id) ON DELETE SET NULL, -- Will reference new repair_contents table
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Bảng đánh giá sau sửa chữa
CREATE TABLE repair_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES repair_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Chèn dữ liệu vai trò
INSERT INTO roles (name, display_name, description) VALUES
('requester', 'Người đề xuất', 'Tạo yêu cầu sửa chữa thiết bị và xem lịch sử cá nhân'),
('logistics', 'Giao liên', 'Bàn giao và nhận thiết bị giữa các bước'),
('clerk', 'Văn thư', 'Tiếp nhận, chuyển đổi và thu hồi thiết bị'),
('technician', 'Đơn vị sửa chữa', 'Thực hiện sửa chữa và cập nhật tiến độ'),
('admin', 'Quản trị viên', 'Quản lý toàn bộ hệ thống và cấu hình');

-- Chèn dữ liệu trạng thái (đã cập nhật với 2 trạng thái mới cho giao liên)
INSERT INTO repair_statuses (code, name, step_order, color, icon, description) VALUES
('PENDING_HANDOVER', 'Chờ bàn giao', 1, '#ffc107', 'fas fa-clock', 'Đơn vừa được tạo, chờ giao liên nhận thiết bị'),
('LOGISTICS_RECEIVED', 'Giao liên đã nhận đề xuất', 2, '#20c997', 'fas fa-inbox', 'Giao liên đã xác nhận nhận được đề xuất từ người đề xuất'),
('LOGISTICS_HANDOVER', 'Giao liên đã bàn giao cho văn thư', 3, '#17a2b8', 'fas fa-hand-holding', 'Giao liên đã kiểm tra và bàn giao thiết bị cho văn thư'),
('HANDED_TO_CLERK', 'Đã đến văn thư – chờ xử lý', 4, '#17a2b8', 'fas fa-hand-holding', 'Văn thư đã nhận thiết bị từ giao liên'),
('SENT_TO_REPAIR', 'Đã chuyển đơn vị sửa chữa', 5, '#fd7e14', 'fas fa-shipping-fast', 'Văn thư đã chuyển thiết bị cho đơn vị sửa chữa'),
('IN_PROGRESS', 'Đang sửa chữa', 6, '#007bff', 'fas fa-tools', 'Đơn vị sửa chữa đang thực hiện'),
('REPAIR_COMPLETED', 'Đã sửa xong – chờ thu hồi', 7, '#28a745', 'fas fa-check-circle', 'Đơn vị sửa chữa đã hoàn thành'),
('RETRIEVED', 'Đã thu hồi – chờ trả lại', 8, '#6f42c1', 'fas fa-undo', 'Văn thư đã thu hồi thiết bị'),
('COMPLETED', 'Hoàn tất', 9, '#20c997', 'fas fa-flag-checkered', 'Đã trả lại thiết bị cho người đề xuất'),
('CANCELLED', 'Đã hủy', 10, '#dc3545', 'fas fa-times-circle', 'Đơn bị hủy bỏ');

-- Chèn dữ liệu đơn vị mẫu
INSERT INTO departments (code, name, address, phone, email, manager_name) VALUES
('IT', 'Phòng Công nghệ thông tin', '123 Đường ABC, Quận 1, TP.HCM', '028-1234567', 'it@company.com', 'Nguyễn Văn A'),
('HR', 'Phòng Nhân sự', '456 Đường DEF, Quận 2, TP.HCM', '028-7654321', 'hr@company.com', 'Trần Thị B'),
('ACC', 'Phòng Kế toán', '789 Đường GHI, Quận 3, TP.HCM', '028-9876543', 'acc@company.com', 'Lê Văn C'),
('TECH', 'Phòng Kỹ thuật', '321 Đường JKL, Quận 4, TP.HCM', '028-5432167', 'tech@company.com', 'Phạm Thị D');

-- Chèn dữ liệu loại thiết bị
INSERT INTO equipment_types (name, description, icon) VALUES
('Máy tính', 'Máy tính để bàn và laptop', 'fas fa-desktop'),
('Máy in', 'Máy in laser, phun, đa chức năng', 'fas fa-print'),
('Điều hòa', 'Máy lạnh, điều hòa không khí', 'fas fa-snowflake'),
('Thiết bị mạng', 'Router, Switch, Access Point', 'fas fa-network-wired'),
('Thiết bị văn phòng', 'Máy photocopy, máy scan, máy fax', 'fas fa-copy'),
('Thiết bị âm thanh', 'Loa, micro, amply', 'fas fa-volume-up');

-- Chèn dữ liệu thiết bị mẫu
INSERT INTO equipments (code, name, model, brand, type_id, department_id, location, purchase_date, warranty_date, purchase_price, status, description) VALUES
-- Máy tính
('PC-IT-001', 'Máy tính để bàn IT-001', 'OptiPlex 7090', 'Dell', 1, 1, 'Phòng IT - Bàn số 1', '2023-01-15', '2026-01-15', 15000000, 'active', 'Máy tính làm việc chính của nhân viên IT'),
('PC-IT-002', 'Laptop Dell IT-002', 'Latitude 5520', 'Dell', 1, 1, 'Phòng IT - Bàn số 2', '2023-02-20', '2026-02-20', 20000000, 'active', 'Laptop di động cho công việc'),
('PC-HR-001', 'Máy tính HR-001', 'ThinkCentre M75s', 'Lenovo', 1, 2, 'Phòng Nhân sự - Bàn trưởng phòng', '2022-12-10', '2025-12-10', 12000000, 'active', 'Máy tính trưởng phòng nhân sự'),
('PC-ACC-001', 'Máy tính ACC-001', 'OptiPlex 3080', 'Dell', 1, 3, 'Phòng Kế toán - Bàn số 1', '2023-03-05', '2026-03-05', 13000000, 'active', 'Máy tính kế toán viên'),
-- Máy in
('PR-IT-001', 'Máy in laser IT-001', 'LaserJet Pro M404dn', 'HP', 2, 1, 'Phòng IT - Góc in ấn', '2023-01-20', '2024-01-20', 5000000, 'active', 'Máy in laser đen trắng'),
('PR-HR-001', 'Máy in màu HR-001', 'Color LaserJet Pro M454dn', 'HP', 2, 2, 'Phòng Nhân sự - Kệ tài liệu', '2023-02-15', '2024-02-15', 8000000, 'active', 'Máy in màu cho văn bản HR'),
('PR-ALL-001', 'Máy photocopy chung', 'IR-ADV C3330i', 'Canon', 5, 1, 'Khu vực chung - Tầng 1', '2022-11-30', '2024-11-30', 25000000, 'active', 'Máy photocopy đa chức năng cho toàn công ty'),
-- Điều hòa
('AC-IT-001', 'Điều hòa Phòng IT', 'Inverter 18000 BTU', 'Daikin', 3, 1, 'Phòng IT - Trần nhà', '2022-08-15', '2025-08-15', 12000000, 'active', 'Điều hòa 2 chiều inverter'),
('AC-HR-001', 'Điều hòa Phòng HR', 'Inverter 12000 BTU', 'Panasonic', 3, 2, 'Phòng Nhân sự - Trần nhà', '2022-09-10', '2025-09-10', 8000000, 'active', 'Điều hòa 1 chiều'),
-- Thiết bị mạng
('NW-001', 'Router chính', 'Archer AX6000', 'TP-Link', 4, 1, 'Phòng Server - Tủ rack', '2023-01-10', '2026-01-10', 3000000, 'active', 'Router WiFi 6 băng tần kép'),
('NW-002', 'Switch mạng chính', '24-Port Gigabit', 'Cisco', 4, 1, 'Phòng Server - Tủ rack', '2023-01-10', '2026-01-10', 5000000, 'active', 'Switch 24 port quản lý'),
-- Thiết bị âm thanh
('AU-001', 'Hệ thống âm thanh hội trường', 'Professional PA System', 'JBL', 6, 1, 'Hội trường - Sân khấu', '2022-10-20', '2025-10-20', 15000000, 'active', 'Hệ thống âm thanh hội trường 100 chỗ ngồi');

-- Tạo user admin mặc định (password: admin123)
INSERT INTO users (username, password, full_name, email, role_id, department_id, status) VALUES
('admin', 'admin123', 'Quản trị viên', 'admin@company.com', 5, 1, 'active');

-- Tạo các user demo
INSERT INTO users (username, password, full_name, email, role_id, department_id, status) VALUES
('user1', 'user123', 'Nguyễn Văn User', 'user1@company.com', 1, 2, 'active'),
('logistics1', 'user123', 'Trần Thị Giao Liên', 'logistics1@company.com', 2, 1, 'active'),
('clerk1', 'user123', 'Lê Văn Văn Thư', 'clerk1@company.com', 3, 1, 'active'),
('tech1', 'user123', 'Phạm Thị Kỹ Thuật', 'tech1@company.com', 4, 4, 'active');

-- Tạo indexes để tối ưu performance
CREATE INDEX idx_repair_requests_status ON repair_requests(current_status_id);
CREATE INDEX idx_repair_requests_requester ON repair_requests(requester_id);
CREATE INDEX idx_repair_requests_equipment ON repair_requests(equipment_id);
CREATE INDEX idx_repair_requests_logistics_received ON repair_requests(logistics_received_at);
CREATE INDEX idx_repair_requests_logistics_handover ON repair_requests(logistics_handover_at);
CREATE INDEX idx_repair_requests_created ON repair_requests(created_at);
CREATE INDEX idx_repair_status_history_request ON repair_status_history(request_id);
CREATE INDEX idx_equipments_department ON equipments(department_id);
CREATE INDEX idx_equipments_type ON equipments(type_id);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_department ON users(department_id);

-- ===================================
-- TABLES MỚI THÊM
-- ===================================

-- Bảng activity_logs để log hoạt động của user
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng repair_contents để quản lý nội dung sửa chữa
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

-- Bảng system_settings để quản lý cài đặt hệ thống
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

-- ===================================
-- DỮ LIỆU MẪU CHO TABLES MỚI
-- ===================================

-- Thêm dữ liệu mẫu cho repair_contents
INSERT INTO `repair_contents` (`name`, `description`, `category`, `estimated_cost`, `created_by`) VALUES
('Thay thế màn hình LCD', 'Thay thế màn hình LCD bị vỡ hoặc không hiển thị', 'hardware', 500000, 1),
('Sửa chữa bàn phím', 'Sửa chữa hoặc thay thế bàn phím không hoạt động', 'hardware', 200000, 1),
('Cài đặt lại hệ điều hành', 'Format và cài đặt lại Windows/Linux', 'software', 100000, 1),
('Diệt virus', 'Quét và diệt virus, malware', 'software', 50000, 1),
('Sửa chữa card mạng', 'Sửa chữa kết nối mạng không ổn định', 'network', 150000, 1),
('Thay thế ổ cứng', 'Thay thế ổ cứng bị hỏng', 'hardware', 800000, 1),
('Vệ sinh máy tính', 'Vệ sinh bụi bẩn, thay keo tản nhiệt', 'maintenance', 80000, 1),
('Nâng cấp RAM', 'Thêm hoặc thay thế RAM', 'hardware', 300000, 1),
('Cài đặt phần mềm', 'Cài đặt các phần mềm cần thiết', 'software', 0, 1),
('Backup dữ liệu', 'Sao lưu dữ liệu quan trọng', 'maintenance', 0, 1);

-- Thêm settings mặc định cho system_settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `category`) VALUES
('app_name', 'Hệ thống quản lý sửa chữa thiết bị', 'string', 'Tên ứng dụng', 'general'),
('app_version', '1.0.0', 'string', 'Phiên bản ứng dụng', 'general'),
('max_file_size', '10485760', 'number', 'Kích thước file tối đa (bytes)', 'upload'),
('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx', 'string', 'Các loại file được phép upload', 'upload'),
('email_notifications', '0', 'boolean', 'Bật thông báo email (đã tắt)', 'notifications'),
('maintenance_mode', '0', 'boolean', 'Chế độ bảo trì', 'general'),
('session_timeout', '3600', 'number', 'Thời gian timeout session (giây)', 'security'),
('password_min_length', '6', 'number', 'Độ dài mật khẩu tối thiểu', 'security'),
('default_equipment_status', 'active', 'string', 'Trạng thái thiết bị mặc định', 'equipment'),
('auto_assign_requests', '1', 'boolean', 'Tự động phân công yêu cầu', 'workflow');

-- ===================================
-- MULTI TECH DEPARTMENTS SUPPORT
-- Hỗ trợ nhiều phòng kỹ thuật tham gia sửa chữa tuần tự
-- ===================================

-- 1. Tạo bảng repair_workflow_steps để quản lý quy trình sửa chữa nhiều bước
CREATE TABLE IF NOT EXISTS `repair_workflow_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `assigned_department_id` int(11) NOT NULL,
  `assigned_technician_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','skipped') NOT NULL DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_step_order` (`step_order`),
  KEY `idx_assigned_department` (`assigned_department_id`),
  KEY `idx_assigned_technician` (`assigned_technician_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`request_id`) REFERENCES `repair_requests` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`assigned_technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Thêm cột current_workflow_step_id vào bảng repair_requests
ALTER TABLE `repair_requests` ADD COLUMN `current_workflow_step_id` int(11) DEFAULT NULL;
ALTER TABLE `repair_requests` ADD KEY `idx_current_workflow_step` (`current_workflow_step_id`);
ALTER TABLE `repair_requests` ADD CONSTRAINT `fk_current_workflow_step` 
  FOREIGN KEY (`current_workflow_step_id`) REFERENCES `repair_workflow_steps` (`id`) ON DELETE SET NULL;

-- 3. Sửa đổi bảng repair_details để liên kết với workflow step
ALTER TABLE `repair_details` ADD COLUMN `workflow_step_id` int(11) DEFAULT NULL;
ALTER TABLE `repair_details` ADD KEY `idx_workflow_step` (`workflow_step_id`);
ALTER TABLE `repair_details` ADD CONSTRAINT `fk_repair_details_workflow_step` 
  FOREIGN KEY (`workflow_step_id`) REFERENCES `repair_workflow_steps` (`id`) ON DELETE SET NULL;

-- 4. Thêm các phòng kỹ thuật mẫu
INSERT INTO `departments` (`code`, `name`, `address`, `phone`, `email`, `manager_name`, `status`) VALUES
('TECH_A', 'Phòng Kỹ thuật A - Phần cứng', '101 Đường Kỹ thuật, Quận 1, TP.HCM', '028-1111111', 'tech_a@company.com', 'Nguyễn Văn Kỹ Thuật A', 'active'),
('TECH_B', 'Phòng Kỹ thuật B - Phần mềm', '102 Đường Kỹ thuật, Quận 1, TP.HCM', '028-2222222', 'tech_b@company.com', 'Trần Thị Kỹ Thuật B', 'active'),
('TECH_C', 'Phòng Kỹ thuật C - Mạng', '103 Đường Kỹ thuật, Quận 1, TP.HCM', '028-3333333', 'tech_c@company.com', 'Lê Văn Kỹ Thuật C', 'active'),
('TECH_D', 'Phòng Kỹ thuật D - Bảo trì', '104 Đường Kỹ thuật, Quận 1, TP.HCM', '028-4444444', 'tech_d@company.com', 'Phạm Thị Kỹ Thuật D', 'active');

-- 5. Thêm các technician user mẫu cho mỗi phòng kỹ thuật
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role_id`, `department_id`, `status`) VALUES
('tech_a1', 'tech123', 'Nguyễn Văn Kỹ Thuật A1', 'tech_a1@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_A'), 'active'),
('tech_a2', 'tech123', 'Trần Thị Kỹ Thuật A2', 'tech_a2@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_A'), 'active'),
('tech_b1', 'tech123', 'Lê Văn Kỹ Thuật B1', 'tech_b1@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_B'), 'active'),
('tech_b2', 'tech123', 'Phạm Thị Kỹ Thuật B2', 'tech_b2@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_B'), 'active'),
('tech_c1', 'tech123', 'Hoàng Văn Kỹ Thuật C1', 'tech_c1@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_C'), 'active'),
('tech_c2', 'tech123', 'Ngô Thị Kỹ Thuật C2', 'tech_c2@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_C'), 'active'),
('tech_d1', 'tech123', 'Vũ Văn Kỹ Thuật D1', 'tech_d1@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_D'), 'active'),
('tech_d2', 'tech123', 'Đỗ Thị Kỹ Thuật D2', 'tech_d2@company.com', 4, (SELECT id FROM departments WHERE code = 'TECH_D'), 'active');

-- 6. Tạo view để dễ dàng truy vấn workflow steps
CREATE OR REPLACE VIEW `repair_workflow_view` AS
SELECT 
    rws.id,
    rws.request_id,
    rws.step_order,
    rws.status as step_status,
    rws.started_at,
    rws.completed_at,
    rws.notes as step_notes,
    d.name as department_name,
    d.code as department_code,
    u.full_name as technician_name,
    u.username as technician_username,
    rr.request_code,
    rr.problem_description,
    rs.name as request_status_name,
    rs.code as request_status_code
FROM repair_workflow_steps rws
LEFT JOIN departments d ON rws.assigned_department_id = d.id
LEFT JOIN users u ON rws.assigned_technician_id = u.id
LEFT JOIN repair_requests rr ON rws.request_id = rr.id
LEFT JOIN repair_statuses rs ON rr.current_status_id = rs.id
ORDER BY rws.request_id, rws.step_order;

-- 7. Tạo stored procedure để tạo workflow steps cho một request
DELIMITER $$
CREATE PROCEDURE `CreateWorkflowSteps`(
    IN p_request_id INT,
    IN p_department_ids JSON,
    IN p_created_by INT
)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE dept_count INT;
    DECLARE dept_id INT;
    
    SET dept_count = JSON_LENGTH(p_department_ids);
    
    -- Xóa các workflow steps cũ nếu có
    DELETE FROM repair_workflow_steps WHERE request_id = p_request_id;
    
    -- Tạo workflow steps mới
    WHILE i < dept_count DO
        SET dept_id = JSON_UNQUOTE(JSON_EXTRACT(p_department_ids, CONCAT('$[', i, ']')));
        
        INSERT INTO repair_workflow_steps (
            request_id, 
            step_order, 
            assigned_department_id, 
            status,
            created_by
        ) VALUES (
            p_request_id, 
            i + 1, 
            dept_id, 
            CASE WHEN i = 0 THEN 'pending' ELSE 'pending' END,
            p_created_by
        );
        
        SET i = i + 1;
    END WHILE;
    
    -- Cập nhật current_workflow_step_id cho request (step đầu tiên)
    UPDATE repair_requests 
    SET current_workflow_step_id = (
        SELECT id FROM repair_workflow_steps 
        WHERE request_id = p_request_id AND step_order = 1
    )
    WHERE id = p_request_id;
    
END$$
DELIMITER ;

-- 8. Tạo stored procedure để chuyển sang bước tiếp theo
DELIMITER $$
CREATE PROCEDURE `MoveToNextWorkflowStep`(
    IN p_request_id INT,
    IN p_current_step_id INT,
    IN p_technician_id INT,
    IN p_completion_notes TEXT
)
BEGIN
    DECLARE next_step_id INT DEFAULT NULL;
    DECLARE current_step_order INT;
    
    -- Lấy step_order hiện tại
    SELECT step_order INTO current_step_order 
    FROM repair_workflow_steps 
    WHERE id = p_current_step_id;
    
    -- Đánh dấu step hiện tại là completed
    UPDATE repair_workflow_steps 
    SET 
        status = 'completed',
        completed_at = NOW(),
        notes = COALESCE(CONCAT(COALESCE(notes, ''), '\n', p_completion_notes), p_completion_notes)
    WHERE id = p_current_step_id;
    
    -- Tìm step tiếp theo
    SELECT id INTO next_step_id
    FROM repair_workflow_steps 
    WHERE request_id = p_request_id 
    AND step_order = current_step_order + 1;
    
    IF next_step_id IS NOT NULL THEN
        -- Có step tiếp theo: chuyển sang step đó
        UPDATE repair_workflow_steps 
        SET 
            status = 'in_progress',
            started_at = NOW()
        WHERE id = next_step_id;
        
        -- Cập nhật current_workflow_step_id
        UPDATE repair_requests 
        SET current_workflow_step_id = next_step_id
        WHERE id = p_request_id;
        
    ELSE
        -- Không có step tiếp theo: hoàn thành toàn bộ
        UPDATE repair_requests 
        SET 
            current_status_id = (SELECT id FROM repair_statuses WHERE code = 'REPAIR_COMPLETED'),
            current_workflow_step_id = NULL,
            actual_completion = NOW()
        WHERE id = p_request_id;
        
        -- Thêm vào lịch sử trạng thái
        INSERT INTO repair_status_history (
            request_id, 
            status_id, 
            user_id, 
            notes, 
            created_at
        ) VALUES (
            p_request_id,
            (SELECT id FROM repair_statuses WHERE code = 'REPAIR_COMPLETED'),
            p_technician_id,
            CONCAT('Hoàn thành sửa chữa toàn bộ. ', COALESCE(p_completion_notes, '')),
            NOW()
        );
    END IF;
    
END$$
DELIMITER ;

-- 9. Tạo function để lấy danh sách technician theo department
DELIMITER $$
CREATE FUNCTION `GetTechniciansByDepartment`(p_department_id INT) 
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result JSON;
    
    SELECT JSON_ARRAYAGG(
        JSON_OBJECT(
            'id', u.id,
            'username', u.username,
            'full_name', u.full_name,
            'email', u.email
        )
    ) INTO result
    FROM users u
    WHERE u.department_id = p_department_id 
    AND u.role_id = (SELECT id FROM roles WHERE name = 'technician')
    AND u.status = 'active';
    
    RETURN COALESCE(result, JSON_ARRAY());
END$$
DELIMITER ;

-- 10. Tạo bảng workflow_templates để lưu template quy trình sửa chữa
CREATE TABLE IF NOT EXISTS `workflow_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `equipment_type_id` int(11) DEFAULT NULL,
  `departments_sequence` json NOT NULL COMMENT 'Thứ tự các department tham gia',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_type` (`equipment_type_id`),
  KEY `idx_is_default` (`is_default`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`equipment_type_id`) REFERENCES `equipment_types` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Thêm workflow templates mẫu
INSERT INTO `workflow_templates` (`name`, `description`, `equipment_type_id`, `departments_sequence`, `is_default`, `created_by`) VALUES
('Quy trình sửa máy tính', 'Quy trình sửa chữa máy tính: Phần cứng -> Phần mềm -> Kiểm tra mạng', 1, 
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_B'),
   (SELECT id FROM departments WHERE code = 'TECH_C')
 ), 1, 1),
('Quy trình sửa máy in', 'Quy trình sửa chữa máy in: Phần cứng -> Bảo trì', 2,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_A'),
   (SELECT id FROM departments WHERE code = 'TECH_D')
 ), 1, 1),
('Quy trình sửa điều hòa', 'Quy trình sửa chữa điều hòa: Bảo trì chuyên sâu', 3,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_D')
 ), 1, 1),
('Quy trình sửa thiết bị mạng', 'Quy trình sửa chữa thiết bị mạng: Mạng -> Phần mềm', 4,
 JSON_ARRAY(
   (SELECT id FROM departments WHERE code = 'TECH_C'),
   (SELECT id FROM departments WHERE code = 'TECH_B')
 ), 1, 1);

-- 12. Index để tối ưu performance
CREATE INDEX idx_repair_workflow_steps_request_order ON repair_workflow_steps(request_id, step_order);
CREATE INDEX idx_repair_workflow_steps_department_status ON repair_workflow_steps(assigned_department_id, status);

-- 13. Tạo bảng workflow_templates
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
 ), 1, 1),
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

-- ===================================
-- DỮ LIỆU MẪU
-- ===================================

-- Tạo một số repair requests mẫu với workflow steps
INSERT INTO repair_requests (
    request_code, equipment_id, requester_id, problem_description, 
    urgency_level, current_status_id
) VALUES 
('REQ-MULTI-001', 1, 2, 'Máy tính không khởi động được, có thể do cả phần cứng và phần mềm', 'high', 
 (SELECT id FROM repair_statuses WHERE code = 'SENT_TO_REPAIR')),
('REQ-MULTI-002', 5, 2, 'Máy in bị kẹt giấy và in chữ mờ', 'medium', 
 (SELECT id FROM repair_statuses WHERE code = 'SENT_TO_REPAIR'));

-- Tạo workflow steps cho các requests mẫu
-- Request 1: Máy tính (TECH_A -> TECH_B -> TECH_C)
CALL CreateWorkflowSteps(
    (SELECT id FROM repair_requests WHERE request_code = 'REQ-MULTI-001'),
    JSON_ARRAY(
        (SELECT id FROM departments WHERE code = 'TECH_A'),
        (SELECT id FROM departments WHERE code = 'TECH_B'),
        (SELECT id FROM departments WHERE code = 'TECH_C')
    ),
    1
);

-- Request 2: Máy in (TECH_A -> TECH_D)
CALL CreateWorkflowSteps(
    (SELECT id FROM repair_requests WHERE request_code = 'REQ-MULTI-002'),
    JSON_ARRAY(
        (SELECT id FROM departments WHERE code = 'TECH_A'),
        (SELECT id FROM departments WHERE code = 'TECH_D')
    ),
    1
);

-- Bắt đầu step đầu tiên cho request 1
UPDATE repair_workflow_steps 
SET 
    status = 'in_progress',
    started_at = NOW(),
    assigned_technician_id = (SELECT id FROM users WHERE username = 'tech_a1')
WHERE request_id = (SELECT id FROM repair_requests WHERE request_code = 'REQ-MULTI-001')
AND step_order = 1;

-- Bắt đầu step đầu tiên cho request 2  
UPDATE repair_workflow_steps 
SET 
    status = 'in_progress',
    started_at = NOW(),
    assigned_technician_id = (SELECT id FROM users WHERE username = 'tech_a2')
WHERE request_id = (SELECT id FROM repair_requests WHERE request_code = 'REQ-MULTI-002')
AND step_order = 1;
