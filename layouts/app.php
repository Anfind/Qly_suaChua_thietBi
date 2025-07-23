<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? e($title) . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-color: #e2e8f0;
            --sidebar-width: 280px;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
            line-height: 1.6;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-brand h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-section {
            margin-bottom: 1.5rem;
        }
        
        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.7;
            font-weight: 600;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            background: none;
        }
        
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
            border-right: 3px solid #fbbf24;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background-color: var(--light-color);
        }
        
        /* Header */
        .main-header {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            text-decoration: none;
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }
        
        .card-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            padding: 0.6rem 1.2rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }
        
        /* Forms */
        .form-control {
            border-radius: 8px;
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-left: 4px solid var(--success-color);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border-left: 4px solid var(--warning-color);
        }
        
        .alert-info {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
            border-left: 4px solid var(--info-color);
        }
        
        /* Tables */
        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .table thead th {
            background: var(--light-color);
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table tbody tr:hover {
            background-color: rgba(37, 99, 235, 0.02);
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .status-in-progress { background: rgba(37, 99, 235, 0.15); color: #2563eb; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .status-cancelled { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        
        /* Stats cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }
        
        .stats-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .main-header {
                padding: 1rem;
            }
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* File upload area */
        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.02);
        }
        
        .upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
    </style>
    
    <?php if (isset($custom_css)): ?>
        <?= $custom_css ?>
    <?php endif; ?>
</head>
<body>
    <?php 
    $user = current_user();
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    ?>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-tools me-2"></i><?= APP_NAME ?></h4>
        </div>
        
        <div class="sidebar-nav">
            <!-- Dashboard -->
            <div class="nav-section">
                <div class="nav-section-title">Tổng quan</div>
                <a href="<?= url('dashboard.php') ?>" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>
                    Dashboard
                </a>
            </div>
            
            <!-- Quản lý đơn sửa chữa -->
            <div class="nav-section">
                <div class="nav-section-title">Sửa chữa thiết bị</div>
                
                <?php if (has_role('requester')): ?>
                <a href="<?= url('repairs/create.php') ?>" class="nav-link <?= $current_page === 'create' ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i>
                    Tạo đề xuất
                </a>
                <a href="<?= url('repairs/index.php') ?>" class="nav-link <?= $current_page === 'index' && strpos($_SERVER['REQUEST_URI'], 'repairs') ? 'active' : '' ?>">
                    <i class="fas fa-list-alt"></i>
                    Đơn của tôi
                </a>
                <?php endif; ?>
                
                <?php if (has_role('logistics')): ?>
                <a href="<?= url('logistics/handover.php') ?>" class="nav-link <?= $current_page === 'handover' ? 'active' : '' ?>">
                    <i class="fas fa-hand-holding"></i>
                    Bàn giao thiết bị
                </a>
                <a href="<?= url('logistics/return.php') ?>" class="nav-link <?= $current_page === 'return' ? 'active' : '' ?>">
                    <i class="fas fa-undo"></i>
                    Trả lại thiết bị
                </a>
                <?php endif; ?>
                
                <?php if (has_role('clerk')): ?>
                <a href="<?= url('clerk/receive.php') ?>" class="nav-link <?= $current_page === 'receive' ? 'active' : '' ?>">
                    <i class="fas fa-inbox"></i>
                    Tiếp nhận
                </a>
                <a href="<?= url('clerk/transfer.php') ?>" class="nav-link <?= $current_page === 'transfer' ? 'active' : '' ?>">
                    <i class="fas fa-shipping-fast"></i>
                    Chuyển sửa chữa
                </a>
                <a href="<?= url('clerk/retrieve.php') ?>" class="nav-link <?= $current_page === 'retrieve' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-check"></i>
                    Thu hồi
                </a>
                <?php endif; ?>
                
                <?php if (has_role('technician')): ?>
                <a href="<?= url('repair/in-progress.php') ?>" class="nav-link <?= $current_page === 'in-progress' ? 'active' : '' ?>">
                    <i class="fas fa-tools"></i>
                    Đang sửa chữa
                </a>
                <a href="<?= url('repair/completed.php') ?>" class="nav-link <?= $current_page === 'completed' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    Đã hoàn thành
                </a>
                <?php endif; ?>
                
                <?php if (has_any_role(['admin', 'clerk', 'logistics'])): ?>
                <a href="<?= url('repairs/index.php') ?>" class="nav-link <?= $current_page === 'index' && strpos($_SERVER['REQUEST_URI'], 'repairs') ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i>
                    Tất cả đơn
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Quản lý hệ thống -->
            <?php if (has_role('admin')): ?>
            <div class="nav-section">
                <div class="nav-section-title">Quản lý hệ thống</div>
                <a href="<?= url('admin/users.php') ?>" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    Người dùng
                </a>
                <a href="<?= url('admin/departments.php') ?>" class="nav-link <?= $current_page === 'departments' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    Đơn vị
                </a>
                <a href="<?= url('admin/equipments.php') ?>" class="nav-link <?= $current_page === 'equipments' ? 'active' : '' ?>">
                    <i class="fas fa-desktop"></i>
                    Thiết bị
                </a>
                <a href="<?= url('admin/repair-contents.php') ?>" class="nav-link <?= $current_page === 'repair-contents' ? 'active' : '' ?>">
                    <i class="fas fa-wrench"></i>
                    Nội dung sửa chữa
                </a>
                <a href="<?= url('admin/settings.php') ?>" class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    Cài đặt
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Báo cáo -->
            <?php if (has_any_role(['admin', 'clerk'])): ?>
            <div class="nav-section">
                <div class="nav-section-title">Báo cáo</div>
                <a href="<?= url('reports/overview.php') ?>" class="nav-link <?= $current_page === 'overview' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i>
                    Tổng quan
                </a>
                <a href="<?= url('reports/equipment.php') ?>" class="nav-link <?= $current_page === 'equipment' && strpos($_SERVER['REQUEST_URI'], 'reports') ? 'active' : '' ?>">
                    <i class="fas fa-laptop"></i>
                    Theo thiết bị
                </a>
                <a href="<?= url('reports/department.php') ?>" class="nav-link <?= $current_page === 'department' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    Theo đơn vị
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="main-header">
            <div class="header-content">
                <div>
                    <button class="btn btn-link d-md-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?= e($title ?? 'Dashboard') ?></h1>
                </div>
                
                <div class="user-menu">
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-link position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-info-circle me-2"></i>Thông báo 1</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle me-2"></i>Thông báo 2</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">Xem tất cả</a></li>
                        </ul>
                    </div>
                    
                    <!-- User dropdown -->
                    <div class="dropdown">
                        <a href="#" class="user-avatar" data-bs-toggle="dropdown">
                            <?php if ($user && $user['avatar']): ?>
                                <img src="<?= upload_url('avatars/' . $user['avatar']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?= $user ? strtoupper(substr($user['full_name'], 0, 1)) : 'U' ?>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">
                                <strong><?= e($user['full_name'] ?? 'Người dùng') ?></strong><br>
                                <small class="text-muted"><?= e($user['role_display_name'] ?? '') ?></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fas fa-user me-2"></i>Hồ sơ cá nhân</a></li>
                            <li><a class="dropdown-item" href="<?= url('change-password.php') ?>"><i class="fas fa-key me-2"></i>Đổi mật khẩu</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url('auth/logout.php') ?>"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content Area -->
        <main class="content-area">
            <!-- Flash Messages -->
            <?php $flash_messages = get_flash_messages(); ?>
            <?php if (!empty($flash_messages)): ?>
                <?php foreach ($flash_messages as $message): ?>
                    <div class="alert alert-<?= $message['type'] === 'error' ? 'danger' : $message['type'] ?> alert-dismissible fade show">
                        <?= e($message['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Breadcrumb -->
            <?php if (isset($breadcrumbs)): ?>
                <?= breadcrumb($breadcrumbs) ?>
            <?php endif; ?>
            
            <!-- Page Content -->
            <?php if (isset($content)): ?>
                <?= $content ?>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Auto hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Confirm delete
        document.addEventListener('click', function(e) {
            if (e.target.matches('.btn-delete, .btn-danger[data-confirm]')) {
                if (!confirm('Bạn có chắc chắn muốn xóa?')) {
                    e.preventDefault();
                }
            }
        });
        
        // File upload preview
        function previewFile(input, previewId) {
            const file = input.files[0];
            const preview = document.getElementById(previewId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">`;
                    } else {
                        preview.innerHTML = `<p class="text-muted"><i class="fas fa-file"></i> ${file.name}</p>`;
                    }
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Loading state for forms
        document.addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner me-2"></span>Đang xử lý...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 3000);
            }
        });
    </script>
    
    <?php if (isset($custom_js)): ?>
        <?= $custom_js ?>
    <?php endif; ?>
</body>
</html>
