<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/config.php';

$userId  = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? null;

$allowedUploadRoles = ['complainant','police_staff','mtc_staff','rtc_staff','mtc_judge','rtc_judge'];
if (!in_array($userRole, $allowedUploadRoles)) {
    die("Access denied");
}

$caseId = (int) ($_POST['case_id'] ?? 0);
if (!$caseId) {
    die("Invalid case id");
}

// Check case exists
$stmt = $pdo->prepare("SELECT * FROM cases WHERE id = :id");
$stmt->execute([':id' => $caseId]);
$case = $stmt->fetch();
if (!$case) {
    die("Case not found");
}

// Complainant can only upload to their own case
if ($userRole === 'complainant' && $case['complainant_id'] != $userId) {
    die("Access denied");
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    die("Upload error");
}

$originalName = $_FILES['document']['name'];
$tmpPath      = $_FILES['document']['tmp_name'];
$mimeType     = mime_content_type($tmpPath);
$fileSize     = filesize($tmpPath);

$fileData = file_get_contents($tmpPath);

$ivLength = openssl_cipher_iv_length(DOC_ENC_METHOD);
$iv       = openssl_random_pseudo_bytes($ivLength);

$encryptedData = openssl_encrypt(
    $fileData,
    DOC_ENC_METHOD,
    DOC_ENC_KEY,
    OPENSSL_RAW_DATA,
    $iv
);

$randomName   = bin2hex(random_bytes(16)) . '.enc';
$storageDir   = __DIR__ . '/../storage/documents/';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}
$fullPath = $storageDir . $randomName;

file_put_contents($fullPath, $encryptedData);

$stmt = $pdo->prepare("INSERT INTO case_documents
    (case_id, uploaded_by, original_filename, stored_filename, mime_type, file_size, iv)
    VALUES (:case_id, :uploaded_by, :original_filename, :stored_filename, :mime_type, :file_size, :iv)");

$stmt->execute([
    ':case_id'          => $caseId,
    ':uploaded_by'      => $userId,
    ':original_filename'=> $originalName,
    ':stored_filename'  => $randomName,
    ':mime_type'        => $mimeType,
    ':file_size'        => $fileSize,
    ':iv'               => $iv
]);

header('Location: case_view.php?id=' . $caseId);
exit;
