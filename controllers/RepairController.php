<?php
/**
 * Controller xử lý các request liên quan đến đơn sửa chữa
 */
class RepairController {
    private $repairModel;
    private $userModel;
    private $equipmentModel;
    
    public function __construct() {
        $this->repairModel = new RepairRequest();
        $this->userModel = new User();
        $this->equipmentModel = new Equipment();
    }
    
    /**
     * Trang danh sách đơn sửa chữa theo role
     */
    public function index() {
        require_login();
        
        $user = current_user();
        $role = $user['role_name'];
        
        switch ($role) {
            case 'requester':
                return $this->requesterDashboard();
            case 'logistics':
                return $this->logisticsDashboard();
            case 'clerk':
                return $this->clerkDashboard();
            case 'technician':
                return $this->technicianDashboard();
            case 'admin':
                return $this->adminDashboard();
            default:
                redirect('dashboard.php', 'Vai trò không được hỗ trợ', 'error');
        }
    }
    
    /**
     * Dashboard người đề xuất
     */
    private function requesterDashboard() {
        $user = current_user();
        $filters = [
            'status' => $_GET['status'] ?? '',
            'equipment_id' => $_GET['equipment_id'] ?? ''
        ];
        
        $requests = $this->repairModel->getByRequester($user['id'], $filters);
        $equipments = $this->equipmentModel->getByDepartment($user['department_id']);
        
        return compact('requests', 'equipments', 'filters');
    }
    
    /**
     * Dashboard giao liên
     */
    private function logisticsDashboard() {
        $pendingHandover = $this->repairModel->getByStatus('PENDING_HANDOVER');
        $readyForReturn = $this->repairModel->getByStatus('RETRIEVED');
        
        return compact('pendingHandover', 'readyForReturn');
    }
    
    /**
     * Dashboard văn thư
     */
    private function clerkDashboard() {
        $handed = $this->repairModel->getByStatus('LOGISTICS_HANDOVER');
        $alsoHanded = $this->repairModel->getByStatus('HANDED_TO_CLERK'); // Để hỗ trợ dữ liệu cũ
        $completed = $this->repairModel->getByStatus('REPAIR_COMPLETED');
        
        // Gộp 2 danh sách lại
        $handed = array_merge($handed, $alsoHanded);
        
        return compact('handed', 'completed');
    }
    
    /**
     * Dashboard kỹ thuật (CHỈ hiển thị đơn của phòng ban trong workflow)
     */
    private function technicianDashboard() {
        $user = current_user();
        
        // CHỈ lấy workflow steps cho phòng ban hiện tại (KHÔNG bao gồm assigned_technician_id)
        $pendingSteps = $this->repairModel->getByWorkflowForTechnician($user['id'], ['pending']);
        $inProgressSteps = $this->repairModel->getByWorkflowForTechnician($user['id'], ['in_progress']);
        
        // KHÔNG sử dụng fallback assigned_technician_id để tránh hiển thị sai
        // Chỉ dùng workflow system hoàn toàn
        $sent = []; // Không lấy đơn truyền thống nữa
        $inProgress = []; // Không lấy đơn truyền thống nữa
        
        return compact('sent', 'inProgress', 'pendingSteps', 'inProgressSteps');
    }
    
    /**
     * Dashboard admin
     */
    private function adminDashboard() {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'department_id' => $_GET['department_id'] ?? '',
            'urgency_level' => $_GET['urgency_level'] ?? ''
        ];
        
        $allRequests = [];
        if (empty($filters['status'])) {
            // Lấy tất cả trạng thái
            $statuses = ['PENDING_HANDOVER', 'LOGISTICS_RECEIVED', 'LOGISTICS_HANDOVER', 'HANDED_TO_CLERK', 'SENT_TO_REPAIR', 'IN_PROGRESS', 'REPAIR_COMPLETED', 'RETRIEVED'];
            foreach ($statuses as $status) {
                $requests = $this->repairModel->getByStatus($status, $filters);
                $allRequests[$status] = $requests;
            }
        } else {
            $allRequests[$filters['status']] = $this->repairModel->getByStatus($filters['status'], $filters);
        }
        
        return compact('allRequests', 'filters');
    }
    
    /**
     * Tạo đơn sửa chữa mới
     */
    public function create() {
        require_role('requester');
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Không cần lấy equipments nữa vì người dùng sẽ tự nhập
            return [];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                verify_csrf();
                
                // Validate thiết bị info
                if (empty(trim($_POST['equipment_name']))) {
                    throw new Exception('Vui lòng nhập tên thiết bị');
                }
                
                if (empty(trim($_POST['equipment_location']))) {
                    throw new Exception('Vui lòng nhập vị trí thiết bị');
                }
                
                if (empty(trim($_POST['problem_description']))) {
                    throw new Exception('Vui lòng mô tả tình trạng lỗi');
                }
                
                // Tạo hoặc tìm thiết bị
                $equipmentData = [
                    'name' => trim($_POST['equipment_name']),
                    'code' => !empty($_POST['equipment_code']) ? trim($_POST['equipment_code']) : null,
                    'model' => !empty($_POST['equipment_model']) ? trim($_POST['equipment_model']) : null,
                    'brand' => !empty($_POST['equipment_brand']) ? trim($_POST['equipment_brand']) : null,
                    'location' => trim($_POST['equipment_location']),
                    'department_id' => current_user()['department_id'],
                    'type_id' => 1, // Default type - có thể cần sửa lại
                    'status' => 'active'
                ];
                
                // Tìm thiết bị có sẵn hoặc tạo mới
                $equipment_id = $this->findOrCreateEquipment($equipmentData);
                
                $data = [
                    'equipment_id' => $equipment_id,
                    'requester_id' => current_user()['id'],
                    'problem_description' => trim($_POST['problem_description']),
                    'urgency_level' => $_POST['urgency_level'] ?? 'medium',
                    'images' => $_FILES['images'] ?? [],
                    'videos' => $_FILES['videos'] ?? []
                ];
                
                $request_code = $this->repairModel->create($data);
                
                redirect('repairs/view.php?code=' . $request_code, 
                    'Tạo đơn sửa chữa thành công! Mã đơn: ' . $request_code, 'success');
                
            } catch (Exception $e) {
                $error = $e->getMessage();
                return compact('error');
            }
        }
    }
    
    /**
     * Tìm hoặc tạo thiết bị mới
     */
    private function findOrCreateEquipment($equipmentData) {
        // Nếu có mã thiết bị, tìm theo mã
        if (!empty($equipmentData['code'])) {
            $existing = $this->equipmentModel->getByCode($equipmentData['code']);
            if ($existing) {
                // Cập nhật thông tin nếu cần
                $this->equipmentModel->update($existing['id'], [
                    'name' => $equipmentData['name'],
                    'model' => $equipmentData['model'],
                    'brand' => $equipmentData['brand'],
                    'location' => $equipmentData['location']
                ]);
                return $existing['id'];
            }
        }
        
        // Tìm thiết bị tương tự (cùng tên và vị trí)
        $similar = $this->equipmentModel->findSimilar(
            $equipmentData['name'], 
            $equipmentData['location'], 
            $equipmentData['department_id']
        );
        
        if ($similar) {
            return $similar['id'];
        }
        
        // Tạo mã thiết bị nếu chưa có
        if (empty($equipmentData['code'])) {
            $equipmentData['code'] = $this->generateEquipmentCode($equipmentData['name']);
        }
        
        // Tạo thiết bị mới
        return $this->equipmentModel->create($equipmentData);
    }
    
    /**
     * Tạo mã thiết bị tự động
     */
    private function generateEquipmentCode($equipmentName) {
        // Tạo prefix từ tên thiết bị
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $equipmentName), 0, 4));
        if (strlen($prefix) < 2) {
            $prefix = 'EQ';
        }
        
        // Tìm số thứ tự tiếp theo
        $counter = 1;
        do {
            $code = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $existing = $this->equipmentModel->getByCode($code);
            $counter++;
        } while ($existing && $counter <= 999);
        
        return $code;
    }
    
    /**
     * Xem chi tiết đơn sửa chữa
     */
    public function view() {
        require_login();
        
        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            redirect('repairs/', 'Không tìm thấy đơn sửa chữa', 'error');
        }
        
        $request = $this->repairModel->getByCode($code);
        if (!$request) {
            redirect('repairs/', 'Không tìm thấy đơn sửa chữa', 'error');
        }
        
        // Kiểm tra quyền xem
        $user = current_user();
        if (!has_role('admin') && $request['requester_id'] != $user['id']) {
            // Cho phép các role khác xem nếu đơn thuộc về workflow của họ
            $allowedRoles = ['logistics', 'clerk', 'technician'];
            if (!has_any_role($allowedRoles)) {
                redirect('repairs/', 'Bạn không có quyền xem đơn này', 'error');
            }
        }
        
        $statusHistory = $this->repairModel->getStatusHistory($request['id']);
        $repairDetails = $this->repairModel->getRepairDetails($request['id']);
        
        return compact('request', 'statusHistory', 'repairDetails');
    }
    
    /**
     * Cập nhật trạng thái đơn
     */
    public function updateStatus() {
        require_login();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                verify_csrf();
                
                $request_id = $_POST['request_id'];
                $new_status = $_POST['new_status'];
                $notes = $_POST['notes'] ?? '';
                $user_id = current_user()['id'];
                $user_role = current_user()['role_name'] ?? '';
                
                // Kiểm tra quyền cập nhật trạng thái
                $allowed_roles = ['admin', 'clerk', 'technician', 'logistics'];
                if (!in_array($user_role, $allowed_roles)) {
                    throw new Exception('Bạn không có quyền thực hiện hành động này');
                }
                
                // Xử lý attachments nếu có
                $attachments = [];
                if (isset($_FILES['attachments'])) {
                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['attachments']['name'][$key],
                                'tmp_name' => $tmp_name,
                                'size' => $_FILES['attachments']['size'][$key],
                                'error' => $_FILES['attachments']['error'][$key]
                            ];
                            $filename = upload_file($file, UPLOAD_REQUEST_PATH);
                            $attachments[] = $filename;
                        }
                    }
                }
                
                $this->repairModel->updateStatus($request_id, $new_status, $user_id, $notes, $attachments);
                
                $request = $this->repairModel->getById($request_id);
                redirect('repairs/view.php?code=' . $request['request_code'], 
                    'Cập nhật trạng thái thành công', 'success');
                
            } catch (Exception $e) {
                redirect($_SERVER['HTTP_REFERER'] ?? 'repairs/', 
                    'Lỗi: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Thêm chi tiết sửa chữa
     */
    public function addRepairDetail() {
        require_any_role(['technician', 'admin']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                verify_csrf();
                
                $data = [
                    'content_id' => $_POST['content_id'] ?? null,
                    'description' => $_POST['description'],
                    'parts_replaced' => $_POST['parts_replaced'] ?? '',
                    'parts_cost' => floatval($_POST['parts_cost'] ?? 0),
                    'labor_cost' => floatval($_POST['labor_cost'] ?? 0),
                    'time_spent' => intval($_POST['time_spent'] ?? 0),
                    'technician_id' => current_user()['id'],
                    'notes' => $_POST['notes'] ?? '',
                    'images' => $_FILES['images'] ?? []
                ];
                
                if (empty($data['description'])) {
                    throw new Exception('Vui lòng nhập mô tả công việc');
                }
                
                $request_id = $_POST['request_id'];
                $this->repairModel->addRepairDetail($request_id, $data);
                
                $request = $this->repairModel->getById($request_id);
                redirect('repairs/view.php?code=' . $request['request_code'], 
                    'Thêm chi tiết sửa chữa thành công', 'success');
                
            } catch (Exception $e) {
                redirect($_SERVER['HTTP_REFERER'] ?? 'repairs/', 
                    'Lỗi: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Export báo cáo
     */
    public function export() {
        require_any_role(['admin', 'manager']);
        
        $format = $_GET['format'] ?? 'excel';
        $filters = [
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'status' => $_GET['status'] ?? '',
            'department_id' => $_GET['department_id'] ?? ''
        ];
        
        // TODO: Implement export functionality
        // This would generate Excel/PDF reports
        
        switch ($format) {
            case 'excel':
                $this->exportExcel($filters);
                break;
            case 'pdf':
                $this->exportPdf($filters);
                break;
            default:
                redirect('repairs/', 'Định dạng export không hỗ trợ', 'error');
        }
    }
    
    /**
     * API endpoint để lấy dữ liệu cho charts
     */
    public function getChartData() {
        require_login();
        
        header('Content-Type: application/json');
        
        $type = $_GET['type'] ?? '';
        $data = [];
        
        switch ($type) {
            case 'status':
                $stats = $this->repairModel->getStats();
                $data = $stats['by_status'];
                break;
                
            case 'monthly':
                $stats = $this->repairModel->getStats();
                $data = $stats['by_month'];
                break;
                
            case 'urgency':
                $stats = $this->repairModel->getStats();
                $data = $stats['by_urgency'];
                break;
        }
        
        echo json_encode($data);
        exit;
    }
    
    /**
     * Tìm kiếm đơn sửa chữa
     */
    public function search() {
        require_login();
        
        $query = $_GET['q'] ?? '';
        if (strlen($query) < 2) {
            echo json_encode([]);
            exit;
        }
        
        $sql = "SELECT r.request_code, r.created_at, e.name as equipment_name,
                       u.full_name as requester_name, s.name as status_name
                FROM repair_requests r
                LEFT JOIN equipments e ON r.equipment_id = e.id
                LEFT JOIN users u ON r.requester_id = u.id
                LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                WHERE r.request_code LIKE ? OR e.name LIKE ? OR u.full_name LIKE ?
                ORDER BY r.created_at DESC
                LIMIT 10";
        
        $searchTerm = '%' . $query . '%';
        $results = $this->repairModel->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm]);
        
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }
    
    /**
     * Xác nhận nhận đề xuất (Logistics)
     */
    public function confirmHandover() {
        try {
            // Kiểm tra quyền trực tiếp
            $user = current_user();
            if (!$user || !in_array($user['role_name'], ['logistics', 'admin'])) {
                throw new Exception('Bạn không có quyền thực hiện hành động này');
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                verify_csrf();
                
                $request_id = $_POST['request_id'];
                $notes = $_POST['notes'] ?? '';
                $user_id = current_user()['id'];
                
                // Cập nhật trạng thái thành "HANDED_TO_CLERK"
                $this->repairModel->updateStatus($request_id, 'HANDED_TO_CLERK', $user_id, $notes);
                
                $request = $this->repairModel->getById($request_id);
                redirect('logistics/index.php', 
                    'Đã xác nhận nhận đề xuất đơn ' . $request['request_code'], 'success');
                
            } else {
                throw new Exception('Phương thức không hợp lệ');
            }
        } catch (Exception $e) {
            redirect($_SERVER['HTTP_REFERER'] ?? 'logistics/index.php', 
                'Lỗi: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Xác nhận trả thiết bị (Logistics)
     */
    public function confirmReturn() {
        require_any_role(['logistics', 'admin']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                verify_csrf();
                
                $request_id = $_POST['request_id'];
                $notes = $_POST['notes'] ?? '';
                $user_id = current_user()['id'];
                
                // Cập nhật trạng thái thành "COMPLETED"
                $this->repairModel->updateStatus($request_id, 'COMPLETED', $user_id, $notes);
                
                $request = $this->repairModel->getById($request_id);
                redirect('logistics/index.php', 
                    'Đã xác nhận trả thiết bị đơn ' . $request['request_code'], 'success');
                
            } catch (Exception $e) {
                redirect($_SERVER['HTTP_REFERER'] ?? 'logistics/index.php', 
                    'Lỗi: ' . $e->getMessage(), 'error');
            }
        }
    }
}
?>
