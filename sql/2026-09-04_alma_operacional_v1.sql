-- Evolucao incremental do ALMA V1 para preenchimento operacional por obra.
-- Preserva revisoes, campos narrativos legados e todo o historico existente.

CREATE TABLE IF NOT EXISTS alma_projeto_direcao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    obra_id INT NOT NULL,
    biblioteca_versao_id BIGINT UNSIGNED NOT NULL,
    criada_por_usuario_id INT NULL,
    atualizada_por_usuario_id INT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    lock_version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_projeto_direcao_obra (obra_id),
    KEY idx_alma_projeto_biblioteca (biblioteca_versao_id),
    CONSTRAINT fk_alma_projeto_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra) ON DELETE CASCADE,
    CONSTRAINT fk_alma_projeto_biblioteca FOREIGN KEY (biblioteca_versao_id) REFERENCES alma_biblioteca_versao (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_projeto_criador FOREIGN KEY (criada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL,
    CONSTRAINT fk_alma_projeto_atualizador FOREIGN KEY (atualizada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_projeto_selecao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    projeto_direcao_id BIGINT UNSIGNED NOT NULL,
    dimensao_id BIGINT UNSIGNED NOT NULL,
    item_biblioteca_id BIGINT UNSIGNED NOT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_projeto_selecao_dimensao (projeto_direcao_id, dimensao_id),
    KEY idx_alma_projeto_selecao_item (item_biblioteca_id),
    CONSTRAINT fk_alma_projeto_selecao_direcao FOREIGN KEY (projeto_direcao_id) REFERENCES alma_projeto_direcao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_projeto_selecao_dimensao FOREIGN KEY (dimensao_id) REFERENCES alma_biblioteca_dimensao (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_projeto_selecao_item FOREIGN KEY (item_biblioteca_id) REFERENCES alma_biblioteca_item (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_projeto_referencia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selecao_id BIGINT UNSIGNED NOT NULL,
    sire_referencia_id BIGINT NOT NULL,
    criada_por_usuario_id INT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alma_projeto_referencia (selecao_id, sire_referencia_id),
    KEY idx_alma_projeto_referencia_sire (sire_referencia_id),
    CONSTRAINT fk_alma_projeto_referencia_selecao FOREIGN KEY (selecao_id) REFERENCES alma_projeto_selecao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_projeto_referencia_sire FOREIGN KEY (sire_referencia_id) REFERENCES sire_referencia (id) ON DELETE RESTRICT,
    CONSTRAINT fk_alma_projeto_referencia_criador FOREIGN KEY (criada_por_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alma_projeto_evento (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    projeto_direcao_id BIGINT UNSIGNED NOT NULL,
    ator_usuario_id INT NULL,
    acao VARCHAR(60) NOT NULL,
    antes_json JSON NULL,
    depois_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alma_projeto_evento_data (projeto_direcao_id, criado_em),
    CONSTRAINT fk_alma_projeto_evento_direcao FOREIGN KEY (projeto_direcao_id) REFERENCES alma_projeto_direcao (id) ON DELETE CASCADE,
    CONSTRAINT fk_alma_projeto_evento_ator FOREIGN KEY (ator_usuario_id) REFERENCES usuario (idusuario) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- As dimensoes fotograficas legadas permanecem no banco para preservar historico.
-- Apenas Direcao Fotografica continua ativa no fluxo operacional atual.
UPDATE alma_biblioteca_dimensao
   SET ativa = CASE WHEN codigo = 'fotografia_direcao' THEN 1 ELSE 0 END,
       exige_item_biblioteca = CASE WHEN codigo = 'fotografia_direcao' THEN 1 ELSE exige_item_biblioteca END
 WHERE codigo IN ('fotografia_direcao', 'fotografia_teste_angulos', 'fotografia_enquadramento', 'fotografia_referencias_sire');

-- Reaproveita o vocabulario fotografico ja administrado no SIRE como itens ALMA,
-- sem inventar uma segunda taxonomia paralela.
INSERT IGNORE INTO alma_biblioteca_item
    (dimensao_id, codigo, titulo, ordem, ativo)
SELECT d.id,
       CONCAT('sire-', v.id),
       v.nome,
       1000 + ROW_NUMBER() OVER (PARTITION BY d.id ORDER BY v.nome),
       v.ativo
  FROM alma_biblioteca_dimensao d
  JOIN sire_pilar p ON p.codigo = d.pilar_codigo
  JOIN sire_pilar_valor v ON v.pilar_id = p.id
 WHERE d.codigo = 'fotografia_direcao';

ALTER TABLE alma_revisao_selecao
    ADD UNIQUE KEY uq_alma_revisao_selecao_dimensao (revisao_id, dimensao_id);

ALTER TABLE alma_revisao_referencia
    ADD UNIQUE KEY uq_alma_revisao_referencia (revisao_id, dimensao_id, sire_referencia_id);

