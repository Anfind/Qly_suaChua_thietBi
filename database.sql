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

-- Bảng nội dung sửa chữa template
CREATE TABLE repair_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    solution TEXT,
    estimated_cost DECIMAL(15,2) DEFAULT 0,
    estimated_time INT DEFAULT 0, -- số giờ
    equipment_type_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_type_id) REFERENCES equipment_types(id) ON DELETE SET NULL
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
    FOREIGN KEY (content_id) REFERENCES repair_contents(id) ON DELETE SET NULL,
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

-- Tạo user admin mặc định (password: admin123)
INSERT INTO users (username, password, full_name, email, role_id, department_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên', 'admin@company.com', 5, 1);

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
