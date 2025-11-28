<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/barangay_escalation.php';
require_login();

$userRole = $_SESSION['role'] ?? null;
$allowedRoles = ['barangay_staff', 'punong_barangay', 'lupon_secretary', 'system_admin'];
if (!in_array($userRole, $allowedRoles)) {
    die("Access denied.");
}

$userId = $_SESSION['user_id'];
$recordId = (int)($_GET['record_id'] ?? $_POST['record_id'] ?? 0);
$formType = $_GET['form_type'] ?? $_POST['form_type'] ?? 'KASUNDUAN';

if (!$recordId) {
    die("Invalid record ID.");
}

// Get the record with all related data
$recordQuery = "SELECT br.*, bi.barangay_name, bms.mediation_summary, bms.recommendations
                FROM barangay_records br 
                JOIN barangay_info bi ON br.barangay_id = bi.id 
                LEFT JOIN barangay_mediation_summary bms ON br.id = bms.barangay_record_id
                WHERE br.id = :id";
$recordStmt = $pdo->prepare($recordQuery);
$recordStmt->execute([':id' => $recordId]);
$record = $recordStmt->fetch();

if (!$record) {
    die("Record not found.");
}

$message = '';
$messageType = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_form') {
    try {
        $pdo->beginTransaction();

        // Generate unique form number
        $monthYear = date('Ym');
        $formPrefix = substr($formType, 0, 3); // KAS, CFA, CNA
        $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM barangay_settlements WHERE settlement_type = '$formType'");
        $count = $countStmt->fetch()['cnt'] + 1;
        $formNumber = $formPrefix . '-' . $monthYear . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

        // Save settlement form
        $settlementStmt = $pdo->prepare("INSERT INTO barangay_settlements 
            (barangay_record_id, settlement_type, settlement_terms, created_by)
            VALUES (:record_id, :form_type, :terms, :created_by)");
        
        $settlementStmt->execute([
            ':record_id' => $recordId,
            ':form_type' => $formType,
            ':terms' => trim($_POST['settlement_terms']),
            ':created_by' => $userId
        ]);

        $settlementId = $pdo->lastInsertId();

        // If it's a CFA, create CFA record for escalation
        if ($formType === 'CFA') {
            $cfaStmt = $pdo->prepare("INSERT INTO barangay_cfa 
                (barangay_record_id, settlement_id, cfa_number, reason_for_escalation, escalated_to, created_by)
                VALUES (:record_id, :settlement_id, :cfa_number, :reason, :escalated_to, :created_by)");
            
            $cfaStmt->execute([
                ':record_id' => $recordId,
                ':settlement_id' => $settlementId,
                ':cfa_number' => $formNumber,
                ':reason' => trim($_POST['reason_for_escalation']),
                ':escalated_to' => $_POST['escalated_to'] ?? 'POLICE',
                ':created_by' => $userId
            ]);

            // Update record status to escalated
            $updateStmt = $pdo->prepare("UPDATE barangay_records SET status = 'ESCALATED' WHERE id = :id");
            $updateStmt->execute([':id' => $recordId]);
            
            // AUTOMATICALLY ESCALATE TO POLICE BLOTTER
            $escalatedCaseId = escalateToPoliceBlotter($pdo, $recordId, $formNumber, $userId);
            if ($escalatedCaseId === false) {
                throw new Exception("Failed to create police blotter case. Please check error logs.");
            }
        }

        $pdo->commit();
        $message = "✅ Form saved successfully. Form #: <strong>$formNumber</strong>";
        $messageType = 'success';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get existing settlement forms
$settlementStmt = $pdo->prepare("SELECT * FROM barangay_settlements WHERE barangay_record_id = :record_id");
$settlementStmt->execute([':record_id' => $recordId]);
$settlements = $settlementStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settlement Form - eJustice Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
    <style>
        .form-preview { background: white; padding: 3cm; font-family: 'Times New Roman', serif; }
        .form-header { text-align: center; margin-bottom: 1.5cm; border-bottom: 2px solid #000; padding-bottom: 0.5cm; }
        .form-seal { font-size: 2rem; margin-bottom: 0.5cm; }
        .form-title { font-size: 1.5rem; font-weight: bold; margin: 0.5cm 0; }
        .form-subtitle { font-size: 0.95rem; margin: 0.3cm 0; }
        .form-section { margin: 1cm 0; }
        .form-section-title { font-weight: bold; text-decoration: underline; margin-bottom: 0.3cm; }
        .form-field { margin: 0.3cm 0; line-height: 1.8; }
        .form-field-label { font-weight: bold; }
        .signature-line { border-bottom: 1px solid #000; width: 3cm; display: inline-block; margin-top: 0.5cm; }
        @media print {
            body { margin: 0; padding: 0; }
            .non-printable { display: none; }
            .form-preview { padding: 0; box-shadow: none; page-break-after: always; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="barangay-container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h1>📜 Settlement Form Generator</h1>
            <p class="text-muted">Create official Barangay documents for case resolution</p>
        </div>
        <div class="col-md-3 text-end non-printable">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">🖨️ Print</button>
                <a href="barangay_mediation.php?record_id=<?php echo $recordId; ?>" class="btn btn-sm btn-secondary">← Back</a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-court alert-<?php echo $messageType; ?> non-printable">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Form Type Selector -->
    <div class="card card-court mb-4 non-printable">
        <div class="card-header">
            📋 Select Form Type
        </div>
        <div class="card-body">
            <div class="btn-group" role="group">
                <a href="?record_id=<?php echo $recordId; ?>&form_type=KASUNDUAN" 
                   class="btn btn-outline-primary <?php echo $formType === 'KASUNDUAN' ? 'active' : ''; ?>">
                    Kasunduan sa Pag-aayos (Settlement Agreement)
                </a>
                <a href="?record_id=<?php echo $recordId; ?>&form_type=CFA" 
                   class="btn btn-outline-danger <?php echo $formType === 'CFA' ? 'active' : ''; ?>">
                    Certificate to File Action (CFA)
                </a>
                <a href="?record_id=<?php echo $recordId; ?>&form_type=CNA" 
                   class="btn btn-outline-warning <?php echo $formType === 'CNA' ? 'active' : ''; ?>">
                    Certificate of Non-Appearance (CNA)
                </a>
            </div>
        </div>
    </div>

    <!-- Form Creation Section -->
    <div class="card card-court mb-4 non-printable">
        <div class="card-header">
            ✍️ Create <?php echo $formType; ?> Form
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="save_form">
                <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">
                <input type="hidden" name="form_type" value="<?php echo $formType; ?>">

                <div class="col-12">
                    <label for="settlement_terms" class="form-label">
                        <?php 
                            echo match($formType) {
                                'KASUNDUAN' => 'Settlement Terms & Conditions',
                                'CFA' => 'CFA Details',
                                'CNA' => 'CNA Details',
                                default => 'Form Details'
                            };
                        ?>
                        <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="settlement_terms" name="settlement_terms" rows="5" required 
                              placeholder="Enter the terms or details..."></textarea>
                </div>

                <?php if ($formType === 'CFA'): ?>
                    <div class="col-12">
                        <label for="reason_for_escalation" class="form-label">Reason for Escalation <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason_for_escalation" name="reason_for_escalation" rows="3" required
                                  placeholder="Why is this case being escalated to police/court?"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="escalated_to" class="form-label">Escalate To <span class="text-danger">*</span></label>
                        <select class="form-select" id="escalated_to" name="escalated_to" required>
                            <option value="POLICE">Police Station (Blotter Entry)</option>
                            <option value="MTC">Municipal Trial Court</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <button type="submit" class="btn btn-court">💾 Save & Create Form</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form Preview -->
    <div class="form-preview" id="formPreview">
        <div class="form-header">
            <div class="form-seal">⚖️ 🇵🇭 ⚖️</div>
            <div class="form-title">
                <?php 
                    echo match($formType) {
                        'KASUNDUAN' => 'KASUNDUAN SA PAG-AAYOS',
                        'CFA' => 'CERTIFICATE TO FILE ACTION',
                        'CNA' => 'CERTIFICATE OF NON-APPEARANCE',
                        default => 'BARANGAY SETTLEMENT FORM'
                    };
                ?>
            </div>
            <div class="form-subtitle">
                <?php echo htmlspecialchars($record['barangay_name']); ?>
            </div>
            <div class="form-subtitle" style="font-size: 0.9rem;">
                Form #: ___________________
            </div>
        </div>

        <div class="form-section">
            <div class="form-field">
                <span class="form-field-label">Complaint Number:</span> <?php echo htmlspecialchars($record['complaint_number']); ?>
            </div>
            <div class="form-field">
                <span class="form-field-label">Date Filed:</span> <?php echo date('F d, Y', strtotime($record['created_at'])); ?>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">PARTIES INVOLVED</div>
            <div class="form-field">
                <span class="form-field-label">COMPLAINANT:</span> <?php echo htmlspecialchars($record['complainant_name']); ?><br>
                <span style="margin-left: 1cm;">Address: <?php echo htmlspecialchars($record['complainant_address']); ?></span>
            </div>
            <div class="form-field">
                <span class="form-field-label">RESPONDENT:</span> <?php echo htmlspecialchars($record['respondent_name']); ?><br>
                <span style="margin-left: 1cm;">Address: <?php echo htmlspecialchars($record['respondent_address']); ?></span>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">NATURE OF DISPUTE</div>
            <div class="form-field">
                <span class="form-field-label">Category:</span> <?php echo htmlspecialchars($record['dispute_category']); ?><br>
                <span class="form-field-label">Description:</span> <?php echo htmlspecialchars($record['nature_of_dispute']); ?>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">DETAILS</div>
            <div class="form-field">
                <?php 
                    if ($formType === 'KASUNDUAN') {
                        echo '<span class="form-field-label">Settlement Agreement:</span><br>';
                        echo 'The parties have agreed to the following terms and conditions for the settlement of this dispute:<br>';
                        echo '_______________________________________________________________________________<br>';
                        echo '_______________________________________________________________________________<br>';
                    } elseif ($formType === 'CFA') {
                        echo '<span class="form-field-label">Reason for Escalation to ' . ($_POST['escalated_to'] ?? 'Police') . ':</span><br>';
                        echo 'The barangay mediation has been unsuccessful in resolving this dispute.<br>';
                        echo 'The case is hereby certified for filing action.<br>';
                    } elseif ($formType === 'CNA') {
                        echo '<span class="form-field-label">Certificate of Non-Appearance:</span><br>';
                        echo 'This certifies that __________________ failed to appear during the scheduled mediation.<br>';
                    }
                ?>
            </div>
        </div>

        <div class="form-section" style="margin-top: 2cm;">
            <div style="display: flex; justify-content: space-around; margin-top: 1.5cm;">
                <div style="text-align: center;">
                    <div class="signature-line"></div>
                    <div style="font-size: 0.9rem; margin-top: 0.2cm;">Complainant Signature</div>
                </div>
                <div style="text-align: center;">
                    <div class="signature-line"></div>
                    <div style="font-size: 0.9rem; margin-top: 0.2cm;">Respondent Signature</div>
                </div>
                <div style="text-align: center;">
                    <div class="signature-line"></div>
                    <div style="font-size: 0.9rem; margin-top: 0.2cm;">Punong Barangay</div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 1cm; font-size: 0.9rem;">
                Date: ____________________
            </div>
        </div>
    </div>

    <!-- Existing Forms List -->
    <?php if (!empty($settlements)): ?>
        <div class="card card-court mt-4 non-printable">
            <div class="card-header">
                📂 Existing Forms
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Form Type</th>
                            <th>Created Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settlements as $settlement): ?>
                            <tr>
                                <td><span class="badge bg-info"><?php echo $settlement['settlement_type']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($settlement['created_at'])); ?></td>
                                <td><?php echo $settlement['is_verified'] ? '✓ Verified' : 'Pending'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
