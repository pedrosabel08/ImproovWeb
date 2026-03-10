-- Migration: adiciona coluna valor em funcao_colaborador
-- e popula com os valores contratuais de cada colaborador/função.
-- 2026-03-09

ALTER TABLE funcao_colaborador
    ADD COLUMN valor DECIMAL(10,2) NULL DEFAULT NULL AFTER nivel_finalizacao;

-- ─────────────────────────────────────────────────
-- Valores padrão por função (idfuncao)
-- 1  Caderno          → 50,00
-- 2  Modelagem        → 50,00  (exceto Thiago, id 16 → 1000,00)
-- 3  Composição       → 50,00
-- 4  Finalização      → depende do nivel_finalizacao (abaixo)
-- 5  Pós-produção     → 60,00
-- 6  Alteração        → NULL (sem valor padrão definido)
-- 7  Planta Humanizada→ NULL (calculado por nome de imagem em runtime)
-- 8  Filtro de assets → 20,00
-- 9  Pré-Finalização  → NULL
-- 10 Animação         → NULL (regras por volume / colaborador específico)
-- ─────────────────────────────────────────────────

-- Caderno (funcao_id = 1)
UPDATE funcao_colaborador SET valor = 50.00 WHERE funcao_id = 1;

-- Filtro de assets (funcao_id = 8)
UPDATE funcao_colaborador SET valor = 20.00 WHERE funcao_id = 8;

-- Modelagem (funcao_id = 2) – padrão 50,00
UPDATE funcao_colaborador SET valor = 50.00 WHERE funcao_id = 2;
-- Thiago (id 16) – modelagem de fachada: 1000,00
UPDATE funcao_colaborador SET valor = 1000.00 WHERE colaborador_id = 16 AND funcao_id = 2;

-- Composição (funcao_id = 3)
UPDATE funcao_colaborador SET valor = 50.00 WHERE funcao_id = 3;

-- Pós-produção (funcao_id = 5)
UPDATE funcao_colaborador SET valor = 60.00 WHERE funcao_id = 5;

-- Finalização (funcao_id = 4) por nivel
UPDATE funcao_colaborador SET valor = 250.00 WHERE funcao_id = 4 AND nivel_finalizacao = 1;
UPDATE funcao_colaborador SET valor = 300.00 WHERE funcao_id = 4 AND nivel_finalizacao = 2;
UPDATE funcao_colaborador SET valor = 380.00 WHERE funcao_id = 4 AND nivel_finalizacao = 3;
UPDATE funcao_colaborador SET valor = 400.00 WHERE funcao_id = 4 AND colaborador_id = 8;

-- Animação (funcao_id = 10) – André Tavares (id 13): 175,00 fixo por cena
UPDATE funcao_colaborador SET valor = 350.00 WHERE colaborador_id = 13 AND funcao_id = 10;
-- Diego (id 39): nivel 1 de animação = 250,00 (baseado no nível, mesmo que seja "cenas")
UPDATE funcao_colaborador SET valor = 250.00 WHERE colaborador_id = 39 AND funcao_id = 10;
-- Rafael (id 37) e André Moreira (id 20) e Vitor (id 23): animação → sem valor fixo por enquanto (volume-based)
-- Deixar NULL para ser definido manualmente ou via lógica de volume

-- Planta Humanizada (funcao_id = 7): NULL — calculado no insert com base no nome da imagem
-- (ver lógica em insereFuncao.php)
