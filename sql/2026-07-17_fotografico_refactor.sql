-- Refatoracao do Planejamento Fotografico: mapa com pins e conferencia global.
-- Executar depois de 2026-07-17_fotografico_mvp.sql.
-- A migration preserva anexos e a tabela fotografico_execucao_captura para consulta legada.

ALTER TABLE obra
    DROP COLUMN latitude,
    DROP COLUMN longitude;

ALTER TABLE fotografico_plano_versao
    DROP COLUMN latitude_snapshot,
    DROP COLUMN longitude_snapshot,
    ADD COLUMN mapa_anexo_id BIGINT UNSIGNED NULL AFTER maps_url_snapshot;

ALTER TABLE fotografico_anexo
    MODIFY COLUMN entidade_tipo ENUM('PLANO', 'POSICAO', 'EXECUCAO', 'CAPTURA', 'VERSAO') NOT NULL,
    ADD COLUMN categoria ENUM('EVIDENCIA', 'MAPA') NOT NULL DEFAULT 'EVIDENCIA' AFTER tipo;

ALTER TABLE fotografico_posicao
    DROP COLUMN latitude,
    DROP COLUMN longitude,
    ADD COLUMN x_percentual DECIMAL(6, 3) NOT NULL DEFAULT 50.000 AFTER codigo,
    ADD COLUMN y_percentual DECIMAL(6, 3) NOT NULL DEFAULT 50.000 AFTER x_percentual,
    ADD COLUMN criado_por INT NULL AFTER anotacao_json,
    ADD COLUMN atualizado_por INT NULL AFTER criado_por,
    ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER atualizado_por,
    ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em,
    ADD KEY idx_fotografico_posicao_mapa (versao_id, ordem),
    ADD CONSTRAINT fk_fotografico_posicao_criador FOREIGN KEY (criado_por) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL,
    ADD CONSTRAINT fk_fotografico_posicao_atualizador FOREIGN KEY (atualizado_por) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL;

-- Converte a anotacao proporcional que ja existia no rascunho atual. Pins sem anotacao ficam no centro, sem perda do rascunho.
UPDATE fotografico_posicao
   SET x_percentual = COALESCE(
           CAST(JSON_UNQUOTE(JSON_EXTRACT(anotacao_json, '$.x_percentual')) AS DECIMAL(6,3)),
           CAST(JSON_UNQUOTE(JSON_EXTRACT(anotacao_json, '$.x')) AS DECIMAL(6,3)),
           50.000
       ),
       y_percentual = COALESCE(
           CAST(JSON_UNQUOTE(JSON_EXTRACT(anotacao_json, '$.y_percentual')) AS DECIMAL(6,3)),
           CAST(JSON_UNQUOTE(JSON_EXTRACT(anotacao_json, '$.y')) AS DECIMAL(6,3)),
           50.000
       );

ALTER TABLE fotografico_captura
    ADD UNIQUE KEY uk_fotografico_captura_posicao_periodo (posicao_id, periodo_id);

ALTER TABLE fotografico_execucao
    MODIFY COLUMN resultado ENUM('EM_CONFERENCIA', 'APROVADA', 'APROVADA_COM_RESSALVAS', 'COMPLEMENTO', 'REPROVADA') NOT NULL DEFAULT 'EM_CONFERENCIA',
    ADD COLUMN enviado_por INT NULL AFTER responsavel_id,
    ADD CONSTRAINT fk_fotografico_execucao_enviado_por FOREIGN KEY (enviado_por) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL;

CREATE TABLE fotografico_execucao_conferencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    execucao_id BIGINT UNSIGNED NOT NULL,
    decisao ENUM('APROVADO', 'APROVADO_COM_RESSALVAS', 'COMPLEMENTO_NECESSARIO', 'REPROVADO') NOT NULL,
    consideracao TEXT NOT NULL,
    status_anterior VARCHAR(40) NOT NULL,
    status_resultante VARCHAR(40) NOT NULL,
    corrigida_de_id BIGINT UNSIGNED NULL,
    conferido_por INT NULL,
    conferido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fotografico_conferencia_execucao (execucao_id, conferido_em, id),
    CONSTRAINT fk_fotografico_conferencia_execucao FOREIGN KEY (execucao_id) REFERENCES fotografico_execucao(id) ON DELETE CASCADE,
    CONSTRAINT fk_fotografico_conferencia_origem FOREIGN KEY (corrigida_de_id) REFERENCES fotografico_execucao_conferencia(id) ON DELETE SET NULL,
    CONSTRAINT fk_fotografico_conferencia_ator FOREIGN KEY (conferido_por) REFERENCES colaborador(idcolaborador) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fotografico_plano_versao
    ADD CONSTRAINT fk_fotografico_versao_mapa FOREIGN KEY (mapa_anexo_id) REFERENCES fotografico_anexo(id) ON DELETE SET NULL;

-- Migra o anexo de mapa antigo quando ele estiver ligado a uma posicao da versao e for uma imagem valida.
UPDATE fotografico_anexo a
JOIN fotografico_posicao p ON p.id = a.entidade_id AND a.entidade_tipo = 'POSICAO'
JOIN fotografico_plano_versao v ON v.id = p.versao_id
LEFT JOIN fotografico_anexo ja_mapa ON ja_mapa.entidade_tipo = 'VERSAO' AND ja_mapa.entidade_id = v.id AND ja_mapa.categoria = 'MAPA' AND ja_mapa.arquivado_em IS NULL
   SET a.entidade_tipo = 'VERSAO', a.entidade_id = v.id, a.categoria = 'MAPA'
 WHERE ja_mapa.id IS NULL
   AND a.mime IN ('image/jpeg', 'image/png', 'image/webp')
   AND a.arquivado_em IS NULL;

UPDATE fotografico_plano_versao v
JOIN fotografico_anexo a ON a.entidade_tipo = 'VERSAO' AND a.entidade_id = v.id AND a.categoria = 'MAPA' AND a.arquivado_em IS NULL
   SET v.mapa_anexo_id = a.id
 WHERE v.mapa_anexo_id IS NULL;

-- subtipo_imagem tambem possui referencias que nao sao pavimentos; o snapshot e exibido como "Referencia" no modulo.
UPDATE fotografico_plano_imagem pi
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = pi.imagem_id
LEFT JOIN subtipo_imagem s ON s.id = i.subtipo_id
   SET pi.pavimento_referencia = COALESCE(NULLIF(pi.pavimento_referencia, ''), s.nome);
