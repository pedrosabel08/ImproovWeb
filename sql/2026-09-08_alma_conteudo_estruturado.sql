-- ALMA: conteúdo semântico versionado para itens da Biblioteca.
-- Execute uma única vez, depois do backup habitual do ambiente.
-- Os campos TEXT/LONGTEXT legados permanecem intactos e seguem como fallback.

ALTER TABLE alma_biblioteca_item
ADD COLUMN conteudo_estruturado JSON NULL COMMENT 'Documento ALMA de conteúdo semântico: {version, blocks}',
ADD COLUMN conteudo_estruturado_revisao_status ENUM(
    'PENDENTE',
    'AUTOMATICO',
    'REVISAR',
    'MANUAL'
) NOT NULL DEFAULT 'PENDENTE' COMMENT 'Origem/confiabilidade da conversão do conteúdo estruturado' AFTER conteudo_estruturado;