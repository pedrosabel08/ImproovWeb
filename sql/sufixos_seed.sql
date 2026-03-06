-- ─────────────────────────────────────────────────────────────
--  Tabela de sufixos por tipo de arquivo
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sufixos` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `tipo_arquivo` VARCHAR(20)  NOT NULL,
  `valor`        VARCHAR(100) NOT NULL,
  `criado_em`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_tipo_valor` (`tipo_arquivo`, `valor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  Carga inicial (espelta dos sufixos hardcoded do script.js)
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `sufixos` (`tipo_arquivo`, `valor`) VALUES
-- DWG
('DWG','TERREO'),('DWG','LAZER'),('DWG','COBERTURA'),('DWG','MEZANINO'),
('DWG','CORTES'),('DWG','GERAL'),('DWG','TIPO'),('DWG','GARAGEM'),
('DWG','FACHADA'),('DWG','DUPLEX'),('DWG','ROOFTOP'),('DWG','LOGO'),
('DWG','ACABAMENTOS'),('DWG','ESQUADRIA'),('DWG','ARQUITETONICO'),
('DWG','REFERENCIA'),('DWG','IMPLANTACAO'),('DWG','SUBSOLO'),
('DWG','G1'),('DWG','G2'),('DWG','G3'),('DWG','G4'),
('DWG','DUPLEX_SUPERIOR'),('DWG','DUPLEX_INFERIOR'),('DWG','TOON'),
('DWG','DIFERENCIADO'),('DWG','CAIXA_AGUA'),('DWG','CASA_MAQUINA'),('DWG','PENTHOUSE'),
-- PDF
('PDF','DOCUMENTACAO'),('PDF','RELATORIO'),('PDF','LOGO'),
('PDF','ARQUITETONICO'),('PDF','REFERENCIA'),('PDF','ESQUADRIA'),
('PDF','ACABAMENTOS'),('PDF','TIPOLOGIA'),('PDF','IMPLANTACAO'),
('PDF','SUBSOLO'),('PDF','G1'),('PDF','G2'),('PDF','G3'),('PDF','G4'),
('PDF','DUPLEX_SUPERIOR'),('PDF','DUPLEX_INFERIOR'),('PDF','TERREO'),
('PDF','LAZER'),('PDF','COBERTURA'),('PDF','MEZANINO'),('PDF','CORTES'),
('PDF','GERAL'),('PDF','TIPO'),('PDF','GARAGEM'),('PDF','FACHADA'),
('PDF','TOON'),('PDF','DIFERENCIADO'),('PDF','PENTHOUSE'),('PDF','ROOFTOP'),
-- SKP
('SKP','MODELAGEM'),('SKP','REFERENCIA'),('SKP','TOON'),('SKP','DIFERENCIADO'),('SKP','PENTHOUSE'),
-- IMG
('IMG','FACHADA'),('IMG','INTERNA'),('IMG','EXTERNA'),('IMG','UNIDADE'),
('IMG','LOGO'),('IMG','REFERENCIAS'),('IMG','GERAL'),('IMG','TOON'),
('IMG','DIFERENCIADO'),('IMG','PENTHOUSE'),
-- IFC
('IFC','BIM'),
-- Outros
('Outros','GERAL'),('Outros','TOON'),('Outros','DIFERENCIADO'),('Outros','PENTHOUSE');
