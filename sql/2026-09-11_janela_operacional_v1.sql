-- Janela Operacional V1
-- Configuracao versionada, ciclos imutaveis no primeiro inicio e eventos append-only.
-- Esta migration nao altera o significado legado de funcao_imagem.prazo.

CREATE TABLE IF NOT EXISTS janela_operacional_perfil (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    nome VARCHAR(100) NOT NULL,
    aplica_regra TINYINT(1) NOT NULL,
    limite_dias_uteis SMALLINT UNSIGNED NULL,
    vigente TINYINT(1) NOT NULL DEFAULT 1,
    vigente_token VARCHAR(10) NULL DEFAULT 'VIGENTE',
    criado_por_colaborador_id INT NULL,
    criado_por_usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_janela_perfil_versao (codigo, versao),
    UNIQUE KEY uq_janela_perfil_vigente (codigo, vigente_token),
    KEY idx_janela_perfil_consulta (codigo, vigente),
    CONSTRAINT chk_janela_perfil_limite CHECK (
        (
            aplica_regra = 1
            AND limite_dias_uteis IS NOT NULL
            AND limite_dias_uteis > 0
        )
        OR (
            aplica_regra = 0
            AND limite_dias_uteis IS NULL
        )
    ),
    CONSTRAINT fk_janela_perfil_criador FOREIGN KEY (criado_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS janela_operacional_motivo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    label VARCHAR(120) NOT NULL,
    exige_texto TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_janela_motivo_codigo (codigo),
    KEY idx_janela_motivo_ativo_ordem (ativo, ordem)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS janela_operacional_ciclo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave_referencia VARCHAR(120) NOT NULL,
    ciclo_anterior_id BIGINT UNSIGNED NULL,
    unidade_trabalho_id BIGINT UNSIGNED NULL,
    perfil_id BIGINT UNSIGNED NOT NULL,
    perfil_codigo_snapshot VARCHAR(50) NOT NULL,
    perfil_versao_snapshot INT UNSIGNED NOT NULL,
    perfil_nome_snapshot VARCHAR(100) NOT NULL,
    aplica_regra_snapshot TINYINT(1) NOT NULL,
    limite_dias_uteis_snapshot SMALLINT UNSIGNED NULL,
    inicio_em DATETIME NOT NULL,
    limite_data_original DATE NULL,
    limite_data_atual DATE NULL,
    prazo_necessario_snapshot DATE NULL,
    planejamento_versao_id_snapshot BIGINT NULL,
    previsao_original DATE NOT NULL,
    previsao_atual DATE NOT NULL,
    estado_original ENUM(
        'NORMAL',
        'EXCECAO_OPERACIONAL',
        'CONFLITO_PLANEJAMENTO'
    ) NOT NULL,
    estado_atual ENUM(
        'NORMAL',
        'EXCECAO_OPERACIONAL',
        'CONFLITO_PLANEJAMENTO'
    ) NOT NULL,
    motivo_original_id BIGINT UNSIGNED NULL,
    motivo_original_codigo VARCHAR(50) NULL,
    motivo_original_label VARCHAR(120) NULL,
    motivo_original_texto VARCHAR(500) NULL,
    responsavel_original_id INT NOT NULL,
    responsavel_atual_id INT NOT NULL,
    situacao ENUM(
        'ATIVO',
        'PAUSADO',
        'ENCERRADO'
    ) NOT NULL DEFAULT 'ATIVO',
    ativo_token VARCHAR(10) NULL DEFAULT 'ATIVO',
    encerrado_em DATETIME NULL,
    motivo_encerramento VARCHAR(50) NULL,
    criado_por_colaborador_id INT NULL,
    criado_por_usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_janela_ciclo_ativo (chave_referencia, ativo_token),
    KEY idx_janela_ciclo_estado (
        situacao,
        estado_atual,
        responsavel_atual_id
    ),
    KEY idx_janela_ciclo_inicio (inicio_em),
    KEY idx_janela_ciclo_perfil (
        perfil_codigo_snapshot,
        criado_em
    ),
    CONSTRAINT chk_janela_ciclo_limite CHECK (
        (
            aplica_regra_snapshot = 1
            AND limite_dias_uteis_snapshot IS NOT NULL
            AND limite_data_original IS NOT NULL
        )
        OR (
            aplica_regra_snapshot = 0
            AND limite_dias_uteis_snapshot IS NULL
            AND limite_data_original IS NULL
        )
    ),
    CONSTRAINT fk_janela_ciclo_anterior FOREIGN KEY (ciclo_anterior_id) REFERENCES janela_operacional_ciclo (id) ON DELETE SET NULL,
    CONSTRAINT fk_janela_ciclo_unidade FOREIGN KEY (unidade_trabalho_id) REFERENCES unidade_trabalho (id) ON DELETE SET NULL,
    CONSTRAINT fk_janela_ciclo_perfil FOREIGN KEY (perfil_id) REFERENCES janela_operacional_perfil (id) ON DELETE RESTRICT,
    CONSTRAINT fk_janela_ciclo_motivo_original FOREIGN KEY (motivo_original_id) REFERENCES janela_operacional_motivo (id) ON DELETE SET NULL,
    CONSTRAINT fk_janela_ciclo_resp_original FOREIGN KEY (responsavel_original_id) REFERENCES colaborador (idcolaborador) ON DELETE RESTRICT,
    CONSTRAINT fk_janela_ciclo_resp_atual FOREIGN KEY (responsavel_atual_id) REFERENCES colaborador (idcolaborador) ON DELETE RESTRICT,
    CONSTRAINT fk_janela_ciclo_criador FOREIGN KEY (criado_por_colaborador_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS janela_operacional_ciclo_item (
    ciclo_id BIGINT UNSIGNED NOT NULL,
    funcao_imagem_id INT NOT NULL,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ciclo_id, funcao_imagem_id),
    KEY idx_janela_item_tarefa (funcao_imagem_id, ciclo_id),
    CONSTRAINT fk_janela_item_ciclo FOREIGN KEY (ciclo_id) REFERENCES janela_operacional_ciclo (id) ON DELETE CASCADE,
    CONSTRAINT fk_janela_item_tarefa FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem (idfuncao_imagem) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS janela_operacional_pausa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ciclo_id BIGINT UNSIGNED NOT NULL,
    inicio_em DATETIME NOT NULL,
    fim_em DATETIME NULL,
    dias_uteis_suspensos SMALLINT UNSIGNED NULL,
    origem VARCHAR(50) NOT NULL DEFAULT 'HOLD',
    flow_issue_id BIGINT NULL,
    ativa_token VARCHAR(10) NULL DEFAULT 'ATIVA',
    criado_por_colaborador_id INT NULL,
    criado_por_usuario_id INT NULL,
    encerrado_por_colaborador_id INT NULL,
    encerrado_por_usuario_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_janela_pausa_ativa (ciclo_id, ativa_token),
    KEY idx_janela_pausa_periodo (ciclo_id, inicio_em, fim_em),
    CONSTRAINT fk_janela_pausa_ciclo FOREIGN KEY (ciclo_id) REFERENCES janela_operacional_ciclo (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS janela_operacional_evento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ciclo_id BIGINT UNSIGNED NOT NULL,
    evento VARCHAR(50) NOT NULL,
    estado_anterior VARCHAR(40) NULL,
    estado_novo VARCHAR(40) NULL,
    previsao_anterior DATE NULL,
    previsao_nova DATE NULL,
    prazo_necessario_snapshot DATE NULL,
    limite_data_snapshot DATE NULL,
    motivo_id BIGINT UNSIGNED NULL,
    motivo_codigo_snapshot VARCHAR(50) NULL,
    motivo_label_snapshot VARCHAR(120) NULL,
    motivo_texto VARCHAR(500) NULL,
    responsavel_anterior_id INT NULL,
    responsavel_novo_id INT NULL,
    dias_uteis_consumidos SMALLINT UNSIGNED NULL,
    ator_colaborador_id INT NULL,
    ator_usuario_id INT NULL,
    detalhes JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_janela_evento_ciclo_data (ciclo_id, criado_em),
    KEY idx_janela_evento_tipo_data (evento, criado_em),
    KEY idx_janela_evento_motivo (
        motivo_codigo_snapshot,
        criado_em
    ),
    CONSTRAINT fk_janela_evento_ciclo FOREIGN KEY (ciclo_id) REFERENCES janela_operacional_ciclo (id) ON DELETE CASCADE,
    CONSTRAINT fk_janela_evento_motivo FOREIGN KEY (motivo_id) REFERENCES janela_operacional_motivo (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    janela_operacional_motivo (
        codigo,
        label,
        exige_texto,
        ordem
    )
VALUES (
        'COMPLEXIDADE_TAREFA',
        'Complexidade da tarefa',
        0,
        10
    ),
    (
        'DEPENDENCIA_PROJETO',
        'Dependência de projeto',
        0,
        20
    ),
    (
        'PRIORIDADE_DIRECIONADA',
        'Prioridade direcionada',
        0,
        30
    ),
    (
        'AUSENCIA_PROGRAMADA',
        'Ausência / viagem programada',
        0,
        40
    ),
    (
        'DEPENDENCIA_TECNICA',
        'Dependência técnica',
        0,
        50
    ),
    ('OUTRO', 'Outro', 1, 60)
ON DUPLICATE KEY UPDATE
    codigo = VALUES(codigo);

INSERT INTO
    janela_operacional_perfil (
        codigo,
        versao,
        nome,
        aplica_regra,
        limite_dias_uteis,
        vigente,
        vigente_token
    )
VALUES (
        'CADERNO_FILTRO',
        1,
        'Caderno + Filtro',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'CADERNO',
        1,
        'Caderno',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'FILTRO_ASSETS',
        1,
        'Filtro de Assets',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'MODELAGEM_COMUM',
        1,
        'Modelagem comum',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'MODELAGEM_FACHADA',
        1,
        'Modelagem fachada',
        1,
        10,
        1,
        'VIGENTE'
    ),
    (
        'MODELAGEM_COMPOSICAO',
        1,
        'Modelagem + Composição',
        1,
        4,
        1,
        'VIGENTE'
    ),
    (
        'COMPOSICAO',
        1,
        'Composição',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'FINALIZACAO_INTERNA',
        1,
        'Finalização interna',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'FINALIZACAO_EXTERNA',
        1,
        'Finalização externa',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'FINALIZACAO_PLANTA',
        1,
        'Finalização planta',
        1,
        2,
        1,
        'VIGENTE'
    ),
    (
        'POS_PRODUCAO',
        1,
        'Pós-produção',
        1,
        1,
        1,
        'VIGENTE'
    ),
    (
        'ALTERACAO',
        1,
        'Alteração',
        0,
        NULL,
        1,
        'VIGENTE'
    )
ON DUPLICATE KEY UPDATE
    codigo = VALUES(codigo);