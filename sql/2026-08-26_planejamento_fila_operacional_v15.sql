-- V1.5: ordem operacional confirmada e snapshots de projeção.
-- Não cria tarefas, não substitui prioridade_funcao e não altera baseline.
CREATE TABLE IF NOT EXISTS entrega_planejamento_fila_operacional (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planejamento_id BIGINT UNSIGNED NOT NULL,
    versao_id BIGINT UNSIGNED NOT NULL,
    entrega_id INT NOT NULL,
    obra_id INT NOT NULL,
    codigo_etapa VARCHAR(50) NOT NULL,
    referencia_fila VARCHAR(100) NOT NULL,
    colaborador_id INT NOT NULL,
    posicao SMALLINT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    fingerprint CHAR(64) NOT NULL,
    motivo VARCHAR(500) NULL,
    confirmado_por_colaborador_id INT NULL,
    confirmado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fila_ativa_colaborador (ativo, colaborador_id, codigo_etapa, posicao),
    KEY idx_fila_referencia (colaborador_id, codigo_etapa, referencia_fila, ativo),
    KEY idx_fila_entrega (entrega_id, ativo),
    KEY idx_fila_planejamento_versao (planejamento_id, versao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_planejamento_projecao_operacional (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planejamento_id BIGINT UNSIGNED NOT NULL,
    versao_id BIGINT UNSIGNED NOT NULL,
    entrega_id INT NOT NULL,
    obra_id INT NOT NULL,
    tipo ENUM('FILA_CONFIRMADA') NOT NULL DEFAULT 'FILA_CONFIRMADA',
    status_operacional VARCHAR(50) NOT NULL,
    data_referencia DATE NOT NULL,
    fim_operacional_projetado DATE NULL,
    margem_operacional_dias_uteis INT NULL,
    fingerprint CHAR(64) NOT NULL,
    confirmado_por_colaborador_id INT NULL,
    confirmado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    snapshot_json JSON NOT NULL,
    PRIMARY KEY (id),
    KEY idx_projecao_entrega (entrega_id, confirmado_em),
    KEY idx_projecao_fingerprint (fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_planejamento_projecao_etapa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    projecao_id BIGINT UNSIGNED NOT NULL,
    codigo_etapa VARCHAR(50) NOT NULL,
    inicio_operacional_projetado DATE NULL,
    fim_operacional_projetado DATE NULL,
    desvio_baseline_dias_uteis INT NULL,
    desvio_plano_vigente_dias_uteis INT NULL,
    margem_operacional_dias_uteis INT NULL,
    status_operacional VARCHAR(50) NOT NULL,
    confianca VARCHAR(30) NOT NULL,
    explicacao_json JSON NULL,
    PRIMARY KEY (id),
    KEY idx_projecao_etapa (projecao_id, codigo_etapa),
    CONSTRAINT fk_projecao_etapa_cabecalho FOREIGN KEY (projecao_id)
        REFERENCES entrega_planejamento_projecao_operacional(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
