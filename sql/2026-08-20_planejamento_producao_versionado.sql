-- Planejamento de Produção R00: versões imutáveis, baseline e auditoria.
-- Aplicar uma vez antes de habilitar confirmação no ambiente correspondente.
-- Requer MySQL 5.7+ (JSON é usado somente como armazenamento de snapshot).

CREATE TABLE IF NOT EXISTS entrega_planejamento_producao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entrega_id INT NOT NULL,
    estado ENUM(
        'RASCUNHO',
        'CONFIRMADO',
        'DESATUALIZADO',
        'REPLANEJAMENTO',
        'CONCLUIDO'
    ) NOT NULL DEFAULT 'RASCUNHO',
    versao_atual_id BIGINT UNSIGNED NULL,
    baseline_versao_id BIGINT UNSIGNED NULL,
    lock_version INT UNSIGNED NOT NULL DEFAULT 1,
    ultimo_fingerprint CHAR(64) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entrega_planejamento_entrega (entrega_id),
    KEY idx_entrega_planejamento_estado (estado),
    CONSTRAINT fk_entrega_planejamento_entrega FOREIGN KEY (entrega_id) REFERENCES entregas (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_planejamento_versao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planejamento_id BIGINT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    tipo ENUM('BASELINE', 'REPLANEJAMENTO') NOT NULL,
    vigente TINYINT(1) NOT NULL DEFAULT 0,
    vigente_token CHAR(7) NULL,
    fingerprint CHAR(64) NOT NULL,
    data_inicio DATE NOT NULL,
    prazo_r00 DATE NULL,
    fim_previsto DATE NULL,
    margem_dias_uteis INT NULL,
    status_plano VARCHAR(40) NOT NULL,
    motivo_codigo VARCHAR(40) NULL,
    motivo_observacao VARCHAR(500) NULL,
    confirmado_por_colaborador_id INT NULL,
    confirmado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    snapshot_json JSON NOT NULL,
    contexto_fingerprint_json JSON NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entrega_planejamento_versao_numero (planejamento_id, numero),
    UNIQUE KEY uq_entrega_planejamento_uma_vigente (
        planejamento_id,
        vigente_token
    ),
    KEY idx_entrega_planejamento_versao_vigente (planejamento_id, vigente),
    KEY idx_entrega_planejamento_versao_fingerprint (fingerprint),
    CONSTRAINT fk_entrega_planejamento_versao_planejamento FOREIGN KEY (planejamento_id) REFERENCES entrega_planejamento_producao (id) ON DELETE CASCADE,
    CONSTRAINT fk_entrega_planejamento_versao_confirmado_por FOREIGN KEY (confirmado_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- versao_atual_id e baseline_versao_id são referências de conveniência para
-- leitura rápida. As versões possuem a FK forte de volta para o planejamento;
-- manter essas duas colunas sem FK evita a dependência circular e faz a
-- migration permanecer reaplicável com CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS entrega_planejamento_funcao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    versao_id BIGINT UNSIGNED NOT NULL,
    codigo_etapa VARCHAR(50) NOT NULL,
    ordem_apresentacao SMALLINT UNSIGNED NOT NULL,
    nome_etapa VARCHAR(160) NOT NULL,
    volume INT UNSIGNED NOT NULL DEFAULT 0,
    pessoas_alocadas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    capacidade_editavel TINYINT(1) NOT NULL DEFAULT 0,
    estrategia_duracao VARCHAR(60) NULL,
    produtividade_json JSON NULL,
    formula_calculo TEXT NULL,
    duracao_dias_uteis INT NULL,
    data_inicio DATE NULL,
    data_limite DATE NULL,
    dependencias_json JSON NULL,
    confianca VARCHAR(30) NULL,
    origem_calculo VARCHAR(80) NULL,
    caminho_critico TINYINT(1) NOT NULL DEFAULT 0,
    metadados_json JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entrega_planejamento_funcao_versao_etapa (versao_id, codigo_etapa),
    KEY idx_entrega_planejamento_funcao_etapa (codigo_etapa),
    CONSTRAINT fk_entrega_planejamento_funcao_versao FOREIGN KEY (versao_id) REFERENCES entrega_planejamento_versao (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_planejamento_evento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planejamento_id BIGINT UNSIGNED NOT NULL,
    versao_id BIGINT UNSIGNED NULL,
    entrega_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    motivo_codigo VARCHAR(40) NULL,
    motivo_observacao VARCHAR(500) NULL,
    ator_colaborador_id INT NULL,
    metadados_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entrega_planejamento_evento_entrega_data (entrega_id, criado_em),
    KEY idx_entrega_planejamento_evento_planejamento_data (planejamento_id, criado_em),
    CONSTRAINT fk_entrega_planejamento_evento_planejamento FOREIGN KEY (planejamento_id) REFERENCES entrega_planejamento_producao (id) ON DELETE CASCADE,
    CONSTRAINT fk_entrega_planejamento_evento_versao FOREIGN KEY (versao_id) REFERENCES entrega_planejamento_versao (id) ON DELETE SET NULL,
    CONSTRAINT fk_entrega_planejamento_evento_entrega FOREIGN KEY (entrega_id) REFERENCES entregas (id) ON DELETE CASCADE,
    CONSTRAINT fk_entrega_planejamento_evento_ator FOREIGN KEY (ator_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;