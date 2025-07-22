<?php
/**
 * Export Service - Xuất báo cáo Excel, PDF
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExportService {
    
    /**
     * Export danh sách đơn sửa chữa ra Excel
     */
    public function exportRepairRequestsToExcel($requests, $filters = []) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Thiết lập tiêu đề
        $sheet->setTitle('Báo cáo đơn sửa chữa');
        
        // Header thông tin
        $sheet->setCellValue('A1', 'BÁO CÁO DANH SÁCH ĐƠN SỬA CHỮA THIẾT BỊ');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'Ngày xuất: ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Filters info
        $row = 4;
        if (!empty($filters)) {
            $sheet->setCellValue('A3', 'Điều kiện lọc:');
            $sheet->getStyle('A3')->getFont()->setBold(true);
            
            if (!empty($filters['from_date'])) {
                $sheet->setCellValue('A' . $row, 'Từ ngày: ' . format_date($filters['from_date']));
                $row++;
            }
            if (!empty($filters['to_date'])) {
                $sheet->setCellValue('A' . $row, 'Đến ngày: ' . format_date($filters['to_date']));
                $row++;
            }
            if (!empty($filters['department'])) {
                $sheet->setCellValue('A' . $row, 'Đơn vị: ' . $filters['department']);
                $row++;
            }
            if (!empty($filters['status'])) {
                $sheet->setCellValue('A' . $row, 'Trạng thái: ' . $filters['status']);
                $row++;
            }
            $row++;
        }
        
        // Headers
        $headers = [
            'A' => 'STT',
            'B' => 'Mã đơn',
            'C' => 'Thiết bị',
            'D' => 'Người đề xuất',
            'E' => 'Đơn vị',
            'F' => 'Mô tả sự cố',
            'G' => 'Trạng thái',
            'H' => 'Ngày tạo',
            'I' => 'Ngày hoàn thành',
            'J' => 'Chi phí (VNĐ)'
        ];
        
        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . $row, $header);
        }
        
        // Style headers
        $headerRange = 'A' . $row . ':J' . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('E3F2FD');
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN);
        
        // Data
        $dataRow = $row + 1;
        $stt = 1;
        
        foreach ($requests as $request) {
            $sheet->setCellValue('A' . $dataRow, $stt++);
            $sheet->setCellValue('B' . $dataRow, $request['request_code']);
            $sheet->setCellValue('C' . $dataRow, $request['equipment_name']);
            $sheet->setCellValue('D' . $dataRow, $request['requester_name']);
            $sheet->setCellValue('E' . $dataRow, $request['department_name']);
            $sheet->setCellValue('F' . $dataRow, $request['problem_description']);
            $sheet->setCellValue('G' . $dataRow, $request['status_name']);
            $sheet->setCellValue('H' . $dataRow, format_date($request['created_at']));
            $sheet->setCellValue('I' . $dataRow, $request['actual_completion'] ? format_date($request['actual_completion']) : '');
            $sheet->setCellValue('J' . $dataRow, number_format($request['total_cost'], 0, ',', '.'));
            
            $dataRow++;
        }
        
        // Style data
        $dataRange = 'A' . ($row + 1) . ':J' . ($dataRow - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN);
        
        // Auto width
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Summary
        if (count($requests) > 0) {
            $summaryRow = $dataRow + 1;
            $sheet->setCellValue('A' . $summaryRow, 'TỔNG KẾT:');
            $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true);
            
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Tổng số đơn: ' . count($requests));
            
            $completedCount = count(array_filter($requests, function($r) { return $r['status_code'] === 'COMPLETED'; }));
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Đã hoàn thành: ' . $completedCount);
            
            $totalCost = array_sum(array_column($requests, 'total_cost'));
            $summaryRow++;
            $sheet->setCellValue('A' . $summaryRow, 'Tổng chi phí: ' . number_format($totalCost, 0, ',', '.') . ' VNĐ');
        }
        
        // Save
        $filename = 'bao_cao_sua_chua_' . date('Y_m_d_H_i_s') . '.xlsx';
        $filepath = UPLOAD_PATH . 'exports/' . $filename;
        
        // Tạo thư mục export nếu chưa có
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => upload_url('exports/' . $filename)
        ];
    }
    
    /**
     * Export báo cáo tổng quan ra PDF
     */
    public function exportOverviewToPdf($data) {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        $html = $this->generateOverviewPdfHtml($data);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'bao_cao_tong_quan_' . date('Y_m_d_H_i_s') . '.pdf';
        $filepath = UPLOAD_PATH . 'exports/' . $filename;
        
        // Tạo thư mục export nếu chưa có
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, $dompdf->output());
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => upload_url('exports/' . $filename)
        ];
    }
    
    /**
     * Generate HTML cho PDF báo cáo tổng quan
     */
    private function generateOverviewPdfHtml($data) {
        $html = "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <title>Báo cáo tổng quan</title>
            <style>
                body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; line-height: 1.4; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
                .header h1 { color: #007bff; margin: 0; font-size: 24px; }
                .header p { margin: 5px 0; color: #666; }
                .stats-grid { display: table; width: 100%; margin: 20px 0; }
                .stats-item { display: table-cell; width: 25%; text-align: center; padding: 20px; background: #f8f9fa; margin: 5px; border-radius: 5px; }
                .stats-number { font-size: 24px; font-weight: bold; color: #007bff; }
                .stats-label { color: #666; font-size: 11px; }
                .section { margin: 30px 0; }
                .section h3 { background: #007bff; color: white; padding: 10px; margin: 0 0 15px 0; font-size: 16px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f8f9fa; font-weight: bold; }
                .status-badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
                .chart-placeholder { height: 200px; background: #f8f9fa; border: 1px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #666; }
                .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #eee; padding-top: 20px; }
                .page-break { page-break-before: always; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>BÁO CÁO TỔNG QUAN HỆ THỐNG</h1>
                <p>" . APP_NAME . "</p>
                <p>Thời gian xuất: " . date('d/m/Y H:i:s') . "</p>
                " . (isset($data['date_range']) ? "<p>Khoảng thời gian: {$data['date_range']}</p>" : "") . "
            </div>
            
            <div class='stats-grid'>
                <div class='stats-item'>
                    <div class='stats-number'>" . ($data['total_requests'] ?? 0) . "</div>
                    <div class='stats-label'>Tổng đơn</div>
                </div>
                <div class='stats-item'>
                    <div class='stats-number'>" . ($data['completed_requests'] ?? 0) . "</div>
                    <div class='stats-label'>Hoàn thành</div>
                </div>
                <div class='stats-item'>
                    <div class='stats-number'>" . ($data['pending_requests'] ?? 0) . "</div>
                    <div class='stats-label'>Đang xử lý</div>
                </div>
                <div class='stats-item'>
                    <div class='stats-number'>" . number_format($data['total_cost'] ?? 0, 0, ',', '.') . "</div>
                    <div class='stats-label'>Tổng chi phí (VNĐ)</div>
                </div>
            </div>";
        
        // Thống kê theo trạng thái
        if (!empty($data['status_stats'])) {
            $html .= "
            <div class='section'>
                <h3>Thống kê theo trạng thái</h3>
                <table>
                    <thead>
                        <tr><th>Trạng thái</th><th>Số lượng</th><th>Tỷ lệ (%)</th></tr>
                    </thead>
                    <tbody>";
            
            foreach ($data['status_stats'] as $stat) {
                $percentage = $data['total_requests'] > 0 ? round(($stat['count'] / $data['total_requests']) * 100, 1) : 0;
                $html .= "<tr>
                    <td>{$stat['status_name']}</td>
                    <td>{$stat['count']}</td>
                    <td>{$percentage}%</td>
                </tr>";
            }
            
            $html .= "</tbody></table></div>";
        }
        
        // Thống kê theo đơn vị
        if (!empty($data['department_stats'])) {
            $html .= "
            <div class='section'>
                <h3>Thống kê theo đơn vị</h3>
                <table>
                    <thead>
                        <tr><th>Đơn vị</th><th>Số đơn</th><th>Hoàn thành</th><th>Tỷ lệ hoàn thành (%)</th></tr>
                    </thead>
                    <tbody>";
            
            foreach ($data['department_stats'] as $stat) {
                $completionRate = $stat['total'] > 0 ? round(($stat['completed'] / $stat['total']) * 100, 1) : 0;
                $html .= "<tr>
                    <td>{$stat['department_name']}</td>
                    <td>{$stat['total']}</td>
                    <td>{$stat['completed']}</td>
                    <td>{$completionRate}%</td>
                </tr>";
            }
            
            $html .= "</tbody></table></div>";
        }
        
        // Top thiết bị có sự cố
        if (!empty($data['top_equipments'])) {
            $html .= "
            <div class='section'>
                <h3>Top thiết bị có nhiều sự cố nhất</h3>
                <table>
                    <thead>
                        <tr><th>STT</th><th>Mã thiết bị</th><th>Tên thiết bị</th><th>Số lần sửa chữa</th></tr>
                    </thead>
                    <tbody>";
            
            $stt = 1;
            foreach ($data['top_equipments'] as $equipment) {
                $html .= "<tr>
                    <td>{$stt}</td>
                    <td>{$equipment['equipment_code']}</td>
                    <td>{$equipment['equipment_name']}</td>
                    <td>{$equipment['repair_count']}</td>
                </tr>";
                $stt++;
            }
            
            $html .= "</tbody></table></div>";
        }
        
        $html .= "
            <div class='footer'>
                <p>Báo cáo được tạo tự động bởi " . APP_NAME . "</p>
                <p>© " . date('Y') . " - Tất cả quyền được bảo lưu</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Export lịch sử thiết bị ra PDF
     */
    public function exportEquipmentHistoryToPdf($equipmentId) {
        $db = Database::getInstance();
        
        // Lấy thông tin thiết bị
        $equipment = $db->fetch(
            "SELECT e.*, t.name as type_name, d.name as department_name
             FROM equipments e
             LEFT JOIN equipment_types t ON e.type_id = t.id
             LEFT JOIN departments d ON e.department_id = d.id
             WHERE e.id = ?",
            [$equipmentId]
        );
        
        if (!$equipment) {
            throw new Exception('Không tìm thấy thiết bị');
        }
        
        // Lấy lịch sử sửa chữa
        $history = $db->fetchAll(
            "SELECT r.*, s.name as status_name, u.full_name as requester_name
             FROM repair_requests r
             LEFT JOIN repair_statuses s ON r.current_status_id = s.id
             LEFT JOIN users u ON r.requester_id = u.id
             WHERE r.equipment_id = ?
             ORDER BY r.created_at DESC",
            [$equipmentId]
        );
        
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        
        $html = "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <title>Lịch sử thiết bị</title>
            <style>
                body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; line-height: 1.4; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
                .header h1 { color: #007bff; margin: 0; font-size: 20px; }
                .equipment-info { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .equipment-info h3 { margin-top: 0; color: #007bff; }
                .info-grid { display: table; width: 100%; }
                .info-row { display: table-row; }
                .info-label { display: table-cell; width: 30%; font-weight: bold; padding: 5px 0; }
                .info-value { display: table-cell; padding: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f8f9fa; font-weight: bold; }
                .status-badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>LỊCH SỬ SỬA CHỮA THIẾT BỊ</h1>
                <p>Thời gian xuất: " . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <div class='equipment-info'>
                <h3>Thông tin thiết bị</h3>
                <div class='info-grid'>
                    <div class='info-row'>
                        <div class='info-label'>Mã thiết bị:</div>
                        <div class='info-value'>{$equipment['code']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Tên thiết bị:</div>
                        <div class='info-value'>{$equipment['name']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Loại:</div>
                        <div class='info-value'>{$equipment['type_name']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Đơn vị sử dụng:</div>
                        <div class='info-value'>{$equipment['department_name']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Vị trí:</div>
                        <div class='info-value'>{$equipment['location']}</div>
                    </div>
                </div>
            </div>
            
            <h3>Lịch sử sửa chữa</h3>";
        
        if (empty($history)) {
            $html .= "<p>Thiết bị chưa có lịch sử sửa chữa nào.</p>";
        } else {
            $html .= "
            <table>
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã đơn</th>
                        <th>Người đề xuất</th>
                        <th>Mô tả sự cố</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Chi phí</th>
                    </tr>
                </thead>
                <tbody>";
            
            $stt = 1;
            foreach ($history as $item) {
                $html .= "<tr>
                    <td>{$stt}</td>
                    <td>{$item['request_code']}</td>
                    <td>{$item['requester_name']}</td>
                    <td>" . substr($item['problem_description'], 0, 100) . "...</td>
                    <td>{$item['status_name']}</td>
                    <td>" . format_date($item['created_at']) . "</td>
                    <td>" . number_format($item['total_cost'], 0, ',', '.') . " VNĐ</td>
                </tr>";
                $stt++;
            }
            
            $html .= "</tbody></table>";
        }
        
        $html .= "</body></html>";
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'lich_su_thiet_bi_' . $equipment['code'] . '_' . date('Y_m_d_H_i_s') . '.pdf';
        $filepath = UPLOAD_PATH . 'exports/' . $filename;
        
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, $dompdf->output());
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => upload_url('exports/' . $filename)
        ];
    }
    
    /**
     * Download file export
     */
    public function downloadFile($filename) {
        $filepath = UPLOAD_PATH . 'exports/' . $filename;
        
        if (!file_exists($filepath)) {
            throw new Exception('File không tồn tại');
        }
        
        $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf'
        ];
        
        header('Content-Type: ' . ($contentType[$fileExtension] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        readfile($filepath);
        exit;
    }
    
    /**
     * Cleanup old export files
     */
    public function cleanupOldExports($daysOld = 7) {
        $exportDir = UPLOAD_PATH . 'exports/';
        if (!is_dir($exportDir)) return 0;
        
        $deletedCount = 0;
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        
        $files = glob($exportDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }
}
?>
