<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userRole = $_SESSION['role'] ?? null;
$allowedRoles = ['barangay_staff', 'punong_barangay', 'lupon_secretary', 'system_admin'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied.");
}

$userId = $_SESSION['user_id'];
$recordId = (int)($_GET['record_id'] ?? $_POST['record_id'] ?? 0);

if (!$recordId) {
    die("Invalid record ID.");
}

// Get the record
$recordQuery = "SELECT br.*, bi.barangay_name FROM barangay_records br 
                JOIN barangay_info bi ON br.barangay_id = bi.id 
                WHERE br.id = :id";
$recordStmt = $pdo->prepare($recordQuery);
$recordStmt->execute([':id' => $recordId]);
$record = $recordStmt->fetch();

if (!$record) {
    die("Record not found.");
}

$message = '';
$messageType = '';

// Handle Adding Mediation Effort
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_effort') {
    try {
        $effortStmt = $pdo->prepare("INSERT INTO barangay_mediation_efforts 
            (barangay_record_id, mediation_date, attendees, effort_description, outcome_status, recorded_by)
            VALUES (:record_id, :mediation_date, :attendees, :description, :outcome, :recorded_by)");
        
        $effortStmt->execute([
            ':record_id' => $recordId,
            ':mediation_date' => $_POST['mediation_date'],
            ':attendees' => trim($_POST['attendees']),
            ':description' => trim($_POST['effort_description']),
            ':outcome' => $_POST['outcome_status'],
            ':recorded_by' => $userId
        ]);
        
        $message = "✅ Mediation effort recorded successfully.";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle Mediation Summary Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_summary') {
    try {
        $pdo->beginTransaction();
        
        // Check if summary exists
        $checkStmt = $pdo->query("SELECT id FROM barangay_mediation_summary WHERE barangay_record_id = $recordId");
        $existingSummary = $checkStmt->fetch();
        
        if ($existingSummary) {
            $summaryStmt = $pdo->prepare("UPDATE barangay_mediation_summary SET 
                punong_barangay_name = :pb_name,
                lupon_secretary_name = :ls_name,
                mediation_summary = :summary,
                complainant_attended = :comp_attended,
                respondent_attended = :resp_attended,
                settlement_attempts_made = :attempts,
                observations = :observations,
                recommendations = :recommendations,
                status = :status,
                submitted_by = :submitted_by,
                updated_at = NOW()
                WHERE barangay_record_id = :record_id");
        } else {
            $summaryStmt = $pdo->prepare("INSERT INTO barangay_mediation_summary 
                (barangay_record_id, punong_barangay_name, lupon_secretary_name, mediation_summary, 
                 complainant_attended, respondent_attended, settlement_attempts_made, observations, 
                 recommendations, status, submitted_by)
                VALUES (:record_id, :pb_name, :ls_name, :summary, :comp_attended, :resp_attended, 
                        :attempts, :observations, :recommendations, :status, :submitted_by)");
        }
        
        $summaryStmt->execute([
            ':record_id' => $recordId,
            ':pb_name' => trim($_POST['punong_barangay_name']),
            ':ls_name' => trim($_POST['lupon_secretary_name']),
            ':summary' => trim($_POST['mediation_summary']),
            ':comp_attended' => isset($_POST['complainant_attended']) ? 1 : 0,
            ':resp_attended' => isset($_POST['respondent_attended']) ? 1 : 0,
            ':attempts' => (int)$_POST['settlement_attempts_made'],
            ':observations' => trim($_POST['observations']),
            ':recommendations' => trim($_POST['recommendations']),
            ':status' => 'SUBMITTED',
            ':submitted_by' => $userId
        ]);
        
        // Update record status
        $updateRecordStmt = $pdo->prepare("UPDATE barangay_records SET status = :status WHERE id = :id");
        $updateRecordStmt->execute([
            ':status' => $_POST['record_status'],
            ':id' => $recordId
        ]);
        
        $pdo->commit();
        $message = "✅ Mediation summary submitted successfully.";
        $messageType = 'success';
        
        // Refresh record data
        $recordStmt->execute([':id' => $recordId]);
        $record = $recordStmt->fetch();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get mediation efforts
$effortsStmt = $pdo->prepare("SELECT * FROM barangay_mediation_efforts WHERE barangay_record_id = :record_id ORDER BY mediation_date DESC");
$effortsStmt->execute([':record_id' => $recordId]);
$efforts = $effortsStmt->fetchAll();

// Get mediation summary
$summaryStmt = $pdo->prepare("SELECT * FROM barangay_mediation_summary WHERE barangay_record_id = :record_id");
$summaryStmt->execute([':record_id' => $recordId]);
$summary = $summaryStmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mediation Notes - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="barangay-container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h1>📋 Mediation Notes & Summary</h1>
            <p class="text-muted">Record mediation efforts and finalize settlement recommendations</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-court alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Case Information Card -->
    <div class="card card-court mb-4">
        <div class="card-header">
            📌 Case Information
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Complaint #:</strong><br>
                    <code><?php echo htmlspecialchars($record['complaint_number']); ?></code>
                </div>
                <div class="col-md-3">
                    <strong>Complainant:</strong><br>
                    <?php echo htmlspecialchars($record['complainant_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Respondent:</strong><br>
                    <?php echo htmlspecialchars($record['respondent_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-info"><?php echo str_replace('_', ' ', $record['status']); ?></span>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <strong>Nature of Dispute:</strong><br>
                    <small><?php echo htmlspecialchars($record['nature_of_dispute']); ?></small>
                </div>
                <div class="col-md-3">
                    <strong>Category:</strong><br>
                    <span class="badge bg-secondary"><?php echo $record['dispute_category']; ?></span>
                </div>
                <div class="col-md-3">
                    <strong>Barangay:</strong><br>
                    <small><?php echo htmlspecialchars($record['barangay_name']); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Mediation Efforts Tab -->
    <div class="card card-court mb-4">
        <div class="card-header">
            ⚖️ Mediation Efforts & Attempts
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 mb-4 p-3 bg-light rounded">
                <input type="hidden" name="action" value="add_effort">
                <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">
                
                <div class="col-md-3">
                    <label for="mediation_date" class="form-label">Mediation Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="mediation_date" name="mediation_date" required>
                </div>

                <div class="col-md-3">
                    <label for="attendees" class="form-label">Attendees</label>
                    <input type="text" class="form-control" id="attendees" name="attendees" placeholder="e.g., Complainant, PB, Secretary">
                </div>

                <div class="col-md-3">
                    <label for="outcome_status" class="form-label">Outcome <span class="text-danger">*</span></label>
                    <select class="form-select" id="outcome_status" name="outcome_status" required>
                        <option value="">-- Select Outcome --</option>
                        <option value="ONGOING">Ongoing</option>
                        <option value="PARTIAL_SETTLEMENT">Partial Settlement</option>
                        <option value="UNSUCCESSFUL">Unsuccessful</option>
                        <option value="SETTLED">Settled</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="effort_description" class="form-label">Description of Effort <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="effort_description" name="effort_description" rows="3" 
                              placeholder="Describe what was discussed, agreements made, etc." required></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-court">➕ Record Mediation Effort</button>
                </div>
            </form>

            <!-- Previous Efforts List -->
            <?php if (!empty($efforts)): ?>
                <h5 class="mt-4">Previous Attempts:</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Attendees</th>
                                <th>Outcome</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($efforts as $effort): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($effort['mediation_date'])); ?></td>
                                    <td><small><?php echo htmlspecialchars($effort['attendees']); ?></small></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo str_replace('_', ' ', $effort['outcome_status']); ?></span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars(substr($effort['effort_description'], 0, 50)); ?>...</small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No mediation efforts recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mediation Summary Form -->
    <div class="card card-court mb-4">
        <div class="card-header">
            📝 Mediation Summary & Recommendations
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="submit_summary">
                <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">

                <div class="col-md-6">
                    <label for="punong_barangay_name" class="form-label">Punong Barangay Name</label>
                    <input type="text" class="form-control" id="punong_barangay_name" name="punong_barangay_name" 
                           value="<?php echo htmlspecialchars($summary['punong_barangay_name'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label for="lupon_secretary_name" class="form-label">Lupon Secretary Name</label>
                    <input type="text" class="form-control" id="lupon_secretary_name" name="lupon_secretary_name"
                           value="<?php echo htmlspecialchars($summary['lupon_secretary_name'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <label for="mediation_summary" class="form-label">Summary of Mediation <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="mediation_summary" name="mediation_summary" rows="4" required>
<?php echo htmlspecialchars($summary['mediation_summary'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="complainant_attended" name="complainant_attended"
                               <?php echo ($summary['complainant_attended'] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="complainant_attended">
                            Complainant Attended
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="respondent_attended" name="respondent_attended"
                               <?php echo ($summary['respondent_attended'] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="respondent_attended">
                            Respondent Attended
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="settlement_attempts_made" class="form-label">Number of Settlement Attempts</label>
                    <input type="number" class="form-control" id="settlement_attempts_made" name="settlement_attempts_made"
                           value="<?php echo htmlspecialchars($summary['settlement_attempts_made'] ?? count($efforts)); ?>" min="0">
                </div>

                <div class="col-md-6">
                    <label for="record_status" class="form-label">Update Case Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="record_status" name="record_status" required>
                        <option value="MEDIATION_IN_PROGRESS" <?php echo $record['status'] === 'MEDIATION_IN_PROGRESS' ? 'selected' : ''; ?>>
                            Mediation In Progress
                        </option>
                        <option value="SETTLED" <?php echo $record['status'] === 'SETTLED' ? 'selected' : ''; ?>>
                            Settled
                        </option>
                        <option value="ESCALATED" <?php echo $record['status'] === 'ESCALATED' ? 'selected' : ''; ?>>
                            To be Escalated
                        </option>
                        <option value="DISMISSED" <?php echo $record['status'] === 'DISMISSED' ? 'selected' : ''; ?>>
                            Dismissed
                        </option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="observations" class="form-label">Observations</label>
                    <textarea class="form-control" id="observations" name="observations" rows="3">
<?php echo htmlspecialchars($summary['observations'] ?? ''); ?></textarea>
                </div>

                <div class="col-12">
                    <label for="recommendations" class="form-label">Recommendations <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="recommendations" name="recommendations" rows="3" required>
<?php echo htmlspecialchars($summary['recommendations'] ?? ''); ?></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-court">💾 Save Mediation Summary</button>
                    <a href="barangay_record_entry.php" class="btn btn-secondary">← Back</a>
                    <?php if ($record['status'] === 'ESCALATED'): ?>
                        <a href="barangay_settlement_form.php?record_id=<?php echo $recordId; ?>&form_type=CFA" class="btn btn-danger">
                            📜 Generate Certificate to File Action
                        </a>
                    <?php endif; ?>
                    <?php if ($record['status'] === 'SETTLED'): ?>
                        <a href="barangay_settlement_form.php?record_id=<?php echo $recordId; ?>&form_type=KASUNDUAN" class="btn btn-success">
                            ✍️ Generate Kasunduan
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
