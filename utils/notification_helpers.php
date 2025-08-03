<?php
/**
 * NOTIFICATION SYSTEM HELPERS
 * Hệ thống thông báo toàn diện cho ứng dụng quản lý sửa chữa thiết bị
 */

/**
 * Tạo thông báo mới
 */
function createNotification($userId, $title, $message, $type = 'info', $relatedType = 'repair_request', $relatedId = null, $actionUrl = null) {
    $db = Database::getInstance();
    
    try {
        $data = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action_url' => $actionUrl,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $id = $db->insert('notifications', $data);
        
        // Log notification creation
        error_log("Notification created: ID={$id}, User={$userId}, Title={$title}");
        
        return $id;
    } catch (Exception $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Tạo thông báo cho nhiều người dùng
 */
function createBulkNotifications($userIds, $title, $message, $type = 'info', $relatedType = 'repair_request', $relatedId = null, $actionUrl = null) {
    $db = Database::getInstance();
    
    try {
        // Chỉ bắt đầu transaction nếu chưa có transaction nào đang chạy
        $needTransaction = !$db->inTransaction();
        if ($needTransaction) {
            $db->beginTransaction();
        }
        
        $created = 0;
        foreach ($userIds as $userId) {
            $data = [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'action_url' => $actionUrl,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('notifications', $data);
            $created++;
        }
        
        // Chỉ commit nếu chúng ta đã tạo transaction
        if ($needTransaction) {
            $db->commit();
        }
        
        error_log("Bulk notifications created: {$created} notifications");
        return $created;
    } catch (Exception $e) {
        // Chỉ rollback nếu chúng ta đã tạo transaction
        if ($needTransaction && $db->inTransaction()) {
            $db->rollback();
        }
        error_log("Create bulk notifications error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Lấy số lượng thông báo chưa đọc
 */
function getUnreadNotificationCount($userId) {
    $db = Database::getInstance();
    
    try {
        $result = $db->fetch("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ", [$userId]);
        
        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Get notification count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Lấy danh sách thông báo của người dùng
 */
function getUserNotifications($userId, $limit = 20, $offset = 0) {
    $db = Database::getInstance();
    
    try {
        $notifications = $db->fetchAll("
            SELECT *
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ", [$userId, $limit, $offset]);
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Get user notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy thông báo gần đây với thông tin thời gian đã format
 */
function getRecentNotifications($userId, $limit = 5) {
    $db = Database::getInstance();
    
    try {
        $notifications = $db->fetchAll("
            SELECT *
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ", [$userId, $limit]);
        
        // Format time ago for each notification
        foreach ($notifications as &$notification) {
            $notification['time_ago'] = timeAgo($notification['created_at']);
        }
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Get recent notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Đánh dấu thông báo đã đọc
 */
function markNotificationAsRead($notificationId) {
    $db = Database::getInstance();
    
    try {
        $result = $db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$notificationId]
        );
        
        return $result > 0;
    } catch (Exception $e) {
        error_log("Mark notification as read error: " . $e->getMessage());
        return false;
    }
}

/**
 * Đánh dấu tất cả thông báo của user đã đọc
 */
function markAllNotificationsAsRead($userId) {
    $db = Database::getInstance();
    
    try {
        $result = $db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
            'user_id = ? AND is_read = 0', 
            [$userId]
        );
        
        return $result;
    } catch (Exception $e) {
        error_log("Mark all notifications as read error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Xóa thông báo
 */
function deleteNotification($notificationId) {
    $db = Database::getInstance();
    
    try {
        $result = $db->delete('notifications', 'id = ?', [$notificationId]);
        return $result > 0;
    } catch (Exception $e) {
        error_log("Delete notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Tính toán thời gian "... trước" từ timestamp
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Vừa xong';
    if ($time < 3600) return floor($time/60) . ' phút trước';
    if ($time < 86400) return floor($time/3600) . ' giờ trước';
    if ($time < 2592000) return floor($time/86400) . ' ngày trước';
    if ($time < 31536000) return floor($time/2592000) . ' tháng trước';
    return floor($time/31536000) . ' năm trước';
}

// ===================================
// WORKFLOW NOTIFICATION FUNCTIONS
// ===================================

/**
 * Gửi thông báo khi tạo đơn sửa chữa mới
 */
function notifyNewRepairRequest($requestId, $requestCode, $requesterId) {
    $db = Database::getInstance();
    
    try {
        // Lấy thông tin đơn
        $request = $db->fetch("
            SELECT r.*, u.full_name as requester_name, e.name as equipment_name
            FROM repair_requests r
            JOIN users u ON r.requester_id = u.id
            JOIN equipments e ON r.equipment_id = e.id
            WHERE r.id = ?
        ", [$requestId]);
        
        if (!$request) return false;
        
        // Thông báo cho logistics
        $logisticsUsers = $db->fetchAll("
            SELECT id FROM users 
            WHERE role_id = (SELECT id FROM roles WHERE name = 'logistics') 
            AND status = 'active'
        ");
        
        $logisticsUserIds = array_column($logisticsUsers, 'id');
        
        if (!empty($logisticsUserIds)) {
            createBulkNotifications(
                $logisticsUserIds,
                '🆕 Đơn sửa chữa mới',
                "Đơn {$requestCode} - {$request['equipment_name']} cần được nhận đề xuất",
                'info',
                'repair_request',
                $requestId,
                url("logistics/handover.php?id={$requestId}")
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Notify new repair request error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi thông báo khi logistics nhận đề xuất
 */
function notifyLogisticsReceived($requestId, $requestCode, $logisticsUserId) {
    $db = Database::getInstance();
    
    try {
        $request = $db->fetch("
            SELECT r.*, u.full_name as requester_name, e.name as equipment_name
            FROM repair_requests r
            JOIN users u ON r.requester_id = u.id
            JOIN equipments e ON r.equipment_id = e.id
            WHERE r.id = ?
        ", [$requestId]);
        
        if (!$request) return false;
        
        // Thông báo cho người đề xuất
        createNotification(
            $request['requester_id'],
            '✅ Đề xuất đã được nhận',
            "Đơn {$requestCode} - {$request['equipment_name']} đã được giao liên tiếp nhận",
            'success',
            'repair_request',
            $requestId,
            url("repairs/view.php?code={$requestCode}")
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Notify logistics received error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi thông báo khi logistics bàn giao cho văn thư
 */
function notifyLogisticsHandover($requestId, $requestCode, $logisticsUserId) {
    $db = Database::getInstance();
    
    try {
        // Thông báo cho clerk
        $clerkUsers = $db->fetchAll("
            SELECT id FROM users 
            WHERE role_id = (SELECT id FROM roles WHERE name = 'clerk') 
            AND status = 'active'
        ");
        
        $clerkUserIds = array_column($clerkUsers, 'id');
        
        if (!empty($clerkUserIds)) {
            createBulkNotifications(
                $clerkUserIds,
                '📋 Thiết bị đã được bàn giao',
                "Đơn {$requestCode} đã được giao liên bàn giao, cần xử lý",
                'warning',
                'repair_request',
                $requestId,
                url("clerk/send.php?id={$requestId}")
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Notify logistics handover error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi thông báo khi clerk chuyển sửa chữa (CHỈ CHO PHÒNG BAN TRONG WORKFLOW)
 */
function notifyClerkSentToRepair($requestId, $requestCode, $clerkUserId, $departmentIds = []) {
    $db = Database::getInstance();
    
    try {
        // **FIX QUAN TRỌNG**: CHỈ thông báo cho phòng ban được chỉ định trong workflow
        if (!empty($departmentIds)) {
            // Multi-department workflow: chỉ thông báo cho phòng ban được chọn
            $technicianUsers = $db->fetchAll("
                SELECT id FROM users 
                WHERE department_id IN (" . implode(',', array_fill(0, count($departmentIds), '?')) . ")
                AND role_id = (SELECT id FROM roles WHERE name = 'technician') 
                AND status = 'active'
            ", $departmentIds);
        } else {
            // Nếu không có departmentIds, lấy từ workflow steps
            $workflowDepts = $db->fetchAll("
                SELECT DISTINCT assigned_department_id as id
                FROM repair_workflow_steps 
                WHERE request_id = ?
            ", [$requestId]);
            
            if (!empty($workflowDepts)) {
                $deptIds = array_column($workflowDepts, 'id');
                $technicianUsers = $db->fetchAll("
                    SELECT id FROM users 
                    WHERE department_id IN (" . implode(',', array_fill(0, count($deptIds), '?')) . ")
                    AND role_id = (SELECT id FROM roles WHERE name = 'technician') 
                    AND status = 'active'
                ", $deptIds);
                
                // Log để debug
                error_log("NOTIFICATION: Gửi thông báo cho " . count($technicianUsers) . " technician trong " . count($deptIds) . " phòng ban workflow");
            } else {
                // KHÔNG có workflow → KHÔNG gửi thông báo
                error_log("NOTIFICATION: Không có workflow cho request $requestId → không gửi thông báo");
                return true; // Không phải lỗi, chỉ là không có workflow
            }
        }
        
        $technicianUserIds = array_column($technicianUsers, 'id');
        
        if (!empty($technicianUserIds)) {
            createBulkNotifications(
                $technicianUserIds,
                '🔧 Đơn sửa chữa mới',
                "Đơn {$requestCode} đã được giao cho kỹ thuật, cần xử lý",
                'info',
                'repair_request',
                $requestId,
                url("technician/workflow.php")
            );
            
            error_log("NOTIFICATION SUCCESS: Đã gửi thông báo cho " . count($technicianUserIds) . " technician");
        } else {
            error_log("NOTIFICATION WARNING: Không tìm thấy technician nào trong workflow departments");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Notify clerk sent to repair error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi thông báo khi technician hoàn thành sửa chữa
 */
function notifyRepairCompleted($requestId, $requestCode, $technicianUserId) {
    $db = Database::getInstance();
    
    try {
        $request = $db->fetch("
            SELECT r.*, u.full_name as requester_name, e.name as equipment_name
            FROM repair_requests r
            JOIN users u ON r.requester_id = u.id
            JOIN equipments e ON r.equipment_id = e.id
            WHERE r.id = ?
        ", [$requestId]);
        
        if (!$request) return false;
        
        // Thông báo cho clerk
        $clerkUsers = $db->fetchAll("
            SELECT id FROM users 
            WHERE role_id = (SELECT id FROM roles WHERE name = 'clerk') 
            AND status = 'active'
        ");
        
        $clerkUserIds = array_column($clerkUsers, 'id');
        
        if (!empty($clerkUserIds)) {
            createBulkNotifications(
                $clerkUserIds,
                '✅ Sửa chữa hoàn thành',
                "Đơn {$requestCode} - {$request['equipment_name']} đã sửa chữa xong, cần xử lý thu hồi",
                'success',
                'repair_request',
                $requestId,
                url("clerk/retrieve.php?id={$requestId}")
            );
        }
        
        // Thông báo cho người đề xuất
        createNotification(
            $request['requester_id'],
            '🎉 Thiết bị đã sửa xong',
            "Đơn {$requestCode} - {$request['equipment_name']} đã được sửa chữa hoàn thành",
            'success',
            'repair_request',
            $requestId,
            url("repairs/view.php?code={$requestCode}")
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Notify repair completed error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi thông báo khi clerk đã thu hồi thiết bị
 */
function notifyEquipmentRetrieved($requestId, $requestCode, $clerkUserId) {
    $db = Database::getInstance();
    
    try {
        // Thông báo cho logistics
        $logisticsUsers = $db->fetchAll("
            SELECT id FROM users 
            WHERE role_id = (SELECT id FROM roles WHERE name = 'logistics') 
            AND status = 'active'
        ");
        
        $logisticsUserIds = array_column($logisticsUsers, 'id');
        
        if (!empty($logisticsUserIds)) {
            createBulkNotifications(
                $logisticsUserIds,
                '🚚 Thiết bị sẵn sàng trả lại',
                "Đơn {$requestCode} đã thu hồi xong, cần trả lại cho người đề xuất",
                'info',
                'repair_request',
                $requestId,
                url("logistics/return.php?id={$requestId}")
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Notify equipment retrieved error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi thông báo khi hoàn thành toàn bộ quy trình
 */
function notifyProcessCompleted($requestId, $requestCode, $logisticsUserId) {
    $db = Database::getInstance();
    
    try {
        $request = $db->fetch("
            SELECT r.*, u.full_name as requester_name, e.name as equipment_name
            FROM repair_requests r
            JOIN users u ON r.requester_id = u.id
            JOIN equipments e ON r.equipment_id = e.id
            WHERE r.id = ?
        ", [$requestId]);
        
        if (!$request) return false;
        
        // Thông báo cho người đề xuất
        createNotification(
            $request['requester_id'],
            '🎊 Hoàn thành quy trình',
            "Đơn {$requestCode} - {$request['equipment_name']} đã hoàn thành toàn bộ quy trình sửa chữa",
            'success',
            'repair_request',
            $requestId,
            url("repairs/view.php?code={$requestCode}")
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Notify process completed error: " . $e->getMessage());
        return false;
    }
}

?>
