-- Barangay Case Routing System
-- Links online-filed cases to Barangay for initial processing
-- Routes cases: Complainant Online Filing → Barangay → Police Blotter → Court

-- Add barangay_id to cases table to link online-filed cases to a barangay
ALTER TABLE cases ADD COLUMN barangay_id INT NULL AFTER parent_case_id;
ALTER TABLE cases ADD COLUMN barangay_record_id INT NULL AFTER barangay_id;
ALTER TABLE cases ADD CONSTRAINT fk_cases_barangay FOREIGN KEY (barangay_id) REFERENCES barangay_info(id) ON DELETE SET NULL;
ALTER TABLE cases ADD CONSTRAINT fk_cases_barangay_record FOREIGN KEY (barangay_record_id) REFERENCES barangay_records(id) ON DELETE SET NULL;
ALTER TABLE cases ADD INDEX idx_barangay_id (barangay_id);
ALTER TABLE cases ADD INDEX idx_barangay_record_id (barangay_record_id);

-- Add reference to online-filed case in barangay_records
ALTER TABLE barangay_records ADD COLUMN online_case_id INT NULL AFTER barangay_id;
ALTER TABLE barangay_records ADD CONSTRAINT fk_barangay_records_case FOREIGN KEY (online_case_id) REFERENCES cases(id) ON DELETE SET NULL;
ALTER TABLE barangay_records ADD INDEX idx_online_case_id (online_case_id);

-- Update status field to accommodate online case states
ALTER TABLE barangay_records MODIFY COLUMN status ENUM('ACTIVE', 'MEDIATION_IN_PROGRESS', 'SETTLED', 'ESCALATED', 'DISMISSED', 'CLOSED', 'WAITING_FOR_BARANGAY') DEFAULT 'ACTIVE';

-- Create index for faster lookups of pending online cases
CREATE INDEX idx_barangay_records_status_date ON barangay_records(barangay_id, status, created_at);
CREATE INDEX idx_cases_barangay_stage ON cases(barangay_id, stage, status);
