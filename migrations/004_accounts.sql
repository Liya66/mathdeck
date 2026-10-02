-- Phase 8: real accounts.
--
-- Until now a bearer token was the player id verbatim, which authenticated nobody.
-- The boundary was real from phase 4; this is the check behind it.

CREATE TABLE IF NOT EXISTS accounts (
    player_id     VARCHAR(64)  NOT NULL,
    display_name  VARCHAR(120) NOT NULL,
    role          VARCHAR(16)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    -- The key everything in fact_attempt is written under. Random, not derived: a
    -- derived key can be recomputed by anyone who guesses the scheme, which makes
    -- it a label rather than a pseudonym. Dropping this column would leave the
    -- aggregate picture intact and the individuals unidentifiable.
    analytics_key CHAR(32)     NOT NULL,
    created_at    DATETIME(6)  NOT NULL,
    PRIMARY KEY (player_id),
    UNIQUE KEY uniq_analytics_key (analytics_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- fact_attempt now stores a pseudonym rather than a name. Renamed rather than
-- added so there is no column left holding the thing we just stopped storing.
ALTER TABLE fact_attempt
    CHANGE COLUMN student_id student_key VARCHAR(64) NOT NULL;

ALTER TABLE fact_attempt
    DROP INDEX idx_attempt_student,
    ADD KEY idx_attempt_student (student_key, occurred_at);
