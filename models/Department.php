<?php
/**
 * Model Department - Quản lý đơn vị
 */
class Department {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy tất cả departments
     */
    public function getAll($filters = []) {
        $where = ["status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(name LIKE ? OR code LIKE ? OR manager_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        $sql = "SELECT *, 
                       (SELECT COUNT(*) FROM users u WHERE u.department_id = departments.id AND u.status = 'active') as user_count,
                       (SELECT COUNT(*) FROM equipments e WHERE e.department_id = departments.id AND e.status != 'disposed') as equipment_count
                FROM departments 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy department theo ID
     */
    public function getById($id) {
        $sql = "SELECT *, 
                       (SELECT COUNT(*) FROM users u WHERE u.department_id = departments.id AND u.status = 'active') as user_count,
                       (SELECT COUNT(*) FROM equipments e WHERE e.department_id = departments.id AND e.status != 'disposed') as equipment_count
                FROM departments 
                WHERE id = ? AND status != 'deleted'";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Tạo department mới
     */
    public function create($data) {
        // Kiểm tra mã đơn vị đã tồn tại
        if ($this->codeExists($data['code'])) {
            throw new Exception('Mã đơn vị đã tồn tại');
        }
        
        $departmentData = [
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'address' => trim($data['address'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'manager_name' => trim($data['manager_name'] ?? ''),
            'status' => $data['status'] ?? 'active'
        ];
        
        return $this->db->insert('departments', $departmentData);
    }
    
    /**
     * Cập nhật department
     */
    public function update($id, $data) {
        $department = $this->getById($id);
        if (!$department) {
            throw new Exception('Không tìm thấy đơn vị');
        }
        
        // Kiểm tra mã đơn vị đã tồn tại (trừ đơn vị hiện tại)
        if (isset($data['code']) && strtoupper($data['code']) !== $department['code']) {
            if ($this->codeExists($data['code'])) {
                throw new Exception('Mã đơn vị đã tồn tại');
            }
        }
        
        $updateData = [];
        $allowedFields = ['code', 'name', 'address', 'phone', 'email', 'manager_name', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'code') {
                    $updateData[$field] = strtoupper(trim($data[$field]));
                } else {
                    $updateData[$field] = trim($data[$field]);
                }
            }
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('departments', $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Xóa department (soft delete)
     */
    public function delete($id) {
        $department = $this->getById($id);
        if (!$department) {
            throw new Exception('Không tìm thấy đơn vị');
        }
        
        // Kiểm tra có users hoặc equipments thuộc đơn vị này không
        if ($department['user_count'] > 0) {
            throw new Exception('Không thể xóa đơn vị đang có người dùng. Hãy chuyển người dùng sang đơn vị khác trước.');
        }
        
        if ($department['equipment_count'] > 0) {
            throw new Exception('Không thể xóa đơn vị đang có thiết bị. Hãy chuyển thiết bị sang đơn vị khác trước.');
        }
        
        return $this->db->update('departments', 
            ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$id]
        );
    }
    
    /**
     * Kiểm tra mã đơn vị đã tồn tại
     */
    private function codeExists($code) {
        $result = $this->db->fetch(
            "SELECT id FROM departments WHERE code = ? AND status != 'deleted'", 
            [strtoupper(trim($code))]
        );
        return $result !== false;
    }
    
    /**
     * Lấy thống kê departments
     */
    public function getStats() {
        $stats = [];
        
        // Tổng số đơn vị
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM departments WHERE status != 'deleted'");
        $stats['total'] = $total['count'];
        
        // Đơn vị theo trạng thái
        $statusStats = $this->db->fetchAll(
            "SELECT status, COUNT(*) as count 
             FROM departments 
             WHERE status != 'deleted'
             GROUP BY status"
        );
        $stats['by_status'] = $statusStats;
        
        // Top đơn vị có nhiều người dùng nhất
        $topByUsers = $this->db->fetchAll(
            "SELECT d.name, d.code, COUNT(u.id) as user_count
             FROM departments d
             LEFT JOIN users u ON d.id = u.department_id AND u.status = 'active'
             WHERE d.status = 'active'
             GROUP BY d.id
             ORDER BY user_count DESC
             LIMIT 5"
        );
        $stats['top_by_users'] = $topByUsers;
        
        // Top đơn vị có nhiều thiết bị nhất
        $topByEquipments = $this->db->fetchAll(
            "SELECT d.name, d.code, COUNT(e.id) as equipment_count
             FROM departments d
             LEFT JOIN equipments e ON d.id = e.department_id AND e.status != 'disposed'
             WHERE d.status = 'active'
             GROUP BY d.id
             ORDER BY equipment_count DESC
             LIMIT 5"
        );
        $stats['top_by_equipments'] = $topByEquipments;
        
        return $stats;
    }
    
    /**
     * Lấy danh sách users thuộc department
     */
    public function getUsers($departmentId) {
        $sql = "SELECT u.*, r.display_name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.department_id = ? AND u.status = 'active'
                ORDER BY u.full_name";
        
        return $this->db->fetchAll($sql, [$departmentId]);
    }
    
    /**
     * Lấy danh sách equipments thuộc department
     */
    public function getEquipments($departmentId) {
        $sql = "SELECT e.*, t.name as type_name
                FROM equipments e
                LEFT JOIN equipment_types t ON e.type_id = t.id
                WHERE e.department_id = ? AND e.status != 'disposed'
                ORDER BY e.name";
        
        return $this->db->fetchAll($sql, [$departmentId]);
    }
    
    /**
     * Chuyển tất cả users sang đơn vị khác
     */
    public function transferUsers($fromDepartmentId, $toDepartmentId) {
        $sql = "UPDATE users SET department_id = ?, updated_at = NOW() 
                WHERE department_id = ? AND status = 'active'";
        
        return $this->db->query($sql, [$toDepartmentId, $fromDepartmentId]);
    }
    
    /**
     * Chuyển tất cả equipments sang đơn vị khác
     */
    public function transferEquipments($fromDepartmentId, $toDepartmentId) {
        $sql = "UPDATE equipments SET department_id = ?, updated_at = NOW() 
                WHERE department_id = ? AND status != 'disposed'";
        
        return $this->db->query($sql, [$toDepartmentId, $fromDepartmentId]);
    }
}
?>
