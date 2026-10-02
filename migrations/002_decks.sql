-- Phase 6: deck authoring.
--
-- A published version is immutable. Editing one means forking a new version, which
-- is why (deck_id, version) is unique and nothing here is ever updated in place
-- once status is 'published'.

CREATE TABLE IF NOT EXISTS deck_versions (
    deck_version_id VARCHAR(80)  NOT NULL,
    deck_id         VARCHAR(64)  NOT NULL,
    version         INT UNSIGNED NOT NULL,
    name            VARCHAR(160) NOT NULL,
    author_id       VARCHAR(64)  NOT NULL,
    status          VARCHAR(16)  NOT NULL,
    definition      JSON         NOT NULL,
    updated_at      DATETIME(6)  NOT NULL,
    published_at    DATETIME(6)  NULL,
    PRIMARY KEY (deck_version_id),
    UNIQUE KEY uniq_deck_version (deck_id, version),
    KEY idx_deck_status (status, updated_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Deliberately no foreign key from matches.deck_version_id to here. A match copies
-- the rules it was created with, so it must outlive the deck it came from; an FK
-- would make deleting an old deck destroy the history of every match that used it.

INSERT INTO deck_versions
    (deck_version_id, deck_id, version, name, author_id, status, definition, updated_at, published_at)
VALUES
    ('starter@1', 'starter', 1, 'Starter deck', 'system', 'published',
     '{"schemaVersion":1,"name":"Starter deck","description":"Whole numbers to 12 with all four operations.","operands":{"values":[0,1,2,3,4,5,6,7,8,9,10,11,12],"copies":3},"operators":{"symbols":["+","-","*","/"],"copies":8},"targets":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24],"play":{"handSize":7,"minimumCards":3,"maximumCards":7,"targetsPerMatch":10,"requireIntegerResult":true,"baseScore":10}}',
     '2026-01-01 00:00:00.000000', '2026-01-01 00:00:00.000000')
ON DUPLICATE KEY UPDATE deck_version_id = deck_version_id;
