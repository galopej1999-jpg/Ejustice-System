-- Audit Logs Table for document access tracking
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NULL,
    case_id INT NULL,
    action VARCHAR(50) NOT NULL,
    action_details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES case_documents(id) ON DELETE SET NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_document_id (document_id),
    INDEX idx_case_id (case_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
