CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NULL,
    transaction_id INT NULL,
    recipient_email VARCHAR(150) NOT NULL,
    email_type VARCHAR(30) NOT NULL DEFAULT 'thank_you',
    status ENUM('sent','failed') NOT NULL,
    error_message VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_logs_status (status),
    INDEX idx_email_logs_donor (donor_id),
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
);
