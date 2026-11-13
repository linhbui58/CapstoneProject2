-- Add unique constraint to impact_summaries so ON DUPLICATE KEY UPDATE works
-- Run in phpMyAdmin after advanced_features.sql
USE vcf_donation;

ALTER TABLE impact_summaries
    ADD COLUMN IF NOT EXISTS donor_id INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS campaign_id INT NOT NULL DEFAULT 0;

-- Add unique key if not exists
SET @idx = (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'impact_summaries' AND index_name = 'uq_donor_campaign');

SET @sql = IF(@idx = 0,
    'ALTER TABLE impact_summaries ADD UNIQUE KEY uq_donor_campaign (donor_id, campaign_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
