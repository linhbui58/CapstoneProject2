-- =====================================================
-- VCF Donation & Fundraising Management System
-- Database Schema
-- =====================================================

CREATE DATABASE IF NOT EXISTS vcf_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vcf_donation;

-- =====================================================
-- USERS TABLE (All roles)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('donor', 'finance_staff', 'campaign_staff', 'manager') NOT NULL,
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- DONOR PROFILES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS donor_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address TEXT,
    organization VARCHAR(150),
    donor_type ENUM('individual', 'organization') DEFAULT 'individual',
    total_donated DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- CAMPAIGNS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    target_amount DECIMAL(15,2) NOT NULL,
    raised_amount DECIMAL(15,2) DEFAULT 0.00,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'active', 'completed', 'closed') DEFAULT 'draft',
    image_url VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =====================================================
-- DONATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    campaign_id INT,
    amount DECIMAL(15,2) NOT NULL,
    channel ENUM('bank_transfer', 'e_wallet', 'credit_card', 'paypal', 'cash') NOT NULL,
    message TEXT,
    is_anonymous TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
);

-- =====================================================
-- TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    reference_code VARCHAR(100),
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    transaction_date DATETIME,
    amount_transferred DECIMAL(15,2),
    verification_status ENUM('pending_verification', 'verified', 'rejected') DEFAULT 'pending_verification',
    rejection_reason TEXT,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- VERIFICATION HISTORY TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS verification_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    action ENUM('submitted', 'verified', 'rejected', 'resubmitted') NOT NULL,
    performed_by INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- =====================================================
-- FUND ALLOCATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS fund_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    description TEXT,
    allocated_by INT NOT NULL,
    allocation_date DATE NOT NULL,
    status ENUM('planned', 'disbursed', 'completed') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (allocated_by) REFERENCES users(id)
);

-- =====================================================
-- NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    type ENUM('confirmation', 'rejection', 'campaign_update', 'system') DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Default password for all accounts: password
INSERT INTO users (name, email, password, role, phone) VALUES
('Admin Manager', 'manager@vcf.org', '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'manager', '0901234567'),
('Nguyen Thi Man', 'finance@vcf.org', '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'finance_staff', '0901234568'),
('Ngo Gia Nguyen', 'campaign@vcf.org', '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'campaign_staff', '0901234569'),
('Bui Thi Linh', 'donor@vcf.org', '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor', '0901234570');

INSERT INTO donor_profiles (user_id, address, donor_type) VALUES
(4, 'Ho Chi Minh City, Vietnam', 'individual');

INSERT INTO campaigns (title, description, target_amount, raised_amount, start_date, end_date, status, created_by) VALUES
('Education for Rural Children', 'Support education programs for underprivileged children in rural Vietnam. Funds will be used for school supplies, scholarships, and infrastructure improvements.', 500000000, 125000000, '2026-01-01', '2026-12-31', 'active', 3),
('Clean Water Initiative', 'Provide clean water access to remote communities in the Central Highlands. This campaign aims to build water filtration systems and wells.', 300000000, 210000000, '2026-02-01', '2026-10-31', 'active', 3),
('Healthcare Outreach Program', 'Mobile healthcare units to bring medical services to underserved communities across Vietnam.', 800000000, 45000000, '2026-03-01', '2026-12-31', 'active', 3);

-- =====================================================
-- END OF SCHEMA
-- =====================================================
