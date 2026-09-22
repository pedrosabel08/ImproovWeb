-- Ciclos de execução oficiais (V2).
-- Migração aditiva: mantém as estruturas de prazo/histórico legadas intactas.

ALTER TABLE janela_operacional_ciclo
    ADD COLUMN numero_ciclo SMALLINT UNSIGNED NULL AFTER chave_referencia,
    ADD COLUMN origem_abertura VARCHAR(40) NULL AFTER numero_ciclo,
    ADD COLUMN qualidade_dados VARCHAR(40) NOT NULL DEFAULT 'CANONICO' AFTER origem_abertura,
    ADD COLUMN status_saida VARCHAR(60) NULL AFTER motivo_encerramento;

-- Os ciclos históricos recebem numeração determinística e continuam auditáveis como legado.
UPDATE janela_operacional_ciclo c
JOIN (
    SELECT id,
           ROW_NUMBER() OVER (
               PARTITION BY chave_referencia
               ORDER BY COALESCE(inicio_em, criado_em), id
           ) AS numero_calculado
    FROM janela_operacional_ciclo
) numerados ON numerados.id = c.id
SET c.numero_ciclo = COALESCE(c.numero_ciclo, numerados.numero_calculado),
    c.origem_abertura = COALESCE(c.origem_abertura, 'RECUPERADO'),
    c.qualidade_dados = CASE
        WHEN c.qualidade_dados IS NULL OR c.qualidade_dados = 'CANONICO' THEN 'LEGADO_PARCIAL'
        ELSE c.qualidade_dados
    END;

ALTER TABLE janela_operacional_ciclo
    MODIFY COLUMN perfil_id BIGINT UNSIGNED NULL,
    MODIFY COLUMN perfil_codigo_snapshot VARCHAR(50) NULL,
    MODIFY COLUMN perfil_versao_snapshot INT UNSIGNED NULL,
    MODIFY COLUMN perfil_nome_snapshot VARCHAR(100) NULL,
    MODIFY COLUMN aplica_regra_snapshot TINYINT(1) NULL,
    MODIFY COLUMN inicio_em DATETIME NULL,
    MODIFY COLUMN previsao_original DATE NULL,
    MODIFY COLUMN previsao_atual DATE NULL,
    MODIFY COLUMN estado_original ENUM('NORMAL','EXCECAO_OPERACIONAL','CONFLITO_PLANEJAMENTO') NULL,
    MODIFY COLUMN estado_atual ENUM('NORMAL','EXCECAO_OPERACIONAL','CONFLITO_PLANEJAMENTO') NULL,
    MODIFY COLUMN situacao ENUM('AGUARDANDO_INICIO','ATIVO','PAUSADO','ENCERRADO') NOT NULL DEFAULT 'ATIVO';

ALTER TABLE janela_operacional_ciclo DROP CHECK chk_janela_ciclo_limite;
ALTER TABLE janela_operacional_ciclo
    ADD CONSTRAINT chk_janela_ciclo_limite CHECK (
        situacao = 'AGUARDANDO_INICIO'
        OR (
            (aplica_regra_snapshot = 1
             AND limite_dias_uteis_snapshot IS NOT NULL
             AND limite_data_original IS NOT NULL)
            OR
            (aplica_regra_snapshot = 0
             AND limite_dias_uteis_snapshot IS NULL
             AND limite_data_original IS NULL)
        )
    ),
    ADD UNIQUE KEY uq_janela_ciclo_numero (chave_referencia, numero_ciclo),
    ADD KEY idx_janela_ciclo_origem_situacao (origem_abertura, situacao);

ALTER TABLE janela_operacional_evento
    ADD COLUMN status_tarefa_anterior VARCHAR(60) NULL AFTER evento,
    ADD COLUMN status_tarefa_novo VARCHAR(60) NULL AFTER status_tarefa_anterior;

CREATE TABLE IF NOT EXISTS funcao_imagem_angulo_ciencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    funcao_imagem_id INT NOT NULL,
    historico_imagem_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    escolhido_por_colaborador_id INT NULL,
    escolhido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visualizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_angulo_ciencia_destinatario (funcao_imagem_id, historico_imagem_id, colaborador_id),
    KEY idx_angulo_ciencia_pendente (colaborador_id, visualizado_em, escolhido_em),
    CONSTRAINT fk_angulo_ciencia_funcao FOREIGN KEY (funcao_imagem_id)
        REFERENCES funcao_imagem (idfuncao_imagem) ON DELETE CASCADE,
    CONSTRAINT fk_angulo_ciencia_colaborador FOREIGN KEY (colaborador_id)
        REFERENCES colaborador (idcolaborador) ON DELETE RESTRICT,
    CONSTRAINT fk_angulo_ciencia_escolhedor FOREIGN KEY (escolhido_por_colaborador_id)
        REFERENCES colaborador (idcolaborador) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
