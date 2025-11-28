<?php
/**
 * Barangay Escalation Helper Functions
 * Handles automatic escalation of unresolved cases to police blotter
 */

// Require audit functions if not already loaded
if (!function_exists('logAuditAction')) {
    require_once __DIR__ . '/audit.php';
}

/**
 * Escalate a Barangay case to Police Blotter
 * 
 * @param PDO $pdo Database connection
 * @param int $barangayRecordId Barangay record ID
 * @param string $cfaNumber Certificate to File Action number
 * @param int $userId User performing the escalation
 * @return bool|int Case ID created in police blotter or false on failure
 */
function escalateToPoliceBlotter($pdo, $barangayRecordId, $cfaNumber, $userId) {
    try {
        // NOTE: Caller is responsible for transaction management
        // This function assumes we're already inside a transaction
        
        // Get barangay record details
        $barangayRecordStmt = $pdo->prepare("SELECT * FROM barangay_records WHERE id = :id");
        $barangayRecordStmt->execute([':id' => $barangayRecordId]);
        $barangayRecord = $barangayRecordStmt->fetch();
        
        if (!$barangayRecord) {
            throw new Exception("Barangay record not found");
        }
        
        // Get or create a Police staff user for initial recording
        $policeStmt = $pdo->query("SELECT id FROM users WHERE role = 'police_staff' LIMIT 1");
        $policeUser = $policeStmt->fetch();
        
        if (!$policeUser) {
            throw new Exception("No police staff user found in system");
        }
        
        $policeUserId = $policeUser['id'];
        
        // Generate Police Blotter case number: PB-YYYYMM-NNNNN
        $monthYear = date('Ym');
        $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM cases WHERE case_number LIKE 'PB-$monthYear%'");
        $count = $countStmt->fetch()['cnt'] + 1;
        $caseNumber = 'PB-' . $monthYear . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
        
        // Create case in main cases table (police_blotter stage)
        $createCaseStmt = $pdo->prepare("INSERT INTO cases 
            (case_number, stage, status, complainant_id, respondent_name, incident_details, 
             incident_datetime, location, created_by, complaint_type, complaint_subtype)
            VALUES (:case_number, :stage, :status, :complainant_id, :respondent_name, 
                    :incident_details, :incident_datetime, :location, :created_by, 
                    :complaint_type, :complaint_subtype)");
        
        $createCaseStmt->execute([
            ':case_number' => $caseNumber,
            ':stage' => 'police_blotter',
            ':status' => 'FILED',
            ':complainant_id' => $barangayRecord['complainant_id'] ?? $userId,
            ':respondent_name' => $barangayRecord['respondent_name'],
            ':incident_details' => $barangayRecord['nature_of_dispute'] . "\n\nEscalated from Barangay Case #" . $barangayRecord['complaint_number'] . " (CFA: " . $cfaNumber . ")",
            ':incident_datetime' => $barangayRecord['incident_date'] ? $barangayRecord['incident_date'] . ' 00:00:00' : date('Y-m-d H:i:s'),
            ':location' => $barangayRecord['incident_details'],
            ':created_by' => $policeUserId,
            ':complaint_type' => strtolower($barangayRecord['dispute_category']),
            ':complaint_subtype' => $barangayRecord['dispute_subcategory']
        ]);
        
        $caseId = $pdo->lastInsertId();
        
        // Update the CFA record to link it to the police case
        $updateCfaStmt = $pdo->prepare("UPDATE barangay_cfa SET police_blotter_number = :case_number, is_acknowledged = 1, acknowledged_by = :acknowledged_by, acknowledged_date = NOW() WHERE cfa_number = :cfa_number");
        $updateCfaStmt->execute([
            ':case_number' => $caseNumber,
            ':acknowledged_by' => $policeUserId,
            ':cfa_number' => $cfaNumber
        ]);
        
        // Log the escalation
        $auditStmt = $pdo->prepare("INSERT INTO barangay_audit_log (barangay_id, record_id, user_id, action_type, action_details, ip_address)
                                   VALUES (:barangay_id, :record_id, :user_id, 'ESCALATED_TO_POLICE', :details, :ip)");
        $auditStmt->execute([
            ':barangay_id' => $barangayRecord['barangay_id'],
            ':record_id' => $barangayRecordId,
            ':user_id' => $userId,
            ':details' => "Escalated to Police Blotter: $caseNumber",
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Also log in main audit_logs table
        logAuditAction($pdo, $userId, 'CASE_CREATE', null, $caseId, 
            "Barangay escalation: $caseNumber from Barangay #" . $barangayRecord['complaint_number']);
        
        return $caseId;
        
    } catch (Exception $e) {
        // Don't rollback here - let caller handle transaction
        error_log("Escalation error: " . $e->getMessage());
        throw $e; // Re-throw to let caller handle
    }
}

/**
 * Check if a barangay case is ready for auto-escalation
 * (i.e., multiple mediation attempts failed and deadline passed)
 * 
 * @param PDO $pdo Database connection
 * @param int $recordId Barangay record ID
 * @param int $daysBeforeAutoEscalation Days to wait before auto-escalation (default 30)
 * @return bool True if ready for escalation
 */
function isReadyForAutoEscalation($pdo, $recordId, $daysBeforeAutoEscalation = 30) {
    try {
        $stmt = $pdo->prepare("SELECT br.*, COUNT(bme.id) as effort_count
                              FROM barangay_records br
                              LEFT JOIN barangay_mediation_efforts bme ON br.id = bme.barangay_record_id
                              WHERE br.id = :id
                              GROUP BY br.id");
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            return false;
        }
        
        // Check if record is still active (not settled or dismissed)
        if ($record['status'] === 'SETTLED' || $record['status'] === 'DISMISSED' || $record['status'] === 'ESCALATED') {
            return false;
        }
        
        // Check if minimum attempts made
        if ($record['effort_count'] < 2) {
            return false;
        }
        
        // Check if enough days have passed since creation
        $daysPassed = (strtotime('now') - strtotime($record['created_at'])) / (60 * 60 * 24);
        
        return $daysPassed >= $daysBeforeAutoEscalation;
        
    } catch (Exception $e) {
        error_log("Auto-escalation check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all cases pending escalation
 * 
 * @param PDO $pdo Database connection
 * @param int $barangayId Filter by barangay (optional)
 * @return array Array of barangay records ready for escalation
 */
function getPendingEscalations($pdo, $barangayId = null) {
    try {
        $query = "SELECT br.*, bms.recommendations 
                  FROM barangay_records br
                  LEFT JOIN barangay_mediation_summary bms ON br.id = bms.barangay_record_id
                  WHERE br.status = 'ESCALATED'
                  AND br.id NOT IN (SELECT barangay_record_id FROM barangay_cfa WHERE police_blotter_number IS NOT NULL)";
        
        if ($barangayId) {
            $query .= " AND br.barangay_id = " . (int)$barangayId;
        }
        
        $query .= " ORDER BY br.updated_at ASC";
        
        return $pdo->query($query)->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get pending escalations error: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a batch escalation report
 * 
 * @param PDO $pdo Database connection
 * @param int $barangayId Barangay ID
 * @param DateTime $startDate Report start date
 * @param DateTime $endDate Report end date
 * @return array Escalation statistics
 */
function getEscalationReport($pdo, $barangayId, $startDate = null, $endDate = null) {
    try {
        $startDate = $startDate ?: date('Y-01-01');
        $endDate = $endDate ?: date('Y-m-d');
        
        $stats = [];
        
        // Total barangay cases
        $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_records 
                                   WHERE barangay_id = :barangay_id 
                                   AND created_at BETWEEN :start AND :end");
        $totalStmt->execute([
            ':barangay_id' => $barangayId,
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        $stats['total_cases'] = $totalStmt->fetch()['total'];
        
        // Settled cases
        $settledStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_records 
                                      WHERE barangay_id = :barangay_id 
                                      AND status = 'SETTLED'
                                      AND updated_at BETWEEN :start AND :end");
        $settledStmt->execute([
            ':barangay_id' => $barangayId,
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        $stats['settled_cases'] = $settledStmt->fetch()['total'];
        
        // Escalated cases
        $escalatedStmt = $pdo->prepare("SELECT COUNT(*) as total FROM barangay_cfa 
                                        WHERE barangay_record_id IN (
                                            SELECT id FROM barangay_records WHERE barangay_id = :barangay_id
                                        )
                                        AND created_at BETWEEN :start AND :end");
        $escalatedStmt->execute([
            ':barangay_id' => $barangayId,
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        $stats['escalated_cases'] = $escalatedStmt->fetch()['total'];
        
        // Settlement rate
        $stats['settlement_rate'] = $stats['total_cases'] > 0 
            ? round(($stats['settled_cases'] / $stats['total_cases']) * 100, 2) 
            : 0;
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Escalation report error: " . $e->getMessage());
        return [];
    }
}
