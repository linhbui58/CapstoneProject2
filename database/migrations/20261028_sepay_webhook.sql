ALTER TABLE transactions
    ADD COLUMN sepay_transaction_id BIGINT UNSIGNED NULL AFTER amount_transferred,
    ADD UNIQUE KEY uq_transactions_sepay_id (sepay_transaction_id);
