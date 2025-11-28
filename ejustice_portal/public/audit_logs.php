<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

$userRole = $_SESSION['role'] ?? null;

// Only allow system_admin and higher-level staff to view audit logs
$allowedRoles = ['system_admin', 'rtc_judge', 'rtc_staff'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied. Only authorized administrators can view audit logs.");
}

$filters = [];
$params = [];

// Filter by date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');
$filters[] = "al.created_at >= :date_from AND al.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)";
$params[':date_from'] = $dateFrom . ' 00:00:00';
$params[':date_to'] = $dateTo;

// Filter by action
if (!empty($_GET['action'])) {
    $filters[] = "al.action = :action";
    $params[':action'] = $_GET['action'];
}

// Filter by user
if (!empty($_GET['user_id'])) {
    $filters[] = "al.user_id = :user_id";
    $params[':user_id'] = (int)$_GET['user_id'];
}

// Filter by document
if (!empty($_GET['document_id'])) {
    $filters[] = "al.document_id = :document_id";
    $params[':document_id'] = (int)$_GET['document_id'];
}

$whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

// Get total count
$countSql = "SELECT COUNT(*) as total FROM audit_logs al $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'] ?? 0;

// Pagination
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalPages = ceil($totalRecords / $perPage);

// Get audit logs with user and document information
$sql = "SELECT 
            al.id,
            al.user_id,
            u.full_name,
            u.email,
            u.role,
            al.action,
            al.action_details,
            al.document_id,
            cd.original_filename,
            al.case_id,
            al.ip_address,
            al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN case_documents cd ON al.document_id = cd.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT $offset, $perPage";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get available actions for filter dropdown
$actionSql = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$actionStmt = $pdo->query($actionSql);
$availableActions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);

// Get users list for filter
$userSql = "SELECT id, full_name, email FROM users ORDER BY full_name";
$userStmt = $pdo->query($userSql);
$usersList = $userStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .audit-container { margin-top: 2rem; }
        .filter-section { background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; }
        .log-table { font-size: 0.9rem; }
        .action-badge { font-weight: 600; }
        .action-decrypt { background-color: #dc3545; }
        .action-view { background-color: #0dcaf0; }
        .action-upload { background-color: #198754; }
        .timestamp { font-size: 0.85rem; color: #666; }
        .ip-address { font-family: monospace; font-size: 0.85rem; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="audit-container">
    <div class="row mb-4">
        <div class="col">
            <h1>Document Access Audit Log</h1>
            <p class="text-muted">Track all document views, decryptions, and other sensitive actions</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($availableActions as $act): ?>
                        <option value="<?php echo htmlspecialchars($act); ?>" 
                            <?php echo $_GET['action'] == $act ? 'selected' : ''; ?>>
                            <?php echo getActionLabel($act); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?php echo $u['id']; ?>" 
                            <?php echo $_GET['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="document_id" class="form-label">Document ID</label>
                <input type="number" class="form-control" id="document_id" name="document_id" 
                    value="<?php echo htmlspecialchars($_GET['document_id'] ?? ''); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="audit_logs.php" class="btn btn-secondary w-100 ms-2">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="alert alert-info">
        Found <strong><?php echo $totalRecords; ?></strong> audit log entries
    </div>

    <!-- Audit Log Table -->
    <div class="table-responsive">
        <table class="table table-hover table-sm log-table">
            <thead class="table-dark">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Document</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No audit log entries found for the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <span class="timestamp">
                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['full_name']): ?>
                                    <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                    <br>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($log['role']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted"><em>Deleted User (ID: <?php echo $log['user_id']; ?>)</em></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge action-badge <?php 
                                    if (strpos($log['action'], 'DECRYPT') !== false) echo 'action-decrypt';
                                    elseif (strpos($log['action'], 'VIEW') !== false) echo 'action-view';
                                    elseif (strpos($log['action'], 'UPLOAD') !== false) echo 'action-upload';
                                ?>">
                                    <?php echo getActionLabel($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['document_id']): ?>
                                    <small>
                                        ID: <?php echo $log['document_id']; ?><br>
                                        <em><?php echo htmlspecialchars(substr($log['original_filename'] ?? 'N/A', 0, 30)); ?></em>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ip-address"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($log['action_details'] ?? '—'); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">First</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php 
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">Last</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
