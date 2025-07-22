# Thiết lập cron jobs cho hệ thống

## 1. Cron job gửi email hàng ngày
Chạy vào 8:00 sáng mỗi ngày:
```bash
0 8 * * * php /path/to/Qly_suaChua_thietBi/cron/daily_email.php
```

## 2. Cron job backup database (tuần)
Chạy vào 2:00 sáng chủ nhật:
```bash
0 2 * * 0 php /path/to/Qly_suaChua_thietBi/cron/backup_database.php
```

## 3. Cron job dọn dẹp files (tháng)
Chạy vào 3:00 sáng ngày 1 hàng tháng:
```bash
0 3 1 * * php /path/to/Qly_suaChua_thietBi/cron/cleanup_files.php
```

## Để thiết lập cron jobs trên Windows:

### Sử dụng Task Scheduler:

1. Mở Task Scheduler
2. Tạo Basic Task
3. Tên: "Equipment Repair Daily Email"
4. Trigger: Daily, 8:00 AM
5. Action: Start a program
6. Program: C:\path\to\php.exe
7. Arguments: C:\path\to\Qly_suaChua_thietBi\cron\daily_email.php

### Hoặc sử dụng batch file:

Tạo file `run_cron.bat`:
```batch
@echo off
cd /d "C:\path\to\Qly_suaChua_thietBi"
php cron\daily_email.php
```

Sau đó schedule batch file này trong Task Scheduler.

## Log files

Logs sẽ được lưu trong:
- PHP error log (nếu có lỗi)
- Custom log files trong thư mục `logs/` (nếu có)

## Kiểm tra cron

Để test cron job:
```bash
php cron/daily_email.php
```
