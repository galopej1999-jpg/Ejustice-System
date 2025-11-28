<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow registering complainant accounts via this page
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$full_name || !$email || !$password) {
        $error = 'Please fill all fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) 
                                   VALUES (:full_name, :email, :password_hash, 'complainant')");
            $stmt->execute([
                ':full_name' => $full_name,
                ':email' => $email,
                ':password_hash' => $hash
            ]);
            $success = 'Registration successful! You can now login.';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Email already in use.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <h3>Register as Complainant</h3>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label>Full Name</label>
        <input type="text" name="full_name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Confirm Password</label>
        <input type="password" name="confirm" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">Register</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
