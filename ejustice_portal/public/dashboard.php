<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$role = current_user_role();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h3>Dashboard</h3>
<p>Welcome to the eJustice Portal.</p>

<?php if ($role === 'complainant'): ?>
    <div class="alert alert-info">
        <p>You can file a new complaint and check the status of your cases.</p>
        <a href="file_case.php" class="btn btn-primary btn-sm">File New Case</a>
        <a href="cases.php" class="btn btn-secondary btn-sm">My Cases</a>
    </div>
<?php elseif ($role === 'police_staff'): ?>
    <div class="alert alert-info">
        <p>View and manage police blotter cases.</p>
        <a href="cases.php" class="btn btn-primary btn-sm">Blotter Cases</a>
    </div>
<?php elseif (in_array($role, ['mtc_staff','mtc_judge'])): ?>
    <div class="alert alert-info">
        <p>View and manage Municipal Trial Court cases.</p>
        <a href="cases.php" class="btn btn-primary btn-sm">MTC Cases</a>
    </div>
<?php elseif (in_array($role, ['rtc_staff','rtc_judge'])): ?>
    <div class="alert alert-info">
        <p>View and manage Regional Trial Court cases.</p>
        <a href="cases.php" class="btn btn-primary btn-sm">RTC Cases</a>
    </div>
<?php elseif ($role === 'system_admin'): ?>
    <div class="alert alert-info">
        <p>System admin: manage database/users directly using phpMyAdmin or extend this system.</p>
        <a href="cases.php" class="btn btn-primary btn-sm">All Cases (read-only)</a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
