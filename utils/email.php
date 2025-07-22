<?php
/**
 * Email notification system - Hoàn thiện
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Lớp quản lý email
 */
class EmailService {
    private $mailer;
    private $isConfigured;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->isConfigured = $this->configure();
    }
    
    /**
     * Cấu hình SMTP
     */
    private function configure() {
        if (!defined('SMTP_HOST') || !SMTP_HOST) {
            return false;
        }
        
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom(FROM_EMAIL, FROM_NAME);
            
            return true;
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gửi email cơ bản
     */
    public function sendEmail($to, $subject, $body, $isHtml = true, $attachments = []) {
        if (!$this->isConfigured) {
            return false;
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Recipients
            if (is_array($to)) {
                foreach ($to as $email) {
                    $this->mailer->addAddress($email);
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Content
            $this->mailer->isHTML($isHtml);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            // Attachments
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mailer->addAttachment($attachment);
                }
            }
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tạo email template
     */
    public function createEmailTemplate($title, $content, $footerText = null) {
        $footerText = $footerText ?: 'Email này được gửi tự động từ ' . APP_NAME;
        
        return "
        <!DOCTYPE html>
        <html lang='vi'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 30px 20px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; }
                .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: 500; margin: 10px 0; }
                .btn:hover { background: #0056b3; }
                .alert { padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; background: #f7f7f7; }
                .alert.success { border-left-color: #28a745; background: #d4edda; }
                .alert.warning { border-left-color: #ffc107; background: #fff3cd; }
                .alert.danger { border-left-color: #dc3545; background: #f8d7da; }
                .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                .table th { background: #f8f9fa; font-weight: 600; }
                .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
                .status-pending { background: #fff3cd; color: #856404; }
                .status-progress { background: #cce5ff; color: #004085; }
                .status-completed { background: #d4edda; color: #155724; }
                .status-cancelled { background: #f8d7da; color: #721c24; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$title}</h1>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>{$footerText}</p>
                    <p>© " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}

// Instance toàn cục
$emailService = new EmailService();

/**
 * Send email notification (legacy function)
 */
function send_email_notification($to, $subject, $message, $isHtml = true) {
    global $emailService;
    return $emailService->sendEmail($to, $subject, $message, $isHtml);
}

/**
 * Send status change notification
 */
function notify_status_change($requestId, $newStatus, $userId) {
    $db = Database::getInstance();
    
    $request = $db->fetch(
        "SELECT r.*, e.name as equipment_name, u.full_name as requester_name, u.email as requester_email,
                s.name as status_name, s.color as status_color
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE r.id = ?",
        [$requestId]
    );
    
    if (!$request || !$request['requester_email']) {
        return false;
    }
    
    $statusMessages = [
        'HANDED_TO_CLERK' => 'Đơn sửa chữa đã được bàn giao cho văn thư',
        'SENT_TO_REPAIR' => 'Thiết bị đã được chuyển đến đơn vị sửa chữa',
        'IN_PROGRESS' => 'Đơn vị sửa chữa đã bắt đầu xử lý',
        'REPAIR_COMPLETED' => 'Việc sửa chữa đã hoàn thành',
        'RETRIEVED' => 'Thiết bị đã được thu hồi từ đơn vị sửa chữa',
        'COMPLETED' => 'Đơn sửa chữa đã hoàn tất. Vui lòng nhận lại thiết bị',
        'CANCELLED' => 'Đơn sửa chữa đã bị hủy bỏ'
    ];
    
    $message = $statusMessages[$newStatus] ?? 'Trạng thái đơn đã được cập nhật';
    
    $subject = "[{$request['request_code']}] Cập nhật trạng thái - {$request['status_name']}";
    
    $emailBody = "
    <h3>Thông báo cập nhật trạng thái đơn sửa chữa</h3>
    <p><strong>Mã đơn:</strong> {$request['request_code']}</p>
    <p><strong>Thiết bị:</strong> {$request['equipment_name']}</p>
    <p><strong>Trạng thái mới:</strong> <span style='color: {$request['status_color']}'>{$request['status_name']}</span></p>
    <p><strong>Thông báo:</strong> {$message}</p>
    <hr>
    <p>Vui lòng truy cập hệ thống để xem chi tiết: <a href='" . url("repairs/view.php?code={$request['request_code']}") . "'>Xem đơn</a></p>
    <p><small>Email này được gửi tự động từ hệ thống quản lý sửa chữa thiết bị.</small></p>
    ";
    
    return send_email_notification($request['requester_email'], $subject, $emailBody);
}

/**
 * Send daily summary report
 */
function send_daily_summary($email, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $db = Database::getInstance();
    
    $stats = [
        'new_requests' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests WHERE DATE(created_at) = ?", [$date])['count'],
        'completed' => $db->fetch("SELECT COUNT(*) as count FROM repair_status_history WHERE DATE(created_at) = ? AND status_id = (SELECT id FROM repair_statuses WHERE code = 'COMPLETED')", [$date])['count'],
        'in_progress' => $db->fetch("SELECT COUNT(*) as count FROM repair_requests r JOIN repair_statuses s ON r.current_status_id = s.id WHERE s.code IN ('IN_PROGRESS', 'SENT_TO_REPAIR')")['count']
    ];
    
    $subject = "Báo cáo hàng ngày - " . format_date($date);
    
    $emailBody = "
    <h3>Báo cáo hoạt động ngày " . format_date($date) . "</h3>
    <ul>
        <li>Đơn mới tạo: <strong>{$stats['new_requests']}</strong></li>
        <li>Đơn hoàn thành: <strong>{$stats['completed']}</strong></li>
        <li>Đơn đang xử lý: <strong>{$stats['in_progress']}</strong></li>
    </ul>
    <p><a href='" . url('reports/overview.php') . "'>Xem báo cáo chi tiết</a></p>
    ";
    
    return send_email_notification($email, $subject, $emailBody);
}

/**
 * Send overdue notification
 */
function send_overdue_notifications() {
    $db = Database::getInstance();
    
    $overdueRequests = $db->fetchAll(
        "SELECT r.*, e.name as equipment_name, u.email as requester_email,
                DATEDIFF(NOW(), r.created_at) as days_old
         FROM repair_requests r
         LEFT JOIN equipments e ON r.equipment_id = e.id
         LEFT JOIN users u ON r.requester_id = u.id
         LEFT JOIN repair_statuses s ON r.current_status_id = s.id
         WHERE s.code NOT IN ('COMPLETED', 'CANCELLED')
         AND DATEDIFF(NOW(), r.created_at) > 7"
    );
    
    foreach ($overdueRequests as $request) {
        if ($request['requester_email']) {
            $subject = "[{$request['request_code']}] Nhắc nhở - Đơn đã quá hạn";
            $message = "
            <h3>Thông báo đơn sửa chữa quá hạn</h3>
            <p>Đơn sửa chữa <strong>{$request['request_code']}</strong> đã tồn tại {$request['days_old']} ngày mà chưa hoàn thành.</p>
            <p>Thiết bị: {$request['equipment_name']}</p>
            <p>Vui lòng liên hệ bộ phận liên quan để xử lý.</p>
            ";
            
            send_email_notification($request['requester_email'], $subject, $message);
        }
    }
}
?>
