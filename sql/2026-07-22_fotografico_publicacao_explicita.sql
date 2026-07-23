-- Publicacao explicita do plano fotografico.
-- Planos antigos que foram marcados como prontos para execucao sem uma versao
-- publicada voltam para o ponto correto do fluxo, sem alterar conteudo algum.
UPDATE fotografico_plano p
LEFT JOIN fotografico_plano_versao v
  ON v.plano_id = p.id AND v.status = 'PUBLICADA'
SET p.status = 'PRONTO_PARA_PUBLICAR'
WHERE p.status = 'PRONTO_EXECUCAO'
  AND v.id IS NULL;
