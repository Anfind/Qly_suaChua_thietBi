<?php
/**
 * Function helper để đếm tổng số file đính kèm của một đơn
 */

if (!function_exists('count_request_attachments')) {
    function count_request_attachments($request_id) {
        $db = Database::getInstance();
        
        // Lấy thông tin đơn
        $request = $db->fetch(
            "SELECT images, videos FROM repair_requests WHERE id = ?",
            [$request_id]
        );
        
        $count = 0;
        
        // Đếm ảnh và video ban đầu
        if ($request) {
            $images = json_decode($request['images'] ?? '[]', true);
            $videos = json_decode($request['videos'] ?? '[]', true);
            $count += count($images) + count($videos);
        }
        
        // Đếm ảnh từ chi tiết sửa chữa
        $repairDetails = $db->fetchAll(
            "SELECT images FROM repair_details WHERE request_id = ?",
            [$request_id]
        );
        
        foreach ($repairDetails as $detail) {
            $detailImages = json_decode($detail['images'] ?? '[]', true);
            $count += count($detailImages);
        }
        
        // Đếm file từ lịch sử trạng thái
        $statusHistory = $db->fetchAll(
            "SELECT attachments FROM repair_status_history WHERE request_id = ?",
            [$request_id]
        );
        
        foreach ($statusHistory as $history) {
            $attachments = json_decode($history['attachments'] ?? '[]', true);
            $count += count($attachments);
        }
        
        return $count;
    }
}

if (!function_exists('get_request_attachments_summary')) {
    function get_request_attachments_summary($request_id) {
        $db = Database::getInstance();
        
        // Lấy thông tin đơn
        $request = $db->fetch(
            "SELECT images, videos FROM repair_requests WHERE id = ?",
            [$request_id]
        );
        
        $summary = [
            'initial_images' => 0,
            'initial_videos' => 0,
            'repair_images' => 0,
            'status_attachments' => 0,
            'total' => 0
        ];
        
        // Đếm ảnh và video ban đầu
        if ($request) {
            $images = json_decode($request['images'] ?? '[]', true);
            $videos = json_decode($request['videos'] ?? '[]', true);
            $summary['initial_images'] = count($images);
            $summary['initial_videos'] = count($videos);
        }
        
        // Đếm ảnh từ chi tiết sửa chữa
        $repairDetails = $db->fetchAll(
            "SELECT images FROM repair_details WHERE request_id = ?",
            [$request_id]
        );
        
        foreach ($repairDetails as $detail) {
            $detailImages = json_decode($detail['images'] ?? '[]', true);
            $summary['repair_images'] += count($detailImages);
        }
        
        // Đếm file từ lịch sử trạng thái
        $statusHistory = $db->fetchAll(
            "SELECT attachments FROM repair_status_history WHERE request_id = ?",
            [$request_id]
        );
        
        foreach ($statusHistory as $history) {
            $attachments = json_decode($history['attachments'] ?? '[]', true);
            $summary['status_attachments'] += count($attachments);
        }
        
        $summary['total'] = $summary['initial_images'] + $summary['initial_videos'] + 
                           $summary['repair_images'] + $summary['status_attachments'];
        
        return $summary;
    }
}
?>
