-- ===================================
-- HỆ THỐNG THÔNG BÁO HOÀN CHỈNH
-- Thông báo theo flow workflow cho từng vai trò
-- ===================================

-- 1. Tạo bảng notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'ID người nhận thông báo',
  `title` varchar(255) NOT NULL COMMENT 'Tiêu đề thông báo',
  `message` text NOT NULL COMMENT 'Nội dung thông báo',
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info' COMMENT 'Loại thông báo',
  `related_type` enum('repair_request','equipment','user','system') NOT NULL DEFAULT 'repair_request' COMMENT 'Liên quan đến',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID của đối tượng liên quan',
  `action_url` varchar(500) DEFAULT NULL COMMENT 'URL hành động',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Đã đọc chưa',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_related` (`related_type`, `related_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Trigger tự động tạo thông báo khi có đơn mới
DELIMITER $$
CREATE TRIGGER `auto_notify_new_request` 
AFTER INSERT ON `repair_requests` 
FOR EACH ROW 
BEGIN
    -- Thông báo cho tất cả logistics về đơn mới
    INSERT INTO notifications (user_id, title, message, type, related_type, related_id, action_url)
    SELECT 
        u.id,
        'Đơn sửa chữa mới',
        CONCAT('Đơn #', NEW.request_code, ' - ', LEFT(NEW.problem_description, 50), '...'),
        'info',
        'repair_request',
        NEW.id,
        CONCAT('logistics/handover.php?id=', NEW.id)
    FROM users u 
    INNER JOIN roles r ON u.role_id = r.id 
    WHERE r.name = 'logistics' AND u.status = 'active';
END$$

-- 3. Trigger thông báo khi thay đổi trạng thái
CREATE TRIGGER `auto_notify_status_change` 
AFTER UPDATE ON `repair_requests` 
FOR EACH ROW 
BEGIN
    DECLARE current_status_code VARCHAR(50);
    DECLARE notification_title VARCHAR(255);
    DECLARE notification_message TEXT;
    DECLARE target_role VARCHAR(50);
    
    -- Lấy mã trạng thái hiện tại
    SELECT code INTO current_status_code 
    FROM repair_statuses 
    WHERE id = NEW.current_status_id;
    
    -- Xác định ai cần nhận thông báo dựa trên trạng thái
    CASE current_status_code
        WHEN 'LOGISTICS_RECEIVED' THEN
            SET notification_title = 'Đề xuất đã được nhận';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đã được giao liên xác nhận nhận');
            SET target_role = 'requester';
            
        WHEN 'LOGISTICS_HANDOVER' THEN 
            SET notification_title = 'Đề xuất đã được bàn giao';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đã được bàn giao cho văn thư');
            SET target_role = 'clerk';
            
        WHEN 'HANDED_TO_CLERK' THEN
            SET notification_title = 'Đơn đến văn thư';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đã đến văn thư - chờ xử lý');
            SET target_role = 'clerk';
            
        WHEN 'SENT_TO_REPAIR' THEN
            SET notification_title = 'Đơn đã chuyển sửa chữa';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đã được chuyển đến kỹ thuật');
            SET target_role = 'technician';
            
        WHEN 'IN_PROGRESS' THEN
            SET notification_title = 'Đang sửa chữa';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đang được sửa chữa');
            SET target_role = 'requester';
            
        WHEN 'REPAIR_COMPLETED' THEN
            SET notification_title = 'Sửa chữa hoàn thành';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đã sửa chữa xong');
            SET target_role = 'clerk';
            
        WHEN 'RETRIEVED' THEN
            SET notification_title = 'Thiết bị đã thu hồi';
            SET notification_message = CONCAT('Thiết bị đơn #', NEW.request_code, ' đã được thu hồi');
            SET target_role = 'logistics';
            
        WHEN 'COMPLETED' THEN
            SET notification_title = 'Đơn hoàn thành';
            SET notification_message = CONCAT('Đơn #', NEW.request_code, ' đã hoàn thành toàn bộ quy trình');
            SET target_role = 'requester';
            
        ELSE 
            SET target_role = NULL;
    END CASE;
    
    -- Tạo thông báo cho role tương ứng
    IF target_role IS NOT NULL THEN
        -- Thông báo cho requester
        IF target_role = 'requester' THEN
            INSERT INTO notifications (user_id, title, message, type, related_type, related_id, action_url)
            VALUES (
                NEW.requester_id,
                notification_title,
                notification_message,
                'success',
                'repair_request',
                NEW.id,
                CONCAT('repairs/view.php?code=', NEW.request_code)
            );
            
        -- Thông báo cho các role khác
        ELSE
            INSERT INTO notifications (user_id, title, message, type, related_type, related_id, action_url)
            SELECT 
                u.id,
                notification_title,
                notification_message,
                'info',
                'repair_request',
                NEW.id,
                CASE target_role
                    WHEN 'logistics' THEN CONCAT('logistics/return.php?id=', NEW.id)
                    WHEN 'clerk' THEN CONCAT('clerk/send.php?id=', NEW.id)
                    WHEN 'technician' THEN CONCAT('technician/index.php')
                    ELSE CONCAT('repairs/view.php?code=', NEW.request_code)
                END
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE r.name = target_role AND u.status = 'active';
        END IF;
    END IF;
END$$

DELIMITER ;

-- 4. Indexes để tối ưu hiệu suất
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_notifications_related ON notifications(related_type, related_id);

-- 5. Thêm dữ liệu thông báo mẫu cho test
INSERT INTO notifications (user_id, title, message, type, related_type, related_id, action_url)
SELECT 
    2, -- user ID
    'Chào mừng đến hệ thống',
    'Hệ thống quản lý sửa chữa thiết bị đã sẵn sàng sử dụng!',
    'success',
    'system',
    NULL,
    'dashboard.php'
WHERE EXISTS (SELECT 1 FROM users WHERE id = 2);

COMMIT;
