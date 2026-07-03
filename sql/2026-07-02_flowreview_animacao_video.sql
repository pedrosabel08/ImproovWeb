-- Flow Review: approval media support for animation and video.
-- Run once per environment. Runtime endpoints also call an idempotent
-- schema helper so old environments can evolve safely.

ALTER TABLE historico_aprovacoes_imagens
  MODIFY funcao_imagem_id INT NULL,
  ADD COLUMN funcao_animacao_id INT NULL AFTER funcao_imagem_id,
  ADD COLUMN media_tipo VARCHAR(20) NOT NULL DEFAULT 'imagem' AFTER caminho_imagem,
  ADD COLUMN mime_type VARCHAR(100) NULL AFTER media_tipo,
  ADD COLUMN tamanho BIGINT NULL AFTER mime_type,
  ADD COLUMN duracao_ms INT NULL AFTER tamanho,
  ADD COLUMN poster_path VARCHAR(255) NULL AFTER duracao_ms;

CREATE INDEX idx_hai_funcao_animacao
  ON historico_aprovacoes_imagens (funcao_animacao_id, indice_envio, data_envio);

CREATE INDEX idx_hai_media_tipo
  ON historico_aprovacoes_imagens (media_tipo);

ALTER TABLE historico_aprovacoes
  MODIFY funcao_imagem_id INT NULL,
  ADD COLUMN funcao_animacao_id INT NULL AFTER funcao_imagem_id;

CREATE INDEX idx_ha_funcao_animacao
  ON historico_aprovacoes (funcao_animacao_id, data_aprovacao);

ALTER TABLE comentarios_imagem
  ADD COLUMN video_time_ms INT NULL AFTER y;

CREATE INDEX idx_comentarios_ap_video_time
  ON comentarios_imagem (ap_imagem_id, video_time_ms);
