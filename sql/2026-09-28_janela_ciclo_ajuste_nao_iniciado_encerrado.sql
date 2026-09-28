-- Permite encerrar um ciclo de ajuste que foi recebido, mas nunca iniciado.
-- Isso acontece quando a revisao aprova a tarefa antes de o colaborador
-- iniciar o novo ciclo de execucao.

ALTER TABLE janela_operacional_ciclo
    DROP CHECK chk_janela_ciclo_limite;

ALTER TABLE janela_operacional_ciclo
    ADD CONSTRAINT chk_janela_ciclo_limite CHECK (
        (
            situacao IN ('AGUARDANDO_INICIO', 'ENCERRADO')
            AND inicio_em IS NULL
        )
        OR (
            (aplica_regra_snapshot = 1
             AND limite_dias_uteis_snapshot IS NOT NULL
             AND limite_data_original IS NOT NULL)
            OR
            (aplica_regra_snapshot = 0
             AND limite_dias_uteis_snapshot IS NULL
             AND limite_data_original IS NULL)
        )
    );
