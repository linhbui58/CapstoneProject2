ALTER TABLE donations
    ADD COLUMN payment_provider VARCHAR(20) NOT NULL DEFAULT 'legacy' AFTER channel;
