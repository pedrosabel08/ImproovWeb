-- Capacidade produtiva por vínculo colaborador × função.
-- Mantém a semântica existente: o vínculo continua significando que a pessoa
-- pode executar a função; tipo_atuacao apenas qualifica seu papel operacional.

-- Os dois pares duplicados existentes no banco atual são idênticos em nível e
-- valor. Mantemos o vínculo mais antigo antes de proteger a integridade.
DELETE duplicado
FROM
    funcao_colaborador AS duplicado
    JOIN funcao_colaborador AS preservado ON preservado.colaborador_id = duplicado.colaborador_id
    AND preservado.funcao_id = duplicado.funcao_id
    AND preservado.idfuncao_colaborador < duplicado.idfuncao_colaborador
WHERE
    duplicado.nivel_finalizacao <=> preservado.nivel_finalizacao
    AND duplicado.valor <=> preservado.valor;

ALTER TABLE funcao_colaborador
ADD COLUMN tipo_atuacao ENUM('PRINCIPAL', 'SECUNDARIA') NOT NULL DEFAULT 'SECUNDARIA' COMMENT 'Papel operacional do colaborador nesta função; não altera sua elegibilidade' AFTER funcao_id,
ADD UNIQUE KEY uk_funcao_colaborador_colaborador_funcao (colaborador_id, funcao_id);

-- Elegibilidade é atributo da pessoa, não do vínculo: uma pessoa genérica ou
-- administrativa não deve ampliar a capacidade produtiva de nenhuma função.
ALTER TABLE colaborador
ADD COLUMN elegivel_capacidade TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica se o colaborador representa capacidade produtiva nos planejamentos' AFTER ativo;

-- Configuração inicial explícita dos registros administrativos existentes.
UPDATE colaborador
SET
    elegivel_capacidade = 0
WHERE
    nome_colaborador IN ('Não se aplica', 'Freelancer');