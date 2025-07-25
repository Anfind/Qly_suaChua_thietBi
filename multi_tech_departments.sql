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
