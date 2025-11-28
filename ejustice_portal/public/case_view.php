<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$role = current_user_role();
$user_id = $_SESSION['user_id'];

$case_id = (int) ($_GET['id'] ?? 0);
if (!$case_id) {
    die("Invalid case id.");
}

// Load case
$stmt = $pdo->prepare("SELECT c.*, u.full_name AS complainant_name 
                       FROM cases c 
                       JOIN users u ON c.complainant_id = u.id
                       WHERE c.id = :id");
$stmt->execute([':id' => $case_id]);
$case = $stmt->fetch();

if (!$case) {
    die("Case not found.");
}

// Role-based visibility
if ($role === 'complainant' && $case['complainant_id'] != $user_id) {
    die("Access denied.");
}
if ($role === 'police_staff' && $case['stage'] !== 'police_blotter') {
    die("Access denied.");
}
if (in_array($role, ['mtc_staff','mtc_judge']) && $case['stage'] !== 'mtc_case') {
    die("Access denied.");
}
if (in_array($role, ['rtc_staff','rtc_judge']) && $case['stage'] !== 'rtc_case') {
    die("Access denied.");
}

// Handle status updates / escalations
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status = trim($_POST['new_status'] ?? '');
        if ($new_status) {
            $stmtUp = $pdo->prepare("UPDATE cases SET status = :st WHERE id = :id");
            $stmtUp->execute([':st' => $new_status, ':id' => $case_id]);
            $message = "Status updated.";
            $case['status'] = $new_status;
        }
    } elseif (isset($_POST['escalate_mtc']) && $role === 'police_staff' && $case['stage'] === 'police_blotter') {
        // Create new MTC case
        $year = date('Y');
        $random = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $mtc_case_number = 'MTC-' . $year . '-' . $random;

        $pdo->beginTransaction();
        try {
            $stmtIns = $pdo->prepare("INSERT INTO cases 
                (case_number, parent_case_id, stage, status, complainant_id, respondent_name, incident_details, incident_datetime, location, created_by, complaint_type, complaint_subtype)
                VALUES
                (:case_number, :parent_case_id, 'mtc_case', 'escalated_to_mtc', :complainant_id, :respondent_name, :incident_details, :incident_datetime, :location, :created_by, :complaint_type, :complaint_subtype)");
            $stmtIns->execute([
                ':case_number' => $mtc_case_number,
                ':parent_case_id' => $case['id'],
                ':complainant_id' => $case['complainant_id'],
                ':respondent_name' => $case['respondent_name'],
                ':incident_details' => $case['incident_details'],
                ':incident_datetime' => $case['incident_datetime'],
                ':location' => $case['location'],
                ':created_by' => $user_id,
                ':complaint_type' => $case['complaint_type'],
                ':complaint_subtype' => $case['complaint_subtype'],
            ]);
            $stmtUp = $pdo->prepare("UPDATE cases SET status = 'escalated_to_mtc' WHERE id = :id");
            $stmtUp->execute([':id' => $case_id]);
            $pdo->commit();
            $message = "Case escalated to MTC as $mtc_case_number.";
            $case['status'] = 'escalated_to_mtc';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error escalating case: " . $e->getMessage();
        }
    } elseif (isset($_POST['escalate_rtc']) && in_array($role, ['mtc_staff','mtc_judge']) && $case['stage'] === 'mtc_case') {
        // Create new RTC case
        $year = date('Y');
        $random = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $rtc_case_number = 'RTC-' . $year . '-' . $random;

        $pdo->beginTransaction();
        try {
            $stmtIns = $pdo->prepare("INSERT INTO cases 
                (case_number, parent_case_id, stage, status, complainant_id, respondent_name, incident_details, incident_datetime, location, created_by, complaint_type, complaint_subtype)
                VALUES
                (:case_number, :parent_case_id, 'rtc_case', 'escalated_to_rtc', :complainant_id, :respondent_name, :incident_details, :incident_datetime, :location, :created_by, :complaint_type, :complaint_subtype)");
            $stmtIns->execute([
                ':case_number' => $rtc_case_number,
                ':parent_case_id' => $case['id'],
                ':complainant_id' => $case['complainant_id'],
                ':respondent_name' => $case['respondent_name'],
                ':incident_details' => $case['incident_details'],
                ':incident_datetime' => $case['incident_datetime'],
                ':location' => $case['location'],
                ':created_by' => $user_id,
                ':complaint_type' => $case['complaint_type'],
                ':complaint_subtype' => $case['complaint_subtype'],
            ]);
            $stmtUp = $pdo->prepare("UPDATE cases SET status = 'escalated_to_rtc' WHERE id = :id");
            $stmtUp->execute([':id' => $case_id]);
            $pdo->commit();
            $message = "Case escalated to RTC as $rtc_case_number.";
            $case['status'] = 'escalated_to_rtc';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error escalating case: " . $e->getMessage();
        }
    }
}

// Load documents
$stmtDoc = $pdo->prepare("SELECT * FROM case_documents WHERE case_id = :cid ORDER BY created_at DESC");
$stmtDoc->execute([':cid' => $case_id]);
$docs = $stmtDoc->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h3>Case: <?php echo htmlspecialchars($case['case_number']); ?></h3>

<?php if ($message): ?>
  <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <h5 class="card-title">Complainant: <?php echo htmlspecialchars($case['complainant_name']); ?></h5>
    <p><strong>Respondent:</strong> <?php echo htmlspecialchars($case['respondent_name']); ?></p>
    <p><strong>Category:</strong> <?php echo htmlspecialchars(strtoupper($case['complaint_type'])); ?> - <?php echo htmlspecialchars($case['complaint_subtype']); ?></p>
    <p><strong>Stage:</strong> <?php echo htmlspecialchars($case['stage']); ?></p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($case['status']); ?></p>
    <p><strong>Incident Date & Time:</strong> <?php echo htmlspecialchars($case['incident_datetime']); ?></p>
    <p><strong>Location:</strong> <?php echo htmlspecialchars($case['location']); ?></p>
    <p><strong>Details:</strong><br><?php echo nl2br(htmlspecialchars($case['incident_details'])); ?></p>
  </div>
</div>

<?php if ($role !== 'complainant'): ?>
<div class="card mb-3">
  <div class="card-body">
    <h5>Update Status</h5>
    <form method="post" class="row g-2">
      <div class="col-md-6">
        <input type="text" name="new_status" class="form-control" placeholder="e.g. under_mediation, settled, for_hearing, decided">
      </div>
      <div class="col-md-3">
        <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'police_staff' && $case['stage'] === 'police_blotter'): ?>
<div class="card mb-3">
  <div class="card-body">
    <h5>Escalate to Municipal Trial Court (MTC)</h5>
    <form method="post">
      <button type="submit" name="escalate_mtc" class="btn btn-warning">Escalate to MTC</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (in_array($role, ['mtc_staff','mtc_judge']) && $case['stage'] === 'mtc_case'): ?>
<div class="card mb-3">
  <div class="card-body">
    <h5>Escalate to Regional Trial Court (RTC)</h5>
    <form method="post">
      <button type="submit" name="escalate_rtc" class="btn btn-danger">Escalate to RTC</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <h5>Encrypted Documents</h5>
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Original Name</th>
          <th>MIME</th>
          <th>Size (bytes)</th>
          <th>Uploaded At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($docs as $d): ?>
        <tr>
          <td><?php echo htmlspecialchars($d['original_filename']); ?></td>
          <td><?php echo htmlspecialchars($d['mime_type']); ?></td>
          <td><?php echo htmlspecialchars($d['file_size']); ?></td>
          <td><?php echo htmlspecialchars($d['created_at']); ?></td>
          <td>
            <?php if (in_array($role, ['police_staff','mtc_staff','mtc_judge','rtc_staff','rtc_judge'])): ?>
              <a class="btn btn-sm btn-success" href="view_document.php?id=<?php echo $d['id']; ?>">View (Decrypt)</a>
            <?php else: ?>
              <span class="text-muted">Not authorized</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h5>Upload New Document (Encrypted)</h5>
    <form method="post" action="upload_document.php" enctype="multipart/form-data">
      <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
      <div class="mb-3">
        <input type="file" name="document" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Upload & Encrypt</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
