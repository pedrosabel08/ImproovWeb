CREATE TABLE IF NOT EXISTS deadline_recovery_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    render_id INT NOT NULL,
    job_id CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id INT NOT NULL,
    collaborator_id INT NOT NULL,
    action VARCHAR(16) NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    params_json LONGTEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'queued',
    active_job CHAR(24) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (
        CASE
            WHEN status IN ('queued', 'running') THEN job_id
            ELSE NULL
        END
    ) STORED,
    before_json LONGTEXT NULL,
    result_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    UNIQUE KEY recovery_active_job (active_job),
    UNIQUE KEY recovery_single_use (source_id),
    KEY recovery_queue (status, id),
    KEY recovery_history (job_id, id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;