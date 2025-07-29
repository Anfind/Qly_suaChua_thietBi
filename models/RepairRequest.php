<?php
require_once __DIR__ . '/User.php';

/**
 * Model RepairRequest - Quản lý đơn sửa chữa
 */
class RepairRequest {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Tạo đơn sửa chữa mới
     */
    public function create($data) {
        $this->db->beginTransaction();
        
        try {
            // Tạo mã đơn
            $request_code = $this->generateRequestCode();
            
            // Lấy trạng thái ban đầu
            $initial_status = $this->getStatusByCode('PENDING_HANDOVER');
            
            // Xử lý upload files
            $images = [];
            $videos = [];
            
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $image) {
                    if ($image['error'] === UPLOAD_ERR_OK) {
                        $filename = upload_file($image, UPLOAD_REQUEST_PATH, ALLOWED_IMAGE_TYPES);
                        $images[] = $filename;
                    }
                }
            }
            
            if (isset($data['videos']) && is_array($data['videos'])) {
                foreach ($data['videos'] as $video) {
                    if ($video['error'] === UPLOAD_ERR_OK) {
                        $filename = upload_file($video, UPLOAD_REQUEST_PATH, ALLOWED_VIDEO_TYPES);
                        $videos[] = $filename;
                    }
                }
            }
            
            // Tạo đơn sửa chữa
            $requestData = [
                'request_code' => $request_code,
                'equipment_id' => $data['equipment_id'],
                'requester_id' => $data['requester_id'],
                'problem_description' => $data['problem_description'],
                'urgency_level' => $data['urgency_level'] ?? 'medium',
                'images' => json_encode($images),
                'videos' => json_encode($videos),
                'current_status_id' => $initial_status['id']
            ];
            
            $request_id = $this->db->insert('repair_requests', $requestData);
            
            // Tạo lịch sử trạng thái
            $this->addStatusHistory($request_id, $initial_status['id'], $data['requester_id'], 'Tạo đơn sửa chữa');
            
            $this->db->commit();
            return $request_code;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Cập nhật trạng thái đơn
     */
    public function updateStatus($request_id, $new_status_code, $user_id, $notes = '', $attachments = []) {
        $this->db->beginTransaction();
        
        try {
            $request = $this->getById($request_id);
            if (!$request) {
                throw new Exception('Không tìm thấy đơn sửa chữa');
            }
            
            $new_status = $this->getStatusByCode($new_status_code);
            if (!$new_status) {
                throw new Exception('Trạng thái không hợp lệ');
            }
            
            // Kiểm tra quyền chuyển trạng thái
            $this->validateStatusTransition($request['current_status_code'], $new_status_code, $user_id);
            
            // Cập nhật trạng thái đơn
            $updateData = [
                'current_status_id' => $new_status['id'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Cập nhật thông tin assignment
            $user = (new User())->getById($user_id);
            switch ($new_status_code) {
                case 'HANDED_TO_CLERK':
                    $updateData['assigned_logistics_id'] = $user_id;
                    break;
                case 'SENT_TO_REPAIR':
                    $updateData['assigned_clerk_id'] = $user_id;
                    break;
                case 'IN_PROGRESS':
                    $updateData['assigned_technician_id'] = $user_id;
                    break;
                case 'COMPLETED':
                    $updateData['actual_completion'] = date('Y-m-d H:i:s');
                    break;
            }
            
            $this->db->update('repair_requests', $updateData, 'id = ?', [$request_id]);
            
            // Thêm lịch sử
            $this->addStatusHistory($request_id, $new_status['id'], $user_id, $notes, $attachments);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Lấy đơn theo requester
     */
    public function getByRequester($requester_id, $filters = []) {
        $where = ["r.requester_id = ?"];
        $params = [$requester_id];
        
        if (!empty($filters['status'])) {
            $where[] = "s.code = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['equipment_id'])) {
            $where[] = "r.equipment_id = ?";
            $params[] = $filters['equipment_id'];
        }
        
        $sql = "SELECT r.*, e.name as equipment_name, e.code as equipment_code, 
                       s.name as status_name, s.code as status_code, s.color as status_color,
                       s.icon as status_icon, et.name as equipment_type_name
                FROM repair_requests r
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN equipment_types et ON e.type_id = et.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy đơn theo trạng thái
     */
    public function getByStatus($status_code, $filters = []) {
        $where = ["s.code = ?"];
        $params = [$status_code];
        
        if (!empty($filters['department_id'])) {
            $where[] = "u.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['urgency_level'])) {
            $where[] = "r.urgency_level = ?";
            $params[] = $filters['urgency_level'];
        }
        
        if (!empty($filters['technician_id'])) {
            $where[] = "r.assigned_technician_id = ?";
            $params[] = $filters['technician_id'];
        }
        
        $sql = "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
                       u.full_name as requester_name, d.name as department_name,
                       s.name as status_name, s.color as status_color, s.icon as status_icon,
                       et.name as equipment_type_name
                FROM repair_requests r
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN equipment_types et ON e.type_id = et.id
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.created_at ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy đơn theo workflow cho technician (multi-department)
     */
    public function getByWorkflowForTechnician($technician_id, $status_codes = []) {
        // Lấy department của technician
        $user = (new User())->getById($technician_id);
        if (!$user) {
            return [];
        }
        
        $where = ["rws.assigned_department_id = ?"];
        $params = [$user['department_id']];
        
        if (!empty($status_codes)) {
            $placeholders = str_repeat('?,', count($status_codes) - 1) . '?';
            $where[] = "rws.status IN ($placeholders)";
            $params = array_merge($params, $status_codes);
        }
        
        $sql = "SELECT DISTINCT r.*, e.name as equipment_name, e.code as equipment_code,
                       u.full_name as requester_name, d_req.name as department_name,
                       s.name as status_name, s.color as status_color, s.icon as status_icon,
                       et.name as equipment_type_name,
                       rws.id as workflow_step_id, rws.step_order, rws.status as step_status,
                       rws.started_at as step_started_at, rws.notes as step_notes,
                       d_assigned.name as assigned_department_name, d_assigned.code as assigned_department_code
                FROM repair_requests r
                INNER JOIN repair_workflow_steps rws ON r.id = rws.request_id
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN equipment_types et ON e.type_id = et.id
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN departments d_req ON u.department_id = d_req.id
                LEFT JOIN departments d_assigned ON rws.assigned_department_id = d_assigned.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY 
                    CASE rws.status 
                        WHEN 'in_progress' THEN 1 
                        WHEN 'pending' THEN 2 
                        WHEN 'completed' THEN 3
                    END,
                    r.urgency_level = 'critical' DESC,
                    r.urgency_level = 'high' DESC,
                    r.urgency_level = 'medium' DESC,
                    r.created_at ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy đơn theo ID
     */
    public function getById($id) {
        $sql = "SELECT r.*, e.name as equipment_name, e.code as equipment_code, e.model as equipment_model,
                       e.location as equipment_location, et.name as equipment_type_name,
                       u.full_name as requester_name, u.phone as requester_phone, u.email as requester_email,
                       d.name as department_name, d.code as department_code,
                       s.name as status_name, s.code as status_code, s.color as status_color, s.icon as status_icon,
                       logistics.full_name as logistics_name,
                       clerk.full_name as clerk_name,
                       tech.full_name as technician_name
                FROM repair_requests r
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN equipment_types et ON e.type_id = et.id
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                LEFT JOIN users logistics ON r.assigned_logistics_id = logistics.id
                LEFT JOIN users clerk ON r.assigned_clerk_id = clerk.id
                LEFT JOIN users tech ON r.assigned_technician_id = tech.id
                WHERE r.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Lấy đơn theo mã đơn
     */
    public function getByCode($request_code) {
        $sql = "SELECT r.*, 
                       e.name as equipment_name, e.code as equipment_code, e.model as equipment_model, e.location as equipment_location,
                       u.full_name as requester_name, u.phone as requester_phone, u.email as requester_email,
                       d.name as department_name,
                       s.name as status_name, s.code as status_code, s.color as status_color, s.icon as status_icon,
                       logistics.full_name as logistics_name,
                       clerk.full_name as clerk_name,
                       tech.full_name as technician_name
                FROM repair_requests r
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                LEFT JOIN users logistics ON r.assigned_logistics_id = logistics.id
                LEFT JOIN users clerk ON r.assigned_clerk_id = clerk.id
                LEFT JOIN users tech ON r.assigned_technician_id = tech.id
                WHERE r.request_code = ?";
        
        return $this->db->fetch($sql, [$request_code]);
    }
    
    /**
     * Lấy lịch sử trạng thái
     */
    public function getStatusHistory($request_id) {
        $sql = "SELECT h.*, s.name as status_name, s.color as status_color, s.icon as status_icon,
                       u.full_name as user_name, r.display_name as role_name
                FROM repair_status_history h
                LEFT JOIN repair_statuses s ON h.status_id = s.id
                LEFT JOIN users u ON h.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE h.request_id = ?
                ORDER BY h.created_at ASC";
        
        return $this->db->fetchAll($sql, [$request_id]);
    }
    
    /**
     * Thêm chi tiết sửa chữa
     */
    public function addRepairDetail($request_id, $data) {
        // Xử lý upload hình ảnh
        $images = [];
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $image) {
                if ($image['error'] === UPLOAD_ERR_OK) {
                    $filename = upload_file($image, UPLOAD_REQUEST_PATH, ALLOWED_IMAGE_TYPES);
                    $images[] = $filename;
                }
            }
        }
        
        $detailData = [
            'request_id' => $request_id,
            'content_id' => $data['content_id'] ?? null,
            'description' => $data['description'],
            'parts_replaced' => $data['parts_replaced'] ?? null,
            'parts_cost' => $data['parts_cost'] ?? 0,
            'labor_cost' => $data['labor_cost'] ?? 0,
            'time_spent' => $data['time_spent'] ?? 0,
            'technician_id' => $data['technician_id'],
            'notes' => $data['notes'] ?? null,
            'images' => json_encode($images)
        ];
        
        $detail_id = $this->db->insert('repair_details', $detailData);
        
        // Cập nhật tổng chi phí
        $this->updateTotalCost($request_id);
        
        return $detail_id;
    }
    
    /**
     * Lấy chi tiết sửa chữa
     */
    public function getRepairDetails($request_id) {
        $sql = "SELECT d.*, u.full_name as technician_name, c.name as content_name
                FROM repair_details d
                LEFT JOIN users u ON d.technician_id = u.id
                LEFT JOIN repair_contents c ON d.content_id = c.id
                WHERE d.request_id = ?
                ORDER BY d.created_at ASC";
        
        return $this->db->fetchAll($sql, [$request_id]);
    }
    
    /**
     * Thống kê đơn sửa chữa
     */
    public function getStats($filters = []) {
        $stats = [];
        
        // Tổng số đơn
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM repair_requests");
        $stats['total'] = $total['count'];
        
        // Đơn theo trạng thái
        $statusStats = $this->db->fetchAll(
            "SELECT s.name, s.color, s.code, COUNT(r.id) as count 
             FROM repair_statuses s 
             LEFT JOIN repair_requests r ON s.id = r.current_status_id 
             GROUP BY s.id, s.name, s.color, s.code 
             ORDER BY s.step_order"
        );
        $stats['by_status'] = $statusStats;
        
        // Đơn theo mức độ khẩn cấp
        $urgencyStats = $this->db->fetchAll(
            "SELECT urgency_level, COUNT(*) as count 
             FROM repair_requests 
             GROUP BY urgency_level"
        );
        $stats['by_urgency'] = $urgencyStats;
        
        // Đơn theo tháng (12 tháng gần nhất)
        $monthlyStats = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM repair_requests 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month"
        );
        $stats['by_month'] = $monthlyStats;
        
        return $stats;
    }
    
    /**
     * Validate status transition
     */
    private function validateStatusTransition($current_status, $new_status, $user_id) {
        $user = (new User())->getById($user_id);
        $role = $user['role_name'];
        
        // Admin có thể thực hiện mọi chuyển đổi trạng thái
        if ($role === 'admin') {
            return;
        }
        
        $allowed_transitions = [
            'PENDING_HANDOVER' => ['logistics' => ['HANDED_TO_CLERK', 'CANCELLED']],
            'HANDED_TO_CLERK' => ['clerk' => ['SENT_TO_REPAIR', 'CANCELLED']],
            'SENT_TO_REPAIR' => ['technician' => ['IN_PROGRESS', 'CANCELLED']],
            'IN_PROGRESS' => ['technician' => ['REPAIR_COMPLETED', 'CANCELLED']],
            'REPAIR_COMPLETED' => ['clerk' => ['RETRIEVED', 'CANCELLED']],
            'RETRIEVED' => ['logistics' => ['COMPLETED', 'CANCELLED']]
        ];
        
        if (!isset($allowed_transitions[$current_status][$role]) || 
            !in_array($new_status, $allowed_transitions[$current_status][$role])) {
            throw new Exception('Bạn không có quyền thực hiện hành động này');
        }
    }
    
    /**
     * Generate request code
     */
    private function generateRequestCode() {
        $year = date('Y');
        $month = date('m');
        
        // Lấy số thứ tự trong tháng
        $sql = "SELECT COUNT(*) as count FROM repair_requests 
                WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
        $result = $this->db->fetch($sql, [$year, $month]);
        $count = $result['count'] + 1;
        
        return sprintf("SC%s%s%04d", $year, $month, $count);
    }
    
    /**
     * Get status by code
     */
    private function getStatusByCode($code) {
        $sql = "SELECT * FROM repair_statuses WHERE code = ?";
        return $this->db->fetch($sql, [$code]);
    }
    
    /**
     * Add status history
     */
    private function addStatusHistory($request_id, $status_id, $user_id, $notes, $attachments = []) {
        $historyData = [
            'request_id' => $request_id,
            'status_id' => $status_id,
            'user_id' => $user_id,
            'notes' => $notes,
            'attachments' => json_encode($attachments)
        ];
        
        return $this->db->insert('repair_status_history', $historyData);
    }
    
    /**
     * Update total cost
     */
    private function updateTotalCost($request_id) {
        $sql = "SELECT SUM(parts_cost + labor_cost) as total_cost 
                FROM repair_details 
                WHERE request_id = ?";
        $result = $this->db->fetch($sql, [$request_id]);
        $total_cost = $result['total_cost'] ?? 0;
        
        $this->db->update('repair_requests', 
            ['total_cost' => $total_cost], 
            'id = ?', 
            [$request_id]
        );
    }
}
?>
