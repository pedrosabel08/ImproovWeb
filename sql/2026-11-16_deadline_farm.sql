CREATE TABLE IF NOT EXISTS deadline_farm_cache (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    workers_json LONGTEXT NOT NULL,
    checked_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
    successful_at BIGINT UNSIGNED NULL,
    health VARCHAR(20) NOT NULL DEFAULT 'unavailable'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO
    deadline_farm_cache (id, workers_json)
VALUES (1, '[]');

CREATE TABLE IF NOT EXISTS deadline_farm_incidents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    worker VARCHAR(255) NOT NULL DEFAULT '',
    kind VARCHAR(24) NOT NULL,
    detected_at BIGINT UNSIGNED NOT NULL,
    opened_at BIGINT UNSIGNED NULL,
    last_seen_at BIGINT UNSIGNED NOT NULL,
    resolved_at BIGINT UNSIGNED NULL,
    resolved_reason VARCHAR(24) NULL,
    notified_open TINYINT(1) NOT NULL DEFAULT 0,
    notified_resolved TINYINT(1) NOT NULL DEFAULT 0,
    active_kind VARCHAR(24) GENERATED ALWAYS AS (
        IF(
            resolved_at IS NULL,
            kind,
            NULL
        )
    ) STORED,
    UNIQUE KEY uq_deadline_farm_active (worker, active_kind),
    KEY idx_deadline_farm_history (opened_at, resolved_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deadline_job_execution (
    deadline_job_id CHAR(24) NOT NULL PRIMARY KEY,
    execution_state VARCHAR(20) NOT NULL,
    worker VARCHAR(255) NULL,
    observed_at DATETIME NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;