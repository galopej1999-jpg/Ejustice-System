<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(['complainant']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complainant_id = $_SESSION['user_id'];
    $respondent_name = trim($_POST['respondent_name'] ?? '');
    $incident_details = trim($_POST['incident_details'] ?? '');
    $incident_datetime = trim($_POST['incident_datetime'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $complaint_type = $_POST['complaint_type'] ?? '';
    $complaint_subtype = trim($_POST['complaint_subtype'] ?? '');
    $barangay_id = (int)($_POST['barangay_id'] ?? 0);

    if (!$respondent_name || !$incident_details || !$incident_datetime || !$location || !$complaint_type || !$complaint_subtype || !$barangay_id) {
        $error = 'Please fill in all fields including Barangay selection.';
    } else {
        try {
            $pdo->beginTransaction();

            // Get user full name for complainant
            $userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
            $userStmt->execute([':id' => $complainant_id]);
            $user = $userStmt->fetch();
            $complainant_name = $user['full_name'] ?? 'Online Complainant';

            // Step 1: Create record in cases table (linked to barangay)
            $case_year = date('Y');
            $case_random = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $case_number = 'BLT-' . $case_year . '-' . $case_random;

            $caseStmt = $pdo->prepare("INSERT INTO cases 
                (case_number, barangay_id, parent_case_id, stage, status, complainant_id, respondent_name, 
                 incident_details, incident_datetime, location, created_by, complaint_type, complaint_subtype)
                VALUES
                (:case_number, :barangay_id, NULL, 'barangay_initial', 'PENDING_BARANGAY', :complainant_id, 
                 :respondent_name, :incident_details, :incident_datetime, :location, :created_by, 
                 :complaint_type, :complaint_subtype)");
            
            $caseStmt->execute([
                ':case_number' => $case_number,
                ':barangay_id' => $barangay_id,
                ':complainant_id' => $complainant_id,
                ':respondent_name' => $respondent_name,
                ':incident_details' => $incident_details,
                ':incident_datetime' => $incident_datetime,
                ':location' => $location,
                ':created_by' => $complainant_id,
                ':complaint_type' => $complaint_type,
                ':complaint_subtype' => $complaint_subtype,
            ]);
            
            $case_id = $pdo->lastInsertId();

            // Step 2: Create Barangay record linked to online case
            $monthYear = date('Ym');
            $brCountStmt = $pdo->query("SELECT COUNT(*) as cnt FROM barangay_records WHERE complaint_number LIKE 'BR-$monthYear%'");
            $brCount = $brCountStmt->fetch()['cnt'] + 1;
            $complaint_number = 'BR-' . $monthYear . '-' . str_pad($brCount, 5, '0', STR_PAD_LEFT);

            // Extract incident date from datetime
            $incident_date = date('Y-m-d', strtotime($incident_datetime));

            // Map complaint type to dispute category
            $dispute_category = strtoupper($complaint_type);
            if ($dispute_category === 'CRIMINAL') $dispute_category = 'CRIMINAL';
            elseif ($dispute_category === 'CIVIL') $dispute_category = 'CIVIL';
            else $dispute_category = 'ADMINISTRATIVE';

            $barangayRecordStmt = $pdo->prepare("INSERT INTO barangay_records 
                (barangay_id, complaint_number, complainant_name, complainant_address, complainant_contact,
                 respondent_name, respondent_address, respondent_contact, dispute_category, dispute_subcategory,
                 nature_of_dispute, incident_date, incident_details, online_case_id, recorded_by, status)
                VALUES 
                (:barangay_id, :complaint_number, :complainant_name, :complainant_address, :complainant_contact,
                 :respondent_name, :respondent_address, :respondent_contact, :dispute_category, :dispute_subcategory,
                 :nature_of_dispute, :incident_date, :incident_details, :online_case_id, :recorded_by, 'ACTIVE')");

            $barangayRecordStmt->execute([
                ':barangay_id' => $barangay_id,
                ':complaint_number' => $complaint_number,
                ':complainant_name' => $complainant_name,
                ':complainant_address' => $location,
                ':complainant_contact' => '',
                ':respondent_name' => $respondent_name,
                ':respondent_address' => '',
                ':respondent_contact' => '',
                ':dispute_category' => $dispute_category,
                ':dispute_subcategory' => $complaint_subtype,
                ':nature_of_dispute' => $incident_details,
                ':incident_date' => $incident_date,
                ':incident_details' => $incident_details,
                ':online_case_id' => $case_id,
                ':recorded_by' => $complainant_id
            ]);

            $barangay_record_id = $pdo->lastInsertId();

            // Step 3: Link the case to the barangay record
            $linkStmt = $pdo->prepare("UPDATE cases SET barangay_record_id = :barangay_record_id WHERE id = :case_id");
            $linkStmt->execute([':barangay_record_id' => $barangay_record_id, ':case_id' => $case_id]);

            $pdo->commit();

            $success = "✅ Case filed successfully!<br/>
                        <strong>Case Number (Online):</strong> $case_number<br/>
                        <strong>Barangay Record:</strong> $complaint_number<br/>
                        <small class='text-muted'>Your case has been routed to the Barangay for initial mediation processing.</small>";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mt-4">
  <div class="row">
    <div class="col-md-8">
      <h3>📋 File New Case</h3>
      <p class="text-muted">Your case will be routed to your Barangay for initial mediation before escalation to Police Blotter if needed.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>

      <form method="post" class="card card-body card-court">

        <div class="mb-3">
          <label class="form-label">🏘️ Select Barangay <span class="text-danger">*</span></label>
          <select name="barangay_id" id="barangay_id" class="form-select" required>
            <option value="">-- Select Your Barangay --</option>
            <?php
            $barangayStmt = $pdo->query("SELECT id, barangay_name, municipality, province FROM barangay_info ORDER BY barangay_name ASC");
            $barangays = $barangayStmt->fetchAll();
            foreach ($barangays as $brgy):
              echo '<option value="' . $brgy['id'] . '">' . htmlspecialchars($brgy['barangay_name']) . ', ' . htmlspecialchars($brgy['municipality']) . ', ' . htmlspecialchars($brgy['province']) . '</option>';
            endforeach;
            ?>
          </select>
          <small class="form-text text-muted">Your case will be processed at your Barangay Lupon first.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Respondent Name <span class="text-danger">*</span></label>
          <input type="text" name="respondent_name" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Incident Details <span class="text-danger">*</span></label>
          <textarea name="incident_details" class="form-control" rows="4" required placeholder="Describe what happened in detail..."></textarea>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Incident Date & Time <span class="text-danger">*</span></label>
            <input type="datetime-local" name="incident_datetime" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Location <span class="text-danger">*</span></label>
            <input type="text" name="location" class="form-control" required placeholder="Where did this happen?">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Complaint Category <span class="text-danger">*</span></label>
            <select name="complaint_type" id="complaint_type" class="form-select" required>
              <option value="">-- Select Category --</option>
              <option value="criminal">Criminal</option>
              <option value="civil">Civil</option>
              <option value="administrative">Administrative</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Complaint Sub-Type <span class="text-danger">*</span></label>
            <select name="complaint_subtype" id="complaint_subtype" class="form-select" required>
              <option value="">-- Select Sub-Type --</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-court">📤 Submit Case to Barangay</button>
        <a href="dashboard.php" class="btn btn-secondary ms-2">← Back to Dashboard</a>
      </form>
    </div>

    <div class="col-md-4">
      <div class="card card-court">
        <div class="card-header bg-court text-white">
          <h5>ℹ️ Case Filing Process</h5>
        </div>
        <div class="card-body">
          <ol style="font-size: 0.9rem;">
            <li><strong>File Online</strong> - Submit your complaint here</li>
            <li><strong>Barangay Processing</strong> - Barangay Lupon will attempt mediation</li>
            <li><strong>Settlement or Escalation</strong> - If settled, case closes. If not, escalates to Police Blotter</li>
            <li><strong>Police Investigation</strong> - Police will handle formal investigation if needed</li>
            <li><strong>Court Filing</strong> - Court proceedings if necessary</li>
          </ol>
        </div>
      </div>

      <div class="card card-court mt-3">
        <div class="card-header bg-court text-white">
          <h5>⚖️ Katarungan Pambarangay</h5>
        </div>
        <div class="card-body" style="font-size: 0.9rem;">
          <p>Under Philippine law (Katarungan Pambarangay system), most disputes must first go through Barangay mediation.</p>
          <p class="mb-0"><small>This ensures fair, accessible justice at the local level.</small></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const subtypeOptions = {
  criminal: [
    'Crime against persons',
    'Crime against property',
    'Estafa / Fraud',
    'Cybercrime',
    'Drug-related offense (RA 9165)',
    'Child abuse (RA 7610)',
    'VAWC (RA 9262)',
    'Other criminal case'
  ],
  civil: [
    'Damage claims',
    'Breach of contract',
    'Property dispute',
    'Ejectment / Unlawful detainer',
    'Small claims',
    'Family civil matter',
    'Other civil case'
  ],
  administrative: [
    'Neglect of duty',
    'Abuse of authority',
    'Corruption / Bribery',
    'Misconduct',
    'Violation of RA 6713',
    'Other administrative case'
  ]
};

document.getElementById('complaint_type').addEventListener('change', function() {
  const type = this.value;
  const subSelect = document.getElementById('complaint_subtype');
  subSelect.innerHTML = '<option value=\"\">-- Select Sub-Type --</option>';
  if (subtypeOptions[type]) {
    subtypeOptions[type].forEach(function(label) {
      const opt = document.createElement('option');
      opt.value = label;
      opt.textContent = label;
      subSelect.appendChild(opt);
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
