-- Phase 8 follow-up: two defects found in the README's own list of gaps.

-- 1. Nothing slowed a passcode guesser. Classroom passcodes are short and
--    memorable by design — `play-1234` falls to a few thousand guesses — so a
--    throttle is what makes that choice defensible rather than negligent.
CREATE TABLE IF NOT EXISTS sign_in_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- `account:<playerId>` or `address:<ip>`. Two buckets, either can refuse.
    bucket_key VARCHAR(128) NOT NULL,
    failed_at  DATETIME(6)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_bucket_window (bucket_key, failed_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- 2. A retried POST /v1/matches created a second match. Commands have had an
--    idempotency key since phase 4; creation was the one write that did not,
--    which is exactly the request most likely to be retried on a flaky
--    connection — a child taps "new match", sees nothing, and taps again.
--
--    Same mechanism as the event store: a unique index, and the loser of the race
--    reads back what the winner wrote.
ALTER TABLE matches
    ADD COLUMN creation_key VARCHAR(64) NULL AFTER match_id,
    ADD UNIQUE KEY uniq_match_creation_key (creation_key);
