-- VOSTERA ROLEPLAY | MariaDB foundation for independently written starter.
-- No game login exists yet. Apply only to a dedicated database.
CREATE TABLE IF NOT EXISTS vostera_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'banned') NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_vostera_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vostera_progress (
    account_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    wallet BIGINT UNSIGNED NOT NULL DEFAULT 0,
    completed_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    last_paid_job_at DATETIME(6) NULL,
    row_version INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_vostera_progress_account FOREIGN KEY (account_id)
        REFERENCES vostera_accounts(id) ON DELETE CASCADE,
    CONSTRAINT ck_vostera_wallet CHECK (wallet <= 2000000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vostera_job_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    event_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    job_type ENUM('courier', 'mechanic') NOT NULL,
    reward INT UNSIGNED NOT NULL,
    awarded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_vostera_event (event_key),
    KEY ix_vostera_receipt_account (account_id, awarded_at),
    CONSTRAINT fk_vostera_receipt_account FOREIGN KEY (account_id)
        REFERENCES vostera_accounts(id) ON DELETE CASCADE,
    CONSTRAINT ck_vostera_reward CHECK (reward IN (100, 125))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
