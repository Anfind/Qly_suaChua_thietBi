<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Model User - Quản lý người dùng
 */
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Đăng nhập
     */
    public function login($username, $password) {
        $sql = "SELECT u.*, r.name as role_name, r.display_name as role_display_name, 
                       d.name as department_name, d.code as department_code
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.username = ? AND u.status = 'active'";
        
        $user = $this->db->fetch($sql, [$username]);
        
        if ($user) {
            // Kiểm tra password (hỗ trợ cả plain text và hash)
            $password_valid = false;
            
            // Nếu password trong DB là plain text (không có $ ở đầu)
            if (strpos($user['password'], '$') !== 0) {
                $password_valid = ($password === $user['password']);
            } else {
                // Nếu password đã được hash
                $password_valid = password_verify($password, $user['password']);
            }
            
            if ($password_valid) {
                try {
                    // Cập nhật thời gian đăng nhập cuối
                    $this->db->update('users', 
                        ['last_login' => date('Y-m-d H:i:s')], 
                        'id = ?', 
                        [$user['id']]
                    );
                } catch (Exception $e) {
                    // Log lỗi nhưng không ngăn login thành công
                    error_log("Failed to update last_login for user {$user['id']}: " . $e->getMessage());
                }
                
                // Xóa password khỏi session
                unset($user['password']);
                
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Lấy tất cả users theo role
     */
    public function getUsersByRole($role_name) {
        $sql = "SELECT u.*, d.name as department_name, d.code as department_code
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE r.name = ? AND u.status = 'active'
                ORDER BY u.full_name";
        
        return $this->db->fetchAll($sql, [$role_name]);
    }
    
    /**
     * Lấy tất cả users
     */
    public function getAll($filters = []) {
        $where = ["u.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['role_id'])) {
            $where[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['department_id'])) {
            $where[] = "u.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql = "SELECT u.*, r.display_name as role_name, d.name as department_name
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY u.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Lấy user theo ID
     */
    public function getById($id) {
        $sql = "SELECT u.*, r.name as role_name, r.display_name as role_display_name,
                       d.name as department_name, d.code as department_code
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }
    
    /**
     * Tạo user mới
     */
    public function create($data) {
        // Kiểm tra username đã tồn tại
        if ($this->usernameExists($data['username'])) {
            throw new Exception('Tên đăng nhập đã tồn tại');
        }
        
        // Kiểm tra email đã tồn tại
        if (!empty($data['email']) && $this->emailExists($data['email'])) {
            throw new Exception('Email đã tồn tại');
        }
        
        $userData = [
            'username' => $data['username'],
            'password' => $data['password'],
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'role_id' => $data['role_id'],
            'status' => $data['status'] ?? 'active'
        ];
        
        return $this->db->insert('users', $userData);
    }
    
    /**
     * Cập nhật user
     */
    public function update($id, $data) {
        $user = $this->getById($id);
        if (!$user) {
            throw new Exception('Không tìm thấy người dùng');
        }
        
        // Kiểm tra username đã tồn tại (trừ user hiện tại)
        if (isset($data['username']) && $data['username'] !== $user['username']) {
            if ($this->usernameExists($data['username'])) {
                throw new Exception('Tên đăng nhập đã tồn tại');
            }
        }
        
        // Kiểm tra email đã tồn tại (trừ user hiện tại)
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            if (!empty($data['email']) && $this->emailExists($data['email'])) {
                throw new Exception('Email đã tồn tại');
            }
        }
        
        $updateData = [];
        $allowedFields = ['username', 'full_name', 'email', 'phone', 'department_id', 'role_id', 'status', 'avatar'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Cập nhật password nếu có
        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('users', $updateData, 'id = ?', [$id]);
    }
    
    /**
     * Xóa user (soft delete)
     */
    public function delete($id) {
        return $this->db->update('users', 
            ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$id]
        );
    }
    
    /**
     * Thay đổi mật khẩu
     */
    public function changePassword($id, $currentPassword, $newPassword) {
        $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$id]);
        
        if (!$user || $currentPassword !== $user['password']) {
            throw new Exception('Mật khẩu hiện tại không đúng');
        }
        
        return $this->db->update('users', 
            ['password' => $newPassword, 'updated_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$id]
        );
    }
    
    /**
     * Kiểm tra username đã tồn tại
     */
    private function usernameExists($username) {
        $result = $this->db->fetch("SELECT id FROM users WHERE username = ? AND status != 'deleted'", [$username]);
        return $result !== false;
    }
    
    /**
     * Kiểm tra email đã tồn tại
     */
    private function emailExists($email) {
        $result = $this->db->fetch("SELECT id FROM users WHERE email = ? AND status != 'deleted'", [$email]);
        return $result !== false;
    }
    
    /**
     * Lấy thống kê users
     */
    public function getStats() {
        $stats = [];
        
        // Tổng số users
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE status != 'deleted'");
        $stats['total'] = $total['count'];
        
        // Users theo role
        $roleStats = $this->db->fetchAll(
            "SELECT r.display_name, COUNT(u.id) as count 
             FROM roles r 
             LEFT JOIN users u ON r.id = u.role_id AND u.status != 'deleted'
             GROUP BY r.id, r.display_name 
             ORDER BY r.display_name"
        );
        $stats['by_role'] = $roleStats;
        
        // Users theo department
        $deptStats = $this->db->fetchAll(
            "SELECT d.name, COUNT(u.id) as count 
             FROM departments d 
             LEFT JOIN users u ON d.id = u.department_id AND u.status != 'deleted'
             WHERE d.status = 'active'
             GROUP BY d.id, d.name 
             ORDER BY d.name"
        );
        $stats['by_department'] = $deptStats;
        
        return $stats;
    }
    
    /**
     * Upload avatar
     */
    public function uploadAvatar($userId, $file) {
        try {
            $filename = upload_file($file, UPLOAD_AVATAR_PATH, ALLOWED_IMAGE_TYPES);
            
            // Xóa avatar cũ
            $user = $this->getById($userId);
            if ($user && $user['avatar']) {
                delete_file(UPLOAD_AVATAR_PATH . $user['avatar']);
            }
            
            // Cập nhật avatar mới
            $this->update($userId, ['avatar' => $filename]);
            
            return $filename;
        } catch (Exception $e) {
            throw new Exception('Lỗi upload avatar: ' . $e->getMessage());
        }
    }
}
?>
