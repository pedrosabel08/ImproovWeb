<?php
header('Content-Type: application/json; charset=utf-8');

try {
    include __DIR__ . '/../conexao.php';

    if (!$conn || $conn->connect_error) {
        throw new Exception('Falha na conexao com o banco.');
    }

    $conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

    if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
        http_response_code(400);
        echo json_encode(['error' => 'Parametros invalidos']);
        exit;
    }

    $fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $fimMesData = sprintf('%04d-%02d-%02d', $ano, $mes, $fimMesDia);
    $fimMesDataTime = $fimMesData . ' 23:59:59';

    // 1) Colaboradores base da funcao 4 (Finalizacao Completa)
    $sqlColaboradores = "
        SELECT DISTINCT fc.colaborador_id, c.nome_colaborador
        FROM funcao_colaborador fc
        JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
        WHERE fc.funcao_id = 4
          AND fc.colaborador_id IS NOT NULL
          AND c.ativo = 1
          AND fc.colaborador_id NOT IN (21, 15, 30, 7, 34)
          AND NOT EXISTS (
              SELECT 1
              FROM funcao_colaborador fc2
              WHERE fc2.colaborador_id = fc.colaborador_id
                AND fc2.funcao_id = 7
          )
        ORDER BY c.nome_colaborador ASC
    ";

    $stmtColaboradores = $conn->prepare($sqlColaboradores);
    if (!$stmtColaboradores) {
        throw new Exception('Erro ao preparar consulta de colaboradores: ' . $conn->error);
    }
    $stmtColaboradores->execute();

    $colaboradores = [];
    $colaboradoresBase = [];
    $resColaboradores = $stmtColaboradores->get_result();
    while ($row = $resColaboradores->fetch_assoc()) {
        $colaboradorId = (int) $row['colaborador_id'];
        $nome = $row['nome_colaborador'] ?? '';
        $colaboradoresBase[$colaboradorId] = $nome;
    }
    $stmtColaboradores->close();

        // 2) Quantidade feita = NAO PAGAS, com a mesma regra de buscar_producao.php
        $sqlProducao = "
                SELECT
                    t.colaborador_id,
                    SUM(CASE WHEN t.pagamento <> 1 OR t.pagamento IS NULL THEN 1 ELSE 0 END) AS quantidade_feita
                FROM (
                    SELECT
                        fi.colaborador_id,
                        f.idfuncao AS funcao_id,
                        CASE
                            WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
                            WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
                            ELSE f.nome_funcao
                        END AS nome_funcao,
                        CASE
                            WHEN fi.funcao_id = 4 AND (
                                hi_snap.status_id = 1
                                OR (
                                    hi_snap.status_id IS NULL AND (
                                        EXISTS (
                                            SELECT 1
                                            FROM funcao_imagem fi_sub
                                            JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                                            WHERE fi_sub.imagem_id = fi.imagem_id
                                                AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
                                        )
                                        OR i.status_id = 1
                                    )
                                )
                            ) THEN (
                                CASE
                                    WHEN EXISTS (
                                        SELECT 1
                                        FROM pagamento_itens pi
                                        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                        WHERE pi.origem = 'funcao_imagem'
                                            AND fi_pi.colaborador_id = fi.colaborador_id
                                            AND fi_pi.imagem_id = fi.imagem_id
                                    ) THEN (
                                        CASE
                                            WHEN EXISTS (
                                                SELECT 1
                                                FROM pagamento_itens pi
                                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                                WHERE pi.origem = 'funcao_imagem'
                                                    AND fi_pi.colaborador_id = fi.colaborador_id
                                                    AND DATE(pi.criado_em) <= ?
                                                    AND fi_pi.imagem_id = fi.imagem_id
                                                    AND TRIM(pi.observacao) = 'Finalização Parcial'
                                            ) THEN 1 ELSE 0
                                        END
                                    )
                                    ELSE (
                                        CASE
                                            WHEN fi.data_pagamento IS NOT NULL
                                                AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                                                AND fi.data_pagamento <= ?
                                            THEN 1 ELSE 0
                                        END
                                    )
                                END
                            )
                            WHEN fi.funcao_id = 4 AND EXISTS (
                                SELECT 1
                                FROM pagamento_itens pi
                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                WHERE pi.origem = 'funcao_imagem'
                                    AND fi_pi.colaborador_id = fi.colaborador_id
                                    AND fi_pi.imagem_id = fi.imagem_id
                                    AND TRIM(pi.observacao) = 'Finalização Parcial'
                            ) THEN (
                                CASE
                                    WHEN EXISTS (
                                        SELECT 1
                                        FROM pagamento_itens pi
                                        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                        WHERE pi.origem = 'funcao_imagem'
                                            AND fi_pi.colaborador_id = fi.colaborador_id
                                            AND fi_pi.imagem_id = fi.imagem_id
                                    ) THEN (
                                        CASE
                                            WHEN EXISTS (
                                                SELECT 1
                                                FROM pagamento_itens pi
                                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                                WHERE pi.origem = 'funcao_imagem'
                                                    AND fi_pi.colaborador_id = fi.colaborador_id
                                                    AND DATE(pi.criado_em) <= ?
                                                    AND fi_pi.imagem_id = fi.imagem_id
                                                    AND TRIM(pi.observacao) = 'Pago Completa'
                                            ) THEN 1 ELSE 0
                                        END
                                    )
                                    ELSE (
                                        CASE
                                            WHEN fi.data_pagamento IS NOT NULL
                                                AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                                                AND fi.data_pagamento <= ?
                                            THEN 1 ELSE 0
                                        END
                                    )
                                END
                            )
                            WHEN fi.funcao_id = 4 THEN (
                                CASE
                                    WHEN EXISTS (
                                        SELECT 1
                                        FROM pagamento_itens pi
                                        JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                        WHERE pi.origem = 'funcao_imagem'
                                            AND fi_pi.colaborador_id = fi.colaborador_id
                                            AND fi_pi.imagem_id = fi.imagem_id
                                    ) THEN (
                                        CASE
                                            WHEN EXISTS (
                                                SELECT 1
                                                FROM pagamento_itens pi
                                                JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                                                WHERE pi.origem = 'funcao_imagem'
                                                    AND fi_pi.colaborador_id = fi.colaborador_id
                                                    AND DATE(pi.criado_em) <= ?
                                                    AND fi_pi.imagem_id = fi.imagem_id
                                                    AND (
                                                        pi.observacao IS NULL
                                                        OR TRIM(pi.observacao) = ''
                                                        OR TRIM(pi.observacao) = 'Pago Completa'
                                                    )
                                                    AND (pi.observacao IS NULL OR TRIM(pi.observacao) <> 'Finalização Parcial')
                                            ) THEN 1 ELSE 0
                                        END
                                    )
                                    ELSE (
                                        CASE
                                            WHEN fi.data_pagamento IS NOT NULL
                                                AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                                                AND fi.data_pagamento <= ?
                                            THEN 1 ELSE 0
                                        END
                                    )
                                END
                            )
                            ELSE (
                                CASE
                                    WHEN EXISTS (
                                        SELECT 1
                                        FROM pagamento_itens pi
                                        WHERE pi.origem = 'funcao_imagem'
                                            AND pi.origem_id = fi.idfuncao_imagem
                                    ) THEN (
                                        CASE
                                            WHEN EXISTS (
                                                SELECT 1
                                                FROM pagamento_itens pi
                                                WHERE pi.origem = 'funcao_imagem'
                                                    AND pi.origem_id = fi.idfuncao_imagem
                                                    AND DATE(pi.criado_em) <= ?
                                            ) THEN 1 ELSE 0
                                        END
                                    )
                                    ELSE (
                                        CASE
                                            WHEN fi.data_pagamento IS NOT NULL
                                                AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
                                                AND fi.data_pagamento <= ?
                                            THEN 1 ELSE 0
                                        END
                                    )
                                END
                            )
                        END AS pagamento
                    FROM funcao_imagem fi
                    JOIN funcao f ON f.idfuncao = fi.funcao_id
                    LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
                    LEFT JOIN (
                        SELECT h1.imagem_id, h1.status_id
                        FROM historico_imagens h1
                        INNER JOIN (
                            SELECT imagem_id, MAX(data_movimento) AS max_data
                            FROM historico_imagens
                            WHERE data_movimento <= ?
                            GROUP BY imagem_id
                        ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
                    ) hi_snap ON hi_snap.imagem_id = i.idimagens_cliente_obra
                    WHERE (
                        EXISTS (
                            SELECT 1
                            FROM log_alteracoes la
                            WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                                AND MONTH(la.data) = ?
                                AND YEAR(la.data) = ?
                                AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                        )
                        OR (
                            MONTH(fi.prazo) = ?
                            AND YEAR(fi.prazo) = ?
                            AND LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                        )
                    )
                    AND (
                        LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                        OR EXISTS (
                            SELECT 1
                            FROM log_alteracoes la_fin
                            WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
                                AND la_fin.data <= ?
                                AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                        )
                    )
                    AND fi.colaborador_id NOT IN (21, 15)
                    AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
                    AND NOT (
                        fi.funcao_id = 4
                        AND LOWER(TRIM(i.tipo_imagem)) != 'planta humanizada'
                        AND (
                            EXISTS (
                                SELECT 1
                                FROM historico_imagens hi_p
                                WHERE hi_p.imagem_id = fi.imagem_id
                                  AND hi_p.status_id = 1
                                  AND hi_p.data_movimento = (
                                      SELECT MAX(hm.data_movimento)
                                      FROM historico_imagens hm
                                      WHERE hm.imagem_id = fi.imagem_id
                                        AND hm.data_movimento <= ?
                                  )
                            )
                            OR (
                                NOT EXISTS (
                                    SELECT 1
                                    FROM historico_imagens h_any
                                    WHERE h_any.imagem_id = fi.imagem_id
                                      AND h_any.data_movimento <= ?
                                )
                                AND (
                                    i.status_id = 1
                                    OR EXISTS (
                                        SELECT 1
                                        FROM funcao_imagem fi_sub
                                        JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                                        WHERE fi_sub.imagem_id = fi.imagem_id
                                          AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
                                    )
                                )
                            )
                        )
                    )
                ) AS t
                WHERE t.funcao_id = 4
                    AND t.nome_funcao = 'Finalização Completa'
                GROUP BY t.colaborador_id
        ";

    $stmtProducao = $conn->prepare($sqlProducao);
    if (!$stmtProducao) {
        throw new Exception('Erro ao preparar consulta de producao: ' . $conn->error);
    }
    $stmtProducao->bind_param(
        'sssssssssiiiisss',
        $fimMesData,
        $fimMesData,
        $fimMesData,
        $fimMesData,
        $fimMesData,
        $fimMesData,
        $fimMesData,
        $fimMesData,
        $fimMesDataTime,
        $mes,
        $ano,
        $mes,
        $ano,
        $fimMesDataTime
        ,$fimMesDataTime
        ,$fimMesDataTime
    );
    $stmtProducao->execute();

    $producaoMap = [];
    $resProducao = $stmtProducao->get_result();
    while ($row = $resProducao->fetch_assoc()) {
        $producaoMap[(int) $row['colaborador_id']] = (int) $row['quantidade_feita'];
    }
    $stmtProducao->close();

    // 3) Meta individual por colaborador
    $sqlMetaIndividual = "
        SELECT colaborador_id, meta_tarefas
        FROM meta_colaborador
        WHERE funcao_id = 4
          AND mes = ?
          AND ano = ?
    ";

    $stmtMetaIndividual = $conn->prepare($sqlMetaIndividual);
    if (!$stmtMetaIndividual) {
        throw new Exception('Erro ao preparar consulta de meta individual: ' . $conn->error);
    }
    $stmtMetaIndividual->bind_param('ii', $mes, $ano);
    $stmtMetaIndividual->execute();

    $metaIndividualMap = [];
    $resMetaIndividual = $stmtMetaIndividual->get_result();
    while ($row = $resMetaIndividual->fetch_assoc()) {
        $metaIndividualMap[(int) $row['colaborador_id']] = (int) $row['meta_tarefas'];
    }
    $stmtMetaIndividual->close();

    // 4) Meta mensal da funcao
    $sqlMetaFuncao = "
        SELECT COALESCE(SUM(quantidade_meta), 0) AS meta_funcao
        FROM metas
        WHERE funcao_id = 4
          AND mes = ?
          AND ano = ?
    ";

    $stmtMetaFuncao = $conn->prepare($sqlMetaFuncao);
    if (!$stmtMetaFuncao) {
        throw new Exception('Erro ao preparar consulta de meta da funcao: ' . $conn->error);
    }
    $stmtMetaFuncao->bind_param('ii', $mes, $ano);
    $stmtMetaFuncao->execute();

    $metaFuncao = 0;
    $resMetaFuncao = $stmtMetaFuncao->get_result();
    if ($rowMeta = $resMetaFuncao->fetch_assoc()) {
        $metaFuncao = (int) ($rowMeta['meta_funcao'] ?? 0);
    }
    $stmtMetaFuncao->close();

    // 5) Montagem da resposta
    $totalProduzido = 0;

    foreach ($colaboradoresBase as $colaboradorId => $nomeColaborador) {
        $quantidadeFeita = $producaoMap[$colaboradorId] ?? 0;
        $metaIndividual = $metaIndividualMap[$colaboradorId] ?? 0;
        $saldo = $quantidadeFeita - $metaIndividual;

        $totalProduzido += $quantidadeFeita;

        $colaboradores[] = [
            'colaborador_id' => $colaboradorId,
            'nome_colaborador' => $nomeColaborador,
            'quantidade_feita' => $quantidadeFeita,
            'meta_individual' => $metaIndividual,
            'saldo' => $saldo,
        ];
    }

    echo json_encode([
        'colaboradores' => $colaboradores,
        'total_produzido' => $totalProduzido,
        'meta_funcao' => $metaFuncao,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
