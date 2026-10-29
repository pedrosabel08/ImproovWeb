-- Configuração do motor R01+ na infraestrutura de capacidade existente.
-- Não altera planos históricos, tarefas, pesos R00 ou atribuições.
ALTER TABLE planejamento_capacidade_etapa ADD COLUMN parametros_json JSON NULL;
INSERT INTO planejamento_capacidade_etapa (codigo_etapa,capacidade_padrao,ativo,parametros_json)
VALUES ('ALTERACAO_PONTOS',25,1,'{"niveis":{"1":5,"2":10,"3":15,"4":20,"5":25},"colaboradores":{}}')
ON DUPLICATE KEY UPDATE codigo_etapa=VALUES(codigo_etapa);
