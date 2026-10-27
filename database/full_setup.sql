-- =====================================================
-- VCF Donation & Fundraising Management System
-- Database Schema
-- =====================================================

CREATE DATABASE IF NOT EXISTS vcf_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vcf_donation;
SET NAMES utf8mb4;

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
-- CAMPAIGN UPDATES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS campaign_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(255),
    posted_by INT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_updates_campaign (campaign_id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id)
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
    frequency ENUM('one_time', 'monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'one_time',
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
    evidence_url VARCHAR(255),
    evidence_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (allocated_by) REFERENCES users(id)
);

-- =====================================================
-- DISBURSEMENT REQUESTS TABLE
-- =====================================================
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


-- =====================================================
-- VCF Seed Data — Realistic Sample Data
-- Run AFTER setup.php or vcf_database.sql
-- =====================================================
USE vcf_donation;

-- Clear existing data (keep users)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE verification_history;
TRUNCATE TABLE fund_allocations;
TRUNCATE TABLE transactions;
TRUNCATE TABLE donations;
TRUNCATE TABLE notifications;
TRUNCATE TABLE campaigns;
TRUNCATE TABLE donor_profiles;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- USERS — password = 'password' for all
-- =====================================================
DELETE FROM users;

INSERT INTO users (id, name, email, password, role, phone, is_active) VALUES
(1, 'Nguyen Thi Man',     'manager@vcf.org',   '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'manager',        '0901234567', 1),
(2, 'Tran Van Duc',       'finance@vcf.org',   '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'finance_staff',  '0901234568', 1),
(3, 'Ngo Gia Nguyen',     'campaign@vcf.org',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'campaign_staff', '0901234569', 1),
(4, 'Le Thi Bich Ngoc',   'donor1@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0908123456', 1),
(5, 'Pham Minh Hoang',    'donor2@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0912345678', 1),
(6, 'Vo Thi Lan',         'donor3@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0987654321', 1),
(7, 'Nguyen Van Thanh',   'donor4@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0934567890', 1),
(8, 'Truong Thi Mai',     'donor5@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0945678901', 1),
(9, 'Bui Van Khanh',      'donor6@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0956789012', 1),
(10,'Do Thi Thu Huong',   'donor7@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0967890123', 1),
(11,'Hoang Duc Long',     'donor8@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0978901234', 1),
(12,'Dang Thi Kim Oanh',  'donor9@gmail.com',  '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0989012345', 1),
(13,'Ly Minh Tuan',       'donor10@gmail.com', '$2y$10$LfdPDdyZT.2izrk1brK/BOej7sCcWkRjgbWuEkB7r3gTNbEIpIPiW', 'donor',          '0990123456', 1);

-- Donor profiles
INSERT INTO donor_profiles (user_id, address, donor_type, total_donated) VALUES
(4,  'Ho Chi Minh City',  'individual',    5500000),
(5,  'Ha Noi',            'individual',    12000000),
(6,  'Da Nang',           'individual',    3000000),
(7,  'Can Tho',           'individual',    8000000),
(8,  'Hue',               'individual',    2000000),
(9,  'Bien Hoa',          'individual',    15000000),
(10, 'Vung Tau',          'individual',    4500000),
(11, 'Nha Trang',         'individual',    6000000),
(12, 'Hai Phong',         'individual',    9000000),
(13, 'Quang Ninh',        'organization',  25000000);

-- =====================================================
-- CAMPAIGNS
-- =====================================================
INSERT INTO campaigns (id, title, description, target_amount, raised_amount, start_date, end_date, status, image_url, created_by) VALUES
(1, 'A Brighter Future for Rural Schools',
   'Support education programs for underprivileged children in rural Vietnam. Funds will be used for school supplies, scholarships, infrastructure improvements, and teacher training programs in remote mountain areas.',
   500000000, 325000000, '2026-01-01', '2026-12-31', 'active',
   'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&q=80', 3),

(2, 'Healthcare Access Initiative',
   'Provide mobile healthcare units to bring essential medical services to underserved communities across Vietnam. This campaign focuses on preventive care, vaccinations, and basic medical consultations for rural populations.',
   300000000, 198000000, '2026-02-01', '2026-10-31', 'active',
   'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=600&q=80', 3),

(3, 'Community Clean Water Project',
   'Deliver clean and safe drinking water to remote communities in the Central Highlands. Funds will build water filtration systems, wells, and sanitation facilities for villages currently relying on unsafe water sources.',
   400000000, 412000000, '2026-01-15', '2026-09-30', 'completed',
   'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=600&q=80', 3),

(4, 'Scholarship for Talented Students',
   'Provide scholarships to talented students from low-income families who demonstrate academic excellence. The program covers tuition, books, and living expenses for university students across Vietnam.',
   600000000, 145000000, '2026-03-01', '2026-12-31', 'active',
   'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=80', 3),

(5, 'Elderly Care Support Program',
   'Support elderly citizens living alone in rural areas with food, medicine, and companionship programs. Monthly visits, health checkups, and emergency support for seniors without family support.',
   250000000, 89000000, '2026-04-01', '2026-12-31', 'active',
   'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&q=80', 3),

(6, 'Disaster Relief Fund 2026',
   'Emergency relief fund for communities affected by floods, storms, and natural disasters in Vietnam. Rapid response funding for food, shelter, and immediate medical assistance during crisis situations.',
   800000000, 562000000, '2026-01-01', '2026-12-31', 'active',
   'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?w=600&q=80', 3);

-- =====================================================
-- DONATIONS
-- =====================================================
INSERT INTO donations (id, donor_id, campaign_id, amount, channel, frequency, message, is_anonymous, status, created_at) VALUES
-- Campaign 1: Rural Schools
(1,  4,  1, 2000000,  'bank_transfer', 'monthly',   'Hope this helps the kids!', 0, 'verified',  '2026-01-15 09:23:00'),
(2,  5,  1, 5000000,  'bank_transfer', 'one_time',  'Education is the future', 0, 'verified',  '2026-01-18 14:10:00'),
(3,  6,  1, 1000000,  'e_wallet',      'one_time',  '', 0, 'verified',  '2026-01-22 10:45:00'),
(4,  7,  1, 3000000,  'bank_transfer', 'monthly',   'For the children of Vietnam', 0, 'verified',  '2026-02-05 16:30:00'),
(5,  13, 1, 10000000, 'bank_transfer', 'quarterly', 'Our company supports education', 0, 'verified',  '2026-02-10 11:00:00'),
(6,  9,  1, 5000000,  'credit_card',   'one_time',  '', 1, 'verified',  '2026-02-20 13:20:00'),
(7,  8,  1, 500000,   'e_wallet',      'monthly',   'Small amount but with big heart', 0, 'verified',  '2026-03-01 08:15:00'),
(8,  10, 1, 1500000,  'bank_transfer', 'one_time',  '', 0, 'verified',  '2026-03-15 15:40:00'),
(9,  4,  1, 3000000,  'bank_transfer', 'monthly',   'Monthly contribution', 0, 'pending',   '2026-10-01 09:00:00'),

-- Campaign 2: Healthcare
(10, 5,  2, 7000000,  'bank_transfer', 'one_time',  'Healthcare for all', 0, 'verified',  '2026-02-15 10:30:00'),
(11, 11, 2, 2000000,  'e_wallet',      'monthly',   '', 0, 'verified',  '2026-02-20 14:00:00'),
(12, 12, 2, 3000000,  'bank_transfer', 'one_time',  'Supporting rural healthcare', 0, 'verified',  '2026-03-05 09:45:00'),
(13, 13, 2, 15000000, 'bank_transfer', 'yearly',    'Corporate donation', 0, 'verified',  '2026-03-10 11:30:00'),
(14, 7,  2, 2000000,  'credit_card',   'one_time',  '', 0, 'verified',  '2026-04-01 16:00:00'),
(15, 6,  2, 1000000,  'e_wallet',      'monthly',   '', 0, 'pending',   '2026-10-02 08:00:00'),

-- Campaign 3: Clean Water (completed)
(16, 9,  3, 10000000, 'bank_transfer', 'one_time',  'Clean water is a basic right', 0, 'verified',  '2026-01-20 10:00:00'),
(17, 5,  3, 5000000,  'bank_transfer', 'quarterly', '', 0, 'verified',  '2026-02-01 14:30:00'),
(18, 13, 3, 20000000, 'bank_transfer', 'one_time',  'Full corporate support', 0, 'verified',  '2026-02-15 11:00:00'),
(19, 4,  3, 2000000,  'e_wallet',      'monthly',   '', 0, 'verified',  '2026-03-01 09:00:00'),

-- Campaign 4: Scholarships
(20, 7,  4, 3000000,  'bank_transfer', 'monthly',   'Invest in young minds', 0, 'verified',  '2026-03-10 10:00:00'),
(21, 10, 4, 2000000,  'bank_transfer', 'one_time',  '', 0, 'verified',  '2026-03-20 14:00:00'),
(22, 11, 4, 4000000,  'credit_card',   'one_time',  'For a brighter future', 0, 'verified',  '2026-04-05 11:30:00'),
(23, 8,  4, 1500000,  'e_wallet',      'monthly',   '', 0, 'pending',   '2026-10-02 07:30:00'),

-- Campaign 5: Elderly Care
(24, 6,  5, 1000000,  'e_wallet',      'monthly',   'For the elderly', 0, 'verified',  '2026-04-10 09:00:00'),
(25, 12, 5, 2000000,  'bank_transfer', 'quarterly', '', 0, 'verified',  '2026-04-15 14:00:00'),
(26, 4,  5, 500000,   'e_wallet',      'monthly',   '', 0, 'verified',  '2026-05-01 10:00:00'),
(27, 5,  5, 2000000,  'bank_transfer', 'monthly',   '', 0, 'pending',   '2026-10-02 06:00:00'),

-- Campaign 6: Disaster Relief
(28, 9,  6, 5000000,  'bank_transfer', 'one_time',  'Help flood victims', 0, 'verified',  '2026-01-25 10:00:00'),
(29, 13, 6, 20000000, 'bank_transfer', 'yearly',    'Emergency corporate support', 0, 'verified',  '2026-02-05 11:00:00'),
(30, 7,  6, 3000000,  'bank_transfer', 'one_time',  '', 0, 'verified',  '2026-02-10 14:30:00'),
(31, 10, 6, 2000000,  'e_wallet',      'one_time',  '', 0, 'verified',  '2026-03-01 09:00:00'),
(32, 11, 6, 4000000,  'bank_transfer', 'monthly',   'Disaster response', 0, 'verified',  '2026-03-15 11:00:00'),
(33, 12, 6, 9000000,  'credit_card',   'one_time',  '', 0, 'pending',   '2026-10-02 08:30:00');

-- =====================================================
-- TRANSACTIONS
-- =====================================================
INSERT INTO transactions (donation_id, reference_code, bank_name, account_number, transaction_date, amount_transferred, verification_status, verified_by, verified_at) VALUES
(1,  'VCB2026011501', 'Vietcombank',  '0071001234567', '2026-01-15 09:20:00', 2000000,  'verified', 2, '2026-01-16 10:00:00'),
(2,  'VCB2026011802', 'Vietcombank',  '0071001234567', '2026-01-18 14:05:00', 5000000,  'verified', 2, '2026-01-19 09:30:00'),
(3,  'MOMO20260122',  'MoMo',         'MOMO-VCF',      '2026-01-22 10:42:00', 1000000,  'verified', 2, '2026-01-23 08:15:00'),
(4,  'VTB2026020501', 'VietinBank',   '1234567890',    '2026-02-05 16:25:00', 3000000,  'verified', 2, '2026-02-06 09:00:00'),
(5,  'VCB2026021001', 'Vietcombank',  '0071001234567', '2026-02-10 10:55:00', 10000000, 'verified', 2, '2026-02-11 10:00:00'),
(6,  'VISA2026022001','Visa',         'VISA-XXXX4521', '2026-02-20 13:15:00', 5000000,  'verified', 2, '2026-02-21 09:00:00'),
(7,  'ZALO20260301',  'ZaloPay',      'ZALO-VCF',      '2026-03-01 08:10:00', 500000,   'verified', 2, '2026-03-02 08:30:00'),
(8,  'BIDV2026031501','BIDV',         '31410001234',   '2026-03-15 15:35:00', 1500000,  'verified', 2, '2026-03-16 09:00:00'),
(9,  'VCB2026100101', 'Vietcombank',  '0071001234567', '2026-10-01 08:55:00', 3000000,  'pending_verification', NULL, NULL),
(10, 'VCB2026021502', 'Vietcombank',  '0071001234567', '2026-02-15 10:25:00', 7000000,  'verified', 2, '2026-02-16 10:00:00'),
(11, 'MOMO20260220',  'MoMo',         'MOMO-VCF',      '2026-02-20 13:55:00', 2000000,  'verified', 2, '2026-02-21 10:00:00'),
(12, 'ACB2026030501', 'ACB',          '22222222',      '2026-03-05 09:40:00', 3000000,  'verified', 2, '2026-03-06 09:00:00'),
(13, 'VCB2026031002', 'Vietcombank',  '0071001234567', '2026-03-10 11:25:00', 15000000, 'verified', 2, '2026-03-11 10:00:00'),
(14, 'VISA2026040101','Visa',         'VISA-XXXX8832', '2026-04-01 15:55:00', 2000000,  'verified', 2, '2026-04-02 09:00:00'),
(15, 'MOMO20261002',  'MoMo',         'MOMO-VCF',      '2026-10-02 07:55:00', 1000000,  'pending_verification', NULL, NULL),
(16, 'VCB2026012001', 'Vietcombank',  '0071001234567', '2026-01-20 09:55:00', 10000000, 'verified', 2, '2026-01-21 09:00:00'),
(17, 'VTB2026020101', 'VietinBank',   '1234567890',    '2026-02-01 14:25:00', 5000000,  'verified', 2, '2026-02-02 10:00:00'),
(18, 'VCB2026021503', 'Vietcombank',  '0071001234567', '2026-02-15 10:55:00', 20000000, 'verified', 2, '2026-02-16 11:00:00'),
(19, 'MOMO20260301',  'MoMo',         'MOMO-VCF',      '2026-03-01 08:55:00', 2000000,  'verified', 2, '2026-03-02 09:00:00'),
(20, 'BIDV2026031001','BIDV',         '31410001234',   '2026-03-10 09:55:00', 3000000,  'verified', 2, '2026-03-11 09:00:00'),
(21, 'VCB2026032001', 'Vietcombank',  '0071001234567', '2026-03-20 13:55:00', 2000000,  'verified', 2, '2026-03-21 10:00:00'),
(22, 'VISA2026040501','Visa',         'VISA-XXXX1123', '2026-04-05 11:25:00', 4000000,  'verified', 2, '2026-04-06 09:00:00'),
(23, 'ZALO20261002',  'ZaloPay',      'ZALO-VCF',      '2026-10-02 07:25:00', 1500000,  'pending_verification', NULL, NULL),
(24, 'MOMO20260410',  'MoMo',         'MOMO-VCF',      '2026-04-10 08:55:00', 1000000,  'verified', 2, '2026-04-11 09:00:00'),
(25, 'VCB2026041501', 'Vietcombank',  '0071001234567', '2026-04-15 13:55:00', 2000000,  'verified', 2, '2026-04-16 09:00:00'),
(26, 'MOMO20260501',  'MoMo',         'MOMO-VCF',      '2026-05-01 09:55:00', 500000,   'verified', 2, '2026-05-02 09:00:00'),
(27, 'VCB2026100201', 'Vietcombank',  '0071001234567', '2026-10-02 05:55:00', 2000000,  'pending_verification', NULL, NULL),
(28, 'VCB2026012501', 'Vietcombank',  '0071001234567', '2026-01-25 09:55:00', 5000000,  'verified', 2, '2026-01-26 09:00:00'),
(29, 'VCB2026020501', 'Vietcombank',  '0071001234567', '2026-02-05 10:55:00', 20000000, 'verified', 2, '2026-02-06 10:00:00'),
(30, 'VTB2026021001', 'VietinBank',   '1234567890',    '2026-02-10 14:25:00', 3000000,  'verified', 2, '2026-02-11 09:00:00'),
(31, 'MOMO20260301B', 'MoMo',         'MOMO-VCF',      '2026-03-01 08:55:00', 2000000,  'verified', 2, '2026-03-02 10:00:00'),
(32, 'ACB2026031501', 'ACB',          '22222222',      '2026-03-15 10:55:00', 4000000,  'verified', 2, '2026-03-16 10:00:00'),
(33, 'VISA2026100201','Visa',         'VISA-XXXX7734', '2026-10-02 08:25:00', 9000000,  'pending_verification', NULL, NULL);

-- =====================================================
-- VERIFICATION HISTORY
-- =====================================================
INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes, created_at) VALUES
(1,  'submitted', 4, NULL, 'pending_verification', 'Donation submitted by donor', '2026-01-15 09:23:00'),
(1,  'verified',  2, 'pending_verification', 'verified', 'Amount matches, reference valid', '2026-01-16 10:00:00'),
(2,  'submitted', 5, NULL, 'pending_verification', 'Donation submitted by donor', '2026-01-18 14:10:00'),
(2,  'verified',  2, 'pending_verification', 'verified', 'All criteria satisfied', '2026-01-19 09:30:00'),
(3,  'submitted', 6, NULL, 'pending_verification', 'Donation submitted by donor', '2026-01-22 10:45:00'),
(3,  'verified',  2, 'pending_verification', 'verified', 'MoMo transfer confirmed', '2026-01-23 08:15:00'),
(4,  'submitted', 7, NULL, 'pending_verification', 'Donation submitted by donor', '2026-02-05 16:30:00'),
(4,  'verified',  2, 'pending_verification', 'verified', 'Transaction verified successfully', '2026-02-06 09:00:00'),
(5,  'submitted', 13,NULL, 'pending_verification', 'Corporate donation submitted', '2026-02-10 11:00:00'),
(5,  'verified',  2, 'pending_verification', 'verified', 'Corporate transfer verified', '2026-02-11 10:00:00'),
(9,  'submitted', 4, NULL, 'pending_verification', 'Monthly recurring donation', '2026-10-01 09:00:00'),
(10, 'submitted', 5, NULL, 'pending_verification', 'Donation submitted by donor', '2026-02-15 10:30:00'),
(10, 'verified',  2, 'pending_verification', 'verified', 'Amount and reference verified', '2026-02-16 10:00:00'),
(15, 'submitted', 6, NULL, 'pending_verification', 'Donation submitted by donor', '2026-10-02 08:00:00'),
(23, 'submitted', 8, NULL, 'pending_verification', 'Donation submitted by donor', '2026-10-02 07:30:00'),
(27, 'submitted', 5, NULL, 'pending_verification', 'Monthly donation', '2026-10-02 06:00:00'),
(33, 'submitted', 12,NULL, 'pending_verification', 'Donation submitted', '2026-10-02 08:30:00');

-- =====================================================
-- FUND ALLOCATIONS
-- =====================================================
INSERT INTO fund_allocations (campaign_id, amount, purpose, description, allocated_by, allocation_date, status) VALUES
(1, 50000000,  'School Supplies',        'Notebooks, pens, bags for 500 students in Son La province', 3, '2026-03-01', 'disbursed'),
(1, 80000000,  'Classroom Renovation',   'Repair and repaint 8 classrooms in Dien Bien province',    3, '2026-04-01', 'disbursed'),
(1, 30000000,  'Teacher Training',       'Professional development workshop for 50 rural teachers',  3, '2026-05-15', 'planned'),
(2, 60000000,  'Medical Equipment',      'Basic diagnostic equipment for mobile health units',        3, '2026-03-15', 'disbursed'),
(2, 40000000,  'Medicines & Vaccines',   'Essential medicines and vaccines for 3 communes',          3, '2026-04-15', 'disbursed'),
(3, 150000000, 'Water Filtration Systems','Install 10 water filtration units in Gia Lai villages',   3, '2026-02-20', 'completed'),
(3, 80000000,  'Well Construction',      'Dig and cement 5 community wells in remote areas',         3, '2026-03-10', 'completed'),
(4, 45000000,  'University Scholarships','15 scholarships covering one semester tuition',             3, '2026-04-01', 'disbursed'),
(6, 100000000, 'Emergency Food Kits',    'Food packages for 2,000 flood-affected families',          3, '2026-02-10', 'completed'),
(6, 80000000,  'Temporary Shelters',     'Emergency shelter materials for 500 families in Quang Nam',3, '2026-02-15', 'completed');

-- =====================================================
-- NOTIFICATIONS
-- =====================================================
INSERT INTO notifications (user_id, title, message, is_read, type) VALUES
(4, 'Donation Verified ✓',    'Your donation of 2,000,000 VND has been verified successfully. Thank you!', 1, 'confirmation'),
(4, 'Donation Verified ✓',    'Your donation of 2,000,000 VND to Elderly Care has been verified.', 0, 'confirmation'),
(5, 'Donation Verified ✓',    'Your donation of 5,000,000 VND to Rural Schools has been verified.', 1, 'confirmation'),
(5, 'New Campaign Available', 'Scholarship for Talented Students campaign is now accepting donations.', 0, 'campaign_update'),
(6, 'Donation Verified ✓',    'Your donation of 1,000,000 VND has been verified. Thank you!', 1, 'confirmation'),
(7, 'Donation Verified ✓',    'Your donation of 3,000,000 VND has been verified successfully.', 1, 'confirmation'),
(9, 'Donation Verified ✓',    'Your donation of 5,000,000 VND to Disaster Relief has been verified.', 1, 'confirmation'),
(13,'Donation Verified ✓',    'Corporate donation of 20,000,000 VND to Clean Water Project verified.', 1, 'confirmation'),
(2, 'New Transactions Pending','4 new donation transactions are awaiting verification.',               0, 'system'),
(1, 'Campaign Completed',     'Clean Water Project has reached its fundraising goal of 400,000,000 VND!', 0, 'campaign_update');

-- =====================================================
-- Update donor totals
-- =====================================================
UPDATE donor_profiles dp
JOIN (
    SELECT donor_id, SUM(amount) as total
    FROM donations WHERE status='verified'
    GROUP BY donor_id
) t ON dp.user_id = t.donor_id
SET dp.total_donated = t.total;

SELECT 'Seed data inserted successfully!' as Result;
SELECT COUNT(*) as Total_Users FROM users;
SELECT COUNT(*) as Total_Campaigns FROM campaigns;
SELECT COUNT(*) as Total_Donations FROM donations;
SELECT COUNT(*) as Pending_Verification FROM transactions WHERE verification_status='pending_verification';
