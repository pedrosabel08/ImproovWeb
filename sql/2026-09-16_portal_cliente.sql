-- Part 1 only. Apply with: php scripts/portal_migrate.php
-- Prerequisites: contact architecture + briefing external access v2. MySQL >= 8.0.16.
CREATE TABLE IF NOT EXISTS flow_disciplina (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(40) NOT NULL UNIQUE,
 nome VARCHAR(100) NOT NULL,
 categoria_id INT NOT NULL,
 ativa BOOLEAN NOT NULL DEFAULT TRUE,
 FOREIGN KEY (categoria_id) REFERENCES categorias(idcategoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO flow_disciplina(codigo,nome,categoria_id)
SELECT s.codigo,s.nome,c.idcategoria FROM (
 SELECT 'ARQUITETURA' codigo,'Arquitetura' nome,'Arquitetônico' categoria UNION ALL
 SELECT 'INTERIORES','Interiores','Arquitetônico' UNION ALL
 SELECT 'PAISAGISMO','Paisagismo','Paisagismo' UNION ALL
 SELECT 'LUMINOTECNICO','Luminotécnico','Luminotécnico' UNION ALL
 SELECT 'ESTRUTURAL','Estrutural','Estrutural'
) s JOIN categorias c ON c.nome_categoria=s.categoria
WHERE NOT EXISTS (SELECT 1 FROM flow_disciplina d WHERE d.codigo=s.codigo);

CREATE TABLE IF NOT EXISTS portal_projeto (
 obra_id INT NOT NULL PRIMARY KEY,
 administrador_contato_id INT NOT NULL,
 curador_usuario_id INT NOT NULL,
 estado ENUM('ABERTO','ENCERRADO') NOT NULL DEFAULT 'ABERTO',
 inscricoes_abertas BOOLEAN NOT NULL DEFAULT TRUE,
 convite_hash CHAR(64) NULL UNIQUE,
 revisao INT UNSIGNED NOT NULL DEFAULT 1,
 preparado_em DATETIME NULL,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 criado_por INT NOT NULL,
 FOREIGN KEY (obra_id) REFERENCES obra(idobra),
 FOREIGN KEY (administrador_contato_id) REFERENCES contato_cliente(idcontato_cliente),
 CONSTRAINT fk_portal_admin_vinculo FOREIGN KEY (obra_id,administrador_contato_id) REFERENCES obra_contato(obra_id,contato_cliente_id),
 FOREIGN KEY (curador_usuario_id) REFERENCES usuario(idusuario),
 FOREIGN KEY (criado_por) REFERENCES usuario(idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_participante (
 obra_id INT NOT NULL,
 contato_id INT NOT NULL,
 ingressou_em DATETIME NULL,
 perfil_confirmado_em DATETIME NULL,
 removido_em DATETIME NULL,
 removido_por INT NULL,
 PRIMARY KEY(obra_id,contato_id),
 FOREIGN KEY (obra_id) REFERENCES portal_projeto(obra_id),
 FOREIGN KEY (obra_id,contato_id) REFERENCES obra_contato(obra_id,contato_cliente_id),
 FOREIGN KEY (removido_por) REFERENCES usuario(idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_projeto_disciplina (
 obra_id INT NOT NULL,
 disciplina_id INT NOT NULL,
 PRIMARY KEY(obra_id,disciplina_id),
 FOREIGN KEY(obra_id) REFERENCES portal_projeto(obra_id),
 FOREIGN KEY(disciplina_id) REFERENCES flow_disciplina(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_participante_disciplina (
 obra_id INT NOT NULL,
 contato_id INT NOT NULL,
 disciplina_id INT NOT NULL,
 PRIMARY KEY(obra_id,contato_id,disciplina_id),
 FOREIGN KEY(obra_id,contato_id) REFERENCES portal_participante(obra_id,contato_id),
 FOREIGN KEY(obra_id,disciplina_id) REFERENCES portal_projeto_disciplina(obra_id,disciplina_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_solicitacao (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 obra_id INT NOT NULL UNIQUE,
 estado ENUM('RASCUNHO','PUBLICADA') NOT NULL DEFAULT 'RASCUNHO',
 revisao INT UNSIGNED NOT NULL DEFAULT 1,
 publicada_em DATETIME NULL,
 publicada_por INT NULL,
 UNIQUE KEY uq_solicitacao_obra(id,obra_id),
 FOREIGN KEY(obra_id) REFERENCES portal_projeto(obra_id),
 FOREIGN KEY(publicada_por) REFERENCES usuario(idusuario),
 CONSTRAINT ck_portal_publicacao CHECK ((estado='RASCUNHO' AND publicada_em IS NULL AND publicada_por IS NULL) OR (estado='PUBLICADA' AND publicada_em IS NOT NULL AND publicada_por IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_material (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 solicitacao_id BIGINT UNSIGNED NOT NULL,
 obra_id INT NOT NULL,
 disciplina_id INT NOT NULL,
 categoria_id INT NOT NULL,
 titulo VARCHAR(180) NOT NULL,
 momento ENUM('INICIO','DURANTE') NOT NULL DEFAULT 'INICIO',
 contexto TEXT NOT NULL,
 observacao TEXT NOT NULL,
 origem ENUM('SUGESTAO','CURADORIA') NOT NULL,
 sugestao_chave VARCHAR(80) NULL,
 revisado_por INT NULL,
 removido_em DATETIME NULL,
 UNIQUE KEY uq_material_obra(id,obra_id),
 UNIQUE KEY uq_material_sugestao(solicitacao_id,sugestao_chave),
 KEY idx_material_ativo(solicitacao_id,removido_em),
 FOREIGN KEY(solicitacao_id,obra_id) REFERENCES portal_solicitacao(id,obra_id),
 FOREIGN KEY(obra_id,disciplina_id) REFERENCES portal_projeto_disciplina(obra_id,disciplina_id),
 FOREIGN KEY(categoria_id) REFERENCES categorias(idcategoria),
 FOREIGN KEY(revisado_por) REFERENCES usuario(idusuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_material_formato (
 material_id BIGINT UNSIGNED NOT NULL,
 formato VARCHAR(12) NOT NULL,
 PRIMARY KEY(material_id,formato),
 FOREIGN KEY(material_id) REFERENCES portal_material(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_material_origem (
 material_id BIGINT UNSIGNED NOT NULL,
 requisito_id INT NOT NULL,
 PRIMARY KEY(material_id,requisito_id),
 FOREIGN KEY(material_id) REFERENCES portal_material(id),
 CONSTRAINT fk_portal_origem_requisito FOREIGN KEY(requisito_id) REFERENCES briefing_requisitos_arquivo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_evento (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 obra_id INT NOT NULL,
 tipo VARCHAR(60) NOT NULL,
 usuario_id INT NULL,
 contato_id INT NULL,
 dados JSON NOT NULL,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_portal_evento(obra_id,id),
 FOREIGN KEY(obra_id) REFERENCES portal_projeto(obra_id),
 FOREIGN KEY(usuario_id) REFERENCES usuario(idusuario),
 FOREIGN KEY(contato_id) REFERENCES contato_cliente(idcontato_cliente),
 CONSTRAINT ck_portal_actor CHECK (usuario_id IS NULL OR contato_id IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The CLI runner executes this ALTER only when portal_obra_id is absent.
ALTER TABLE external_otp_challenge
 MODIFY briefing_access_link_id BIGINT UNSIGNED NULL,
 ADD COLUMN portal_obra_id INT NULL,
 ADD COLUMN portal_convite_hash CHAR(64) NULL,
 ADD KEY idx_external_otp_portal(portal_obra_id,email_normalizado,expira_em),
 ADD KEY idx_external_otp_global_ip(ip_solicitacao,criado_em),
 ADD CONSTRAINT fk_external_otp_portal FOREIGN KEY(portal_obra_id) REFERENCES portal_projeto(obra_id),
 ADD CONSTRAINT ck_external_otp_scope CHECK ((briefing_access_link_id IS NOT NULL AND portal_obra_id IS NULL AND portal_convite_hash IS NULL) OR (briefing_access_link_id IS NULL AND portal_obra_id IS NOT NULL AND portal_convite_hash IS NOT NULL));
