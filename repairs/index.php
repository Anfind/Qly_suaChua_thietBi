<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$controller = new RepairController();
$data = $controller->index();

$title = 'Quản lý đơn sửa chữa';
$user = current_user();
$role = $user['role_name'];

// Breadcrumbs
$breadcrumbs = [
    ['title' => 'Trang chủ', 'url' => url('dashboard.php')],
    ['title' => 'Đơn sửa chữa', 'url' => '']
];

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="page-title">
            <i class="fas fa-tools me-2"></i>
            <?php if ($role === 'requester'): ?>
                Đơn sửa chữa của tôi
            <?php elseif ($role === 'logistics'): ?>
                Công việc giao liên
            <?php elseif ($role === 'clerk'): ?>
                Công việc văn thư
            <?php elseif ($role === 'technician'): ?>
                Công việc sửa chữa
            <?php else: ?>
                Tất cả đơn sửa chữa
            <?php endif; ?>
        </h2>
    </div>
    <div class="col-md-4 text-end">
        <?php if ($role === 'requester'): ?>
            <a href="<?= url('repairs/create.php') ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tạo đơn mới
            </a>
        <?php endif; ?>
        
        <?php if ($role === 'admin'): ?>
            <div class="btn-group">
                <a href="<?= url('repairs/create.php') ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Tạo đơn mới
                </a>
                <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= url('repairs/export.php?format=excel') ?>">
                        <i class="fas fa-file-excel me-2"></i>Xuất Excel
                    </a></li>
                    <li><a class="dropdown-item" href="<?= url('repairs/export.php?format=pdf') ?>">
                        <i class="fas fa-file-pdf me-2"></i>Xuất PDF
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= url('repairs/stats.php') ?>">
                        <i class="fas fa-chart-bar me-2"></i>Thống kê
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick stats row -->
<div class="row mb-4">
    <?php if ($role === 'requester' && isset($data['requests'])): ?>
        <?php
        $myStats = [
            'total' => count($data['requests']),
            'pending' => count(array_filter($data['requests'], fn($r) => in_array($r['status_code'], ['PENDING_HANDOVER', 'HANDED_TO_CLERK']))),
            'in_progress' => count(array_filter($data['requests'], fn($r) => in_array($r['status_code'], ['SENT_TO_REPAIR', 'IN_PROGRESS', 'REPAIR_COMPLETED', 'RETRIEVED']))),
            'completed' => count(array_filter($data['requests'], fn($r) => $r['status_code'] === 'COMPLETED'))
        ];
        ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card bg-primary text-white">
                <div class="stats-number"><?= $myStats['total'] ?></div>
                <div class="stats-label">Tổng đơn</div>
                <div class="stats-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card bg-warning text-white">
                <div class="stats-number"><?= $myStats['pending'] ?></div>
                <div class="stats-label">Chờ xử lý</div>
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card bg-info text-white">
                <div class="stats-number"><?= $myStats['in_progress'] ?></div>
                <div class="stats-label">Đang xử lý</div>
                <div class="stats-icon"><i class="fas fa-cogs"></i></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card bg-success text-white">
                <div class="stats-number"><?= $myStats['completed'] ?></div>
                <div class="stats-label">Hoàn thành</div>
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Content based on role -->
<?php if ($role === 'requester'): ?>
    <!-- Requester view -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">Danh sách đơn sửa chữa</h5>
                </div>
                <div class="col-auto">
                    <!-- Filters -->
                    <form method="GET" class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="PENDING_HANDOVER" <?= ($_GET['status'] ?? '') === 'PENDING_HANDOVER' ? 'selected' : '' ?>><?= format_status_display('Chờ bàn giao') ?></option>
                            <option value="LOGISTICS_RECEIVED" <?= ($_GET['status'] ?? '') === 'LOGISTICS_RECEIVED' ? 'selected' : '' ?>><?= format_status_display('Giao liên đã nhận đề xuất') ?></option>
                            <option value="LOGISTICS_HANDOVER" <?= ($_GET['status'] ?? '') === 'LOGISTICS_HANDOVER' ? 'selected' : '' ?>><?= format_status_display('Giao liên đã bàn giao cho văn thư') ?></option>
                            <option value="HANDED_TO_CLERK" <?= ($_GET['status'] ?? '') === 'HANDED_TO_CLERK' ? 'selected' : '' ?>><?= format_status_display('Đã đến văn thư – chờ xử lý') ?></option>
                            <option value="SENT_TO_REPAIR" <?= ($_GET['status'] ?? '') === 'SENT_TO_REPAIR' ? 'selected' : '' ?>>Đã chuyển đơn vị sửa chữa</option>
                            <option value="IN_PROGRESS" <?= ($_GET['status'] ?? '') === 'IN_PROGRESS' ? 'selected' : '' ?>>Đang sửa chữa</option>
                            <option value="REPAIR_COMPLETED" <?= ($_GET['status'] ?? '') === 'REPAIR_COMPLETED' ? 'selected' : '' ?>>Đã sửa xong</option>
                            <option value="RETRIEVED" <?= ($_GET['status'] ?? '') === 'RETRIEVED' ? 'selected' : '' ?>>Đã thu hồi</option>
                            <option value="COMPLETED" <?= ($_GET['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>Hoàn tất</option>
                        </select>
                        
                        <select name="equipment_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả thiết bị</option>
                            <?php foreach ($data['equipments'] as $equipment): ?>
                                <option value="<?= $equipment['id'] ?>" <?= ($_GET['equipment_id'] ?? '') == $equipment['id'] ? 'selected' : '' ?>>
                                    <?= e($equipment['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($data['requests'])): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Chưa có đơn sửa chữa nào</h5>
                    <p class="text-muted">Bắt đầu bằng cách tạo đơn sửa chữa đầu tiên</p>
                    <a href="<?= url('repairs/create.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tạo đơn mới
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã đơn</th>
                                <th>Thiết bị</th>
                                <th>Mô tả lỗi</th>
                                <th>Mức độ</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['requests'] as $request): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($request['request_code']) ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= e($request['equipment_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= e($request['equipment_code']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span title="<?= e($request['problem_description']) ?>">
                                            <?= e(truncate($request['problem_description'], 60)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $urgencyClass = [
                                            'low' => 'success',
                                            'medium' => 'warning', 
                                            'high' => 'danger',
                                            'critical' => 'dark'
                                        ][$request['urgency_level']] ?? 'secondary';
                                        
                                        $urgencyText = [
                                            'low' => 'Thấp',
                                            'medium' => 'Trung bình',
                                            'high' => 'Cao',
                                            'critical' => 'Khẩn cấp'
                                        ][$request['urgency_level']] ?? 'Không xác định';
                                        ?>
                                        <span class="badge bg-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?= e($request['status_color']) ?>15; color: <?= e($request['status_color']) ?>;">
                                            <i class="<?= e($request['status_icon']) ?> me-1"></i>
                                            <?= format_status_display(e($request['status_name'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span title="<?= format_datetime($request['created_at']) ?>">
                                            <?= time_ago($request['created_at']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($role === 'logistics'): ?>
    <!-- Logistics view -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Chờ bàn giao (<?= count($data['pendingHandover'] ?? []) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['pendingHandover'] ?? [])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Không có đơn nào chờ bàn giao</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (($data['pendingHandover'] ?? []) as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                            <small class="text-muted"><?= e($request['equipment_name']) ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= e($request['requester_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block"><?= time_ago($request['created_at']) ?></small>
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-sm btn-warning mt-1">
                                                <i class="fas fa-hand-holding me-1"></i>Bàn giao
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-purple text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-undo me-2"></i>
                        Đã thu hồi - Chờ trả lại (<?= count($data['readyForReturn'] ?? []) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['readyForReturn'] ?? [])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Không có đơn nào chờ trả lại</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (($data['readyForReturn'] ?? []) as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                            <small class="text-muted"><?= e($request['equipment_name']) ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= e($request['requester_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block"><?= time_ago($request['created_at']) ?></small>
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-sm btn-success mt-1">
                                                <i class="fas fa-check me-1"></i>Hoàn thành
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($role === 'clerk'): ?>
    <!-- Clerk view -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-hand-holding me-2"></i>
                        <?= format_status_display('Đã bàn giao') ?> - Chờ chuyển (<?= count($data['handed']) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['handed'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Không có đơn nào chờ chuyển</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($data['handed'] as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                            <small class="text-muted"><?= e($request['equipment_name']) ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= e($request['requester_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block"><?= time_ago($request['created_at']) ?></small>
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-sm btn-info mt-1">
                                                <i class="fas fa-shipping-fast me-1"></i>Chuyển
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Đã sửa xong - Chờ thu hồi (<?= count($data['completed']) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['completed'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Không có đơn nào chờ thu hồi</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($data['completed'] as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                            <small class="text-muted"><?= e($request['equipment_name']) ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= e($request['requester_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block"><?= time_ago($request['created_at']) ?></small>
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-sm btn-warning mt-1">
                                                <i class="fas fa-undo me-1"></i>Thu hồi
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($role === 'technician'): ?>
    <!-- Technician view -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-orange text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shipping-fast me-2"></i>
                        Mới nhận - Chờ sửa chữa (<?= count($data['sent']) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['sent'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Không có đơn nào chờ sửa chữa</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($data['sent'] as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                            <small class="text-muted"><?= e($request['equipment_name']) ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= e($request['requester_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block"><?= time_ago($request['created_at']) ?></small>
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-sm btn-primary mt-1">
                                                <i class="fas fa-tools me-1"></i>Bắt đầu
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Đang sửa chữa (<?= count($data['inProgress']) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['inProgress'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Không có đơn nào đang sửa chữa</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($data['inProgress'] as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                            <small class="text-muted"><?= e($request['equipment_name']) ?></small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?= e($request['requester_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block"><?= time_ago($request['created_at']) ?></small>
                                            <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                               class="btn btn-sm btn-success mt-1">
                                                <i class="fas fa-check me-1"></i>Hoàn thành
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Admin view -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">Tất cả đơn sửa chữa</h5>
                </div>
                <div class="col-auto">
                    <!-- Advanced filters for admin -->
                    <form method="GET" class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="PENDING_HANDOVER" <?= ($_GET['status'] ?? '') === 'PENDING_HANDOVER' ? 'selected' : '' ?>><?= format_status_display('Chờ bàn giao') ?></option>
                            <option value="HANDED_TO_CLERK" <?= ($_GET['status'] ?? '') === 'HANDED_TO_CLERK' ? 'selected' : '' ?>><?= format_status_display('Đã bàn giao cho văn thư') ?></option>
                            <option value="SENT_TO_REPAIR" <?= ($_GET['status'] ?? '') === 'SENT_TO_REPAIR' ? 'selected' : '' ?>>Đã chuyển đơn vị sửa chữa</option>
                            <option value="IN_PROGRESS" <?= ($_GET['status'] ?? '') === 'IN_PROGRESS' ? 'selected' : '' ?>>Đang sửa chữa</option>
                            <option value="REPAIR_COMPLETED" <?= ($_GET['status'] ?? '') === 'REPAIR_COMPLETED' ? 'selected' : '' ?>>Đã sửa xong</option>
                            <option value="RETRIEVED" <?= ($_GET['status'] ?? '') === 'RETRIEVED' ? 'selected' : '' ?>>Đã thu hồi</option>
                            <option value="COMPLETED" <?= ($_GET['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>Hoàn tất</option>
                        </select>
                        
                        <select name="urgency_level" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả mức độ</option>
                            <option value="low" <?= ($_GET['urgency_level'] ?? '') === 'low' ? 'selected' : '' ?>>Thấp</option>
                            <option value="medium" <?= ($_GET['urgency_level'] ?? '') === 'medium' ? 'selected' : '' ?>>Trung bình</option>
                            <option value="high" <?= ($_GET['urgency_level'] ?? '') === 'high' ? 'selected' : '' ?>>Cao</option>
                            <option value="critical" <?= ($_GET['urgency_level'] ?? '') === 'critical' ? 'selected' : '' ?>>Khẩn cấp</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Tabs for different statuses -->
            <ul class="nav nav-tabs" id="statusTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        <?= format_status_display('Chờ bàn giao') ?>
                        <span class="badge bg-warning ms-2"><?= count($data['allRequests']['PENDING_HANDOVER'] ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="handed-tab" data-bs-toggle="tab" data-bs-target="#handed" type="button" role="tab">
                        <?= format_status_display('Đã bàn giao') ?>
                        <span class="badge bg-info ms-2"><?= count($data['allRequests']['HANDED_TO_CLERK'] ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">
                        Đã chuyển sửa chữa
                        <span class="badge bg-orange ms-2"><?= count($data['allRequests']['SENT_TO_REPAIR'] ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="progress-tab" data-bs-toggle="tab" data-bs-target="#progress" type="button" role="tab">
                        Đang sửa chữa
                        <span class="badge bg-primary ms-2"><?= count($data['allRequests']['IN_PROGRESS'] ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                        Đã sửa xong
                        <span class="badge bg-success ms-2"><?= count($data['allRequests']['REPAIR_COMPLETED'] ?? []) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="retrieved-tab" data-bs-toggle="tab" data-bs-target="#retrieved" type="button" role="tab">
                        Đã thu hồi
                        <span class="badge bg-purple ms-2"><?= count($data['allRequests']['RETRIEVED'] ?? []) ?></span>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="statusTabsContent">
                <?php foreach (['PENDING_HANDOVER' => 'pending', 'HANDED_TO_CLERK' => 'handed', 'SENT_TO_REPAIR' => 'sent', 'IN_PROGRESS' => 'progress', 'REPAIR_COMPLETED' => 'completed', 'RETRIEVED' => 'retrieved'] as $statusCode => $tabId): ?>
                    <div class="tab-pane fade <?= $tabId === 'pending' ? 'show active' : '' ?>" id="<?= $tabId ?>" role="tabpanel">
                        <?php if (empty($data['allRequests'][$statusCode])): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Không có đơn nào ở trạng thái này</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($data['allRequests'][$statusCode] as $request): ?>
                                    <div class="list-group-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <div class="fw-bold"><?= e($request['request_code']) ?></div>
                                                <small class="text-muted"><?= time_ago($request['created_at']) ?></small>
                                            </div>
                                            <div class="col-md-3">
                                                <div><?= e($request['equipment_name']) ?></div>
                                                <small class="text-muted"><?= e($request['equipment_code']) ?></small>
                                            </div>
                                            <div class="col-md-3">
                                                <div><?= e($request['requester_name']) ?></div>
                                                <small class="text-muted"><?= e($request['department_name']) ?></small>
                                            </div>
                                            <div class="col-md-2">
                                                <?php
                                                $urgencyClass = [
                                                    'low' => 'success',
                                                    'medium' => 'warning', 
                                                    'high' => 'danger',
                                                    'critical' => 'dark'
                                                ][$request['urgency_level']] ?? 'secondary';
                                                
                                                $urgencyText = [
                                                    'low' => 'Thấp',
                                                    'medium' => 'Trung bình',
                                                    'high' => 'Cao',
                                                    'critical' => 'Khẩn cấp'
                                                ][$request['urgency_level']] ?? 'Không xác định';
                                                ?>
                                                <span class="badge bg-<?= $urgencyClass ?>"><?= $urgencyText ?></span>
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <a href="<?= url('repairs/view.php?code=' . $request['request_code']) ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();

// Custom CSS
$custom_css = "
<style>
    .bg-orange { background-color: #fd7e14 !important; }
    .bg-purple { background-color: #6f42c1 !important; }
    .badge.bg-orange { background-color: #fd7e14 !important; }
    .list-group-item:hover { background-color: #f8f9fa; }
    .stats-card { 
        position: relative; 
        overflow: hidden; 
        transition: transform 0.2s;
    }
    .stats-card:hover { transform: translateY(-2px); }
    .stats-icon { 
        position: absolute; 
        right: 1rem; 
        top: 50%; 
        transform: translateY(-50%); 
        font-size: 2rem; 
        opacity: 0.3; 
    }
    .nav-tabs .nav-link { border-bottom: 2px solid transparent; }
    .nav-tabs .nav-link.active { 
        border-bottom-color: var(--primary-color); 
        font-weight: 600;
    }
</style>
";

include '../layouts/app.php';
?>
