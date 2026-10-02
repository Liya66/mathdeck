-- Phase 7: analytics projections.
--
-- The event log is the telemetry — gameplay is not instrumented separately. This
-- table is a projection of that same stream, written by a worker and never by the
-- request path.
--
-- Reports read only from here. Aggregating over match_events at query time works
-- fine at a hundred matches and falls over at a hundred thousand.

CREATE TABLE IF NOT EXISTS fact_attempt (
    match_id        VARCHAR(64)  NOT NULL,
    -- The seq of the cards_played event. Makes re-projection idempotent: running
    -- the worker twice writes the same rows, not twice as many.
    seq             INT UNSIGNED NOT NULL,
    student_id      VARCHAR(64)  NOT NULL,
    deck_version_id VARCHAR(80)  NOT NULL,
    target          INT          NOT NULL,
    expression      VARCHAR(160) NULL,
    card_count      TINYINT UNSIGNED NOT NULL,
    solved          TINYINT(1)   NOT NULL,
    -- NULL when solved. Only ever a misconception code: protocol faults never
    -- became events, so they cannot pollute the error clusters.
    reason_code     VARCHAR(40)  NULL,
    score           INT          NOT NULL,
    latency_ms      INT UNSIGNED NOT NULL,
    occurred_at     DATETIME(6)  NOT NULL,
    PRIMARY KEY (match_id, seq),
    KEY idx_attempt_deck (deck_version_id, occurred_at),
    KEY idx_attempt_student (student_id, occurred_at),
    KEY idx_attempt_reason (reason_code, occurred_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- How far each projection has consumed each stream. Kept separate from the facts
-- because a projection may legitimately read events that produce no fact.
CREATE TABLE IF NOT EXISTS projection_cursors (
    projection VARCHAR(40) NOT NULL,
    stream_id  VARCHAR(64) NOT NULL,
    seq        INT UNSIGNED NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (projection, stream_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
