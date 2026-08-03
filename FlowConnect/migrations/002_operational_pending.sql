-- Flow Connect: ciclos operacionais. NAO EXECUTAR automaticamente.
-- Aplicar manualmente depois de revisar o backup e as policies.
CREATE TABLE flow_connect_pending_cycles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_key VARCHAR(80) NOT NULL,
    policy_key VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(160) NOT NULL,
    cycle_id VARCHAR(160) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
    responsavel_id BIGINT NULL,
    responsavel_cobranca_id BIGINT NULL,
    started_at DATETIME(6) NOT NULL,
    due_at DATETIME(6) NULL,
    paused_at DATETIME(6) NULL,
    paused_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    resolved_at DATETIME(6) NULL,
    cancelled_at DATETIME(6) NULL,
    context_json JSON NULL,
    last_observed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_pending_cycle (
        module_key,
        policy_key,
        entity_type,
        entity_id,
        cycle_id
    ),
    KEY ix_flow_connect_pending_due (status, due_at),
    KEY ix_flow_connect_pending_entity (
        module_key,
        entity_type,
        entity_id
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_pending_milestones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cycle_id BIGINT UNSIGNED NOT NULL,
    milestone VARCHAR(32) NOT NULL,
    event_uuid CHAR(36) NULL,
    triggered_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_pending_milestone (cycle_id, milestone),
    CONSTRAINT fk_flow_connect_pending_milestone_cycle FOREIGN KEY (cycle_id) REFERENCES flow_connect_pending_cycles (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_pending_summary_windows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_key VARCHAR(120) NOT NULL,
    window_key VARCHAR(80) NOT NULL,
    collaborator_id BIGINT UNSIGNED NOT NULL,
    event_uuid CHAR(36) NULL,
    fired_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_pending_summary_window (
        policy_key,
        window_key,
        collaborator_id
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;