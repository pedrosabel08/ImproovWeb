-- Permite que um colaborador de Finalização atue em mais de uma frente.
CREATE TABLE funcao_colaborador_tipo_finalizacao (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    funcao_colaborador_id INT NOT NULL,
    tipo_finalizacao ENUM('EXTERNA', 'INTERNA', 'PLANTA') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_funcao_colaborador_tipo_finalizacao (funcao_colaborador_id, tipo_finalizacao),
    CONSTRAINT fk_fctf_funcao_colaborador
        FOREIGN KEY (funcao_colaborador_id)
        REFERENCES funcao_colaborador (idfuncao_colaborador)
        ON DELETE CASCADE
);

-- Preserva a configuração operacional que existia antes de o campo ser
-- disponibilizado no cadastro de colaboradores. Cada registro antigo recebe
-- o seu pool anterior e poderá receber outros tipos pelo modal.
INSERT INTO funcao_colaborador_tipo_finalizacao (funcao_colaborador_id, tipo_finalizacao)
SELECT fc.idfuncao_colaborador,
       CASE (CONVERT(LOWER(TRIM(c.nome_colaborador)) USING utf8mb4) COLLATE utf8mb4_unicode_ci)
           WHEN 'marcio' THEN 'EXTERNA'
           WHEN 'heverton' THEN 'EXTERNA'
           WHEN 'bruna' THEN 'INTERNA'
           WHEN 'jose robson' THEN 'INTERNA'
           WHEN 'jose' THEN 'INTERNA'
           WHEN 'jiulia' THEN 'PLANTA'
       END
  FROM funcao_colaborador fc
  JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
 WHERE fc.funcao_id = 4
   AND LOWER(TRIM(c.nome_colaborador)) COLLATE utf8mb4_unicode_ci IN (
       'marcio', 'heverton', 'bruna', 'jose robson', 'jose', 'jiulia'
   );
