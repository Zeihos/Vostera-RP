-- VOSTERA: atomic/idempotent rewards for an authenticated game server.
-- NOT callable by players. Pawn is not wired to this procedure yet.
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS vostera_award_job(
    IN p_account_id BIGINT UNSIGNED,
    IN p_event_key CHAR(36),
    IN p_job VARCHAR(16),
    OUT p_result VARCHAR(24)
)
award: BEGIN
    DECLARE v_status VARCHAR(16) DEFAULT NULL;
    DECLARE v_wallet BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_last DATETIME(6) DEFAULT NULL;
    DECLARE v_reward INT UNSIGNED DEFAULT 0;
    DECLARE v_duplicate BIGINT UNSIGNED DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_result = 'invalid';
    IF p_account_id IS NULL OR p_account_id = 0
        OR p_event_key IS NULL OR NOT (p_event_key REGEXP BINARY
            '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$')
    THEN
        LEAVE award;
    END IF;

    IF p_job = 'courier' THEN
        SET v_reward = 125;
    ELSEIF p_job = 'mechanic' THEN
        SET v_reward = 100;
    ELSE
        LEAVE award;
    END IF;

    START TRANSACTION;
    -- The account row serializes all payouts for the same player.
    SELECT status INTO v_status FROM vostera_accounts
        WHERE id = p_account_id FOR UPDATE;
    IF v_status IS NULL OR v_status <> 'active' THEN
        COMMIT;
        SET p_result = 'forbidden';
        LEAVE award;
    END IF;
    SELECT wallet, last_paid_job_at INTO v_wallet, v_last
        FROM vostera_progress WHERE account_id = p_account_id FOR UPDATE;
    IF v_wallet IS NULL THEN
        COMMIT;
        SET p_result = 'no_progress';
        LEAVE award;
    END IF;

    SELECT COUNT(*) INTO v_duplicate FROM vostera_job_receipts
        WHERE event_key = p_event_key;
    IF v_duplicate > 0 THEN
        COMMIT;
        SET p_result = 'duplicate';
        LEAVE award;
    END IF;
    IF v_last IS NOT NULL
        AND TIMESTAMPDIFF(MICROSECOND, v_last, UTC_TIMESTAMP(6)) < 90000000
    THEN
        COMMIT;
        SET p_result = 'cooldown';
        LEAVE award;
    END IF;
    IF v_wallet > 2000000000 - v_reward THEN
        COMMIT;
        SET p_result = 'wallet_limit';
        LEAVE award;
    END IF;

    INSERT INTO vostera_job_receipts(account_id, event_key, job_type, reward)
        VALUES(p_account_id, p_event_key, p_job, v_reward);
    UPDATE vostera_progress SET
        wallet = wallet + v_reward,
        completed_jobs = completed_jobs + 1,
        last_paid_job_at = UTC_TIMESTAMP(6),
        row_version = row_version + 1
        WHERE account_id = p_account_id;
    COMMIT;
    SET p_result = 'awarded';
END award$$
DELIMITER ;
