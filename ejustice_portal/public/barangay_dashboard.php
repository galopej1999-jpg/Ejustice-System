<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/barangay_escalation.php';
require_login();

$userRole = $_SESSION['role'] ?? null;
$allowedRoles = ['barangay_staff', 'punong_barangay', 'lupon_secretary', 'system_admin'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied. Only authorized Barangay personnel can access this module.");
}

$userId = $_SESSION['user_id'];

// Get Barangay Information
$barangayQuery = "SELECT bi.*, bu.position FROM barangay_info bi 
                  LEFT JOIN barangay_users bu ON bu.barangay_id = bi.id 
                  WHERE bu.user_id = :user_id LIMIT 1";
$barangayStmt = $pdo->prepare($barangayQuery);
$barangayStmt->execute([':user_id' => $userId]);
$barangay = $barangayStmt->fetch();

if (!$barangay && $userRole !== 'system_admin') {
    die("Error: Barangay assignment not found for your account.");
}

$barangayId = $barangay['id'] ?? null;

// Get Dashboard Statistics
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');
$startOfYear = date('Y-01-01');

// Total cases this month (including online-filed cases)
$totalCasesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_records 
                                WHERE barangay_id = :barangay_id AND DATE(created_at) BETWEEN :start AND :end");
$totalCasesStmt->execute([':barangay_id' => $barangayId, ':start' => $thisMonth, ':end' => date('Y-m-t')]);
$totalCasesMonth = $totalCasesStmt->fetch()['total'];

// Active cases (not resolved) - includes barangay_records only
$activeCasesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_records 
                                 WHERE barangay_id = :barangay_id AND status IN ('ACTIVE', 'MEDIATION_IN_PROGRESS', 'WAITING_FOR_BARANGAY')");
$activeCasesStmt->execute([':barangay_id' => $barangayId]);
$activeCases = $activeCasesStmt->fetch()['total'];

// Online-filed cases pending barangay processing
$onlinePendingStmt = $pdo->prepare("SELECT COUNT(*) as total FROM cases 
                                   WHERE barangay_id = :barangay_id AND stage = 'barangay_initial' 
                                   AND status IN ('PENDING_BARANGAY', 'FILED')");
$onlinePendingStmt->execute([':barangay_id' => $barangayId]);
$onlinePending = $onlinePendingStmt->fetch()['total'];

// Settled cases this month
$settledStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_records 
                             WHERE barangay_id = :barangay_id AND status = 'SETTLED' 
                             AND DATE(updated_at) BETWEEN :start AND :end");
$settledStmt->execute([':barangay_id' => $barangayId, ':start' => $thisMonth, ':end' => date('Y-m-t')]);
$settledMonth = $settledStmt->fetch()['total'];

// Escalated cases (pending police acknowledgment)
$escalatedStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_cfa 
                              WHERE barangay_record_id IN (SELECT id FROM barangay_records WHERE barangay_id = :barangay_id)
                              AND is_acknowledged = 0");
$escalatedStmt->execute([':barangay_id' => $barangayId]);
$pendingEscalation = $escalatedStmt->fetch()['total'];

// Settlement rate this month
$settleRateStmt = $pdo->prepare("SELECT 
                                SUM(CASE WHEN status = 'SETTLED' THEN 1 ELSE 0 END) as settled,
                                COUNT(*) as total
                               FROM barangay_records 
                               WHERE barangay_id = :barangay_id AND DATE(created_at) BETWEEN :start AND :end");
$settleRateStmt->execute([':barangay_id' => $barangayId, ':start' => $thisMonth, ':end' => date('Y-m-t')]);
$settleRate = $settleRateStmt->fetch();
$settlementRate = $settleRate['total'] > 0 ? round(($settleRate['settled'] / $settleRate['total']) * 100, 1) : 0;

// Get pending mediation cases
$pendingMediationStmt = $pdo->prepare("SELECT * FROM barangay_records 
                                      WHERE barangay_id = :barangay_id 
                                      AND (status = 'ACTIVE' OR status = 'MEDIATION_IN_PROGRESS')
                                      ORDER BY created_at ASC LIMIT 10");
$pendingMediationStmt->execute([':barangay_id' => $barangayId]);
$pendingCases = $pendingMediationStmt->fetchAll();

// Get recent activities
$activitiesStmt = $pdo->prepare("SELECT bal.*, br.complaint_number, u.full_name FROM barangay_audit_log bal
                                LEFT JOIN barangay_records br ON bal.record_id = br.id
                                LEFT JOIN users u ON bal.user_id = u.id
                                WHERE bal.barangay_id = :barangay_id
                                ORDER BY bal.created_at DESC LIMIT 15");
$activitiesStmt->execute([':barangay_id' => $barangayId]);
$activities = $activitiesStmt->fetchAll();

// Get cases by status
$statusDistStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM barangay_records 
                                WHERE barangay_id = :barangay_id GROUP BY status");
$statusDistStmt->execute([':barangay_id' => $barangayId]);
$statusDist = $statusDistStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get dispute categories breakdown
$categoryStmt = $pdo->prepare("SELECT dispute_category, COUNT(*) as count FROM barangay_records 
                             WHERE barangay_id = :barangay_id AND DATE(created_at) BETWEEN :start AND :end
                             GROUP BY dispute_category");
$categoryStmt->execute([':barangay_id' => $barangayId, ':start' => $thisMonth, ':end' => date('Y-m-t')]);
$categories = $categoryStmt->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Dashboard - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
    <style>
        .stat-card { border-left: 4px solid #003d82; }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #003d82; }
        .stat-label { font-size: 0.95rem; color: #666; text-transform: uppercase; }
        .mini-chart { height: 150px; display: flex; align-items: flex-end; gap: 5px; margin: 1rem 0; }
        .chart-bar { flex: 1; background: #003d82; border-radius: 3px 3px 0 0; opacity: 0.7; }
        .chart-bar.active { opacity: 1; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="barangay-container mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <h1>🏘️ Barangay Justice Dashboard</h1>
            <p class="text-muted">Manage cases, mediation efforts, and escalations</p>
        </div>
        <div class="col-md-3 text-end">
            <?php if ($barangay): ?>
                <div class="card card-court">
                    <div class="card-body">
                        <strong><?php echo htmlspecialchars($barangay['barangay_name']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($barangay['position'] ?? 'Staff'); ?></small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $totalCasesMonth; ?></div>
                    <div class="stat-label">Cases (This Month)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $activeCases; ?></div>
                    <div class="stat-label">Active Cases</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $settledMonth; ?></div>
                    <div class="stat-label">Settled (Month)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-court stat-card">
                <div class="card-body">
                    <div class="stat-number"><?php echo $settlementRate; ?>%</div>
                    <div class="stat-label">Settlement Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group w-100" role="group">
                <a href="barangay_record_entry.php" class="btn btn-court flex-fill">➕ New Complaint Record</a>
                <a href="barangay_record_entry.php" class="btn btn-outline-primary flex-fill">📂 View All Records</a>
                <a href="#online-cases-section" class="btn btn-outline-info flex-fill">📱 Online Filed (<?php echo $onlinePending; ?>)</a>
                <a href="#escalated-section" class="btn btn-outline-danger flex-fill">⚠️ Pending Escalations (<?php echo $pendingEscalation; ?>)</a>
            </div>
        </div>
    </div>

    <!-- Online-Filed Cases Awaiting Barangay Processing -->
    <?php if ($onlinePending > 0): ?>
    <div class="row mb-4" id="online-cases-section">
        <div class="col-12">
            <div class="alert alert-info">
                <strong>🔔 New Online-Filed Cases</strong>
                <p class="mb-0">There are <strong><?php echo $onlinePending; ?></strong> case(s) filed online that need Barangay processing.</p>
            </div>
        </div>
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    📱 Online-Filed Cases Pending Assignment
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Case #</th>
                                    <th>Record #</th>
                                    <th>Complainant</th>
                                    <th>Respondent</th>
                                    <th>Category</th>
                                    <th>Filed Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $onlineCasesStmt = $pdo->prepare("SELECT c.*, br.complaint_number FROM cases c 
                                                                  LEFT JOIN barangay_records br ON c.barangay_record_id = br.id
                                                                  WHERE c.barangay_id = :barangay_id 
                                                                  AND c.stage = 'barangay_initial'
                                                                  AND c.status IN ('PENDING_BARANGAY', 'FILED')
                                                                  ORDER BY c.created_at DESC");
                                $onlineCasesStmt->execute([':barangay_id' => $barangayId]);
                                $onlineCases = $onlineCasesStmt->fetchAll();
                                
                                foreach ($onlineCases as $onlineCase):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($onlineCase['case_number']); ?></strong></td>
                                        <td><?php echo $onlineCase['complaint_number'] ? htmlspecialchars($onlineCase['complaint_number']) : '<span class="text-muted">-</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($onlineCase['respondent_name']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($onlineCase['complaint_type']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($onlineCase['created_at'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($onlineCase['created_at'])); ?></td>
                                        <td>
                                            <a href="barangay_mediation.php?record_id=<?php echo $onlineCase['barangay_record_id']; ?>" class="btn btn-sm btn-outline-primary">Process</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <!-- Pending Mediation Cases -->
        <div class="col-md-8">
            <div class="card card-court">
                <div class="card-header">
                    📋 Pending Mediation Cases
                </div>
                <div class="card-body">
                    <?php if (empty($pendingCases)): ?>
                        <div class="alert alert-info">No pending cases. Great work! 🎉</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Complaint #</th>
                                        <th>Complainant</th>
                                        <th>Respondent</th>
                                        <th>Category</th>
                                        <th>Filed</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingCases as $case): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($case['complaint_number']); ?></strong></td>
                                            <td><small><?php echo htmlspecialchars(substr($case['complainant_name'], 0, 15)); ?></small></td>
                                            <td><small><?php echo htmlspecialchars(substr($case['respondent_name'], 0, 15)); ?></small></td>
                                            <td><span class="badge bg-info"><?php echo $case['dispute_category']; ?></span></td>
                                            <td><?php echo date('M d', strtotime($case['created_at'])); ?></td>
                                            <td>
                                                <a href="barangay_mediation.php?record_id=<?php echo $case['id']; ?>" 
                                                   class="btn btn-sm btn-primary">Process</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Case Status Distribution -->
        <div class="col-md-4">
            <div class="card card-court">
                <div class="card-header">
                    📊 Case Status Distribution
                </div>
                <div class="card-body">
                    <?php if (empty($statusDist)): ?>
                        <div class="text-muted">No data available</div>
                    <?php else: ?>
                        <table class="table table-sm table-borderless">
                            <?php foreach ($statusDist as $status => $count): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo str_replace('_', ' ', $status); ?></span>
                                    </td>
                                    <td class="text-end"><strong><?php echo $count; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Dispute Categories This Month -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    🏷️ Dispute Categories (This Month)
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (empty($categories)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">No disputes recorded this month</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($categories as $category => $count): ?>
                                <div class="col-md-3">
                                    <div class="text-center p-3 bg-light rounded mb-2">
                                        <div style="font-size: 1.8rem; font-weight: bold; color: #003d82;">
                                            <?php echo $count; ?>
                                        </div>
                                        <div class="small text-muted"><?php echo ucfirst(strtolower($category)); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Escalations Section -->
    <div class="row mb-4" id="escalated-section">
        <div class="col-12">
            <div class="card card-court border-danger">
                <div class="card-header bg-danger text-white">
                    ⚠️ Pending Escalations to Police
                </div>
                <div class="card-body">
                    <?php 
                    $pendingEscalations = getPendingEscalations($pdo, $barangayId);
                    if (empty($pendingEscalations)): 
                    ?>
                        <div class="alert alert-success">No pending escalations ✓</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Complaint #</th>
                                        <th>Parties</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingEscalations as $esc): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($esc['complaint_number']); ?></strong></td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars(substr($esc['complainant_name'], 0, 12)); ?> vs 
                                                    <?php echo htmlspecialchars(substr($esc['respondent_name'], 0, 12)); ?>
                                                </small>
                                            </td>
                                            <td><span class="badge bg-warning"><?php echo $esc['dispute_category']; ?></span></td>
                                            <td><span class="badge bg-danger">ESCALATED</span></td>
                                            <td>
                                                <a href="barangay_settlement_form.php?record_id=<?php echo $esc['id']; ?>&form_type=CFA" 
                                                   class="btn btn-sm btn-danger">Generate CFA</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-court">
                <div class="card-header">
                    📝 Recent Activities
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <div class="text-muted">No recent activities</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php 
                                                    $actions = [
                                                        'RECORD_CREATED' => '📝 Record Created',
                                                        'ESCALATED_TO_POLICE' => '⚠️ Escalated to Police',
                                                        'SETTLEMENT_FORM_CREATED' => '✍️ Settlement Form Created'
                                                    ];
                                                    echo $actions[$activity['action_type']] ?? $activity['action_type'];
                                                ?>
                                            </h6>
                                            <p class="mb-1 small text-muted">
                                                <?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?> • 
                                                <?php echo htmlspecialchars($activity['complaint_number'] ?? 'N/A'); ?>
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                <?php echo htmlspecialchars($activity['action_details']); ?>
                                            </p>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
