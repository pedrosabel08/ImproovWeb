-- Flow Block: Issues de impedimento vinculadas exclusivamente a funcao_imagem.
CREATE TABLE IF NOT EXISTS flow_issue_tipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(60) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS flow_issue_fila (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(60) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS flow_issue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(24) NULL UNIQUE,
    funcao_imagem_id INT NOT NULL,
    tipo_id INT NOT NULL,
    fila_id INT NULL,
    responsavel_colaborador_id INT NULL,
    descricao TEXT NOT NULL,
    urgencia ENUM(
        'BAIXA',
        'NORMAL',
        'ALTA',
        'CRITICA'
    ) NOT NULL DEFAULT 'NORMAL',
    status ENUM(
        'ABERTA',
        'AGUARDANDO_ACAO',
        'PAUSADA',
        'RESOLVIDA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'ABERTA',
    bloqueante TINYINT(1) NOT NULL DEFAULT 1,
    criado_por_colaborador_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolvido_por_colaborador_id INT NULL,
    resolvido_em DATETIME NULL,
    encerramento_observacao TEXT NULL,
    confirmada_por_colaborador_id INT NULL,
    confirmada_em DATETIME NULL,
    confirmacao_observacao TEXT NULL,
    sla_atendimento_em DATETIME NOT NULL,
    primeira_tratativa_em DATETIME NULL,
    pausada_em DATETIME NULL,
    pausada_por_colaborador_id INT NULL,
    pausa_motivo TEXT NULL,
    pausa_observacao TEXT NULL,
    retorno_previsto_em DATETIME NULL,
    proxima_cobranca_em DATETIME NULL,
    legado TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_flow_issue_task_status (funcao_imagem_id, status),
    INDEX idx_flow_issue_updated (atualizado_em),
    INDEX idx_flow_issue_responsavel (responsavel_colaborador_id),
    INDEX idx_flow_issue_tipo (tipo_id),
    INDEX idx_flow_issue_fila (fila_id),
    INDEX idx_flow_issue_cobranca (proxima_cobranca_em, status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS flow_issue_ciclo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    status_inicial VARCHAR(50) NULL,
    status_final VARCHAR(50) NULL,
    INDEX idx_flow_issue_ciclo_issue (issue_id, iniciado_em)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS flow_issue_atividade (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    conteudo TEXT NULL,
    metadados JSON NULL,
    criado_por_colaborador_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atividade_pai_id BIGINT NULL,
    atualizado_em DATETIME NULL,
    excluido_em DATETIME NULL,
    excluido_por_colaborador_id INT NULL,
    INDEX idx_flow_issue_atividade_issue (issue_id, criado_em),
    INDEX idx_flow_issue_atividade_pai (atividade_pai_id, criado_em)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS flow_issue_anexo (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    atividade_id BIGINT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    tamanho BIGINT NULL,
    mime_type VARCHAR(120) NULL,
    criado_por_colaborador_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_flow_issue_anexo_issue (issue_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS flow_issue_mencao (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    atividade_id BIGINT NOT NULL,
    colaborador_id INT NOT NULL,
    mencionado_por_colaborador_id INT NULL,
    status ENUM('PENDENTE', 'LIDA') NOT NULL DEFAULT 'PENDENTE',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visualizado_em DATETIME NULL,
    UNIQUE KEY uq_flow_issue_mencao (atividade_id, colaborador_id),
    INDEX idx_flow_issue_mencao_destinatario_status (colaborador_id, status, criado_em),
    INDEX idx_flow_issue_mencao_issue_status (issue_id, status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT IGNORE INTO
    flow_issue_tipo (codigo, nome, ordem)
VALUES (
        'FOTOGRAFICO_FALTANTE',
        'Fotográfico faltante',
        10
    ),
    (
        'REFERENCIA_NAO_DEFINIDA',
        'Referência não definida',
        20
    ),
    (
        'ARQUIVO_INCORRETO',
        'Arquivo incorreto',
        30
    ),
    (
        'ARQUIVO_FALTANTE',
        'Arquivo faltante',
        40
    ),
    (
        'APROVACAO_PENDENTE',
        'Aprovação pendente',
        50
    ),
    (
        'DUVIDA_TECNICA',
        'Dúvida técnica',
        60
    ),
    (
        'DEPENDENCIA_OUTRA_TAREFA',
        'Dependência de outra tarefa',
        70
    ),
    (
        'MUDANCA_ESCOPO',
        'Mudança de escopo',
        80
    ),
    (
        'PAGAMENTO_CONTRATO',
        'Pagamento/contrato',
        90
    ),
    ('OUTRO', 'Outro', 100),
    (
        'LEGADO_NAO_CLASSIFICADO',
        'Legado não classificado',
        999
    );

INSERT IGNORE INTO
    flow_issue_fila (codigo, nome, ordem)
VALUES ('CLIENTE', 'Cliente', 10),
    ('GESTAO', 'Gestão', 20),
    ('PRODUCAO', 'Produção', 30),
    ('COMERCIAL', 'Comercial', 40),
    (
        'ARQUITETURA',
        'Arquitetura',
        50
    ),
    ('TI', 'TI', 60),
    (
        'FINANCEIRO',
        'Financeiro',
        70
    ),
    ('OUTRO', 'Outro', 80);

-- Migração idempotente: somente HOLDs de tarefa sem Issue bloqueante ativa.
INSERT INTO
    flow_issue (
        codigo,
        funcao_imagem_id,
        tipo_id,
        descricao,
        urgencia,
        status,
        bloqueante,
        criado_por_colaborador_id,
        criado_em,
        atualizado_em,
        sla_atendimento_em,
        proxima_cobranca_em,
        legado
    )
SELECT NULL, fi.idfuncao_imagem, t.id, COALESCE(
        NULLIF(fi.observacao, ''), 'HOLD legado migrado sem classificação.'
    ), 'NORMAL', 'ABERTA', 1, COALESCE(fi.colaborador_id, 1), NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 2 HOUR), 1
FROM
    funcao_imagem fi
    JOIN flow_issue_tipo t ON t.codigo = 'LEGADO_NAO_CLASSIFICADO'
WHERE
    fi.status = 'HOLD'
    AND NOT EXISTS (
        SELECT 1
        FROM flow_issue x
        WHERE
            x.funcao_imagem_id = fi.idfuncao_imagem
            AND x.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
    );

UPDATE flow_issue
SET
    codigo = CONCAT('ISSUE-', LPAD(id, 4, '0'))
WHERE
    codigo IS NULL;

INSERT INTO
    flow_issue_ciclo (
        issue_id,
        iniciado_em,
        status_inicial
    )
SELECT i.id, i.criado_em, 'HOLD'
FROM flow_issue i
WHERE
    i.legado = 1
    AND NOT EXISTS (
        SELECT 1
        FROM flow_issue_ciclo c
        WHERE
            c.issue_id = i.id
    );

INSERT INTO
    flow_issue_atividade (
        issue_id,
        tipo,
        conteudo,
        criado_por_colaborador_id,
        criado_em
    )
SELECT i.id, 'CRIADA', 'Issue migrada automaticamente a partir de um HOLD legado.', i.criado_por_colaborador_id, i.criado_em
FROM flow_issue i
WHERE
    i.legado = 1
    AND NOT EXISTS (
        SELECT 1
        FROM flow_issue_atividade a
        WHERE
            a.issue_id = i.id
            AND a.tipo = 'CRIADA'
    );
