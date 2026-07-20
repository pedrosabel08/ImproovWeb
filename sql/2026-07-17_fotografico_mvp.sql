-- MVP do processo Fotografico. Migration aditiva e versionada.
-- Aplicar uma unica vez no banco da aplicacao antes de habilitar o modulo.

ALTER TABLE obra
ADD COLUMN latitude DECIMAL(10, 7) NULL AFTER local,
ADD COLUMN longitude DECIMAL(10, 7) NULL AFTER latitude,
ADD COLUMN maps_url VARCHAR(700) NULL AFTER longitude;

CREATE TABLE fotografico_calendario_feriado (
    id INT NOT NULL AUTO_INCREMENT,
    data_feriado DATE NOT NULL,
    nome VARCHAR(160) NOT NULL,
    escopo ENUM(
        'NACIONAL',
        'ESTADUAL',
        'MUNICIPAL'
    ) NOT NULL DEFAULT 'NACIONAL',
    uf CHAR(2) NULL,
    municipio VARCHAR(120) NULL,
    bloqueia_dia_util TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_feriado (
        data_feriado,
        escopo,
        uf,
        municipio
    ),
    KEY idx_fotografico_feriado_data (
        data_feriado,
        bloqueia_dia_util
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_periodo (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nome VARCHAR(60) NOT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_periodo_codigo (codigo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    fotografico_periodo (codigo, nome, ordem)
VALUES ('DIURNO', 'Diurno', 10),
    (
        'GOLDEN_HOUR',
        'Golden Hour',
        20
    ),
    ('BLUE_HOUR', 'Blue Hour', 30),
    ('NOTURNO', 'Noturno', 40)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    ordem = VALUES(ordem),
    ativo = 1;

CREATE TABLE fotografico_plano (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    obra_id INT NOT NULL,
    campanha_numero INT UNSIGNED NOT NULL,
    origem ENUM(
        'AUTOMATICO',
        'MANUAL',
        'LEGADO'
    ) NOT NULL,
    chave_gatilho VARCHAR(140) NULL,
    imagem_gatilho_id INT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'PLANO_A_FAZER',
    status_antes_hold VARCHAR(40) NULL,
    responsavel_plano_id INT NULL,
    responsavel_execucao_id INT NULL,
    criado_por INT NULL,
    cancelado_por INT NULL,
    motivo_cancelamento VARCHAR(500) NULL,
    data_planejada DATE NULL,
    disparado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    iniciado_em DATETIME NULL,
    publicado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    cancelado_em DATETIME NULL,
    lock_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_plano_campanha (obra_id, campanha_numero),
    UNIQUE KEY uk_fotografico_plano_gatilho (chave_gatilho),
    KEY idx_fotografico_plano_fila (
        status,
        responsavel_plano_id,
        responsavel_execucao_id
    ),
    KEY idx_fotografico_plano_obra_status (obra_id, status),
    CONSTRAINT fk_fotografico_plano_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra),
    CONSTRAINT fk_fotografico_plano_gatilho FOREIGN KEY (imagem_gatilho_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_plano_resp FOREIGN KEY (responsavel_plano_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_plano_executor FOREIGN KEY (responsavel_execucao_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_plano_criador FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_plano_cancelador FOREIGN KEY (cancelado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_plano_versao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    status ENUM(
        'RASCUNHO',
        'PUBLICADA',
        'SUBSTITUIDA'
    ) NOT NULL DEFAULT 'RASCUNHO',
    motivo VARCHAR(500) NULL,
    endereco_snapshot VARCHAR(700) NULL,
    latitude_snapshot DECIMAL(10, 7) NULL,
    longitude_snapshot DECIMAL(10, 7) NULL,
    maps_url_snapshot VARCHAR(700) NULL,
    criado_por INT NULL,
    publicado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    publicado_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_versao_numero (plano_id, numero),
    KEY idx_fotografico_versao_status (plano_id, status),
    CONSTRAINT fk_fotografico_versao_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_versao_criador FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_versao_publicador FOREIGN KEY (publicado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_plano_imagem (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    versao_id BIGINT UNSIGNED NOT NULL,
    imagem_id INT NOT NULL,
    decisao ENUM(
        'PENDENTE',
        'INCLUIDA',
        'EXCLUIDA',
        'REMOVIDA'
    ) NOT NULL DEFAULT 'PENDENTE',
    motivo_exclusao VARCHAR(500) NULL,
    pavimento_referencia VARCHAR(100) NULL,
    observacao_tecnica TEXT NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_versao_imagem (versao_id, imagem_id),
    KEY idx_fotografico_plano_imagem_decisao (versao_id, decisao),
    CONSTRAINT fk_fotografico_plano_imagem_versao FOREIGN KEY (versao_id) REFERENCES fotografico_plano_versao (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_plano_imagem_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_posicao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    versao_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(40) NOT NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    direcao_graus DECIMAL(6, 2) NULL,
    altura_padrao_m DECIMAL(8, 2) NULL,
    pavimento_referencia VARCHAR(100) NULL,
    observacao TEXT NULL,
    anotacao_json JSON NULL,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_posicao_codigo (versao_id, codigo),
    CONSTRAINT fk_fotografico_posicao_versao FOREIGN KEY (versao_id) REFERENCES fotografico_plano_versao (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_captura (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    posicao_id BIGINT UNSIGNED NOT NULL,
    periodo_id SMALLINT UNSIGNED NOT NULL,
    prioridade INT UNSIGNED NOT NULL DEFAULT 1,
    altura_efetiva_m DECIMAL(8, 2) NULL,
    observacao TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_fotografico_captura_prioridade (posicao_id, prioridade),
    CONSTRAINT fk_fotografico_captura_posicao FOREIGN KEY (posicao_id) REFERENCES fotografico_posicao (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_captura_periodo FOREIGN KEY (periodo_id) REFERENCES fotografico_periodo (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_captura_imagem (
    captura_id BIGINT UNSIGNED NOT NULL,
    plano_imagem_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (captura_id, plano_imagem_id),
    KEY idx_fotografico_captura_imagem_item (plano_imagem_id),
    CONSTRAINT fk_fotografico_captura_imagem_captura FOREIGN KEY (captura_id) REFERENCES fotografico_captura (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_captura_imagem_item FOREIGN KEY (plano_imagem_id) REFERENCES fotografico_plano_imagem (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_sla (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('CRIACAO', 'EXECUCAO') NOT NULL,
    started_at DATETIME NOT NULL,
    due_at_original DATETIME NOT NULL,
    due_at_effective DATETIME NOT NULL,
    completed_at DATETIME NULL,
    total_paused_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    resultado ENUM(
        'EM_ANDAMENTO',
        'NO_PRAZO',
        'ATRASADO',
        'CANCELADO'
    ) NOT NULL DEFAULT 'EM_ANDAMENTO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_sla_tipo (plano_id, tipo),
    KEY idx_fotografico_sla_due (resultado, due_at_effective),
    CONSTRAINT fk_fotografico_sla_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_hold (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    detalhes TEXT NULL,
    origem ENUM('AUTOMATICO', 'MANUAL') NOT NULL,
    responsavel_id INT NULL,
    aberto_por INT NULL,
    aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    encerrado_por INT NULL,
    encerrado_em DATETIME NULL,
    status_retorno VARCHAR(40) NOT NULL,
    afeta_sla TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_fotografico_hold_aberto (plano_id, encerrado_em),
    CONSTRAINT fk_fotografico_hold_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_hold_resp FOREIGN KEY (responsavel_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_hold_aberto_por FOREIGN KEY (aberto_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_hold_encerrado_por FOREIGN KEY (encerrado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_sla_pausa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sla_id BIGINT UNSIGNED NOT NULL,
    hold_id BIGINT UNSIGNED NOT NULL,
    iniciado_em DATETIME NOT NULL,
    encerrado_em DATETIME NULL,
    duracao_segundos BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_sla_hold (sla_id, hold_id),
    CONSTRAINT fk_fotografico_sla_pausa_sla FOREIGN KEY (sla_id) REFERENCES fotografico_sla (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_sla_pausa_hold FOREIGN KEY (hold_id) REFERENCES fotografico_hold (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_pendencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    captura_id BIGINT UNSIGNED NULL,
    codigo VARCHAR(60) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    detalhes TEXT NULL,
    status ENUM(
        'ABERTA',
        'RESOLVIDA',
        'IGNORADA'
    ) NOT NULL DEFAULT 'ABERTA',
    responsavel_id INT NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_por INT NULL,
    resolvido_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_fotografico_pendencia_aberta (
        plano_id,
        status,
        responsavel_id
    ),
    CONSTRAINT fk_fotografico_pendencia_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_pendencia_captura FOREIGN KEY (captura_id) REFERENCES fotografico_captura (id) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_pendencia_resp FOREIGN KEY (responsavel_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_pendencia_criador FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_pendencia_resolvedor FOREIGN KEY (resolvido_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_execucao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    versao_id BIGINT UNSIGNED NOT NULL,
    tentativa INT UNSIGNED NOT NULL,
    responsavel_id INT NULL,
    data_planejada DATE NULL,
    executado_em DATETIME NOT NULL,
    submetido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    material_url VARCHAR(1000) NULL,
    observacao TEXT NULL,
    resultado ENUM(
        'EM_CONFERENCIA',
        'APROVADA',
        'COMPLEMENTO'
    ) NOT NULL DEFAULT 'EM_CONFERENCIA',
    conferido_por INT NULL,
    conferido_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_execucao_tentativa (plano_id, tentativa),
    KEY idx_fotografico_execucao_resultado (plano_id, resultado),
    CONSTRAINT fk_fotografico_execucao_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_execucao_versao FOREIGN KEY (versao_id) REFERENCES fotografico_plano_versao (id),
    CONSTRAINT fk_fotografico_execucao_resp FOREIGN KEY (responsavel_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_execucao_conferente FOREIGN KEY (conferido_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_execucao_captura (
    execucao_id BIGINT UNSIGNED NOT NULL,
    captura_id BIGINT UNSIGNED NOT NULL,
    status ENUM(
        'PENDENTE',
        'ATENDIDA',
        'INCOMPLETA',
        'INVALIDA',
        'COMPLEMENTO'
    ) NOT NULL DEFAULT 'PENDENTE',
    observacao TEXT NULL,
    PRIMARY KEY (execucao_id, captura_id),
    KEY idx_fotografico_execucao_captura_status (captura_id, status),
    CONSTRAINT fk_fotografico_execucao_captura_exec FOREIGN KEY (execucao_id) REFERENCES fotografico_execucao (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_execucao_captura_captura FOREIGN KEY (captura_id) REFERENCES fotografico_captura (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_anexo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    entidade_tipo ENUM(
        'PLANO',
        'POSICAO',
        'EXECUCAO',
        'CAPTURA'
    ) NOT NULL,
    entidade_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('UPLOAD', 'URL') NOT NULL,
    nome_original VARCHAR(255) NULL,
    caminho VARCHAR(700) NULL,
    url VARCHAR(1000) NULL,
    mime VARCHAR(120) NULL,
    tamanho_bytes BIGINT UNSIGNED NULL,
    hash_sha1 CHAR(40) NULL,
    anotacao_json JSON NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    arquivado_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_fotografico_anexo_entidade (
        entidade_tipo,
        entidade_id,
        arquivado_em
    ),
    KEY idx_fotografico_anexo_plano (plano_id, criado_em),
    CONSTRAINT fk_fotografico_anexo_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_anexo_criador FOREIGN KEY (criado_por) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_evento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    status_anterior VARCHAR(40) NULL,
    status_novo VARCHAR(40) NULL,
    ator_id INT NULL,
    origem VARCHAR(80) NOT NULL,
    dados_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fotografico_evento_plano (plano_id, criado_em, id),
    CONSTRAINT fk_fotografico_evento_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_evento_ator FOREIGN KEY (ator_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE fotografico_notificacao_envio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id BIGINT UNSIGNED NOT NULL,
    chave VARCHAR(160) NOT NULL,
    notificacao_id INT UNSIGNED NULL,
    enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_fotografico_notificacao_chave (plano_id, chave),
    CONSTRAINT fk_fotografico_notificacao_plano FOREIGN KEY (plano_id) REFERENCES fotografico_plano (id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_notificacao_item FOREIGN KEY (notificacao_id) REFERENCES notificacoes (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    fotografico_calendario_feriado (data_feriado, nome, escopo)
VALUES (
        '2026-01-01',
        'Confraternizacao Universal',
        'NACIONAL'
    ),
    (
        '2026-02-16',
        'Carnaval',
        'NACIONAL'
    ),
    (
        '2026-02-17',
        'Carnaval',
        'NACIONAL'
    ),
    (
        '2026-04-03',
        'Paixao de Cristo',
        'NACIONAL'
    ),
    (
        '2026-04-21',
        'Tiradentes',
        'NACIONAL'
    ),
    (
        '2026-05-01',
        'Dia do Trabalho',
        'NACIONAL'
    ),
    (
        '2026-06-04',
        'Corpus Christi',
        'NACIONAL'
    ),
    (
        '2026-09-07',
        'Independencia do Brasil',
        'NACIONAL'
    ),
    (
        '2026-10-12',
        'Nossa Senhora Aparecida',
        'NACIONAL'
    ),
    (
        '2026-11-02',
        'Finados',
        'NACIONAL'
    ),
    (
        '2026-11-15',
        'Proclamacao da Republica',
        'NACIONAL'
    ),
    (
        '2026-11-20',
        'Dia da Consciencia Negra',
        'NACIONAL'
    ),
    (
        '2026-12-25',
        'Natal',
        'NACIONAL'
    )
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    bloqueia_dia_util = 1;