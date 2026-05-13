-- ============================================================
-- PROVA REAL — Finalização Completa — Maio 2026
-- Colaboradores: 6 (Bruna), 8 (Marcio), 23 (Vitor),
--                33 (José Robson), 37 (Rafael), 40 (Heverton)
-- ============================================================

SET
    SESSION sql_mode = (
        SELECT
        REPLACE (
                @@sql_mode,
                'ONLY_FULL_GROUP_BY',
                ''
            )
    );

-- ============================================================
-- QUERY 1: Tarefas atuais de Maio/2026 — NÃO PAGAS
-- Espelha $sql (main) do buscar_producao.php
-- fimMesData      = '2026-05-31'
-- fimMesDataTime  = '2026-05-31 23:59:59'
-- mes = 5, ano = 2026
-- ============================================================

SELECT
    c.nome_colaborador,
    fi.colaborador_id,
    i.idimagens_cliente_obra AS imagem_id,
    i.imagem_nome,
    fi.status AS status_atual,
    CASE
        WHEN fi.funcao_id = 4
        AND LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada' THEN 'Planta Humanizada'
        WHEN fi.funcao_id = 4
        AND (
            hi_snap.status_id = 1
            OR (
                hi_snap.status_id IS NULL
                AND (
                    EXISTS (
                        SELECT 1
                        FROM
                            funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                        WHERE
                            fi_sub.imagem_id = fi.imagem_id
                            AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
                    )
                    OR i.status_id = 1
                )
            )
        ) THEN 'Parcial'
        WHEN fi.funcao_id = 4 THEN 'Completa'
        ELSE 'Outra'
    END AS tipo_finalizacao,
    -- ---------- cálculo de pagamento (idêntico ao PHP) ----------
    CASE
    -- Parcial: tem pagamento_itens → verifica 'Finalização Parcial'
        WHEN fi.funcao_id = 4
        AND (
            hi_snap.status_id = 1
            OR (
                hi_snap.status_id IS NULL
                AND (
                    EXISTS (
                        SELECT 1
                        FROM
                            funcao_imagem fi_sub
                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                        WHERE
                            fi_sub.imagem_id = fi.imagem_id
                            AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
                    )
                    OR i.status_id = 1
                )
            )
        ) THEN (
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM
                        pagamento_itens pi
                        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                    WHERE
                        pi.origem = 'funcao_imagem'
                        AND fi_pi.colaborador_id = fi.colaborador_id
                        AND fi_pi.imagem_id = fi.imagem_id
                ) THEN (
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM
                                pagamento_itens pi
                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                            WHERE
                                pi.origem = 'funcao_imagem'
                                AND fi_pi.colaborador_id = fi.colaborador_id
                                AND DATE(pi.criado_em) <= '2026-05-31'
                                AND fi_pi.imagem_id = fi.imagem_id
                                AND TRIM(pi.observacao) = 'Finalização Parcial'
                        ) THEN 1
                        ELSE 0
                    END
                )
                ELSE (
                    CASE
                        WHEN fi.data_pagamento IS NOT NULL
                        AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                        AND fi.data_pagamento <= '2026-05-31' THEN 1
                        ELSE 0
                    END
                )
            END
        )
        -- Completa com pagamento via pi 'Finalização Parcial' (excluída da completa → 0)
        WHEN fi.funcao_id = 4
        AND EXISTS (
            SELECT 1
            FROM
                pagamento_itens pi
                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
            WHERE
                pi.origem = 'funcao_imagem'
                AND fi_pi.colaborador_id = fi.colaborador_id
                AND fi_pi.imagem_id = fi.imagem_id
                AND TRIM(pi.observacao) = 'Finalização Parcial'
        ) THEN (
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM
                        pagamento_itens pi
                        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                    WHERE
                        pi.origem = 'funcao_imagem'
                        AND fi_pi.colaborador_id = fi.colaborador_id
                        AND fi_pi.imagem_id = fi.imagem_id
                ) THEN (
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM
                                pagamento_itens pi
                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                            WHERE
                                pi.origem = 'funcao_imagem'
                                AND fi_pi.colaborador_id = fi.colaborador_id
                                AND DATE(pi.criado_em) <= '2026-05-31'
                                AND fi_pi.imagem_id = fi.imagem_id
                                AND TRIM(pi.observacao) = 'Pago Completa'
                        ) THEN 1
                        ELSE 0
                    END
                )
                ELSE (
                    CASE
                        WHEN fi.data_pagamento IS NOT NULL
                        AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                        AND fi.data_pagamento <= '2026-05-31' THEN 1
                        ELSE 0
                    END
                )
            END
        )
        -- Completa normal
        WHEN fi.funcao_id = 4 THEN (
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM
                        pagamento_itens pi
                        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                    WHERE
                        pi.origem = 'funcao_imagem'
                        AND fi_pi.colaborador_id = fi.colaborador_id
                        AND fi_pi.imagem_id = fi.imagem_id
                ) THEN (
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM
                                pagamento_itens pi
                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                            WHERE
                                pi.origem = 'funcao_imagem'
                                AND fi_pi.colaborador_id = fi.colaborador_id
                                AND DATE(pi.criado_em) <= '2026-05-31'
                                AND fi_pi.imagem_id = fi.imagem_id
                                AND (
                                    pi.observacao IS NULL
                                    OR TRIM(pi.observacao) = ''
                                    OR TRIM(pi.observacao) = 'Pago Completa'
                                )
                                AND (
                                    pi.observacao IS NULL
                                    OR TRIM(pi.observacao) <> 'Finalização Parcial'
                                )
                        ) THEN 1
                        ELSE 0
                    END
                )
                ELSE (
                    CASE
                        WHEN fi.data_pagamento IS NOT NULL
                        AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                        AND fi.data_pagamento <= '2026-05-31' THEN 1
                        ELSE 0
                    END
                )
            END
        )
        ELSE 0
    END AS pagamento
FROM
    funcao_imagem fi
    JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
    LEFT JOIN (
        SELECT h1.imagem_id, h1.status_id
        FROM
            historico_imagens h1
            INNER JOIN (
                SELECT imagem_id, MAX(data_movimento) AS max_data
                FROM historico_imagens
                WHERE
                    data_movimento <= '2026-05-31'
                GROUP BY
                    imagem_id
            ) hm ON hm.imagem_id = h1.imagem_id
            AND hm.max_data = h1.data_movimento
    ) hi_snap ON hi_snap.imagem_id = i.idimagens_cliente_obra
WHERE
    fi.colaborador_id IN (6, 8, 23, 33, 37, 40)
    AND fi.funcao_id = 4
    AND NOT(
        LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada'
    )
    AND (
        EXISTS (
            SELECT 1
            FROM log_alteracoes la
            WHERE
                la.funcao_imagem_id = fi.idfuncao_imagem
                AND MONTH(la.data) = 5
                AND YEAR(la.data) = 2026
                AND LOWER(TRIM(la.status_novo)) IN (
                    'finalizado',
                    'em aprovação',
                    'ajuste',
                    'aprovado com ajustes',
                    'aprovado'
                )
        )
        OR (
            MONTH(fi.prazo) = 5
            AND YEAR(fi.prazo) = 2026
            AND LOWER(TRIM(fi.status)) IN (
                'finalizado',
                'em aprovação',
                'ajuste',
                'aprovado com ajustes',
                'aprovado'
            )
        )
    )
    AND (
        LOWER(TRIM(fi.status)) IN (
            'finalizado',
            'em aprovação',
            'ajuste',
            'aprovado com ajustes',
            'aprovado'
        )
        OR EXISTS (
            SELECT 1
            FROM log_alteracoes la_fin
            WHERE
                la_fin.funcao_imagem_id = fi.idfuncao_imagem
                AND la_fin.data <= '2026-05-31 23:59:59'
                AND LOWER(TRIM(la_fin.status_novo)) IN (
                    'finalizado',
                    'em aprovação',
                    'ajuste',
                    'aprovado com ajustes',
                    'aprovado'
                )
        )
    )
HAVING
    tipo_finalizacao = 'Completa'
    AND pagamento = 0
ORDER BY c.nome_colaborador, i.imagem_nome;

-- ============================================================
-- QUERY 2: RECORDE — Finalização Completa por colaborador
-- Espelha $sqlRecorde do buscar_producao.php
-- Exclui: mês atual (2026-05), 2024-10, meses pagos no período
-- Mostra: todos os meses (para ver a variação) + MAX
-- ============================================================

SELECT sub.nome_colaborador, sub.colaborador_id, sub.ano, sub.mes, CONCAT(
        sub.ano, '-', LPAD(sub.mes, 2, '0')
    ) AS mes_ano, sub.qtd_mes
FROM (
        SELECT fi.colaborador_id, c.nome_colaborador, p.yr AS ano, p.mo AS mes, COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
        FROM
            funcao_imagem fi
            JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
            JOIN funcao f ON f.idfuncao = fi.funcao_id
            LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
            INNER JOIN (
                SELECT funcao_imagem_id, YEAR(data) AS yr, MONTH(data) AS mo
                FROM log_alteracoes
                WHERE
                    data >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                    AND LOWER(TRIM(status_novo)) IN (
                        'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                    )
                GROUP BY
                    funcao_imagem_id, YEAR(data), MONTH(data)
                UNION
                SELECT
                    idfuncao_imagem AS funcao_imagem_id, YEAR(prazo) AS yr, MONTH(prazo) AS mo
                FROM funcao_imagem
                WHERE
                    prazo IS NOT NULL
                    AND prazo >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                    AND LOWER(TRIM(status)) IN (
                        'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                    )
                GROUP BY
                    idfuncao_imagem, YEAR(prazo), MONTH(prazo)
            ) AS p ON p.funcao_imagem_id = fi.idfuncao_imagem
        WHERE
            fi.colaborador_id IN (6, 8, 23, 33, 37, 40)
            AND fi.funcao_id = 4
            AND NOT(
                LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada'
            )
            AND NOT(
                fi.funcao_id = 4
                AND i.status_id = 1
            )
            -- exclui mês atual, 2024-10 e meses fora da janela de 36 meses
            AND NOT(
                p.yr = 2026
                AND p.mo = 5
            )
            AND NOT(
                p.yr = 2026
                AND p.mo = 5
            ) -- mês atual = anoSelecionado/mes
            AND NOT(
                p.yr = 2026
                AND p.mo = 5
            ) -- $anoAtual/$mesAtual (coincide em maio/2026)
            AND NOT(
                p.yr = 2024
                AND p.mo = 10
            )
            AND (p.yr * 12 + p.mo) >= (2026 * 12 + 5 - 36)
            -- status finalizado no período
            AND (
                LOWER(TRIM(fi.status)) IN (
                    'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                )
                OR EXISTS (
                    SELECT 1
                    FROM log_alteracoes la_fin
                    WHERE
                        la_fin.funcao_imagem_id = fi.idfuncao_imagem
                        AND la_fin.data <= CONCAT(
                            LAST_DAY(
                                DATE(
                                    CONCAT(
                                        p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                    )
                                )
                            ), ' 23:59:59'
                        )
                        AND LOWER(TRIM(la_fin.status_novo)) IN (
                            'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                        )
                )
            )
            -- não pago no período (Completa)
            AND (
                (
                    EXISTS (
                        SELECT 1
                        FROM
                            pagamento_itens pi_any
                            JOIN funcao_imagem fi_pi4 ON fi_pi4.idfuncao_imagem = pi_any.origem_id
                        WHERE
                            pi_any.origem = 'funcao_imagem'
                            AND fi_pi4.colaborador_id = fi.colaborador_id
                            AND fi_pi4.imagem_id = fi.imagem_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM
                            pagamento_itens pi_full
                            JOIN funcao_imagem fi_pi4f ON fi_pi4f.idfuncao_imagem = pi_full.origem_id
                        WHERE
                            pi_full.origem = 'funcao_imagem'
                            AND fi_pi4f.colaborador_id = fi.colaborador_id
                            AND fi_pi4f.imagem_id = fi.imagem_id
                            AND DATE(pi_full.criado_em) <= LAST_DAY(
                                DATE(
                                    CONCAT(
                                        p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                    )
                                )
                            )
                            AND (
                                pi_full.observacao IS NULL
                                OR TRIM(pi_full.observacao) = ''
                                OR TRIM(pi_full.observacao) = 'Pago Completa'
                            )
                    )
                )
                OR (
                    NOT EXISTS (
                        SELECT 1
                        FROM
                            pagamento_itens pi_any2
                            JOIN funcao_imagem fi_pi4b ON fi_pi4b.idfuncao_imagem = pi_any2.origem_id
                        WHERE
                            pi_any2.origem = 'funcao_imagem'
                            AND fi_pi4b.colaborador_id = fi.colaborador_id
                            AND fi_pi4b.imagem_id = fi.imagem_id
                    )
                    AND (
                        fi.data_pagamento IS NULL
                        OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
                        OR fi.data_pagamento > LAST_DAY(
                            DATE(
                                CONCAT(
                                    p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                )
                            )
                        )
                    )
                )
            )
            -- exclui Parcial no período (hi_snap no último dia do mês)
            AND NOT(
                fi.funcao_id = 4
                AND LOWER(TRIM(i.tipo_imagem)) != 'planta humanizada'
                AND (
                    EXISTS (
                        SELECT 1
                        FROM historico_imagens hi_p
                        WHERE
                            hi_p.imagem_id = fi.imagem_id
                            AND hi_p.status_id = 1
                            AND hi_p.data_movimento = (
                                SELECT MAX(hm.data_movimento)
                                FROM historico_imagens hm
                                WHERE
                                    hm.imagem_id = fi.imagem_id
                                    AND hm.data_movimento <= CONCAT(
                                        LAST_DAY(
                                            DATE(
                                                CONCAT(
                                                    p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                                )
                                            )
                                        ), ' 23:59:59'
                                    )
                            )
                    )
                    OR (
                        NOT EXISTS (
                            SELECT 1
                            FROM historico_imagens h_any
                            WHERE
                                h_any.imagem_id = fi.imagem_id
                                AND h_any.data_movimento <= CONCAT(
                                    LAST_DAY(
                                        DATE(
                                            CONCAT(
                                                p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                            )
                                        )
                                    ), ' 23:59:59'
                                )
                        )
                        AND (
                            i.status_id = 1
                            OR EXISTS (
                                SELECT 1
                                FROM funcao f2
                                WHERE
                                    f2.idfuncao = fi.funcao_id
                                    AND LOWER(f2.nome_funcao) LIKE '%pre%'
                            )
                        )
                    )
                )
            )
        GROUP BY
            fi.colaborador_id, c.nome_colaborador, p.yr, p.mo
    ) AS sub
ORDER BY sub.nome_colaborador, sub.ano, sub.mes;

-- ============================================================
-- QUERY 2b: RECORDE — imagens individuais do mês recorde
-- Mostra cada imagem que compõe o mês de maior produção
-- não paga de Finalização Completa por colaborador
-- ============================================================

-- Passo 1: descobre o mês recorde de cada colaborador
WITH
    meses_recorde AS (
        SELECT
            colaborador_id,
            nome_colaborador,
            ano,
            mes,
            qtd_mes,
            RANK() OVER (
                PARTITION BY
                    colaborador_id
                ORDER BY qtd_mes DESC
            ) AS rnk
        FROM (
                SELECT fi.colaborador_id, c.nome_colaborador, p.yr AS ano, p.mo AS mes, COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
                FROM
                    funcao_imagem fi
                    JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
                    JOIN funcao f ON f.idfuncao = fi.funcao_id
                    LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
                    INNER JOIN (
                        SELECT funcao_imagem_id, YEAR(data) AS yr, MONTH(data) AS mo
                        FROM log_alteracoes
                        WHERE
                            data >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                            AND LOWER(TRIM(status_novo)) IN (
                                'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                            )
                        GROUP BY
                            funcao_imagem_id, YEAR(data), MONTH(data)
                        UNION
                        SELECT
                            idfuncao_imagem AS funcao_imagem_id, YEAR(prazo) AS yr, MONTH(prazo) AS mo
                        FROM funcao_imagem
                        WHERE
                            prazo IS NOT NULL
                            AND prazo >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                            AND LOWER(TRIM(status)) IN (
                                'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                            )
                        GROUP BY
                            idfuncao_imagem, YEAR(prazo), MONTH(prazo)
                    ) AS p ON p.funcao_imagem_id = fi.idfuncao_imagem
                WHERE
                    fi.colaborador_id IN (6, 8, 23, 33, 37, 40)
                    AND fi.funcao_id = 4
                    AND NOT(
                        LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada'
                    )
                    AND NOT(
                        fi.funcao_id = 4
                        AND i.status_id = 1
                    )
                    AND NOT(
                        p.yr = 2026
                        AND p.mo = 5
                    )
                    AND NOT(
                        p.yr = 2024
                        AND p.mo = 10
                    )
                    AND (p.yr * 12 + p.mo) >= (2026 * 12 + 5 - 36)
                    AND (
                        LOWER(TRIM(fi.status)) IN (
                            'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM log_alteracoes la_fin
                            WHERE
                                la_fin.funcao_imagem_id = fi.idfuncao_imagem
                                AND la_fin.data <= CONCAT(
                                    LAST_DAY(
                                        DATE(
                                            CONCAT(
                                                p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                            )
                                        )
                                    ), ' 23:59:59'
                                )
                                AND LOWER(TRIM(la_fin.status_novo)) IN (
                                    'finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'
                                )
                        )
                    )
                    AND (
                        (
                            EXISTS (
                                SELECT 1
                                FROM
                                    pagamento_itens pi_any
                                    JOIN funcao_imagem fi_pi4 ON fi_pi4.idfuncao_imagem = pi_any.origem_id
                                WHERE
                                    pi_any.origem = 'funcao_imagem'
                                    AND fi_pi4.colaborador_id = fi.colaborador_id
                                    AND fi_pi4.imagem_id = fi.imagem_id
                            )
                            AND NOT EXISTS (
                                SELECT 1
                                FROM
                                    pagamento_itens pi_full
                                    JOIN funcao_imagem fi_pi4f ON fi_pi4f.idfuncao_imagem = pi_full.origem_id
                                WHERE
                                    pi_full.origem = 'funcao_imagem'
                                    AND fi_pi4f.colaborador_id = fi.colaborador_id
                                    AND fi_pi4f.imagem_id = fi.imagem_id
                                    AND DATE(pi_full.criado_em) <= LAST_DAY(
                                        DATE(
                                            CONCAT(
                                                p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                            )
                                        )
                                    )
                                    AND (
                                        pi_full.observacao IS NULL
                                        OR TRIM(pi_full.observacao) = ''
                                        OR TRIM(pi_full.observacao) = 'Pago Completa'
                                    )
                            )
                        )
                        OR (
                            NOT EXISTS (
                                SELECT 1
                                FROM
                                    pagamento_itens pi_any2
                                    JOIN funcao_imagem fi_pi4b ON fi_pi4b.idfuncao_imagem = pi_any2.origem_id
                                WHERE
                                    pi_any2.origem = 'funcao_imagem'
                                    AND fi_pi4b.colaborador_id = fi.colaborador_id
                                    AND fi_pi4b.imagem_id = fi.imagem_id
                            )
                            AND (
                                fi.data_pagamento IS NULL
                                OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
                                OR fi.data_pagamento > LAST_DAY(
                                    DATE(
                                        CONCAT(
                                            p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                        )
                                    )
                                )
                            )
                        )
                    )
                    AND NOT(
                        fi.funcao_id = 4
                        AND LOWER(TRIM(i.tipo_imagem)) != 'planta humanizada'
                        AND (
                            EXISTS (
                                SELECT 1
                                FROM historico_imagens hi_p
                                WHERE
                                    hi_p.imagem_id = fi.imagem_id
                                    AND hi_p.status_id = 1
                                    AND hi_p.data_movimento = (
                                        SELECT MAX(hm.data_movimento)
                                        FROM historico_imagens hm
                                        WHERE
                                            hm.imagem_id = fi.imagem_id
                                            AND hm.data_movimento <= CONCAT(
                                                LAST_DAY(
                                                    DATE(
                                                        CONCAT(
                                                            p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                                        )
                                                    )
                                                ), ' 23:59:59'
                                            )
                                    )
                            )
                            OR (
                                NOT EXISTS (
                                    SELECT 1
                                    FROM historico_imagens h_any
                                    WHERE
                                        h_any.imagem_id = fi.imagem_id
                                        AND h_any.data_movimento <= CONCAT(
                                            LAST_DAY(
                                                DATE(
                                                    CONCAT(
                                                        p.yr, '-', LPAD(p.mo, 2, '0'), '-01'
                                                    )
                                                )
                                            ), ' 23:59:59'
                                        )
                                )
                                AND (
                                    i.status_id = 1
                                    OR EXISTS (
                                        SELECT 1
                                        FROM funcao f2
                                        WHERE
                                            f2.idfuncao = fi.funcao_id
                                            AND LOWER(f2.nome_funcao) LIKE '%pre%'
                                    )
                                )
                            )
                        )
                    )
                GROUP BY
                    fi.colaborador_id, c.nome_colaborador, p.yr, p.mo
            ) AS sub_contagem
    ),

-- Passo 2: pega apenas o mês de rank 1 (o recorde)
mes_top AS (
    SELECT
        colaborador_id,
        nome_colaborador,
        ano,
        mes,
        qtd_mes,
        CONCAT(ano, '-', LPAD(mes, 2, '0')) AS recorde_mes_ano
    FROM meses_recorde
    WHERE
        rnk = 1
)

-- Passo 3: lista as imagens individuais desse mês recorde
SELECT
    mt.nome_colaborador,
    mt.recorde_mes_ano,
    mt.qtd_mes AS recorde_total,
    i.idimagens_cliente_obra AS imagem_id,
    i.imagem_nome,
    fi.status AS status_atual,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM log_alteracoes la
            WHERE
                la.funcao_imagem_id = fi.idfuncao_imagem
                AND MONTH(la.data) = mt.mes
                AND YEAR(la.data) = mt.ano
                AND LOWER(TRIM(la.status_novo)) IN (
                    'finalizado',
                    'em aprovação',
                    'ajuste',
                    'aprovado com ajustes',
                    'aprovado'
                )
        ) THEN 'via LOG'
        ELSE 'via PRAZO'
    END AS como_entrou
FROM
    mes_top mt
    JOIN funcao_imagem fi ON fi.colaborador_id = mt.colaborador_id
    AND fi.funcao_id = 4
    LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
WHERE
    NOT(
        LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada'
    )
    AND NOT(i.status_id = 1)
    AND (
        EXISTS (
            SELECT 1
            FROM log_alteracoes la
            WHERE
                la.funcao_imagem_id = fi.idfuncao_imagem
                AND MONTH(la.data) = mt.mes
                AND YEAR(la.data) = mt.ano
                AND LOWER(TRIM(la.status_novo)) IN (
                    'finalizado',
                    'em aprovação',
                    'ajuste',
                    'aprovado com ajustes',
                    'aprovado'
                )
        )
        OR (
            MONTH(fi.prazo) = mt.mes
            AND YEAR(fi.prazo) = mt.ano
            AND LOWER(TRIM(fi.status)) IN (
                'finalizado',
                'em aprovação',
                'ajuste',
                'aprovado com ajustes',
                'aprovado'
            )
        )
    )
    AND (
        LOWER(TRIM(fi.status)) IN (
            'finalizado',
            'em aprovação',
            'ajuste',
            'aprovado com ajustes',
            'aprovado'
        )
        OR EXISTS (
            SELECT 1
            FROM log_alteracoes la_fin
            WHERE
                la_fin.funcao_imagem_id = fi.idfuncao_imagem
                AND la_fin.data <= CONCAT(
                    LAST_DAY(
                        DATE(
                            CONCAT(
                                mt.ano,
                                '-',
                                LPAD(mt.mes, 2, '0'),
                                '-01'
                            )
                        )
                    ),
                    ' 23:59:59'
                )
                AND LOWER(TRIM(la_fin.status_novo)) IN (
                    'finalizado',
                    'em aprovação',
                    'ajuste',
                    'aprovado com ajustes',
                    'aprovado'
                )
        )
    )
    -- não pago no período do recorde
    AND (
        (
            EXISTS (
                SELECT 1
                FROM
                    pagamento_itens pi_any
                    JOIN funcao_imagem fi_pi4 ON fi_pi4.idfuncao_imagem = pi_any.origem_id
                WHERE
                    pi_any.origem = 'funcao_imagem'
                    AND fi_pi4.colaborador_id = fi.colaborador_id
                    AND fi_pi4.imagem_id = fi.imagem_id
            )
            AND NOT EXISTS (
                SELECT 1
                FROM
                    pagamento_itens pi_full
                    JOIN funcao_imagem fi_pi4f ON fi_pi4f.idfuncao_imagem = pi_full.origem_id
                WHERE
                    pi_full.origem = 'funcao_imagem'
                    AND fi_pi4f.colaborador_id = fi.colaborador_id
                    AND fi_pi4f.imagem_id = fi.imagem_id
                    AND DATE(pi_full.criado_em) <= LAST_DAY(
                        DATE(
                            CONCAT(
                                mt.ano,
                                '-',
                                LPAD(mt.mes, 2, '0'),
                                '-01'
                            )
                        )
                    )
                    AND (
                        pi_full.observacao IS NULL
                        OR TRIM(pi_full.observacao) = ''
                        OR TRIM(pi_full.observacao) = 'Pago Completa'
                    )
            )
        )
        OR (
            NOT EXISTS (
                SELECT 1
                FROM
                    pagamento_itens pi_any2
                    JOIN funcao_imagem fi_pi4b ON fi_pi4b.idfuncao_imagem = pi_any2.origem_id
                WHERE
                    pi_any2.origem = 'funcao_imagem'
                    AND fi_pi4b.colaborador_id = fi.colaborador_id
                    AND fi_pi4b.imagem_id = fi.imagem_id
            )
            AND (
                fi.data_pagamento IS NULL
                OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
                OR fi.data_pagamento > LAST_DAY(
                    DATE(
                        CONCAT(
                            mt.ano,
                            '-',
                            LPAD(mt.mes, 2, '0'),
                            '-01'
                        )
                    )
                )
            )
        )
    )
    -- não era Parcial no período do recorde
    AND NOT(
        EXISTS (
            SELECT 1
            FROM historico_imagens hi_p
            WHERE
                hi_p.imagem_id = fi.imagem_id
                AND hi_p.status_id = 1
                AND hi_p.data_movimento = (
                    SELECT MAX(hm.data_movimento)
                    FROM historico_imagens hm
                    WHERE
                        hm.imagem_id = fi.imagem_id
                        AND hm.data_movimento <= CONCAT(
                            LAST_DAY(
                                DATE(
                                    CONCAT(
                                        mt.ano,
                                        '-',
                                        LPAD(mt.mes, 2, '0'),
                                        '-01'
                                    )
                                )
                            ),
                            ' 23:59:59'
                        )
                )
        )
        OR (
            NOT EXISTS (
                SELECT 1
                FROM historico_imagens h_any
                WHERE
                    h_any.imagem_id = fi.imagem_id
                    AND h_any.data_movimento <= CONCAT(
                        LAST_DAY(
                            DATE(
                                CONCAT(
                                    mt.ano,
                                    '-',
                                    LPAD(mt.mes, 2, '0'),
                                    '-01'
                                )
                            )
                        ),
                        ' 23:59:59'
                    )
            )
            AND (
                i.status_id = 1
                OR EXISTS (
                    SELECT 1
                    FROM funcao f2
                    WHERE
                        f2.idfuncao = fi.funcao_id
                        AND LOWER(f2.nome_funcao) LIKE '%pre%'
                )
            )
        )
    )
ORDER BY mt.nome_colaborador, i.imagem_nome;

SELECT nome_funcao, MAX(qtd_mes) AS recorde
    FROM (
      SELECT
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END AS nome_funcao,
        p.yr,
        p.mo,
        COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      INNER JOIN (
        SELECT funcao_imagem_id, YEAR(data) AS yr, MONTH(data) AS mo
        FROM log_alteracoes
        WHERE data >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
          AND LOWER(TRIM(status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
        GROUP BY funcao_imagem_id, YEAR(data), MONTH(data)
        UNION
        SELECT idfuncao_imagem AS funcao_imagem_id, YEAR(prazo) AS yr, MONTH(prazo) AS mo
        FROM funcao_imagem
        WHERE prazo IS NOT NULL
          AND prazo >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
          AND LOWER(TRIM(status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
        GROUP BY idfuncao_imagem, YEAR(prazo), MONTH(prazo)
      ) p ON p.funcao_imagem_id = fi.idfuncao_imagem
      WHERE fi.colaborador_id IS NOT NULL
        AND (
          LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          OR EXISTS (
            SELECT 1 FROM log_alteracoes la_fin
            WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
              AND la_fin.data <= CONCAT(
                LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01'))),
                ' 23:59:59'
              )
              AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          )
        )
        AND fi.colaborador_id NOT IN (21, 15)
        AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
        AND NOT (
          fi.funcao_id = 4
          AND LOWER(TRIM(ico.tipo_imagem)) != 'planta humanizada'
          AND (
            EXISTS (
              SELECT 1 FROM historico_imagens hi_p
              WHERE hi_p.imagem_id = fi.imagem_id
                AND hi_p.status_id = 1
                AND hi_p.data_movimento = (
                  SELECT MAX(hm.data_movimento) FROM historico_imagens hm
                  WHERE hm.imagem_id = fi.imagem_id
                    AND hm.data_movimento <= CONCAT(
                      LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01'))),
                      ' 23:59:59'
                    )
                )
            )
            OR (
              NOT EXISTS (
                SELECT 1 FROM historico_imagens h_any
                WHERE h_any.imagem_id = fi.imagem_id
                  AND h_any.data_movimento <= CONCAT(
                    LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01'))),
                    ' 23:59:59'
                  )
              )
              AND (
                ico.status_id = 1
                OR EXISTS (
                  SELECT 1 FROM funcao_imagem fi_sub
                  JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                  WHERE fi_sub.imagem_id = fi.imagem_id
                    AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
                )
              )
            )
          )
        )
        AND NOT (p.yr = 2024 AND p.mo = 10)
        AND (
          (
            fi.funcao_id = 4
            AND (
              (
                EXISTS (
                  SELECT 1 FROM pagamento_itens pi_any
                  JOIN funcao_imagem fi_pi4 ON fi_pi4.idfuncao_imagem = pi_any.origem_id
                  WHERE pi_any.origem = 'funcao_imagem'
                    AND fi_pi4.colaborador_id = fi.colaborador_id
                    AND fi_pi4.imagem_id = fi.imagem_id
                )
                AND NOT EXISTS (
                  SELECT 1 FROM pagamento_itens pi_full
                  JOIN funcao_imagem fi_pi4f ON fi_pi4f.idfuncao_imagem = pi_full.origem_id
                  WHERE pi_full.origem = 'funcao_imagem'
                    AND fi_pi4f.colaborador_id = fi.colaborador_id
                    AND fi_pi4f.imagem_id = fi.imagem_id
                    AND fi_pi4f.funcao_id = 4
                    AND DATE(pi_full.criado_em) <= LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01')))
                    AND (pi_full.observacao IS NULL OR TRIM(pi_full.observacao) = '' OR TRIM(pi_full.observacao) = 'Pago Completa')
                )
              )
              OR (
                NOT EXISTS (
                  SELECT 1 FROM pagamento_itens pi_any2
                  JOIN funcao_imagem fi_pi4b ON fi_pi4b.idfuncao_imagem = pi_any2.origem_id
                  WHERE pi_any2.origem = 'funcao_imagem'
                    AND fi_pi4b.colaborador_id = fi.colaborador_id
                    AND fi_pi4b.imagem_id = fi.imagem_id
                )
                AND (
                  fi.data_pagamento IS NULL
                  OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
                  OR fi.data_pagamento > LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01')))
                )
              )
            )
          )
          OR (
            fi.funcao_id <> 4
            AND NOT EXISTS (
              SELECT 1 FROM pagamento_itens pi_np
              WHERE pi_np.origem = 'funcao_imagem'
                AND pi_np.origem_id = fi.idfuncao_imagem
                AND DATE(pi_np.criado_em) <= LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01')))
            )
            AND (
              fi.data_pagamento IS NULL
              OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
              OR fi.data_pagamento > LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01')))
            )
          )
        )
      GROUP BY
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END,
        p.yr, p.mo
    ) AS s
    GROUP BY nome_funcao;