-- Decisão final da Direção para Alteração: mantém a tarefa aprovada
-- enquanto aguarda o envio para render.
ALTER TABLE funcao_imagem
    ADD COLUMN IF NOT EXISTS requires_render_send TINYINT(1) NOT NULL DEFAULT 0
    AFTER requires_file_upload;
