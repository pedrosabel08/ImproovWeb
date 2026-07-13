-- Flow x Deadline: tentativas, fila persistente, heartbeat e auditoria.
-- Aplicar somente depois de backup do banco e validacao em homologacao.
-- Esta migration nao executa comandos no Deadline e nao cria DELETE_JOB historico.

CREATE TABLE IF NOT EXISTS render_tentativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    render_id INT NOT NULL,
    imagem_id INT NOT NULL,
    status_id INT NOT NULL,
    numero_tentativa INT NOT NULL,
    deadline_job_id VARCHAR(64) NULL,
    deadline_job_name VARCHAR(255) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'AGUARDANDO_JOB',
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    motivo_encerramento VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vinculado_em DATETIME NULL,
    iniciado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    reprovado_em DATETIME NULL,
    encerrado_em DATETIME NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ativa_render_id INT GENERATED ALWAYS AS (
        CASE
            WHEN ativa = 1 THEN render_id
            ELSE NULL
        END
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_render_numero_tentativa (render_id, numero_tentativa),
    UNIQUE KEY uniq_deadline_job_id (deadline_job_id),
    UNIQUE KEY uniq_render_tentativa_ativa (ativa_render_id),
    KEY idx_render_tentativas_render (render_id),
    KEY idx_render_tentativas_imagem_status (imagem_id, status_id),
    KEY idx_render_tentativas_ativa (ativa, status),
    CONSTRAINT fk_render_tentativas_render FOREIGN KEY (render_id) REFERENCES render_alta (idrender_alta) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_render_tentativas_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_render_tentativas_status FOREIGN KEY (status_id) REFERENCES status_imagem (idstatus) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deadline_comandos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL,
    render_id INT NULL,
    tentativa_id BIGINT UNSIGNED NULL,
    imagem_id INT NULL,
    deadline_job_id VARCHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDENTE',
    prioridade INT NOT NULL DEFAULT 100,
    tentativas_execucao INT NOT NULL DEFAULT 0,
    max_tentativas INT NOT NULL DEFAULT 10,
    disponivel_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_por VARCHAR(100) NULL,
    bloqueado_em DATETIME NULL,
    ultimo_erro TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    iniciado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_comando_job_tipo (deadline_job_id, tipo),
    KEY idx_deadline_comandos_fila (
        status,
        disponivel_em,
        prioridade,
        id
    ),
    KEY idx_deadline_comandos_tentativa (tentativa_id),
    KEY idx_deadline_comandos_render (render_id),
    CONSTRAINT fk_deadline_comandos_render FOREIGN KEY (render_id) REFERENCES render_alta (idrender_alta) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_deadline_comandos_tentativa FOREIGN KEY (tentativa_id) REFERENCES render_tentativas (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_deadline_comandos_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deadline_workers (
    worker_id VARCHAR(100) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    pid INT NULL,
    versao VARCHAR(50) NULL,
    iniciado_em DATETIME NOT NULL,
    ultimo_heartbeat DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL,
    detalhes TEXT NULL,
    PRIMARY KEY (worker_id),
    KEY idx_deadline_workers_heartbeat (status, ultimo_heartbeat)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS render_tentativa_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tentativa_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    chave VARCHAR(120) NOT NULL,
    dados_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_tentativa_evento (tentativa_id, tipo, chave),
    KEY idx_tentativa_eventos_tentativa (tentativa_id, criado_em),
    CONSTRAINT fk_tentativa_eventos_tentativa FOREIGN KEY (tentativa_id) REFERENCES render_tentativas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

SET
    @deadline_add_archive_column = (
        SELECT IF(
                COUNT(*) = 0, 'ALTER TABLE render_alta ADD COLUMN excluido_em DATETIME NULL AFTER deadline_job_id', 'SELECT ''render_alta.excluido_em ja existe'' AS informacao'
            )
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'render_alta'
            AND COLUMN_NAME = 'excluido_em'
    );

PREPARE deadline_add_archive_column_stmt
FROM @deadline_add_archive_column;

EXECUTE deadline_add_archive_column_stmt;

DEALLOCATE PREPARE deadline_add_archive_column_stmt;

-- Backfill idempotente. Uma tentativa inicial por render existente.
-- Nenhuma exclusao e enfileirada por este bloco.
INSERT INTO
    render_tentativas (
        render_id,
        imagem_id,
        status_id,
        numero_tentativa,
        deadline_job_id,
        status,
        ativa,
        motivo_encerramento,
        criado_em,
        vinculado_em,
        iniciado_em,
        concluido_em,
        reprovado_em,
        encerrado_em
    )
SELECT
    r.idrender_alta,
    r.imagem_id,
    r.status_id,
    1,
    NULLIF(TRIM(r.deadline_job_id), ''),
    CASE
        WHEN r.status IN (
            'Nao iniciado',
            'Não iniciado'
        )
        AND NULLIF(TRIM(r.deadline_job_id), '') IS NOT NULL THEN 'VINCULADA'
        WHEN r.status IN (
            'Nao iniciado',
            'Não iniciado'
        ) THEN 'AGUARDANDO_JOB'
        WHEN r.status IN (
            'Em andamento',
            'Em aprovacao',
            'Em aprovação',
            'Erro'
        )
        AND NULLIF(TRIM(r.deadline_job_id), '') IS NULL THEN 'INCONSISTENTE'
        WHEN r.status = 'Em andamento' THEN 'EM_ANDAMENTO'
        WHEN r.status = 'Em aprovacao'
        OR r.status = 'Em aprovação' THEN 'EM_APROVACAO'
        WHEN r.status = 'Erro' THEN 'ERRO'
        WHEN r.status = 'Aprovado' THEN 'APROVADA'
        WHEN r.status = 'Reprovado' THEN 'REPROVADA'
        WHEN r.status = 'Refazendo' THEN 'REFAZENDO'
        WHEN r.status IN ('Finalizado', 'Arquivado') THEN 'ENCERRADA'
        ELSE 'INCONSISTENTE'
    END,
    CASE
        WHEN r.status IN (
            'Nao iniciado',
            'Não iniciado',
            'Em andamento',
            'Em aprovacao',
            'Em aprovação',
            'Erro'
        ) THEN 1
        ELSE 0
    END,
    CASE
        WHEN r.status IN (
            'Aprovado',
            'Finalizado',
            'Arquivado',
            'Reprovado',
            'Refazendo'
        ) THEN CONCAT(
            'BACKFILL_STATUS_',
            UPPER(
                REPLACE (r.status, ' ', '_')
            )
        )
        WHEN r.status IN (
            'Em andamento',
            'Em aprovacao',
            'Em aprovação',
            'Erro'
        )
        AND NULLIF(TRIM(r.deadline_job_id), '') IS NULL THEN 'BACKFILL_OPERACIONAL_SEM_JOB_ID'
        WHEN r.status NOT IN(
            'Nao iniciado',
            'Não iniciado',
            'Em andamento',
            'Em aprovacao',
            'Em aprovação',
            'Erro'
        ) THEN 'BACKFILL_ESTADO_NAO_MAPEADO'
        ELSE NULL
    END,
    CASE
        WHEN NULLIF(TRIM(r.deadline_job_id), '') IS NULL
        AND r.status IN (
            'Nao iniciado',
            'Não iniciado',
            'Em andamento',
            'Em aprovacao',
            'Em aprovação',
            'Erro'
        ) THEN NOW()
        ELSE COALESCE(r.submitted, r.data, NOW())
    END,
    CASE
        WHEN NULLIF(TRIM(r.deadline_job_id), '') IS NOT NULL THEN COALESCE(r.submitted, r.data, NOW())
    END,
    CASE
        WHEN r.status = 'Em andamento' THEN COALESCE(r.submitted, r.data, NOW())
    END,
    CASE
        WHEN r.status IN (
            'Em aprovacao',
            'Em aprovação',
            'Aprovado',
            'Finalizado'
        ) THEN COALESCE(r.last_updated, r.data, NOW())
    END,
    CASE
        WHEN r.status IN ('Reprovado', 'Refazendo') THEN COALESCE(r.data, NOW())
    END,
    CASE
        WHEN r.status IN (
            'Aprovado',
            'Finalizado',
            'Arquivado',
            'Reprovado',
            'Refazendo'
        ) THEN COALESCE(r.data, NOW())
    END
FROM render_alta r
WHERE
    r.imagem_id IS NOT NULL
    AND r.status_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1
        FROM render_tentativas rt
        WHERE
            rt.render_id = r.idrender_alta
    );

-- Relatorio do backfill. Executar e salvar o resultado junto ao deploy.
SELECT COUNT(*) AS renders_analisados FROM render_alta;

SELECT COUNT(*) AS tentativas_criadas
FROM render_tentativas
WHERE
    numero_tentativa = 1;

SELECT COUNT(*) AS job_ids_migrados
FROM render_tentativas
WHERE
    deadline_job_id IS NOT NULL;

SELECT
    COUNT(*) AS renders_sem_chaves_para_tentativa
FROM render_alta
WHERE
    imagem_id IS NULL
    OR status_id IS NULL;

SELECT
    status,
    motivo_encerramento,
    COUNT(*) AS quantidade
FROM render_tentativas
GROUP BY
    status,
    motivo_encerramento
ORDER BY status, motivo_encerramento;

SELECT deadline_job_id, COUNT(*) AS quantidade
FROM render_tentativas
WHERE
    deadline_job_id IS NOT NULL
GROUP BY
    deadline_job_id
HAVING
    COUNT(*) > 1;

SELECT
    render_id,
    imagem_id,
    status_id,
    status,
    deadline_job_id,
    motivo_encerramento
FROM render_tentativas
WHERE
    status = 'INCONSISTENTE'
    OR (
        ativa = 0
        AND deadline_job_id IS NOT NULL
    )
ORDER BY render_id;
