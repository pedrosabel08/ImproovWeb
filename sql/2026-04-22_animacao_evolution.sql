-- =============================================================
-- Migração: Evolução do sistema de animações
-- Data: 2026-04-22
-- Executar em sequência. Fazer backup antes.
-- =============================================================

-- ============================================================
-- BACKUP (executar antes de qualquer alteração)
-- ============================================================
SET SESSION sql_mode = '';

CREATE TABLE IF NOT EXISTS animacao_backup_20260422 AS
SELECT *
FROM animacao;

CREATE TABLE IF NOT EXISTS imagem_animacao_backup_20260422 AS
SELECT *
FROM imagem_animacao;

SET SESSION sql_mode = DEFAULT;

-- ============================================================
-- AUDITORIA PRÉVIA
-- Verificar quais registros em animacao NÃO têm correspondência
-- em imagens_cliente_obra antes de migrar.
-- Executar manualmente e resolver antes do passo A:
-- ============================================================
SELECT a.idanimacao, a.imagem_id, ia.imagem_nome, ia.obra_id
FROM animacao a
JOIN imagem_animacao ia ON ia.idimagem_animacao = a.imagem_id
LEFT JOIN imagens_cliente_obra ico ON ico.obra_id = ia.obra_id AND ico.imagem_nome = ia.imagem_nome
WHERE ico.idimagens_cliente_obra IS NULL;

-- ============================================================
-- PASSO A — Mapear FK antiga para imagens_cliente_obra
-- (mantém imagem_id original intacto por enquanto)
-- ============================================================
ALTER TABLE animacao
ADD COLUMN imagem_ico_id INT NULL AFTER imagem_id;

UPDATE animacao a
JOIN imagem_animacao ia ON ia.idimagem_animacao = a.imagem_id
JOIN imagens_cliente_obra ico ON ico.obra_id = ia.obra_id
AND ico.imagem_nome = ia.imagem_nome
SET
    a.imagem_ico_id = ico.idimagens_cliente_obra;

-- ============================================================
-- PASSO B — Alterar tabela animacao
-- ATENÇÃO: confirmar nomes exatos dos índices/FKs com:
--   SHOW CREATE TABLE animacao;
-- Ajustar os nomes abaixo conforme necessário.
-- ============================================================

-- Remover FK antiga ANTES do índice (obrigatório no MySQL)
ALTER TABLE animacao DROP FOREIGN KEY anima_imagem;

-- Remover UNIQUE index em imagem_id (só possível após remover a FK)
ALTER TABLE animacao DROP INDEX imagem_id;

-- Aplicar novo imagem_id (apontando para imagens_cliente_obra)
UPDATE animacao
SET
    imagem_id = imagem_ico_id
WHERE
    imagem_ico_id IS NOT NULL;

DELETE FROM animacao
WHERE imagem_ico_id IS NULL;    

-- Remover coluna temporária
ALTER TABLE animacao DROP COLUMN imagem_ico_id;

-- Remover coluna status_anima (substituída por substatus_id)
ALTER TABLE animacao DROP COLUMN status_anima;

-- Adicionar novas colunas
ALTER TABLE animacao
ADD COLUMN substatus_id INT NOT NULL DEFAULT 7 AFTER imagem_id,
ADD COLUMN tipo_animacao ENUM(
    'vertical',
    'horizontal',
    'reels'
) NOT NULL DEFAULT 'vertical' AFTER substatus_id;

-- Adicionar nova FK para imagens_cliente_obra
-- (só executar após confirmar que todos imagem_id apontam para registros válidos)
ALTER TABLE animacao
    ADD CONSTRAINT fk_animacao_imagem
        FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra(idimagens_cliente_obra);

-- Adicionar FK para substatus_imagem
ALTER TABLE animacao
    ADD CONSTRAINT fk_animacao_substatus
        FOREIGN KEY (substatus_id) REFERENCES substatus_imagem(id);

-- ============================================================
-- PASSO C — Criar tabela funcao_animacao
-- ============================================================
CREATE TABLE IF NOT EXISTS funcao_animacao (
    id INT NOT NULL AUTO_INCREMENT,
    animacao_id INT NOT NULL,
    funcao_id INT NOT NULL,
    colaborador_id INT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Não iniciado',
    prazo DATE NULL,
    valor DOUBLE NOT NULL DEFAULT 0,
    pagamento TINYINT(1) NOT NULL DEFAULT 0,
    data_pagamento DATE NULL,
    observacao VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_animacao_funcao (animacao_id, funcao_id),
    CONSTRAINT fk_fa_animacao FOREIGN KEY (animacao_id) REFERENCES animacao (idanimacao) ON DELETE CASCADE,
    CONSTRAINT fk_fa_funcao FOREIGN KEY (funcao_id) REFERENCES funcao (idfuncao),
    CONSTRAINT fk_fa_colab FOREIGN KEY (colaborador_id) REFERENCES colaborador (idcolaborador)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================
-- PASSO D — Trigger: status padrão em funcao_animacao
-- ============================================================
DELIMITER $$

DROP TRIGGER IF EXISTS trg_funcao_animacao_default_status$$

CREATE TRIGGER trg_funcao_animacao_default_status
BEFORE INSERT ON funcao_animacao
FOR EACH ROW
BEGIN
    IF NEW.status IS NULL OR NEW.status = '' THEN
        SET NEW.status = 'Não iniciado';
    END IF;
END$$

DELIMITER;

-- ============================================================
-- PASSO E — Backfill funcao_animacao para animações existentes
-- ============================================================

-- funcao_id=4 (Finalização): usa o colaborador_id já cadastrado na animacao
INSERT IGNORE INTO
    funcao_animacao (
        animacao_id,
        funcao_id,
        colaborador_id
    )
SELECT idanimacao, 4, colaborador_id
FROM animacao;

-- funcao_id=5 (Pós-produção): sempre colaborador_id=13
INSERT IGNORE INTO
    funcao_animacao (
        animacao_id,
        funcao_id,
        colaborador_id
    )
SELECT idanimacao, 5, 13
FROM animacao;

-- ============================================================
-- VERIFICAÇÃO PÓS-MIGRAÇÃO
-- ============================================================
-- 1. Nenhuma animação sem imagens_cliente_obra correspondente:
SELECT COUNT(*) FROM animacao a
LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = a.imagem_id
WHERE ico.idimagens_cliente_obra IS NULL;
-- Esperado: 0

-- 2. Toda animação tem funcao_id 4 E 5:
SELECT a.idanimacao FROM animacao a
WHERE NOT EXISTS (SELECT 1 FROM funcao_animacao fa WHERE fa.animacao_id = a.idanimacao AND fa.funcao_id = 4)
   OR NOT EXISTS (SELECT 1 FROM funcao_animacao fa WHERE fa.animacao_id = a.idanimacao AND fa.funcao_id = 5);
-- Esperado: 0 linhas

SELECT COUNT(*) AS total FROM animacao;

SELECT COUNT(*) AS casaram
FROM animacao a
JOIN imagem_animacao ia ON ia.idimagem_animacao = a.imagem_id
JOIN imagens_cliente_obra ico ON ico.obra_id = ia.obra_id AND ico.imagem_nome = ia.imagem_nome;

SELECT 
    a.idanimacao,
    ia.obra_id,
    ia.imagem_nome AS nome_em_imagem_animacao,
    ico.imagem_nome AS nome_em_imagens_cliente_obra
FROM animacao a
JOIN imagem_animacao ia ON ia.idimagem_animacao = a.imagem_id
LEFT JOIN imagens_cliente_obra ico ON ico.obra_id = ia.obra_id AND ico.imagem_nome = ia.imagem_nome
WHERE ico.idimagens_cliente_obra IS NULL;

SELECT 
    a.idanimacao,
    ia.obra_id,
    ia.imagem_nome AS nome_em_imagem_animacao,
    ico.imagem_nome AS nome_em_imagens_cliente_obra
FROM animacao a
JOIN imagem_animacao ia ON ia.idimagem_animacao = a.imagem_id
LEFT JOIN imagens_cliente_obra ico ON ico.obra_id = ia.obra_id AND ico.imagem_nome = ia.imagem_nome;


SELECT imagem_nome FROM imagem_animacao WHERE obra_id = 3;
SELECT imagem_nome FROM imagens_cliente_obra WHERE obra_id = 3;

-- Ver o que existe em imagens_cliente_obra para obra_id 3 e 4
SELECT obra_id, imagem_nome 
FROM imagens_cliente_obra 
WHERE obra_id IN (3, 4)
ORDER BY obra_id, imagem_nome;


SELECT * FROM animacao;


SELECT COUNT(*) AS total,
       COUNT(imagem_ico_id) AS mapeadas,
       COUNT(*) - COUNT(imagem_ico_id) AS sem_correspondencia
FROM animacao;