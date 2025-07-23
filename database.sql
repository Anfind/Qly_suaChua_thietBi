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

-- Chèn dữ liệu trạng thái
INSERT INTO repair_statuses (code, name, step_order, color, icon, description) VALUES
('PENDING_HANDOVER', 'Chờ bàn giao', 1, '#ffc107', 'fas fa-clock', 'Đơn vừa được tạo, chờ giao liên nhận thiết bị'),
('HANDED_TO_CLERK', 'Đã bàn giao cho văn thư', 2, '#17a2b8', 'fas fa-hand-holding', 'Giao liên đã nhận và chuyển cho văn thư'),
('SENT_TO_REPAIR', 'Đã chuyển đơn vị sửa chữa', 3, '#fd7e14', 'fas fa-shipping-fast', 'Văn thư đã chuyển thiết bị cho đơn vị sửa chữa'),
('IN_PROGRESS', 'Đang sửa chữa', 4, '#007bff', 'fas fa-tools', 'Đơn vị sửa chữa đang thực hiện'),
('REPAIR_COMPLETED', 'Đã sửa xong – chờ thu hồi', 5, '#28a745', 'fas fa-check-circle', 'Đơn vị sửa chữa đã hoàn thành'),
('RETRIEVED', 'Đã thu hồi – chờ trả lại', 6, '#6f42c1', 'fas fa-undo', 'Văn thư đã thu hồi thiết bị'),
('COMPLETED', 'Hoàn tất', 7, '#20c997', 'fas fa-flag-checkered', 'Đã trả lại thiết bị cho người đề xuất'),
('CANCELLED', 'Đã hủy', 8, '#dc3545', 'fas fa-times-circle', 'Đơn bị hủy bỏ');

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
