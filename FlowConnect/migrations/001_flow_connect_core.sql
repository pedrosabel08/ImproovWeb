-- Flow Connect V1 / FlowReview
-- IMPORTANTE: migration deliberadamente não executada pelo Codex.
-- Revisar e aplicar manualmente somente após aprovação do responsável pelo banco.

CREATE TABLE flow_connect_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    event_type VARCHAR(160) NOT NULL,
    event_version INT UNSIGNED NOT NULL,
    source_module VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(160) NOT NULL,
    actor_id BIGINT NULL,
    occurred_at DATETIME(6) NOT NULL,
    received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    correlation_id CHAR(36) NOT NULL,
    causation_event_uuid CHAR(36) NULL,
    idempotency_key VARCHAR(255) NOT NULL,
    payload_json JSON NOT NULL,
    metadata_json JSON NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    claimed_by VARCHAR(120) NULL,
    claimed_at DATETIME(6) NULL,
    claim_expires_at DATETIME(6) NULL,
    processing_started_at DATETIME(6) NULL,
    processed_at DATETIME(6) NULL,
    last_error_code VARCHAR(120) NULL,
    last_error_safe VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_event_uuid (event_uuid),
    UNIQUE KEY uq_flow_connect_event_idempotency (idempotency_key),
    KEY ix_flow_connect_event_queue (status, claim_expires_at, id),
    KEY ix_flow_connect_event_entity (
        source_module,
        entity_type,
        entity_id
    ),
    KEY ix_flow_connect_event_correlation (correlation_id),
    KEY ix_flow_connect_event_causation (causation_event_uuid)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    notification_key VARCHAR(255) NOT NULL,
    severity VARCHAR(24) NOT NULL,
    category VARCHAR(24) NOT NULL,
    delivery_mode VARCHAR(32) NOT NULL,
    template_code VARCHAR(160) NOT NULL,
    template_version INT UNSIGNED NOT NULL DEFAULT 1,
    recipient_strategy VARCHAR(120) NOT NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'READY',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    last_error_code VARCHAR(120) NULL,
    last_error_safe VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_notification_key (notification_key),
    KEY ix_flow_connect_notification_event (event_id),
    KEY ix_flow_connect_notification_status (status, created_at),
    CONSTRAINT fk_flow_connect_notification_event FOREIGN KEY (event_id) REFERENCES flow_connect_events (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(40) NOT NULL,
    destination_kind VARCHAR(24) NOT NULL,
    destination_key VARCHAR(255) NOT NULL,
    collaborator_id BIGINT NULL,
    slack_user_id VARCHAR(64) NULL,
    rendered_text TEXT NOT NULL,
    rendered_blocks_json JSON NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME(6) NULL,
    sent_at DATETIME(6) NULL,
    claimed_by VARCHAR(120) NULL,
    claimed_at DATETIME(6) NULL,
    claim_expires_at DATETIME(6) NULL,
    last_error_code VARCHAR(120) NULL,
    last_error_safe VARCHAR(500) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_delivery_destination (
        notification_id,
        destination_key
    ),
    KEY ix_flow_connect_delivery_queue (
        status,
        next_attempt_at,
        claim_expires_at,
        id
    ),
    KEY ix_flow_connect_delivery_collaborator (collaborator_id),
    CONSTRAINT fk_flow_connect_delivery_notification FOREIGN KEY (notification_id) REFERENCES flow_connect_notifications (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_delivery_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_id BIGINT UNSIGNED NOT NULL,
    attempt_no INT UNSIGNED NOT NULL,
    started_at DATETIME(6) NOT NULL,
    finished_at DATETIME(6) NULL,
    http_status SMALLINT UNSIGNED NULL,
    provider_message_id VARCHAR(255) NULL,
    provider_error_code VARCHAR(120) NULL,
    error_safe VARCHAR(500) NULL,
    request_fingerprint CHAR(64) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_delivery_attempt (delivery_id, attempt_no),
    KEY ix_flow_connect_attempt_error (
        provider_error_code,
        started_at
    ),
    CONSTRAINT fk_flow_connect_attempt_delivery FOREIGN KEY (delivery_id) REFERENCES flow_connect_deliveries (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(160) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(160) NOT NULL,
    schedule_kind VARCHAR(32) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
    next_due_at DATETIME(6) NOT NULL,
    business_timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
    silence_until DATETIME(6) NULL,
    recurrence_json JSON NULL,
    context_json JSON NULL,
    last_event_uuid CHAR(36) NULL,
    last_fired_at DATETIME(6) NULL,
    resolved_at DATETIME(6) NULL,
    cancelled_at DATETIME(6) NULL,
    claimed_by VARCHAR(120) NULL,
    claimed_at DATETIME(6) NULL,
    claim_expires_at DATETIME(6) NULL,
    last_error_code VARCHAR(120) NULL,
    last_error_safe VARCHAR(500) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_schedule_scope (
        event_type,
        entity_type,
        entity_id,
        schedule_kind
    ),
    KEY ix_flow_connect_schedule_due (
        status,
        next_due_at,
        silence_until,
        claim_expires_at
    ),
    KEY ix_flow_connect_schedule_last_event (last_event_uuid)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_slack_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    colaborador_id BIGINT UNSIGNED NOT NULL,
    slack_user_id VARCHAR(64) NULL,
    slack_display_name VARCHAR(255) NULL,
    slack_real_name VARCHAR(255) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'UNRESOLVED',
    last_synced_at DATETIME(6) NULL,
    source VARCHAR(80) NULL,
    last_error_code VARCHAR(120) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_identity_collaborator (colaborador_id),
    UNIQUE KEY uq_flow_connect_identity_slack_user (slack_user_id),
    KEY ix_flow_connect_identity_status (status, last_synced_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE flow_connect_dead_letters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NULL,
    notification_id BIGINT UNSIGNED NULL,
    delivery_id BIGINT UNSIGNED NULL,
    reason_code VARCHAR(120) NOT NULL,
    dedupe_key CHAR(64) NOT NULL,
    payload_safe_json JSON NOT NULL,
    first_failed_at DATETIME(6) NOT NULL,
    last_failed_at DATETIME(6) NOT NULL,
    reprocessed_at DATETIME(6) NULL,
    resolved_at DATETIME(6) NULL,
    resolved_by BIGINT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flow_connect_dead_letter_dedupe (dedupe_key),
    KEY ix_flow_connect_dead_letter_open (
        resolved_at,
        reprocessed_at,
        last_failed_at
    ),
    KEY ix_flow_connect_dead_letter_event (event_id),
    KEY ix_flow_connect_dead_letter_delivery (delivery_id),
    CONSTRAINT fk_flow_connect_dead_letter_event FOREIGN KEY (event_id) REFERENCES flow_connect_events (id),
    CONSTRAINT fk_flow_connect_dead_letter_notification FOREIGN KEY (notification_id) REFERENCES flow_connect_notifications (id),
    CONSTRAINT fk_flow_connect_dead_letter_delivery FOREIGN KEY (delivery_id) REFERENCES flow_connect_deliveries (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;