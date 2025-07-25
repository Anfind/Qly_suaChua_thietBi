# THAY ĐỔI DATABASE VÀ TÍNH NĂNG MỚI

## 📝 Tóm tắt thay đổi so với database.sql ban đầu:

### ✅ **CÁC BẢNG MỚI ĐƯỢC THÊM:**

1. **`repair_contents`** - Quản lý template nội dung sửa chữa
   - Lưu trữ các loại sửa chữa phổ biến và chi phí ước tính
   - Phân loại theo: hardware, software, network, maintenance

2. **`system_settings`** - Cài đặt hệ thống
   - Lưu trữ các config: kích thước file, timeout, email notifications
   - Có thể chỉnh sửa từ trang admin/settings.php

3. **`activity_logs`** - Log hoạt động người dùng
   - Ghi lại các hành động: login, create, update, delete
   - Bao gồm IP address và user agent

### 🆕 **TÍNH NĂNG MỚI:**

1. **Xem File Đính Kèm (Tất cả role)**
   - Trang: `repairs/attachments.php?code=MÃ_ĐƠN`
   - Xem tất cả ảnh/video từ: đơn ban đầu, quá trình sửa chữa, cập nhật trạng thái
   - Lightbox xem ảnh, download file
   - Helper functions: `count_request_attachments()`, `get_request_attachments_summary()`

2. **Module Kỹ Thuật Viên Hoàn Chỉnh**
   - `technician/index.php` - Dashboard với thống kê
   - `technician/start-repair.php` - Bắt đầu sửa chữa
   - `technician/update-progress.php` - Cập nhật tiến độ với hình ảnh
   - `technician/complete-repair.php` - Hoàn thành sửa chữa
   - `repair/in-progress.php` - Xem đơn đang sửa
   - `repair/completed.php` - Xem đơn đã hoàn thành

3. **Widgets Mới**
   - `render_recent_attachments_widget()` - Đơn có file gần đây
   - `render_attachment_stats_widget()` - Thống kê file đính kèm

### 📁 **CÁC FILE CẬP NHẬT:**

1. **`repairs/view.php`** - Thêm nút "File đính kèm" với số lượng
2. **`utils/attachment_helpers.php`** - Helper functions cho file đính kèm
3. **`widgets/attachment_widgets.php`** - Widget components

## 🗄️ **HƯỚNG DẪN SỬ DỤNG KHI CHUYỂN MÁY:**

### Option 1: Sử dụng file database_updated.sql (KHUYẾN NGHỊ)
```sql
-- Chạy file này thay vì database.sql
mysql -u root -p < database_updated.sql
```

### Option 2: Nếu đã có database cũ, chạy thêm:
```sql
-- Chạy lần lượt các file bổ sung
mysql -u root -p equipment_repair_management < create_activity_logs.sql
mysql -u root -p equipment_repair_management < create_missing_tables.sql
```

### Option 3: Sử dụng setup script
```php
php setup_database.php
```

## 🎯 **DEMO USERS (Không thay đổi):**
- **admin** / admin123 (Quản trị viên)
- **user1** / user123 (Người đề xuất)
- **logistics1** / user123 (Giao liên) 
- **clerk1** / user123 (Văn thư)
- **tech1** / user123 (Kỹ thuật viên)

## 🔗 **TÍNH NĂNG XEM FILE ĐÍNH KÈM:**

### Ai có thể xem file:
- **Admin**: Tất cả file của mọi đơn
- **Requester**: File của đơn mình tạo
- **Technician**: File của đơn được giao
- **Clerk**: File của đơn trong workflow văn thư
- **Logistics**: File của đơn trong workflow giao liên

### Các loại file hiển thị:
- Ảnh và video ban đầu (khi tạo đơn)
- Ảnh từ quá trình sửa chữa (do kỹ thuật viên chụp)
- File đính kèm từ cập nhật trạng thái

### Cách truy cập:
1. Từ trang chi tiết đơn: Nút "File đính kèm (số lượng)"
2. Direct URL: `repairs/attachments.php?code=REQ001`
3. Dropdown menu trong trang chi tiết đơn

## ⚠️ **LƯU Ý QUAN TRỌNG:**

1. **File database_updated.sql** chứa tất cả thay đổi, khuyến nghị sử dụng thay vì database.sql cũ
2. Tất cả **helper functions và widgets** đã được tích hợp vào hệ thống
3. **Không có breaking changes** - tất cả tính năng cũ vẫn hoạt động bình thường
4. **Debug scripts** có thể xóa sau khi hoàn tất: `test_attachments.php`, `debug_*.php`

## 📊 **TEST TÍNH NĂNG:**

Sau khi setup database, có thể test:
1. Tạo đơn sửa chữa với hình ảnh
2. Truy cập `test_attachments.php` để kiểm tra tính năng
3. Login với các role khác nhau để test quyền truy cập

**Ngày cập nhật:** <?= date('Y-m-d H:i:s') ?>
