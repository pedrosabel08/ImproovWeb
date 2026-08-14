-- Evolucao do planejamento fotografico: tipo de foto e altura por ponto.
-- Migration aditiva: registros legados permanecem com os novos campos NULL.
-- Execute uma unica vez, apos 2026-07-22_fotografico_pendencias_integracao.sql.

ALTER TABLE fotografico_alturas
ADD COLUMN identificacao VARCHAR(120) NULL AFTER obra_id,
ADD COLUMN altura_m DECIMAL(7, 2) NULL AFTER altura,
ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER observacoes,
ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
ADD KEY idx_fotografico_alturas_obra_ativa (obra_id, ativo, identificacao);

-- Nao converte o campo textual legado automaticamente: uma descricao antiga pode
-- conter observacoes e nao e evidencia suficiente para inferir uma medida numerica.

ALTER TABLE fotografico_posicao
ADD COLUMN tipo_foto ENUM(
    '360',
    'PANORAMICA',
    'CLIQUE_UNICO'
) NULL AFTER codigo,
ADD COLUMN altura_id INT NULL AFTER altura_padrao_m,
ADD COLUMN altura_identificacao_snapshot VARCHAR(120) NULL AFTER altura_id,
ADD COLUMN altura_m_snapshot DECIMAL(7, 2) NULL AFTER altura_identificacao_snapshot,
ADD KEY idx_fotografico_posicao_altura (altura_id),
ADD CONSTRAINT fk_fotografico_posicao_altura FOREIGN KEY (altura_id) REFERENCES fotografico_alturas (id) ON DELETE RESTRICT;