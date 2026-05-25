ALTER TABLE contato_cliente
DROP FOREIGN KEY fk_contato_cliente,
DROP INDEX fk_contato_cliente;

ALTER TABLE contato_cliente
CHANGE COLUMN idcontato idcontato_cliente INT NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico do contato',
MODIFY COLUMN cliente_id INT NOT NULL COMMENT 'Cliente ao qual o contato pertence',
ADD COLUMN obra_id INT DEFAULT NULL COMMENT 'Obra especifica vinculada ao contato, se aplicavel' AFTER cliente_id,
CHANGE COLUMN nome_contato nome VARCHAR(150) NOT NULL COMMENT 'Nome completo do contato',
MODIFY COLUMN cargo VARCHAR(100) DEFAULT NULL COMMENT 'Cargo/funcao do contato',
MODIFY COLUMN email VARCHAR(150) DEFAULT NULL COMMENT 'Email principal',
ADD COLUMN telefone VARCHAR(30) DEFAULT NULL COMMENT 'Telefone principal' AFTER email,
ADD COLUMN tipo ENUM(
    'COMERCIAL',
    'APROVACAO',
    'FINANCEIRO',
    'MARKETING',
    'ARQUITETO',
    'OUTRO'
) NOT NULL DEFAULT 'OUTRO' COMMENT 'Categoria operacional do contato' AFTER telefone,
ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Controle de ativacao do contato' AFTER tipo,
ADD COLUMN observacoes TEXT DEFAULT NULL COMMENT 'Observacoes operacionais do contato' AFTER ativo,
ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criacao do contato' AFTER observacoes,
ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ultima atualizacao do contato' AFTER created_at;

UPDATE contato_cliente SET tipo = 'OUTRO', ativo = 1 WHERE 1 = 1;

ALTER TABLE contato_cliente
ADD KEY idx_cc_cliente (cliente_id),
ADD KEY idx_cc_obra (obra_id),
ADD KEY idx_cc_tipo (tipo),
ADD KEY idx_cc_ativo (ativo);

ALTER TABLE contato_cliente
ADD CONSTRAINT fk_contato_cliente_cliente FOREIGN KEY (cliente_id) REFERENCES cliente (idcliente) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT fk_contato_cliente_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE obra_contato (
    idobra_contato INT NOT NULL AUTO_INCREMENT COMMENT 'Relacionamento entre obra e contato',
    obra_id INT NOT NULL COMMENT 'Obra vinculada',
    contato_cliente_id INT NOT NULL COMMENT 'Contato vinculado a obra',
    ativo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Controle logico de ativacao',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criacao do vinculo',
    PRIMARY KEY (idobra_contato),
    UNIQUE KEY uk_obra_contato (obra_id, contato_cliente_id),
    KEY idx_oc_obra (obra_id),
    KEY idx_oc_contato (contato_cliente_id),
    CONSTRAINT fk_oc_obra FOREIGN KEY (obra_id) REFERENCES obra (idobra) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oc_contato FOREIGN KEY (contato_cliente_id) REFERENCES contato_cliente (idcontato_cliente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Relacionamento entre obras e contatos do cliente';


INSERT INTO obra_contato (obra_id, contato_cliente_id, ativo) VALUES
    (73, 54, 1);