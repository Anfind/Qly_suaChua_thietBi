<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Model Equipment - Quản lý thiết bị
 */
class Equipment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy tất cả thiết bị
     */
    public function getAll($filters = []) {
        $where = ["e.status != 'disposed'"];
        $params = [];
        
        if (!empty($filters['type_id'])) {
            $where[] = "e.type_id = ?";
            $params[] = $filters['type_id'];
        }
        
        if (!empty($filters['department_id'])) {
            $where[] = "e.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "e.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(e.name LIKE ? OR e.code LIKE ? OR e.model LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql = "SELECT e.*, t.name as type_name, t.icon as type_icon,
                       d.name as department_name, d.code as department_code
                FROM equipments e 
                LEFT JOIN equipment_types t ON e.type_id = t.id 
                LEFT JOIN departments d ON e.department_id = d.id 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy thiết bị theo ID
     */
    public function getById($id) {
        $sql = "SELECT e.*, t.name as type_name, t.icon as type_icon,
                       d.name as department_name, d.code as department_code
                FROM equipments e 
                LEFT JOIN equipment_types t ON e.type_id = t.id 
                LEFT JOIN departments d ON e.department_id = d.id 
                WHERE e.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Lấy thiết bị theo department
     */
    public function getByDepartment($department_id) {
        $sql = "SELECT e.*, t.name as type_name 
                FROM equipments e 
                LEFT JOIN equipment_types t ON e.type_id = t.id 
                WHERE e.department_id = ? AND e.status = 'active'
                ORDER BY e.name";
        
        return $this->db->fetchAll($sql, [$department_id]);
    }
    
    /**
     * Tạo thiết bị mới
     */
    public function create($data) {
        // Kiểm tra mã thiết bị đã tồn tại
        if ($this->codeExists($data['code'])) {
            throw new Exception('Mã thiết bị đã tồn tại');
        }
        
        // Validate type_id
        if (empty($data['type_id'])) {
            throw new Exception('Loại thiết bị là bắt buộc');
        }
        
        $equipmentData = [
            'code' => $data['code'],
            'name' => $data['name'],
            'model' => $data['model'] ?? null,
            'brand' => $data['brand'] ?? null,
            'type_id' => (int)$data['type_id'],
            'department_id' => $data['department_id'] ?? null,
            'location' => $data['location'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'warranty_date' => $data['warranty_date'] ?? null,
            'purchase_price' => $data['purchase_price'] ?? null,
            'specifications' => $data['specifications'] ?? null,
            'image' => $data['image'] ?? null,
            'status' => $data['status'] ?? 'active',
            'description' => $data['description'] ?? null
        ];
        
        return $this->db->insert('equipments', $equipmentData);
    }
    
    /**
     * Cập nhật thiết bị
     */
    public function update($id, $data) {
        $equipment = $this->getById($id);
        if (!$equipment) {
            throw new Exception('Không tìm thấy thiết bị');
        }
        
        // Kiểm tra mã thiết bị đã tồn tại (trừ thiết bị hiện tại)
        if (isset($data['code']) && $data['code'] !== $equipment['code']) {
            if ($this->codeExists($data['code'])) {
                throw new Exception('Mã thiết bị đã tồn tại');
            }
        }
        
        // Validate type_id khi update
        if (isset($data['type_id']) && empty($data['type_id'])) {
            throw new Exception('Loại thiết bị là bắt buộc');
        }
        
        $updateData = [];
        $allowedFields = ['code', 'name', 'model', 'brand', 'type_id', 'department_id', 
                         'location', 'purchase_date', 'warranty_date', 'purchase_price', 
                         'specifications', 'image', 'status', 'description'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'type_id' && !empty($data[$field])) {
                    $updateData[$field] = (int)$data[$field];
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('equipments', $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Xóa thiết bị (soft delete)
     */
    public function delete($id) {
        // Kiểm tra thiết bị có đang trong quá trình sửa chữa không
        $activeRequests = $this->db->fetch(
            "SELECT COUNT(*) as count 
             FROM repair_requests r 
             JOIN repair_statuses s ON r.current_status_id = s.id 
             WHERE r.equipment_id = ? AND s.code NOT IN ('COMPLETED', 'CANCELLED')",
            [$id]
        );
        
        if ($activeRequests['count'] > 0) {
            throw new Exception('Không thể xóa thiết bị đang trong quá trình sửa chữa');
        }
        
        return $this->db->update('equipments', 
            ['status' => 'disposed', 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$id]
        );
    }
    
    /**
     * Kiểm tra mã thiết bị đã tồn tại
     */
    private function codeExists($code) {
        $result = $this->db->fetch("SELECT id FROM equipments WHERE code = ? AND status != 'disposed'", [$code]);
        return $result !== false;
    }
    
    /**
     * Lấy thống kê thiết bị
     */
    public function getStats() {
        $stats = [];
        
        // Tổng số thiết bị
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM equipments WHERE status != 'disposed'");
        $stats['total'] = $total['count'];
        
        // Thiết bị theo trạng thái
        $statusStats = $this->db->fetchAll(
            "SELECT status, COUNT(*) as count 
             FROM equipments 
             WHERE status != 'disposed'
             GROUP BY status"
        );
        $stats['by_status'] = $statusStats;
        
        // Thiết bị theo loại
        $typeStats = $this->db->fetchAll(
            "SELECT t.name, t.icon, COUNT(e.id) as count 
             FROM equipment_types t 
             LEFT JOIN equipments e ON t.id = e.type_id AND e.status != 'disposed'
             GROUP BY t.id, t.name, t.icon 
             ORDER BY count DESC"
        );
        $stats['by_type'] = $typeStats;
        
        // Thiết bị theo đơn vị
        $deptStats = $this->db->fetchAll(
            "SELECT d.name, COUNT(e.id) as count 
             FROM departments d 
             LEFT JOIN equipments e ON d.id = e.department_id AND e.status != 'disposed'
             WHERE d.status = 'active'
             GROUP BY d.id, d.name 
             ORDER BY count DESC"
        );
        $stats['by_department'] = $deptStats;
        
        return $stats;
    }
    
    /**
     * Upload hình ảnh thiết bị
     */
    public function uploadImage($equipmentId, $file) {
        try {
            $filename = upload_file($file, UPLOAD_EQUIPMENT_PATH, ALLOWED_IMAGE_TYPES);
            
            // Xóa ảnh cũ
            $equipment = $this->getById($equipmentId);
            if ($equipment && $equipment['image']) {
                delete_file(UPLOAD_EQUIPMENT_PATH . $equipment['image']);
            }
            
            // Cập nhật ảnh mới
            $this->update($equipmentId, ['image' => $filename]);
            
            return $filename;
        } catch (Exception $e) {
            throw new Exception('Lỗi upload hình ảnh: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy lịch sử sửa chữa của thiết bị
     */
    public function getRepairHistory($equipmentId) {
        $sql = "SELECT r.request_code, r.created_at, r.problem_description,
                       u.full_name as requester_name, s.name as status_name, s.color as status_color,
                       rd.description as repair_description, rd.parts_replaced, rd.cost as repair_cost
                FROM repair_requests r
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                LEFT JOIN repair_details rd ON r.id = rd.request_id
                WHERE r.equipment_id = ?
                ORDER BY r.created_at DESC";
        
        return $this->db->fetchAll($sql, [$equipmentId]);
    }
}
?>
