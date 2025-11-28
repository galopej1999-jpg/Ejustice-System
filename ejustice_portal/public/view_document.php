<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/audit.php';

$userId  = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? null;

$allowedViewRoles = ['police_staff','mtc_staff','mtc_judge','rtc_staff','rtc_judge'];
if (!in_array($userRole, $allowedViewRoles)) {
    die("Access denied");
}

$docId = (int) ($_GET['id'] ?? 0);
if (!$docId) {
    die("Invalid document id");
}

$sql = "SELECT d.*, c.stage, c.complainant_id 
        FROM case_documents d
        JOIN cases c ON d.case_id = c.id
        WHERE d.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $docId]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document not found");
}

// Enforce stage-based access
if ($userRole === 'police_staff' && $doc['stage'] !== 'police_blotter') {
    die("Access denied (wrong stage)");
}
if (in_array($userRole, ['mtc_staff','mtc_judge']) && $doc['stage'] !== 'mtc_case') {
    die("Access denied (wrong stage)");
}
if (in_array($userRole, ['rtc_staff','rtc_judge']) && $doc['stage'] !== 'rtc_case') {
    die("Access denied (wrong stage)");
}

$storageDir = __DIR__ . '/../storage/documents/';
$fullPath   = $storageDir . $doc['stored_filename'];

if (!file_exists($fullPath)) {
    die("File missing");
}

$encryptedData = file_get_contents($fullPath);

$decryptedData = openssl_decrypt(
    $encryptedData,
    DOC_ENC_METHOD,
    DOC_ENC_KEY,
    OPENSSL_RAW_DATA,
    $doc['iv']
);

if ($decryptedData === false) {
    die("Decryption failed");
}

// Log document access
logAuditAction($pdo, $userId, 'DOCUMENT_DECRYPT', $docId, $doc['case_id'], 
    'Document: ' . $doc['original_filename']);

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
header('Content-Length: ' . strlen($decryptedData));

echo $decryptedData;
exit;
