-- =====================================================
-- Advanced Features Migration
-- Run in phpMyAdmin after seed_data.sql
-- =====================================================
USE vcf_donation;

-- Add segment column to donor_profiles
ALTER TABLE donor_profiles
    ADD COLUMN IF NOT EXISTS segment ENUM('new','repeat','loyal','high_value','lapsed') DEFAULT 'new',
    ADD COLUMN IF NOT EXISTS segment_updated_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS total_campaigns INT DEFAULT 0;

-- Impact summaries table (Feature 3)
CREATE TABLE IF NOT EXISTS impact_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    campaign_id INT NOT NULL,
    summary_text TEXT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_sent TINYINT(1) DEFAULT 0,
    FOREIGN KEY (donor_id) REFERENCES users(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
);

-- Update existing donor segments
UPDATE donor_profiles dp
JOIN (
    SELECT
        d.donor_id,
        COUNT(d.id) as cnt,
        COALESCE(SUM(d.amount),0) as total,
        MAX(d.created_at) as last_donation,
        COUNT(DISTINCT d.campaign_id) as camps
    FROM donations d
    WHERE d.status = 'verified'
    GROUP BY d.donor_id
) stats ON dp.user_id = stats.donor_id
SET
    dp.segment = CASE
        WHEN stats.total >= 10000000 THEN 'high_value'
        WHEN stats.cnt >= 5 THEN 'loyal'
        WHEN stats.cnt >= 2 THEN 'repeat'
        WHEN DATEDIFF(NOW(), stats.last_donation) > 90 THEN 'lapsed'
        ELSE 'new'
    END,
    dp.total_donated    = stats.total,
    dp.total_campaigns  = stats.camps,
    dp.segment_updated_at = NOW();

SELECT 'Advanced features migration done!' as Result;
