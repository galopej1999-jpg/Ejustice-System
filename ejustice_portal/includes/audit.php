<?php
/**
 * Audit Log Helper Functions
 * Logs user actions for compliance and security auditing
 */

/**
 * Log an action to the audit_logs table
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User performing the action
 * @param string $action Action type (e.g., 'DOCUMENT_VIEW', 'DOCUMENT_DECRYPT')
 * @param int|null $documentId Document ID if applicable
 * @param int|null $caseId Case ID if applicable
 * @param string|null $details Additional action details
 * @return bool Success status
 */
function logAuditAction($pdo, $userId, $action, $documentId = null, $caseId = null, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $sql = "INSERT INTO audit_logs (user_id, document_id, case_id, action, action_details, ip_address, user_agent)
                VALUES (:user_id, :document_id, :case_id, :action, :action_details, :ip_address, :user_agent)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':document_id' => $documentId,
            ':case_id' => $caseId,
            ':action' => $action,
            ':action_details' => $details,
            ':ip_address' => $ip,
            ':user_agent' => $userAgent
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get readable action label
 * 
 * @param string $action Action type
 * @return string Human-readable action label
 */
function getActionLabel($action) {
    $labels = [
        'DOCUMENT_VIEW' => '📄 Document Viewed',
        'DOCUMENT_DECRYPT' => '🔓 Document Decrypted',
        'DOCUMENT_UPLOAD' => '📤 Document Uploaded',
        'CASE_VIEW' => '👁️ Case Viewed',
        'CASE_CREATE' => '✚ Case Created',
        'CASE_UPDATE' => '✏️ Case Updated',
        'USER_LOGIN' => '🔑 User Login',
        'USER_LOGOUT' => '🚪 User Logout',
        'RECORD_CREATED' => '📝 Record Created',
        'MEDIATION_ADDED' => '🤝 Mediation Recorded',
        'SETTLEMENT_CREATED' => '📋 Settlement Generated',
        'ESCALATED_TO_POLICE' => '🚔 Escalated to Police',
    ];
    
    return $labels[$action] ?? htmlspecialchars($action);
}
