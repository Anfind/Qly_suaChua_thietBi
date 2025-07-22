<?php
/**
 * Ajax handler cho export functions
 */
require_once '../config/config.php';
require_any_role(['admin', 'clerk']);

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $exportService = new ExportService();
    
    switch ($action) {
        case 'export_repair_requests':
            $filters = [
                'from_date' => $_POST['from_date'] ?? '',
                'to_date' => $_POST['to_date'] ?? '',
                'department' => $_POST['department'] ?? '',
                'status' => $_POST['status'] ?? '',
                'search' => $_POST['search'] ?? ''
            ];
            
            // Lấy dữ liệu requests
            $db = Database::getInstance();
            $whereConditions = [];
            $params = [];
            
            if ($filters['from_date']) {
                $whereConditions[] = "DATE(r.created_at) >= ?";
                $params[] = $filters['from_date'];
            }
            
            if ($filters['to_date']) {
                $whereConditions[] = "DATE(r.created_at) <= ?";
                $params[] = $filters['to_date'];
            }
            
            if ($filters['department']) {
                $whereConditions[] = "d.id = ?";
                $params[] = $filters['department'];
            }
            
            if ($filters['status']) {
                $whereConditions[] = "s.code = ?";
                $params[] = $filters['status'];
            }
            
            if ($filters['search']) {
                $whereConditions[] = "(r.request_code LIKE ? OR e.name LIKE ? OR u.full_name LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $requests = $db->fetchAll(
                "SELECT r.*, e.name as equipment_name, e.code as equipment_code,
                        u.full_name as requester_name, d.name as department_name,
                        s.name as status_name, s.code as status_code
                 FROM repair_requests r
                 LEFT JOIN equipments e ON r.equipment_id = e.id
                 LEFT JOIN users u ON r.requester_id = u.id
                 LEFT JOIN departments d ON u.department_id = d.id
                 LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                 {$whereClause}
                 ORDER BY r.created_at DESC",
                $params
            );
            
            $result = $exportService->exportRepairRequestsToExcel($requests, $filters);
            
            echo json_encode([
                'success' => true,
                'download_url' => $result['url'],
                'filename' => $result['filename'],
                'message' => 'Export thành công'
            ]);
            break;
            
        case 'export_overview_pdf':
            $filters = [
                'from_date' => $_POST['from_date'] ?? '',
                'to_date' => $_POST['to_date'] ?? '',
                'department_id' => $_POST['department_id'] ?? '',
                'equipment_type_id' => $_POST['equipment_type_id'] ?? ''
            ];
            
            // Lấy dữ liệu cho báo cáo tổng quan
            $db = Database::getInstance();
            $whereConditions = [];
            $params = [];
            
            if ($filters['from_date']) {
                $whereConditions[] = "DATE(r.created_at) >= ?";
                $params[] = $filters['from_date'];
            }
            
            if ($filters['to_date']) {
                $whereConditions[] = "DATE(r.created_at) <= ?";
                $params[] = $filters['to_date'];
            }
            
            if ($filters['department_id']) {
                $whereConditions[] = "u.department_id = ?";
                $params[] = $filters['department_id'];
            }
            
            if ($filters['equipment_type_id']) {
                $whereConditions[] = "e.type_id = ?";
                $params[] = $filters['equipment_type_id'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Thống kê tổng quan
            $totalRequests = $db->fetch("SELECT COUNT(*) as count FROM repair_requests r LEFT JOIN users u ON r.requester_id = u.id LEFT JOIN equipments e ON r.equipment_id = e.id {$whereClause}", $params)['count'];
            $completedRequests = $db->fetch("SELECT COUNT(*) as count FROM repair_requests r LEFT JOIN users u ON r.requester_id = u.id LEFT JOIN equipments e ON r.equipment_id = e.id LEFT JOIN repair_statuses s ON r.current_status_id = s.id {$whereClause} " . ($whereClause ? 'AND' : 'WHERE') . " s.code = 'COMPLETED'", array_merge($params, ['COMPLETED']))['count'];
            $pendingRequests = $totalRequests - $completedRequests;
            $totalCost = $db->fetch("SELECT COALESCE(SUM(r.total_cost), 0) as total FROM repair_requests r LEFT JOIN users u ON r.requester_id = u.id LEFT JOIN equipments e ON r.equipment_id = e.id {$whereClause}", $params)['total'];
            
            // Thống kê theo trạng thái
            $statusStats = $db->fetchAll(
                "SELECT s.name as status_name, COUNT(*) as count
                 FROM repair_requests r
                 LEFT JOIN users u ON r.requester_id = u.id
                 LEFT JOIN equipments e ON r.equipment_id = e.id
                 LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                 {$whereClause}
                 GROUP BY s.id, s.name
                 ORDER BY count DESC",
                $params
            );
            
            // Thống kê theo đơn vị
            $departmentStats = $db->fetchAll(
                "SELECT d.name as department_name,
                        COUNT(*) as total,
                        SUM(CASE WHEN s.code = 'COMPLETED' THEN 1 ELSE 0 END) as completed
                 FROM repair_requests r
                 LEFT JOIN users u ON r.requester_id = u.id
                 LEFT JOIN departments d ON u.department_id = d.id
                 LEFT JOIN equipments e ON r.equipment_id = e.id
                 LEFT JOIN repair_statuses s ON r.current_status_id = s.id
                 {$whereClause}
                 GROUP BY d.id, d.name
                 ORDER BY total DESC",
                $params
            );
            
            // Top thiết bị có sự cố
            $topEquipments = $db->fetchAll(
                "SELECT e.code as equipment_code, e.name as equipment_name, COUNT(*) as repair_count
                 FROM repair_requests r
                 LEFT JOIN users u ON r.requester_id = u.id
                 LEFT JOIN equipments e ON r.equipment_id = e.id
                 {$whereClause}
                 GROUP BY e.id, e.code, e.name
                 ORDER BY repair_count DESC
                 LIMIT 10",
                $params
            );
            
            $data = [
                'total_requests' => $totalRequests,
                'completed_requests' => $completedRequests,
                'pending_requests' => $pendingRequests,
                'total_cost' => $totalCost,
                'status_stats' => $statusStats,
                'department_stats' => $departmentStats,
                'top_equipments' => $topEquipments,
                'date_range' => ($filters['from_date'] && $filters['to_date']) ? 
                    format_date($filters['from_date']) . ' - ' . format_date($filters['to_date']) : null
            ];
            
            $result = $exportService->exportOverviewToPdf($data);
            
            echo json_encode([
                'success' => true,
                'download_url' => $result['url'],
                'filename' => $result['filename'],
                'message' => 'Export PDF thành công'
            ]);
            break;
            
        case 'export_equipment_history':
            $equipmentId = $_POST['equipment_id'] ?? '';
            if (!$equipmentId) {
                throw new Exception('Thiếu ID thiết bị');
            }
            
            $result = $exportService->exportEquipmentHistoryToPdf($equipmentId);
            
            echo json_encode([
                'success' => true,
                'download_url' => $result['url'],
                'filename' => $result['filename'],
                'message' => 'Export lịch sử thiết bị thành công'
            ]);
            break;
            
        case 'cleanup_exports':
            if (!has_role('admin')) {
                throw new Exception('Không có quyền thực hiện');
            }
            
            $daysOld = (int)($_POST['days_old'] ?? 7);
            $deletedCount = $exportService->cleanupOldExports($daysOld);
            
            echo json_encode([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Đã xóa {$deletedCount} file cũ"
            ]);
            break;
            
        default:
            throw new Exception('Action không được hỗ trợ');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
