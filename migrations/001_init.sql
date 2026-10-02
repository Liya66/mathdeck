-- Phase 3 schema.
--
-- There is deliberately no game-state column anywhere. A match is its seed record
-- plus its event log; state is the fold of the two. Snapshots, if and when replay
-- gets slow, are a cache over this and can be dropped and rebuilt at will.

CREATE TABLE IF NOT EXISTS matches (
    match_id        VARCHAR(64)  NOT NULL,
    deck_version_id VARCHAR(64)  NOT NULL,
    seed            BIGINT       NOT NULL,
    player_ids      JSON         NOT NULL,
    -- The deck's rules as they were at creation. Copied, not referenced: editing a
    -- deck must never change how a finished match replays.
    rules           JSON         NOT NULL,
    created_at      DATETIME(6)  NOT NULL,
    PRIMARY KEY (match_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS match_events (
    match_id    VARCHAR(64)  NOT NULL,
    seq         INT UNSIGNED NOT NULL,
    type        VARCHAR(64)  NOT NULL,
    payload     JSON         NOT NULL,
    occurred_at DATETIME(6)  NOT NULL,
    -- This primary key IS the concurrency control. Two writers racing for the same
    -- seq: one inserts, the other gets a duplicate-key error, reloads and retries.
    PRIMARY KEY (match_id, seq),
    -- For the phase 7 projector, which sweeps by type in time order.
    KEY idx_match_events_type (type, occurred_at),
    CONSTRAINT fk_match_events_match
        FOREIGN KEY (match_id) REFERENCES matches (match_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS match_commands (
    match_id          VARCHAR(64)  NOT NULL,
    client_command_id VARCHAR(64)  NOT NULL,
    from_seq          INT UNSIGNED NOT NULL,
    to_seq            INT UNSIGNED NOT NULL,
    recorded_at       DATETIME(6)  NOT NULL,
    -- Idempotency. Written in the same transaction as the events it produced, so a
    -- retry on flaky school wifi replays the original result instead of the cards.
    PRIMARY KEY (match_id, client_command_id),
    CONSTRAINT fk_match_commands_match
        FOREIGN KEY (match_id) REFERENCES matches (match_id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
