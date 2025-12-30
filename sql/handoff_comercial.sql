-- HANDOFF COMERCIAL -> PRODUÇÃO
-- Tabela por obra (1 registro por obra)

CREATE TABLE IF NOT EXISTS handoff_comercial (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obra_id INT NOT NULL,

  -- 1) Identificação
  projeto_nome VARCHAR(255) NULL,
  projeto_tipo VARCHAR(50) NULL,
  qtd_imagens_vendidas INT NULL,
  projeto_vitrine TINYINT(1) NULL,
  responsavel_comercial VARCHAR(255) NULL,
  responsavel_producao VARCHAR(255) NULL,

  -- 2) Escopo vendido
  escopo_fechado_validado TINYINT(1) NULL,
  qtd_imagens_confirmada INT NULL,
  fotografico_aereo_incluso TINYINT(1) NULL,
  fotografico_planejado_fluxo TINYINT(1) NULL,
  numero_revisoes VARCHAR(20) NULL,
  limite_ajustes_definido TINYINT(1) NULL,
  ajustes_permitidos VARCHAR(30) NULL,
  entrega_antecipada TINYINT(1) NULL,
  entrega_antecipada_quais VARCHAR(255) NULL,
  entrega_antecipada_prazo DATE NULL,

  -- 3) Prazos e compromissos
  prazo_final_prometido DATE NULL,
  datas_intermediarias TINYINT(1) NULL,
  datas_intermediarias_info VARCHAR(255) NULL,
  deadline_externo TINYINT(1) NULL,
  deadline_tipo VARCHAR(30) NULL,
  prazo_compativel_complexidade TINYINT(1) NULL,
  entrega_antecipada_impacta_fluxo TINYINT(1) NULL,

  -- 4) Expectativa criativa
  cuidado_criativo_acima_media TINYINT(1) NULL,
  nivel_liberdade_criativa VARCHAR(10) NULL,
  riscos_criativos_identificados TINYINT(1) NULL,
  riscos_criativos_quais VARCHAR(255) NULL,
  observacoes_criativas VARCHAR(500) NULL,

  -- 5) Condições comerciais
  desconto_relevante TINYINT(1) NULL,
  promessa_especifica TINYINT(1) NULL,
  promessa_especifica_texto VARCHAR(255) NULL,
  parcela_final_atrelada_entrega TINYINT(1) NULL,

  -- 6) Dependências e insumos
  arquivos_iniciais_entregues TINYINT(1) NULL,
  materiais_pendentes_cliente TINYINT(1) NULL,
  materiais_pendentes_texto VARCHAR(255) NULL,
  depende_terceiros TINYINT(1) NULL,
  terceiros_tipo VARCHAR(30) NULL,
  dependencias_registradas_fluxo TINYINT(1) NULL,

  -- 7) Reunião
  reuniao_handoff_realizada TINYINT(1) NULL,
  comercial_apresentou_projeto TINYINT(1) NULL,
  producao_esclareceu_duvidas TINYINT(1) NULL,
  riscos_pontos_sensiveis_discutidos TINYINT(1) NULL,
  decisoes_relevantes_registradas TINYINT(1) NULL,

  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_handoff_obra (obra_id)
);
