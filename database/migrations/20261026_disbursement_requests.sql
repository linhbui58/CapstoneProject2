CREATE TABLE IF NOT EXISTS disbursement_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    beneficiary VARCHAR(200) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    purpose TEXT NOT NULL,
    justification TEXT,
    requested_by INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'disbursed') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    review_note TEXT,
    evidence_url VARCHAR(255),
    evidence_note TEXT,
    disbursed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_disbursement_status (status),
    INDEX idx_disbursement_campaign (campaign_id),
    INDEX idx_disbursement_requester (requested_by),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

SET @has_evidence_url = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'fund_allocations'
      AND column_name = 'evidence_url'
);
SET @sql = IF(
    @has_evidence_url = 0,
    'ALTER TABLE fund_allocations ADD COLUMN evidence_url VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_evidence_note = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'fund_allocations'
      AND column_name = 'evidence_note'
);
SET @sql = IF(
    @has_evidence_note = 0,
    'ALTER TABLE fund_allocations ADD COLUMN evidence_note TEXT NULL',
    'SELECT 1'
);
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
