<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$role = current_user_role();
$user_id = $_SESSION['user_id'];

// Build query based on role
$sql = "SELECT * FROM cases";
$params = [];

if ($role === 'complainant') {
    $sql .= " WHERE complainant_id = :uid ORDER BY created_at DESC";
    $params[':uid'] = $user_id;
} elseif ($role === 'police_staff') {
    $sql .= " WHERE stage = 'police_blotter' ORDER BY created_at DESC";
} elseif (in_array($role, ['mtc_staff','mtc_judge'])) {
    $sql .= " WHERE stage = 'mtc_case' ORDER BY created_at DESC";
} elseif (in_array($role, ['rtc_staff','rtc_judge'])) {
    $sql .= " WHERE stage = 'rtc_case' ORDER BY created_at DESC";
} elseif ($role === 'system_admin') {
    $sql .= " ORDER BY created_at DESC";
} else {
    $sql .= " WHERE 1=0"; // no cases
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h3>Cases</h3>

<table class="table table-bordered table-striped table-sm">
  <thead>
    <tr>
      <th>Case No.</th>
      <th>Category</th>
      <th>Sub-Type</th>
      <th>Stage</th>
      <th>Status</th>
      <th>Respondent</th>
      <th>Filed At</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($cases as $c): ?>
    <tr>
      <td><?php echo htmlspecialchars($c['case_number']); ?></td>
      <td><?php echo htmlspecialchars(strtoupper($c['complaint_type'])); ?></td>
      <td><?php echo htmlspecialchars($c['complaint_subtype']); ?></td>
      <td><?php echo htmlspecialchars($c['stage']); ?></td>
      <td><?php echo htmlspecialchars($c['status']); ?></td>
      <td><?php echo htmlspecialchars($c['respondent_name']); ?></td>
      <td><?php echo htmlspecialchars($c['created_at']); ?></td>
      <td><a class="btn btn-sm btn-primary" href="case_view.php?id=<?php echo $c['id']; ?>">View</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
