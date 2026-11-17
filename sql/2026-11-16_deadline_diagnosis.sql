-- Times from Deadline and the collector are stored in UTC.
CREATE TABLE IF NOT EXISTS deadline_diagnostic_jobs (
    job_id CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    next_poll_at DATETIME(6) NOT NULL,
    last_scan_at DATETIME(6) NULL,
    last_error VARCHAR(48) NULL,
    failure_hint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    priority_poll TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (job_id),
    KEY ix_deadline_diagnostic_poll (
        priority_poll,
        next_poll_at,
        job_id
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS deadline_diagnostic_incidents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    correlation_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    worker VARCHAR(160) NULL,
    episode INT UNSIGNED NOT NULL DEFAULT 1,
    state VARCHAR(16) NOT NULL,
    first_at DATETIME(6) NULL,
    last_at DATETIME(6) NULL,
    resolved_at DATETIME(6) NULL,
    resolution_reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_deadline_diagnostic_incident (correlation_key),
    KEY ix_deadline_diagnostic_state (state, last_at),
    KEY ix_deadline_diagnostic_worker (worker, last_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS deadline_diagnostic_occurrences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    report_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    metadata_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    body_state VARCHAR(16) NOT NULL,
    next_body_retry DATETIME(6) NULL,
    incident_id BIGINT UNSIGNED NULL,
    episode INT UNSIGNED NULL,
    job_name VARCHAR(255) NULL,
    task_id VARCHAR(32) NULL,
    frame VARCHAR(255) NULL,
    worker VARCHAR(160) NULL,
    renderer VARCHAR(32) NULL,
    occurred_at DATETIME(6) NULL,
    collected_at DATETIME(6) NOT NULL,
    observed_since DATETIME(6) NULL,
    signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    catalog_version INT UNSIGNED NULL,
    report_json JSON NOT NULL,
    evidence_json JSON NOT NULL,
    diagnosis_json JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_deadline_diagnostic_report (job_id, report_id),
    KEY ix_deadline_diagnostic_occurrence_incident (
        incident_id,
        episode,
        occurred_at
    ),
    KEY ix_deadline_diagnostic_occurrence_signature (signature, occurred_at),
    KEY ix_deadline_diagnostic_occurrence_job (job_id, occurred_at),
    KEY ix_deadline_diagnostic_occurrence_worker (worker, occurred_at),
    KEY ix_deadline_diagnostic_occurrence_renderer (renderer, occurred_at),
    CONSTRAINT fk_deadline_diagnostic_occurrence_incident FOREIGN KEY (incident_id) REFERENCES deadline_diagnostic_incidents (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS deadline_diagnostic_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incident_id BIGINT UNSIGNED NULL,
    occurrence_id BIGINT UNSIGNED NULL,
    episode INT UNSIGNED NULL,
    kind VARCHAR(24) NOT NULL,
    recorded_at DATETIME(6) NOT NULL,
    detail_json JSON NOT NULL,
    PRIMARY KEY (id),
    KEY ix_deadline_diagnostic_incident_history (incident_id, id),
    KEY ix_deadline_diagnostic_occurrence_history (occurrence_id, id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS deadline_diagnostic_recovery (
    job_id CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    task_id VARCHAR(32) NOT NULL,
    worker VARCHAR(160) NOT NULL,
    report_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    started_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NOT NULL,
    PRIMARY KEY (job_id, task_id, worker)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;