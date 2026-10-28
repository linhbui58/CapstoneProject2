CREATE TABLE IF NOT EXISTS campaign_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(255) NULL,
    posted_by INT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaign_updates_campaign (campaign_id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

SET @campaign_updates_has_image_url = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'campaign_updates'
      AND COLUMN_NAME = 'image_url'
);

SET @campaign_updates_image_sql = IF(
    @campaign_updates_has_image_url = 0,
    'ALTER TABLE `campaign_updates` ADD COLUMN `image_url` VARCHAR(255) NULL AFTER `content`',
    'SELECT 1'
);

PREPARE campaign_updates_image_stmt FROM @campaign_updates_image_sql;
EXECUTE campaign_updates_image_stmt;
DEALLOCATE PREPARE campaign_updates_image_stmt;
