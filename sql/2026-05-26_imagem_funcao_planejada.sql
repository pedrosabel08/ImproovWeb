CREATE TABLE IF NOT EXISTS imagem_funcao_template (
    idimagem_funcao_template INT AUTO_INCREMENT PRIMARY KEY,
    tipo_imagem VARCHAR(55) NOT NULL,
    nome_template VARCHAR(100) NOT NULL,
    versao INT NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ift_tipo_versao (tipo_imagem, versao),
    KEY idx_ift_tipo_ativo (tipo_imagem, ativo)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imagem_funcao_template_item (
    idimagem_funcao_template_item INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    funcao_id INT NOT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
    responsavel_padrao_id INT DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ifti_template_funcao (template_id, funcao_id),
    KEY idx_ifti_funcao (funcao_id),
    KEY idx_ifti_responsavel (responsavel_padrao_id),
    CONSTRAINT fk_ifti_template FOREIGN KEY (template_id) REFERENCES imagem_funcao_template (idimagem_funcao_template) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ifti_funcao FOREIGN KEY (funcao_id) REFERENCES funcao (idfuncao) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ifti_responsavel FOREIGN KEY (responsavel_padrao_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imagem_funcao_planejada (
    idimagem_funcao_planejada INT AUTO_INCREMENT PRIMARY KEY,
    imagem_id INT NOT NULL,
    funcao_id INT NOT NULL,
    template_id INT DEFAULT NULL,
    template_item_id INT DEFAULT NULL,
    template_versao INT DEFAULT NULL,
    funcao_imagem_id INT DEFAULT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM(
        'TODO',
        'INICIADO',
        'CANCELADO'
    ) NOT NULL DEFAULT 'TODO',
    origem ENUM(
        'PLANEJAMENTO',
        'MANUAL',
        'EXECUCAO'
    ) NOT NULL DEFAULT 'PLANEJAMENTO',
    responsavel_sugerido_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ifp_imagem_funcao (imagem_id, funcao_id),
    KEY idx_ifp_status (status),
    KEY idx_ifp_funcao (funcao_id),
    KEY idx_ifp_imagem (imagem_id),
    KEY idx_ifp_template (template_id),
    KEY idx_ifp_funcao_imagem (funcao_imagem_id),
    CONSTRAINT fk_ifp_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ifp_funcao FOREIGN KEY (funcao_id) REFERENCES funcao (idfuncao) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ifp_template FOREIGN KEY (template_id) REFERENCES imagem_funcao_template (idimagem_funcao_template) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ifp_template_item FOREIGN KEY (template_item_id) REFERENCES imagem_funcao_template_item (idimagem_funcao_template_item) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ifp_funcao_imagem FOREIGN KEY (funcao_imagem_id) REFERENCES funcao_imagem (idfuncao_imagem) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ifp_responsavel FOREIGN KEY (responsavel_sugerido_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imagem_funcao_planejada_historico (
    idimagem_funcao_planejada_historico INT AUTO_INCREMENT PRIMARY KEY,
    imagem_funcao_planejada_id INT DEFAULT NULL,
    imagem_id INT NOT NULL,
    funcao_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL,
    payload JSON DEFAULT NULL,
    responsavel_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ifph_imagem (imagem_id),
    KEY idx_ifph_funcao (funcao_id),
    KEY idx_ifph_planejada (imagem_funcao_planejada_id),
    KEY idx_ifph_acao (acao),
    CONSTRAINT fk_ifph_planejada FOREIGN KEY (imagem_funcao_planejada_id) REFERENCES imagem_funcao_planejada (idimagem_funcao_planejada) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ifph_imagem FOREIGN KEY (imagem_id) REFERENCES imagens_cliente_obra (idimagens_cliente_obra) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ifph_funcao FOREIGN KEY (funcao_id) REFERENCES funcao (idfuncao) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ifph_responsavel FOREIGN KEY (responsavel_id) REFERENCES colaborador (idcolaborador) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE imagem_funcao_planejada
ADD COLUMN IF NOT EXISTS template_id INT DEFAULT NULL AFTER funcao_id,
ADD COLUMN IF NOT EXISTS template_item_id INT DEFAULT NULL AFTER template_id,
ADD COLUMN IF NOT EXISTS template_versao INT DEFAULT NULL AFTER template_item_id,
ADD COLUMN IF NOT EXISTS funcao_imagem_id INT DEFAULT NULL AFTER template_versao,
ADD COLUMN IF NOT EXISTS ordem SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER funcao_imagem_id,
ADD COLUMN IF NOT EXISTS obrigatoria TINYINT(1) NOT NULL DEFAULT 1 AFTER ordem,
ADD COLUMN IF NOT EXISTS responsavel_sugerido_id INT DEFAULT NULL AFTER origem,
MODIFY COLUMN origem ENUM(
    'PLANEJAMENTO',
    'MANUAL',
    'EXECUCAO'
) NOT NULL DEFAULT 'PLANEJAMENTO';

INSERT INTO
    imagem_funcao_template (
        tipo_imagem,
        nome_template,
        versao,
        ativo
    )
VALUES (
        'Fachada',
        'Padrao Fachada',
        1,
        1
    ),
    (
        'Unidade',
        'Padrao Unidade',
        1,
        1
    ),
    (
        'Imagem Interna',
        'Padrao Imagem Interna',
        1,
        1
    ),
    (
        'Imagem Externa',
        'Padrao Imagem Externa',
        1,
        1
    ),
    (
        'Planta Humanizada',
        'Padrao Planta Humanizada',
        1,
        1
    )
ON DUPLICATE KEY UPDATE
    nome_template = VALUES(nome_template),
    ativo = VALUES(ativo);

DELETE ifti
FROM imagem_funcao_template_item ifti
INNER JOIN imagem_funcao_template template
    ON template.idimagem_funcao_template = ifti.template_id
WHERE template.tipo_imagem = 'Planta Humanizada'
  AND template.versao = 1
  AND ifti.funcao_id <> 4;

INSERT INTO
    imagem_funcao_template_item (
        template_id,
        funcao_id,
        ordem,
        obrigatoria,
        ativo
    )
SELECT template.idimagem_funcao_template, seed.funcao_id, seed.ordem, seed.obrigatoria, 1
FROM
    imagem_funcao_template template
    INNER JOIN (
        SELECT
            'Fachada' AS tipo_imagem,
            2 AS funcao_id,
            10 AS ordem,
            1 AS obrigatoria
        UNION ALL
        SELECT 'Fachada', 4, 20, 1
        UNION ALL
        SELECT 'Fachada', 5, 30, 1
        UNION ALL
        SELECT 'Unidade', 1, 10, 1
        UNION ALL
        SELECT 'Unidade', 8, 20, 1
        UNION ALL
        SELECT 'Unidade', 2, 30, 1
        UNION ALL
        SELECT 'Unidade', 3, 40, 1
        UNION ALL
        SELECT 'Unidade', 4, 50, 1
        UNION ALL
        SELECT 'Unidade', 5, 60, 1
        UNION ALL
        SELECT 'Imagem Interna', 1, 10, 1
        UNION ALL
        SELECT 'Imagem Interna', 8, 20, 1
        UNION ALL
        SELECT 'Imagem Interna', 2, 30, 1
        UNION ALL
        SELECT 'Imagem Interna', 3, 40, 1
        UNION ALL
        SELECT 'Imagem Interna', 4, 50, 1
        UNION ALL
        SELECT 'Imagem Interna', 5, 60, 1
        UNION ALL
        SELECT 'Imagem Externa', 1, 10, 1
        UNION ALL
        SELECT 'Imagem Externa', 8, 20, 1
        UNION ALL
        SELECT 'Imagem Externa', 2, 30, 1
        UNION ALL
        SELECT 'Imagem Externa', 3, 40, 1
        UNION ALL
        SELECT 'Imagem Externa', 4, 50, 1
        UNION ALL
        SELECT 'Imagem Externa', 5, 60, 1
        UNION ALL
        SELECT 'Planta Humanizada', 4, 10, 1
    ) seed ON seed.tipo_imagem = template.tipo_imagem
    AND template.versao = 1
ON DUPLICATE KEY UPDATE
    ordem = VALUES(ordem),
    obrigatoria = VALUES(obrigatoria),
    ativo = VALUES(ativo);

UPDATE imagem_funcao_planejada ifp
INNER JOIN funcao_imagem fi ON fi.imagem_id = ifp.imagem_id
AND fi.funcao_id = ifp.funcao_id
SET
    ifp.funcao_imagem_id = fi.idfuncao_imagem,
    ifp.status = CASE
        WHEN LOWER(TRIM(COALESCE(fi.status, ''))) IN (
            'cancelado',
            'não se aplica',
            'nao se aplica'
        ) THEN 'CANCELADO'
        ELSE 'INICIADO'
    END
WHERE
    ifp.funcao_imagem_id IS NULL
    OR ifp.funcao_imagem_id <> fi.idfuncao_imagem
    OR ifp.status = 'TODO';

INSERT INTO
    imagem_funcao_planejada (
        imagem_id,
        funcao_id,
        funcao_imagem_id,
        ordem,
        obrigatoria,
        status,
        origem
    )
SELECT
    fi.imagem_id,
    fi.funcao_id,
    fi.idfuncao_imagem,
    999,
    1,
    CASE
        WHEN LOWER(TRIM(COALESCE(fi.status, ''))) IN (
            'cancelado',
            'não se aplica',
            'nao se aplica'
        ) THEN 'CANCELADO'
        ELSE 'INICIADO'
    END,
    'EXECUCAO'
FROM
    funcao_imagem fi
    LEFT JOIN imagem_funcao_planejada ifp ON ifp.imagem_id = fi.imagem_id
    AND ifp.funcao_id = fi.funcao_id
WHERE
    ifp.idimagem_funcao_planejada IS NULL;

DROP TRIGGER IF EXISTS trg_ifp_sync_funcao_imagem_insert;

DROP TRIGGER IF EXISTS trg_ifp_sync_funcao_imagem_update;

DROP TRIGGER IF EXISTS trg_ifp_sync_funcao_imagem_delete;

DELIMITER $$

CREATE TRIGGER trg_ifp_sync_funcao_imagem_insert
AFTER INSERT ON funcao_imagem
FOR EACH ROW
BEGIN
    INSERT INTO imagem_funcao_planejada (
        imagem_id,
        funcao_id,
        funcao_imagem_id,
        ordem,
        obrigatoria,
        status,
        origem
    ) VALUES (
        NEW.imagem_id,
        NEW.funcao_id,
        NEW.idfuncao_imagem,
        999,
        1,
        CASE
            WHEN LOWER(TRIM(COALESCE(NEW.status, ''))) IN ('cancelado', 'não se aplica', 'nao se aplica') THEN 'CANCELADO'
            ELSE 'INICIADO'
        END,
        'EXECUCAO'
    )
    ON DUPLICATE KEY UPDATE
        funcao_imagem_id = VALUES(funcao_imagem_id),
        status = CASE
            WHEN LOWER(TRIM(COALESCE(NEW.status, ''))) IN ('cancelado', 'não se aplica', 'nao se aplica') THEN 'CANCELADO'
            ELSE 'INICIADO'
        END,
        updated_at = CURRENT_TIMESTAMP;
END$$

CREATE TRIGGER trg_ifp_sync_funcao_imagem_update
AFTER UPDATE ON funcao_imagem
FOR EACH ROW
BEGIN
    IF OLD.imagem_id <> NEW.imagem_id OR OLD.funcao_id <> NEW.funcao_id THEN
        UPDATE imagem_funcao_planejada
        SET
            funcao_imagem_id = NULL,
            status = CASE
                WHEN origem = 'EXECUCAO' THEN 'CANCELADO'
                ELSE 'TODO'
            END,
            updated_at = CURRENT_TIMESTAMP
        WHERE imagem_id = OLD.imagem_id
          AND funcao_id = OLD.funcao_id
          AND funcao_imagem_id = OLD.idfuncao_imagem;
    END IF;

    INSERT INTO imagem_funcao_planejada (
        imagem_id,
        funcao_id,
        funcao_imagem_id,
        ordem,
        obrigatoria,
        status,
        origem
    ) VALUES (
        NEW.imagem_id,
        NEW.funcao_id,
        NEW.idfuncao_imagem,
        999,
        1,
        CASE
            WHEN LOWER(TRIM(COALESCE(NEW.status, ''))) IN ('cancelado', 'não se aplica', 'nao se aplica') THEN 'CANCELADO'
            ELSE 'INICIADO'
        END,
        'EXECUCAO'
    )
    ON DUPLICATE KEY UPDATE
        funcao_imagem_id = VALUES(funcao_imagem_id),
        status = CASE
            WHEN LOWER(TRIM(COALESCE(NEW.status, ''))) IN ('cancelado', 'não se aplica', 'nao se aplica') THEN 'CANCELADO'
            ELSE 'INICIADO'
        END,
        updated_at = CURRENT_TIMESTAMP;
END$$

CREATE TRIGGER trg_ifp_sync_funcao_imagem_delete
AFTER DELETE ON funcao_imagem
FOR EACH ROW
BEGIN
    UPDATE imagem_funcao_planejada
    SET
        funcao_imagem_id = NULL,
        status = CASE
            WHEN origem = 'EXECUCAO' THEN 'CANCELADO'
            ELSE 'TODO'
        END,
        updated_at = CURRENT_TIMESTAMP
    WHERE imagem_id = OLD.imagem_id
      AND funcao_id = OLD.funcao_id
      AND funcao_imagem_id = OLD.idfuncao_imagem;
END$$

DELIMITER;