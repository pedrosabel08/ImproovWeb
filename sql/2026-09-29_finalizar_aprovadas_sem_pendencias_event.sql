-- Finaliza diariamente tarefas aprovadas que nao aguardam envio de arquivo,
-- envio de render ou resolucao/confirmacao de um Flow Block bloqueante.
--
-- O evento usa o fuso de Sao Paulo (UTC-03:00). O EVENT SCHEDULER do MySQL
-- precisa estar habilitado no servidor para que a rotina seja executada.

SET time_zone = '-03:00';

DROP EVENT IF EXISTS ev_finalizar_aprovadas_sem_pendencias;

DELIMITER $$

CREATE EVENT ev_finalizar_aprovadas_sem_pendencias
    ON SCHEDULE EVERY 1 DAY
    STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 21 HOUR
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Finaliza tarefas aprovadas sem pendencias de envio.'
DO
BEGIN
    START TRANSACTION;

    INSERT INTO log_alteracoes (
        funcao_imagem_id,
        status_anterior,
        status_novo,
        colaborador_id
    )
    SELECT
        fi.idfuncao_imagem,
        fi.status,
        'Finalizado',
        NULL
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    WHERE fi.status = 'Aprovado'
      AND COALESCE(fi.requires_file_upload, 0) = 0
      AND COALESCE(fi.requires_render_send, 0) = 0
      AND NOT (
          (
              (fi.funcao_id = 4 AND ico.status_id = 2)
              OR (
                  fi.funcao_id = 6
                  AND (
                      ico.tipo_imagem IS NULL
                      OR LOWER(ico.tipo_imagem) NOT LIKE '%humanizada%'
                  )
              )
          )
          AND EXISTS (
              SELECT 1
              FROM historico_aprovacoes ha
              WHERE ha.funcao_imagem_id = fi.idfuncao_imagem
                AND ha.status_novo IN ('Aprovado', 'Aprovado com ajustes')
                AND ha.responsavel IN (21, 2, 9, 31)
                AND NOT EXISTS (
                    SELECT 1
                    FROM historico_aprovacoes ha2
                    WHERE ha2.funcao_imagem_id = ha.funcao_imagem_id
                      AND ha2.id > ha.id
                )
                AND NOT (
                    fi.funcao_id = 6
                    AND ha.observacoes REGEXP CONCAT(CHAR(34), 'direcao_alteracao_destino', CHAR(34), '[[:space:]]*:')
                )
          )
          AND NOT EXISTS (
              SELECT 1
              FROM render_alta ra
              WHERE ra.imagem_id = ico.idimagens_cliente_obra
                AND ra.status_id = ico.status_id
                AND COALESCE(ra.status, '') <> 'Arquivado'
          )
      )
      AND NOT EXISTS (
          SELECT 1
          FROM flow_issue i
          WHERE i.funcao_imagem_id = fi.idfuncao_imagem
            AND i.bloqueante = 1
            AND (
                i.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
                OR (i.status = 'RESOLVIDA' AND i.confirmada_em IS NULL)
            )
      );

    UPDATE funcao_imagem fi
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    SET fi.status = 'Finalizado'
    WHERE fi.status = 'Aprovado'
      AND COALESCE(fi.requires_file_upload, 0) = 0
      AND COALESCE(fi.requires_render_send, 0) = 0
      AND NOT (
          (
              (fi.funcao_id = 4 AND ico.status_id = 2)
              OR (
                  fi.funcao_id = 6
                  AND (
                      ico.tipo_imagem IS NULL
                      OR LOWER(ico.tipo_imagem) NOT LIKE '%humanizada%'
                  )
              )
          )
          AND EXISTS (
              SELECT 1
              FROM historico_aprovacoes ha
              WHERE ha.funcao_imagem_id = fi.idfuncao_imagem
                AND ha.status_novo IN ('Aprovado', 'Aprovado com ajustes')
                AND ha.responsavel IN (21, 2, 9, 31)
                AND NOT EXISTS (
                    SELECT 1
                    FROM historico_aprovacoes ha2
                    WHERE ha2.funcao_imagem_id = ha.funcao_imagem_id
                      AND ha2.id > ha.id
                )
                AND NOT (
                    fi.funcao_id = 6
                    AND ha.observacoes REGEXP CONCAT(CHAR(34), 'direcao_alteracao_destino', CHAR(34), '[[:space:]]*:')
                )
          )
          AND NOT EXISTS (
              SELECT 1
              FROM render_alta ra
              WHERE ra.imagem_id = ico.idimagens_cliente_obra
                AND ra.status_id = ico.status_id
                AND COALESCE(ra.status, '') <> 'Arquivado'
          )
      )
      AND NOT EXISTS (
          SELECT 1
          FROM flow_issue i
          WHERE i.funcao_imagem_id = fi.idfuncao_imagem
            AND i.bloqueante = 1
            AND (
                i.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
                OR (i.status = 'RESOLVIDA' AND i.confirmada_em IS NULL)
            )
      );

    COMMIT;
END$$

DELIMITER;