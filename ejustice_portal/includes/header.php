<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>eJustice Portal - Philippine Court System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/court_system.css">
</head>
<body>
<!-- Philippine Court System Header -->
<div class="court-header">
    <div class="container-fluid">
        <div class="seal-container">
            <span class="court-seal">⚖️</span>
            <span class="court-seal">🇵🇭</span>
            <span class="court-seal">⚖️</span>
        </div>
        <div class="text-center">
            <h1 class="court-header-title">REPUBLIC OF THE PHILIPPINES</h1>
            <p class="court-header-subtitle">OFFICE OF THE COURT ADMINISTRATOR</p>
            <p class="court-info">eJustice Portal - Online Blotter & Case Management System</p>
        </div>
    </div>
</div>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-court sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <span class="me-2">⚖️</span>eJustice Portal
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">📊 Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="cases.php">📋 Cases</a></li>
                    <?php if ($_SESSION['role'] === 'complainant'): ?>
                        <li class="nav-item"><a class="nav-link" href="file_case.php">📝 File Case</a></li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['role'], ['barangay_staff', 'punong_barangay', 'lupon_secretary', 'system_admin'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="barangayNav" role="button" data-bs-toggle="dropdown">
                                🏘️ Barangay
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="barangayNav">
                                <li><a class="dropdown-item" href="barangay_dashboard.php">📊 Dashboard</a></li>
                                <li><a class="dropdown-item" href="barangay_record_entry.php">📝 Record Entry</a></li>
                                <li><a class="dropdown-item" href="barangay_record_entry.php">📂 All Records</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="barangay_settlement_form.php">📜 Forms</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['role'], ['system_admin', 'rtc_judge', 'rtc_staff'])): ?>
                        <li class="nav-item"><a class="nav-link" href="audit_logs.php">🔍 Audit Logs</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">🔐 Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">📝 Register</a></li>
                <?php else: ?>
                    <li class="nav-item">
                        <span class="navbar-text me-2">
                            👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
                            <span class="badge badge-role <?php 
                                if (strpos($_SESSION['role'], 'judge') !== false) echo 'badge-judge';
                                elseif ($_SESSION['role'] === 'complainant') echo 'badge-complainant';
                                else echo 'badge-staff';
                            ?>">
                                <?php 
                                    $roleLabels = [
                                        'complainant' => 'Complainant',
                                        'police_staff' => 'Police Staff',
                                        'mtc_staff' => 'MTC Staff',
                                        'mtc_judge' => 'MTC Judge',
                                        'rtc_staff' => 'RTC Staff',
                                        'rtc_judge' => 'RTC Judge',
                                        'system_admin' => 'System Admin'
                                    ];
                                    echo $roleLabels[$_SESSION['role']] ?? $_SESSION['role'];
                                ?>
                            </span>
                        </span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
