-- Registro financeiro comum para materiais e serviços vinculados a uma obra.
-- Valores legados permanecem nas tabelas originais e são conciliados pela aplicação.
CREATE TABLE IF NOT EXISTS obra_item_categoria (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(80) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_obra_item_categoria_nome (nome)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT IGNORE INTO
    obra_item_categoria (nome)
VALUES ('Imagem'),
    ('Animação'),
    ('Filme'),
    ('Fotografia'),
    ('Material'),
    ('Serviço');

CREATE TABLE IF NOT EXISTS obra_item_financeiro (
    id BIGINT NOT NULL AUTO_INCREMENT,
    obra_id INT NOT NULL,
    categoria_id INT NULL,
    tipo_item VARCHAR(24) NOT NULL DEFAULT 'OUTRO',
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
    unidade VARCHAR(30) NULL,
    origem VARCHAR(24) NOT NULL DEFAULT 'EXTRA',
    imagem_id INT NULL,
    pacote_id INT NULL,
    servico_foto_id INT NULL,
    receita DECIMAL(12, 2) NULL,
    custo_previsto DECIMAL(12, 2) NULL,
    modelo_custo VARCHAR(16) NOT NULL DEFAULT 'DIRETO',
    justificativa_custo_zero VARCHAR(255) NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_obra_item_imagem (imagem_id),
    UNIQUE KEY uq_obra_item_pacote (pacote_id),
    UNIQUE KEY uq_obra_item_foto (servico_foto_id),
    KEY ix_obra_item_obra (obra_id, id),
    KEY ix_obra_item_categoria (categoria_id),
    CONSTRAINT fk_obra_item_categoria FOREIGN KEY (categoria_id) REFERENCES obra_item_categoria (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_item_custo_lancamento (
    id BIGINT NOT NULL AUTO_INCREMENT,
    item_id BIGINT NOT NULL,
    valor DECIMAL(12, 2) NOT NULL,
    descricao VARCHAR(255) NULL,
    data_lancamento DATE NOT NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_obra_item_lancamento_item (item_id, data_lancamento, id),
    CONSTRAINT fk_obra_item_lancamento_item FOREIGN KEY (item_id) REFERENCES obra_item_financeiro (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Os itens antigos são associados ao registro comum sem inventar valores.
-- Custos de imagens seguem inicialmente o modelo de tarefas já existente.
INSERT IGNORE INTO
    obra_item_financeiro (
        obra_id,
        categoria_id,
        tipo_item,
        descricao,
        origem,
        imagem_id,
        receita,
        custo_previsto,
        modelo_custo
    )
SELECT i.obra_id, c.id, 'IMAGEM', i.imagem_nome, 'LEGADO', i.idimagens_cliente_obra, (
        SELECT SUM(ic.valor)
        FROM imagem_comercial ic
        WHERE
            ic.obra_id = i.obra_id
            AND ic.imagem_id = i.idimagens_cliente_obra
    ), (
        SELECT SUM(fi.valor)
        FROM funcao_imagem fi
        WHERE
            fi.imagem_id = i.idimagens_cliente_obra
    ), 'TAREFAS'
FROM
    imagens_cliente_obra i
    LEFT JOIN obra_item_categoria c ON c.nome = 'Imagem';

INSERT IGNORE INTO
    obra_item_financeiro (
        obra_id,
        categoria_id,
        tipo_item,
        descricao,
        origem,
        pacote_id,
        custo_previsto,
        modelo_custo
    )
SELECT
    p.obra_id,
    c.id,
    'PACOTE',
    CONCAT('Pacote ', p.tipo),
    'LEGADO',
    p.idobra_pacote,
    CASE
        WHEN p.tipo = 'ANIMACAO' THEN (
            SELECT SUM(fa.valor)
            FROM
                funcao_animacao fa
                JOIN animacao a ON a.idanimacao = fa.animacao_id
            WHERE
                a.obra_id = p.obra_id
        ) + (
            SELECT SUM(a.valor)
            FROM animacao a
            WHERE
                a.obra_id = p.obra_id
                AND (
                    NOT EXISTS (
                        SELECT 1
                        FROM funcao_animacao fa
                        WHERE
                            fa.animacao_id = a.idanimacao
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM pagamento_itens pi
                        WHERE
                            pi.origem = 'animacao'
                            AND pi.origem_id = a.idanimacao
                    )
                )
        )
        ELSE NULL
    END,
    CASE
        WHEN p.tipo = 'ANIMACAO' THEN 'TAREFAS'
        ELSE 'DIRETO'
    END
FROM
    obra_pacote p
    LEFT JOIN obra_item_categoria c ON c.nome = CASE p.tipo
        WHEN 'ANIMACAO' THEN 'Animação'
        WHEN 'FILME' THEN 'Filme'
        ELSE 'Imagem'
    END;

INSERT IGNORE INTO
    obra_item_financeiro (
        obra_id,
        categoria_id,
        tipo_item,
        descricao,
        origem,
        servico_foto_id,
        receita,
        custo_previsto,
        modelo_custo
    )
SELECT f.obra_id, c.id, 'SERVICO', 'Serviço fotográfico', 'LEGADO', f.id, f.valor, NULL, 'DIRETO'
FROM
    servico_foto f
    LEFT JOIN obra_item_categoria c ON c.nome = 'Fotografia';