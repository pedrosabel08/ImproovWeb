-- Complemento incremental: a base possuía somente o valor fotográfico legado
-- "teste" e ele estava inativo. Os dois tipos abaixo vêm das decisões de
-- produto desta evolução e tornam Direção Fotográfica operacional.

INSERT IGNORE INTO alma_biblioteca_item
    (dimensao_id, codigo, titulo, ordem, ativo)
SELECT d.id, choices.codigo, choices.titulo, choices.ordem, 1
  FROM alma_biblioteca_dimensao d
  JOIN (
        SELECT 'editorial' AS codigo, 'Editorial' AS titulo, 1 AS ordem
        UNION ALL
        SELECT 'imersiva', 'Imersiva', 2
  ) choices
 WHERE d.codigo = 'fotografia_direcao';

