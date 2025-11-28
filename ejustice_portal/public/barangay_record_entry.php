<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userRole = $_SESSION['role'] ?? null;

// Only barangay staff and authorized users can access
$allowedRoles = ['barangay_staff', 'punong_barangay', 'lupon_secretary', 'system_admin'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied. Only authorized Barangay personnel can access this module.");
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get Barangay Information for the logged-in user
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

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_record') {
    try {
        $pdo->beginTransaction();

        // Generate unique complaint number: BR-YYYYMM-NNNNN
        $monthYear = date('Ym');
        $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM barangay_records WHERE complaint_number LIKE 'BR-$monthYear%'");
        $count = $countStmt->fetch()['cnt'] + 1;
        $complaintNumber = 'BR-' . $monthYear . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

        $insertQuery = "INSERT INTO barangay_records (
            barangay_id, complaint_number, complainant_name, complainant_address, complainant_contact,
            respondent_name, respondent_address, respondent_contact, dispute_category, dispute_subcategory,
            nature_of_dispute, incident_date, incident_details, initial_mediation_date, recorded_by, status
        ) VALUES (
            :barangay_id, :complaint_number, :complainant_name, :complainant_address, :complainant_contact,
            :respondent_name, :respondent_address, :respondent_contact, :dispute_category, :dispute_subcategory,
            :nature_of_dispute, :incident_date, :incident_details, :initial_mediation_date, :recorded_by, 'ACTIVE'
        )";

        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([
            ':barangay_id' => $barangayId,
            ':complaint_number' => $complaintNumber,
            ':complainant_name' => trim($_POST['complainant_name']),
            ':complainant_address' => trim($_POST['complainant_address']),
            ':complainant_contact' => trim($_POST['complainant_contact']),
            ':respondent_name' => trim($_POST['respondent_name']),
            ':respondent_address' => trim($_POST['respondent_address']),
            ':respondent_contact' => trim($_POST['respondent_contact']),
            ':dispute_category' => $_POST['dispute_category'],
            ':dispute_subcategory' => trim($_POST['dispute_subcategory']),
            ':nature_of_dispute' => trim($_POST['nature_of_dispute']),
            ':incident_date' => !empty($_POST['incident_date']) ? $_POST['incident_date'] : null,
            ':incident_details' => trim($_POST['incident_details']),
            ':initial_mediation_date' => !empty($_POST['initial_mediation_date']) ? $_POST['initial_mediation_date'] : null,
            ':recorded_by' => $userId
        ]);

        $recordId = $pdo->lastInsertId();

        // Log to audit
        $auditStmt = $pdo->prepare("INSERT INTO barangay_audit_log (barangay_id, record_id, user_id, action_type, ip_address) 
                                    VALUES (:barangay_id, :record_id, :user_id, 'RECORD_CREATED', :ip)");
        $auditStmt->execute([
            ':barangay_id' => $barangayId,
            ':record_id' => $recordId,
            ':user_id' => $userId,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $pdo->commit();
        $message = "✅ Complaint record created successfully. Complaint #: <strong>$complaintNumber</strong>";
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error creating record: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get existing records for the barangay
$recordsQuery = "SELECT * FROM barangay_records WHERE barangay_id = :barangay_id ORDER BY created_at DESC LIMIT 100";
$recordsStmt = $pdo->prepare($recordsQuery);
$recordsStmt->execute([':barangay_id' => $barangayId]);
$records = $recordsStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Record Entry - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="barangay-container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h1>🏘️ Barangay Record Entry System</h1>
            <p class="text-muted">Record initial complaints and disputes for mediation and escalation</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($barangay): ?>
                <div class="card card-court">
                    <div class="card-body">
                        <strong><?php echo htmlspecialchars($barangay['barangay_name']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($barangay['municipality']); ?>, <?php echo htmlspecialchars($barangay['province']); ?></small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-court alert-<?php echo $messageType; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- New Record Entry Form -->
    <div class="card card-court mb-4">
        <div class="card-header">
            📝 New Complaint Record
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="new_record">

                <div class="col-md-6">
                    <label for="complainant_name" class="form-label">Complainant Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="complainant_name" name="complainant_name" required>
                </div>

                <div class="col-md-6">
                    <label for="complainant_contact" class="form-label">Complainant Contact</label>
                    <input type="tel" class="form-control" id="complainant_contact" name="complainant_contact">
                </div>

                <div class="col-12">
                    <label for="complainant_address" class="form-label">Complainant Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="complainant_address" name="complainant_address" rows="2" required></textarea>
                </div>

                <hr>

                <div class="col-md-6">
                    <label for="respondent_name" class="form-label">Respondent Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="respondent_name" name="respondent_name" required>
                </div>

                <div class="col-md-6">
                    <label for="respondent_contact" class="form-label">Respondent Contact</label>
                    <input type="tel" class="form-control" id="respondent_contact" name="respondent_contact">
                </div>

                <div class="col-12">
                    <label for="respondent_address" class="form-label">Respondent Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="respondent_address" name="respondent_address" rows="2" required></textarea>
                </div>

                <hr>

                <div class="col-md-4">
                    <label for="dispute_category" class="form-label">Dispute Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="dispute_category" name="dispute_category" required>
                        <option value="">-- Select Category --</option>
                        <option value="CRIMINAL">Criminal</option>
                        <option value="CIVIL">Civil</option>
                        <option value="ADMINISTRATIVE">Administrative</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="dispute_subcategory" class="form-label">Subcategory (e.g., Theft, Property, Assault)</label>
                    <input type="text" class="form-control" id="dispute_subcategory" name="dispute_subcategory">
                </div>

                <div class="col-md-4">
                    <label for="incident_date" class="form-label">Incident Date</label>
                    <input type="date" class="form-control" id="incident_date" name="incident_date">
                </div>

                <div class="col-12">
                    <label for="nature_of_dispute" class="form-label">Nature of Dispute <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="nature_of_dispute" name="nature_of_dispute" rows="3" required placeholder="Describe the dispute in detail..."></textarea>
                </div>

                <div class="col-12">
                    <label for="incident_details" class="form-label">Incident Details</label>
                    <textarea class="form-control" id="incident_details" name="incident_details" rows="2" placeholder="Additional details about the incident..."></textarea>
                </div>

                <div class="col-md-6">
                    <label for="initial_mediation_date" class="form-label">Scheduled Initial Mediation Date</label>
                    <input type="date" class="form-control" id="initial_mediation_date" name="initial_mediation_date">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-court">📋 Submit Record</button>
                    <button type="reset" class="btn btn-secondary">🔄 Clear</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Records List -->
    <div class="card card-court">
        <div class="card-header">
            📂 Active Records (<?php echo count($records); ?>)
        </div>
        <div class="card-body">
            <?php if (empty($records)): ?>
                <div class="alert alert-info">No records found. Create a new complaint record above.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>Complaint #</th>
                                <th>Complainant</th>
                                <th>Respondent</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date Filed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['complaint_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr($record['complainant_name'], 0, 20)); ?></td>
                                    <td><?php echo htmlspecialchars(substr($record['respondent_name'], 0, 20)); ?></td>
                                    <td><span class="badge bg-info"><?php echo $record['dispute_category']; ?></span></td>
                                    <td>
                                        <?php 
                                            $statusClasses = [
                                                'ACTIVE' => 'bg-warning',
                                                'MEDIATION_IN_PROGRESS' => 'bg-primary',
                                                'SETTLED' => 'bg-success',
                                                'ESCALATED' => 'bg-danger',
                                                'DISMISSED' => 'bg-secondary',
                                                'CLOSED' => 'bg-dark'
                                            ];
                                        ?>
                                        <span class="badge <?php echo $statusClasses[$record['status']] ?? 'bg-secondary'; ?>">
                                            <?php echo str_replace('_', ' ', $record['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($record['created_at'])); ?></td>
                                    <td>
                                        <a href="barangay_mediation.php?record_id=<?php echo $record['id']; ?>" 
                                           class="btn btn-sm btn-primary">View</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
