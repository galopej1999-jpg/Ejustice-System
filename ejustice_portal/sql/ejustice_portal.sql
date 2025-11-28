CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('complainant','police_staff','mtc_staff','mtc_judge','rtc_staff','rtc_judge','system_admin') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) NOT NULL UNIQUE,
    parent_case_id INT NULL,
    stage ENUM('police_blotter','mtc_case','rtc_case') NOT NULL,
    status VARCHAR(50) NOT NULL,
    complainant_id INT NOT NULL,
    respondent_name VARCHAR(255) NOT NULL,
    incident_details TEXT NOT NULL,
    incident_datetime DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    complaint_type ENUM('criminal','civil','administrative') NOT NULL,
    complaint_subtype VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complainant_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (parent_case_id) REFERENCES cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    iv VARBINARY(32) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
