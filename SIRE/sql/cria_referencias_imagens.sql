-- ============================================================
-- SIRE — Sistema de Importação de Referências de Imagens
-- Script: cria_referencias_imagens.sql
-- Descrição: Cria a tabela de controle de importação de imagens finais
-- ============================================================

CREATE TABLE IF NOT EXISTS referencias_imagens (
    id                 BIGINT          AUTO_INCREMENT PRIMARY KEY,

    funcao_imagem_id   BIGINT          NOT NULL,
    nomenclatura       VARCHAR(255)    NULL,
    nome_arquivo       VARCHAR(255)    NOT NULL,

    caminho_origem     TEXT            NOT NULL,
    caminho_storage    TEXT            NOT NULL,

    hash_sha1          CHAR(40)        NOT NULL,

    largura            INT             NULL,
    altura             INT             NULL,
    tamanho_bytes      BIGINT          NULL,

    importado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_hash    (hash_sha1),
    UNIQUE KEY uniq_funcao  (funcao_imagem_id),

    INDEX idx_nome (nome_arquivo),
    INDEX idx_hash (hash_sha1)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Controle de importação de imagens finais para o storage SIRE';
