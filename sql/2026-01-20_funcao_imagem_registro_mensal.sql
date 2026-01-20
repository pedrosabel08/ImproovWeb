-- Registro mensal do que foi efetivamente feito (por data do evento),
-- sem depender de funcao_imagem.prazo.
--
-- A ideia: quando funcao_imagem.status muda para um status relevante,
-- gravamos 1 linha por (funcao_imagem_id, ano, mes).

CREATE TABLE IF NOT EXISTS funcao_imagem_registro_mensal (
    id INT AUTO_INCREMENT PRIMARY KEY,

    funcao_imagem_id INT NOT NULL,
    colaborador_id INT NOT NULL,
    imagem_id INT NOT NULL,
    funcao_id INT NOT NULL,

    status_registrado VARCHAR(50) NOT NULL,
    observacao VARCHAR(100) NOT NULL DEFAULT '',
    data_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    ano SMALLINT NOT NULL,
    mes TINYINT NOT NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_funcao_imagem_ano_mes (funcao_imagem_id, ano, mes, status_registrado, observacao),

    KEY idx_registro_mensal_colab_ano_mes (colaborador_id, ano, mes),
    KEY idx_registro_mensal_imagem (imagem_id),
    KEY idx_registro_mensal_funcao (funcao_id),
    KEY idx_registro_mensal_data_evento (data_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger: cria registro quando o status muda para um dos estados contábeis.
-- Observação: usa NOW() como "mês real" do evento.

DROP TRIGGER IF EXISTS trg_funcao_imagem_registro_mensal;

DELIMITER //
CREATE TRIGGER trg_funcao_imagem_registro_mensal
AFTER UPDATE ON funcao_imagem
FOR EACH ROW
BEGIN
    DECLARE _img_status INT DEFAULT NULL;
    DECLARE _obs VARCHAR(100) DEFAULT NULL;

    IF (NEW.status <> OLD.status)
       AND (NEW.status IN ('Em aprovação', 'Finalizado', 'Aprovado com ajustes', 'Aprovado')) THEN

        -- obter status atual da imagem (tabela imagens_cliente_obra)
        SELECT status_id INTO _img_status
        FROM imagens_cliente_obra
        WHERE idimagens_cliente_obra = NEW.imagem_id
        LIMIT 1;

        IF NEW.funcao_id = 4 THEN
            IF _img_status = 1 THEN
                SET _obs = 'Finalização Parcial';
            ELSE
                SET _obs = 'Finalização Completa';
            END IF;
        ELSE
            SET _obs = '';
        END IF;

        INSERT IGNORE INTO funcao_imagem_registro_mensal (
            funcao_imagem_id,
            colaborador_id,
            imagem_id,
            funcao_id,
            status_registrado,
            observacao,
            data_evento,
            ano,
            mes
        ) VALUES (
            NEW.idfuncao_imagem,
            NEW.colaborador_id,
            NEW.imagem_id,
            NEW.funcao_id,
            NEW.status,
            _obs,
            NOW(),
            YEAR(NOW()),
            MONTH(NOW())
        );
    END IF;
END//
DELIMITER ;
