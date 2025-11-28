-- Barangay Module Database Tables
-- Supports the complete Barangay Justice workflow

-- Barangay Information Table
CREATE TABLE IF NOT EXISTS barangay_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_name VARCHAR(255) NOT NULL UNIQUE,
    municipality VARCHAR(255) NOT NULL,
    province VARCHAR(255) NOT NULL,
    punong_barangay_name VARCHAR(255),
    contact_number VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_barangay_name (barangay_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Barangay Users (Staff, Lupon, Punong Barangay)
CREATE TABLE IF NOT EXISTS barangay_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    barangay_id INT NOT NULL,
    position VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (barangay_id) REFERENCES barangay_info(id) ON DELETE CASCADE,
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Barangay Records (Initial Complaints)
CREATE TABLE IF NOT EXISTS barangay_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_id INT NOT NULL,
    complaint_number VARCHAR(50) NOT NULL UNIQUE,
    complainant_name VARCHAR(255) NOT NULL,
    complainant_address VARCHAR(255) NOT NULL,
    complainant_contact VARCHAR(20),
    respondent_name VARCHAR(255) NOT NULL,
    respondent_address VARCHAR(255) NOT NULL,
    respondent_contact VARCHAR(20),
    dispute_category ENUM('CRIMINAL', 'CIVIL', 'ADMINISTRATIVE', 'OTHER') NOT NULL,
    dispute_subcategory VARCHAR(255),
    nature_of_dispute TEXT NOT NULL,
    incident_date DATE,
    incident_details TEXT,
    initial_mediation_date DATE,
    recorded_by INT NOT NULL,
    status ENUM('ACTIVE', 'MEDIATION_IN_PROGRESS', 'SETTLED', 'ESCALATED', 'DISMISSED', 'CLOSED') DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangay_info(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_complaint_number (complaint_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mediation Attempts/Efforts
CREATE TABLE IF NOT EXISTS barangay_mediation_efforts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_record_id INT NOT NULL,
    mediation_date DATE NOT NULL,
    attendees TEXT,
    effort_description TEXT NOT NULL,
    outcome_status ENUM('ONGOING', 'PARTIAL_SETTLEMENT', 'UNSUCCESSFUL', 'SETTLED') DEFAULT 'ONGOING',
    recorded_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_record_id) REFERENCES barangay_records(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_barangay_record_id (barangay_record_id),
    INDEX idx_mediation_date (mediation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Barangay Mediation Summary (Punong Barangay Notes)
CREATE TABLE IF NOT EXISTS barangay_mediation_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_record_id INT NOT NULL UNIQUE,
    punong_barangay_name VARCHAR(255),
    lupon_secretary_name VARCHAR(255),
    mediation_summary TEXT NOT NULL,
    complainant_attended TINYINT(1) DEFAULT 1,
    respondent_attended TINYINT(1) DEFAULT 1,
    settlement_attempts_made INT DEFAULT 0,
    observations TEXT,
    recommendations TEXT,
    pb_signature_path VARCHAR(255),
    signature_date DATETIME,
    status ENUM('DRAFT', 'SUBMITTED', 'FINALIZED') DEFAULT 'DRAFT',
    submitted_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_record_id) REFERENCES barangay_records(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    INDEX idx_barangay_record_id (barangay_record_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Barangay Settlement Agreement (Kasunduan)
CREATE TABLE IF NOT EXISTS barangay_settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_record_id INT NOT NULL UNIQUE,
    settlement_type ENUM('KASUNDUAN', 'CFA', 'CNA', 'OTHER') NOT NULL,
    settlement_terms TEXT,
    complainant_signature_path VARCHAR(255),
    respondent_signature_path VARCHAR(255),
    pb_signature_path VARCHAR(255),
    lupon_signature_path VARCHAR(255),
    signature_dates TEXT,
    qr_code_data TEXT,
    pdf_path VARCHAR(255),
    digital_signature_hash VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    verification_timestamp DATETIME,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_record_id) REFERENCES barangay_records(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_barangay_record_id (barangay_record_id),
    INDEX idx_settlement_type (settlement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Certificate to File Action (CFA) - for escalation
CREATE TABLE IF NOT EXISTS barangay_cfa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_record_id INT NOT NULL,
    settlement_id INT NULL,
    cfa_number VARCHAR(50) NOT NULL UNIQUE,
    reason_for_escalation TEXT NOT NULL,
    escalated_to ENUM('POLICE', 'MTC', 'OTHER') DEFAULT 'POLICE',
    escalated_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    police_blotter_number VARCHAR(50),
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT,
    acknowledged_date DATETIME,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_record_id) REFERENCES barangay_records(id),
    FOREIGN KEY (settlement_id) REFERENCES barangay_settlements(id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_barangay_record_id (barangay_record_id),
    INDEX idx_cfa_number (cfa_number),
    INDEX idx_escalated_date (escalated_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Barangay Document Storage (for uploaded documents/attachments)
CREATE TABLE IF NOT EXISTS barangay_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_record_id INT NOT NULL,
    document_type VARCHAR(100),
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_record_id) REFERENCES barangay_records(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_barangay_record_id (barangay_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Log for Barangay Actions
CREATE TABLE IF NOT EXISTS barangay_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_id INT,
    record_id INT,
    user_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangay_info(id),
    FOREIGN KEY (record_id) REFERENCES barangay_records(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_record_id (record_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update main users table to support barangay roles
ALTER TABLE users MODIFY COLUMN role ENUM('complainant','police_staff','mtc_staff','mtc_judge','rtc_staff','rtc_judge','system_admin','barangay_staff','punong_barangay','lupon_secretary') NOT NULL;
