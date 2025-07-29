-- =============================================
-- THÊM CÁC TRẠNG THÁI MỚI CHO GIAO LIÊN
-- =============================================

-- Đầu tiên, cập nhật step_order của các trạng thái hiện tại để tạo chỗ cho trạng thái mới
UPDATE repair_statuses SET step_order = step_order + 2 WHERE step_order >= 2;

-- Thêm 2 trạng thái mới cho giao liên
INSERT INTO repair_statuses (code, name, step_order, color, icon, description) VALUES
('LOGISTICS_RECEIVED', 'Giao liên đã nhận đề xuất', 2, '#20c997', 'fas fa-inbox', 'Giao liên đã xác nhận nhận được đề xuất từ người đề xuất'),
('LOGISTICS_HANDOVER', 'Giao liên đã bàn giao cho văn thư', 3, '#17a2b8', 'fas fa-hand-holding', 'Giao liên đã kiểm tra và bàn giao thiết bị cho văn thư');

-- Cập nhật lại các trạng thái hiện tại với step_order mới
UPDATE repair_statuses SET 
    name = 'Đã đến văn thư – chờ xử lý',
    step_order = 4
WHERE code = 'HANDED_TO_CLERK';

UPDATE repair_statuses SET step_order = 5 WHERE code = 'SENT_TO_REPAIR';
UPDATE repair_statuses SET step_order = 6 WHERE code = 'IN_PROGRESS';
UPDATE repair_statuses SET step_order = 7 WHERE code = 'REPAIR_COMPLETED';
UPDATE repair_statuses SET step_order = 8 WHERE code = 'RETRIEVED';
UPDATE repair_statuses SET step_order = 9 WHERE code = 'COMPLETED';
UPDATE repair_statuses SET step_order = 10 WHERE code = 'CANCELLED';

-- Tạo cột mới để theo dõi thời gian nhận và bàn giao của giao liên
ALTER TABLE repair_requests 
ADD COLUMN logistics_received_at TIMESTAMP NULL COMMENT 'Thời gian giao liên nhận đề xuất',
ADD COLUMN logistics_handover_at TIMESTAMP NULL COMMENT 'Thời gian giao liên bàn giao cho văn thư';

-- Thêm index cho hiệu suất
CREATE INDEX idx_repair_requests_logistics_received ON repair_requests(logistics_received_at);
CREATE INDEX idx_repair_requests_logistics_handover ON repair_requests(logistics_handover_at);

-- Cập nhật quy trình chuyển trạng thái trong stored procedure nếu cần
-- (Sẽ được cập nhật trong file riêng hoặc trong application logic)

-- Thêm dữ liệu mẫu cho các trạng thái mới (nếu có request hiện tại)
-- UPDATE existing requests to use new flow if needed
-- Lưu ý: Cần cẩn thận khi cập nhật dữ liệu hiện tại

COMMIT;
