<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
header('Content-Type: application/json');

include '../../conexao.php';

// --- Auth ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$nivelAcesso   = intval($_SESSION['nivel_acesso'] ?? 0);
$isGestor      = in_array($nivelAcesso, [1, 5]);
$colaboradorId = intval($_SESSION['idcolaborador'] ?? 0);

// Gestores podem consultar qualquer colaborador via GET param
if ($isGestor && isset($_GET['colaborador_id']) && intval($_GET['colaborador_id']) > 0) {
    $colaboradorId = intval($_GET['colaborador_id']);
}

if ($colaboradorId === 0) {
    echo json_encode(['error' => 'Colaborador não identificado na sessão']);
    exit();
}

// --- Parâmetros de mês/ano ---
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : (int) date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : (int) date('Y');

if ($mes < 1 || $mes > 12 || $ano < 2020) {
    $mes = (int) date('m');
    $ano = (int) date('Y');
}

// Snapshot histórico: status da imagem no fim do mês consultado (igual a Pagamento/getColaborador.php)
$fimMesDia      = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
$fimMesDataTime = sprintf('%04d-%02d-%02d 23:59:59', $ano, $mes, $fimMesDia);
$snapJoin = "LEFT JOIN (
    SELECT h1.imagem_id, h1.status_id
    FROM historico_imagens h1
    INNER JOIN (
        SELECT imagem_id, MAX(data_movimento) AS max_data
        FROM historico_imagens
        WHERE data_movimento <= ?
        GROUP BY imagem_id
    ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra";
$snapStatusCond = "hi_snap.status_id = 1";

$response = [];

// ======================================================
// HELPER: month filter clause (reused in all queries)
// Uses: fi.colaborador_id, fi.prazo, fi.idfuncao_imagem
// Bind order per use: colabId, ano, mes, mes, ano
// ======================================================
$monthFilterWhere = "
    fi.colaborador_id = ?
    AND fi.status IN ('Finalizado','Em aprovação','Ajuste','Aprovado com ajustes','Aprovado')
    AND (
        (YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?)
        OR EXISTS (
            SELECT 1 FROM log_alteracoes la_mf
            WHERE la_mf.funcao_imagem_id = fi.idfuncao_imagem
              AND MONTH(la_mf.data) = ?
              AND YEAR(la_mf.data)  = ?
              AND LOWER(TRIM(la_mf.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
        )
    )
";

// ===========================================
// 1. KPIs
// ===========================================

// Condição de "não pago" (inclui Finalização Completa com pago parcial pendente)
$naoPagaCond = "
    (
        fi.pagamento = 0
        OR (
            fi.funcao_id = 4
            AND fi.pagamento = 1
            AND (SELECT COUNT(1) FROM pagamento_itens pi_np
                 JOIN funcao_imagem fi_np ON pi_np.origem = 'funcao_imagem' AND pi_np.origem_id = fi_np.idfuncao_imagem
                 WHERE fi_np.imagem_id = fi.imagem_id AND fi_np.funcao_id = 4 AND pi_np.observacao = 'Finalização Parcial') > 0
            AND (SELECT COUNT(1) FROM pagamento_itens pi_nc
                 JOIN funcao_imagem fi_nc ON pi_nc.origem = 'funcao_imagem' AND pi_nc.origem_id = fi_nc.idfuncao_imagem
                 WHERE fi_nc.imagem_id = fi.imagem_id AND fi_nc.funcao_id = 4 AND pi_nc.observacao = 'Pago Completa') = 0
        )
    )
";

// -- 1a. Total de tarefas novas no mês (não pagas ou finalização parcialmente paga)
$sqlNovas = "
    SELECT COUNT(DISTINCT fi.idfuncao_imagem) AS total
    FROM funcao_imagem fi
    WHERE {$monthFilterWhere}
      AND {$naoPagaCond}
";
$stmtN = $conn->prepare($sqlNovas);
$stmtN->bind_param('iiiii', $colaboradorId, $ano, $mes, $mes, $ano);
$stmtN->execute();
$totalNovas = $stmtN->get_result()->fetch_assoc()['total'] ?? 0;
$stmtN->close();

// -- 1b. Valor a receber (inclui Finalização Completa pago parcial)
$sqlValor = "
    SELECT COALESCE(SUM(
        CASE
            -- Finalização Parcial (imagem ainda não aprovada / tem Pós-Finalização) -> 50%
            WHEN fi.funcao_id = 4 AND (
                EXISTS (
                    SELECT 1 FROM funcao_imagem fi_sv
                    JOIN funcao f_sv ON fi_sv.funcao_id = f_sv.idfuncao
                    WHERE fi_sv.imagem_id = fi.imagem_id AND f_sv.nome_funcao = 'Pré-Finalização'
                ) OR {$snapStatusCond}
            ) THEN fi.valor * 0.5
            -- Finalização paga parcialmente (50% já pago, 50% restante)
            WHEN fi.funcao_id = 4 AND fi.pagamento = 1 AND (
                SELECT COUNT(1) FROM pagamento_itens pi_np
                JOIN funcao_imagem fi_np ON pi_np.origem = 'funcao_imagem' AND pi_np.origem_id = fi_np.idfuncao_imagem
                WHERE fi_np.imagem_id = fi.imagem_id AND fi_np.funcao_id = 4 AND pi_np.observacao = 'Finalização Parcial'
            ) > 0 AND (
                SELECT COUNT(1) FROM pagamento_itens pi_nc
                JOIN funcao_imagem fi_nc ON pi_nc.origem = 'funcao_imagem' AND pi_nc.origem_id = fi_nc.idfuncao_imagem
                WHERE fi_nc.imagem_id = fi.imagem_id AND fi_nc.funcao_id = 4 AND pi_nc.observacao = 'Pago Completa'
            ) = 0 THEN fi.valor * 0.5
            ELSE fi.valor
        END
    ), 0) AS valor
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
    {$snapJoin}
    WHERE {$monthFilterWhere}
      AND {$naoPagaCond}
";
$stmtV = $conn->prepare($sqlValor);
$stmtV->bind_param('siiiii', $fimMesDataTime, $colaboradorId, $ano, $mes, $mes, $ano);
$stmtV->execute();
$valorAReceber = floatval($stmtV->get_result()->fetch_assoc()['valor'] ?? 0);
$stmtV->close();

// -- 1d. Média de ajustes por tarefa no mês
$sqlAjustes = "
    SELECT ROUND(AVG(qty), 1) AS media
    FROM (
        SELECT fi.idfuncao_imagem,
               COALESCE(COUNT(la_aj.idlog), 0) AS qty
        FROM funcao_imagem fi
        LEFT JOIN log_alteracoes la_aj
               ON la_aj.funcao_imagem_id = fi.idfuncao_imagem
              AND LOWER(TRIM(la_aj.status_novo)) = 'ajuste'
        WHERE {$monthFilterWhere}
        GROUP BY fi.idfuncao_imagem
    ) t
";
$stmtA = $conn->prepare($sqlAjustes);
$stmtA->bind_param('iiiii', $colaboradorId, $ano, $mes, $mes, $ano);
$stmtA->execute();
$mediaAjustes = floatval($stmtA->get_result()->fetch_assoc()['media'] ?? 0);
$stmtA->close();

$mesesPtKpi = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$response['kpis'] = [
    'total_novas'       => intval($totalNovas),
    'valor_a_receber'   => $valorAReceber,
    'media_ajustes'     => $mediaAjustes,
    'mes_label'         => ($mesesPtKpi[$mes] ?? '') . ' ' . $ano,
];

// ===========================================
// 2. Desempenho por etapa
// ===========================================
// Tempo médio = da entrada em 'Em andamento' até o próximo status de encerramento
$sqlEtapas = "
    SELECT
        sub.nome_funcao,
        COUNT(DISTINCT sub.idfuncao_imagem) AS total,
        ROUND(
            AVG(CASE WHEN sub.horas IS NOT NULL AND sub.horas > 0 THEN sub.horas ELSE NULL END), 1
        ) AS tempo_medio_horas
    FROM (
        SELECT
            fi.idfuncao_imagem,
            fi.funcao_id,
            CASE WHEN fi.funcao_id = 4 THEN
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM funcao_imagem fi_sub
                        JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                        WHERE fi_sub.imagem_id = fi.imagem_id AND f_sub.nome_funcao = 'Pré-Finalização'
                    ) OR {$snapStatusCond} THEN 'Finalização Parcial'
                    ELSE 'Finalização Completa'
                END
            ELSE f.nome_funcao END AS nome_funcao,
            tempos.horas
        FROM funcao_imagem fi
        JOIN funcao f ON f.idfuncao = fi.funcao_id
        JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
        {$snapJoin}
        LEFT JOIN (
            SELECT la1.funcao_imagem_id,
                   TIMESTAMPDIFF(HOUR, la1.data, MIN(la2.data)) AS horas
            FROM log_alteracoes la1
            JOIN log_alteracoes la2
                 ON  la2.funcao_imagem_id = la1.funcao_imagem_id
                 AND la2.data > la1.data
                 AND LOWER(TRIM(la2.status_novo)) IN ('finalizado','em aprovação','ajuste')
            WHERE LOWER(TRIM(la1.status_novo)) = 'em andamento'
            GROUP BY la1.funcao_imagem_id, la1.data
        ) tempos ON tempos.funcao_imagem_id = fi.idfuncao_imagem
        WHERE {$monthFilterWhere}
    ) sub
    GROUP BY sub.nome_funcao
    ORDER BY total DESC
";
$stmtE = $conn->prepare($sqlEtapas);
$stmtE->bind_param('siiiii', $fimMesDataTime, $colaboradorId, $ano, $mes, $mes, $ano);
$stmtE->execute();
$response['por_etapa'] = $stmtE->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtE->close();

// ===========================================
// 3. Lista detalhada de tarefas do mês
// ===========================================
$sqlTarefas = "
    SELECT
        fi.idfuncao_imagem                          AS id,
        ico.imagem_nome,
        o.nome_obra,
        o.nomenclatura,
        CASE WHEN fi.funcao_id = 4 THEN
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM funcao_imagem fi_sub
                    JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                    WHERE fi_sub.imagem_id = fi.imagem_id
                      AND f_sub.nome_funcao = 'Pré-Finalização'
                ) OR {$snapStatusCond} THEN 'Finalização Parcial'
                ELSE 'Finalização Completa'
            END
        ELSE f.nome_funcao END                       AS nome_funcao,
        fi.funcao_id,
        fi.status,
        CASE
            WHEN fi.funcao_id = 4 AND (
                EXISTS (
                    SELECT 1 FROM funcao_imagem fi_sv
                    JOIN funcao f_sv ON fi_sv.funcao_id = f_sv.idfuncao
                    WHERE fi_sv.imagem_id = fi.imagem_id AND f_sv.nome_funcao = 'Pré-Finalização'
                ) OR {$snapStatusCond}
            ) THEN fi.valor * 0.5
            WHEN fi.funcao_id = 4 AND fi.pagamento = 1 AND (
                SELECT COUNT(1) FROM pagamento_itens pi_np
                JOIN funcao_imagem fi_np ON pi_np.origem = 'funcao_imagem' AND pi_np.origem_id = fi_np.idfuncao_imagem
                WHERE fi_np.imagem_id = fi.imagem_id AND fi_np.funcao_id = 4 AND pi_np.observacao = 'Finalização Parcial'
            ) > 0 AND (
                SELECT COUNT(1) FROM pagamento_itens pi_nc
                JOIN funcao_imagem fi_nc ON pi_nc.origem = 'funcao_imagem' AND pi_nc.origem_id = fi_nc.idfuncao_imagem
                WHERE fi_nc.imagem_id = fi.imagem_id AND fi_nc.funcao_id = 4 AND pi_nc.observacao = 'Pago Completa'
            ) = 0 THEN fi.valor * 0.5
            ELSE fi.valor
        END                                          AS valor,
        fi.pagamento,
        fi.prazo,
        COALESCE(aj.qtd_ajustes, 0)                 AS qtd_ajustes,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1) FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Finalização Parcial'
        ) ELSE 0 END                                AS pago_parcial_count,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT COUNT(1) FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4 AND pi.observacao = 'Pago Completa'
        ) ELSE 0 END                                AS pago_completa_count,
        CASE WHEN fi.funcao_id = 4 THEN (
            SELECT GROUP_CONCAT(
                CONCAT(
                    CASE TRIM(pi.observacao)
                        WHEN 'Finalização Parcial' THEN 'Finalização Parcial'
                        WHEN 'Pago Completa' THEN 'Finalização Completa'
                        ELSE pi.observacao
                    END,
                    '|',
                    DATE_FORMAT(pi.criado_em, '%d/%m/%Y')
                )
                ORDER BY pi.criado_em ASC
                SEPARATOR ';'
            )
            FROM pagamento_itens pi
            JOIN funcao_imagem fi_pi ON pi.origem = 'funcao_imagem' AND pi.origem_id = fi_pi.idfuncao_imagem
            WHERE fi_pi.imagem_id = fi.imagem_id AND fi_pi.funcao_id = 4
              AND pi.criado_em IS NOT NULL
        ) ELSE (
            CASE WHEN fi.pagamento = 1 AND fi.data_pagamento IS NOT NULL
                 THEN CONCAT(f.nome_funcao, '|', DATE_FORMAT(fi.data_pagamento, '%d/%m/%Y'))
                 ELSE NULL END
        ) END                                       AS pagamentos_info
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra ico
         ON ico.idimagens_cliente_obra = fi.imagem_id
    JOIN obra o
         ON o.idobra = ico.obra_id
    JOIN funcao f
         ON f.idfuncao = fi.funcao_id
    LEFT JOIN (
        SELECT funcao_imagem_id, COUNT(*) AS qtd_ajustes
        FROM log_alteracoes
        WHERE LOWER(TRIM(status_novo)) = 'ajuste'
        GROUP BY funcao_imagem_id
    ) aj ON aj.funcao_imagem_id = fi.idfuncao_imagem
    {$snapJoin}
    WHERE {$monthFilterWhere}
    ORDER BY fi.prazo DESC, fi.idfuncao_imagem DESC
";
$stmtT = $conn->prepare($sqlTarefas);
$stmtT->bind_param('siiiii', $fimMesDataTime, $colaboradorId, $ano, $mes, $mes, $ano);
$stmtT->execute();
$response['tarefas'] = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtT->close();

// ===========================================
// 4. Seletor de meses disponíveis
// ===========================================
$sqlMeses = "
    SELECT DISTINCT
        YEAR(fi.prazo)            AS ano,
        MONTH(fi.prazo)           AS mes,
        DATE_FORMAT(fi.prazo, '%Y-%m') AS valor
    FROM funcao_imagem fi
    WHERE fi.colaborador_id = ?
      AND fi.prazo IS NOT NULL
      AND fi.status IN ('Finalizado','Em aprovação','Ajuste','Aprovado com ajustes','Aprovado')
    ORDER BY ano DESC, mes DESC
    LIMIT 12
";
$stmtM = $conn->prepare($sqlMeses);
$stmtM->bind_param('i', $colaboradorId);
$stmtM->execute();
$mesesResult = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtM->close();

// Adiciona o mês atual se ainda não aparece (pode não ter prazo este mês mas ter logs)
$mesAtualStr = sprintf('%04d-%02d', $ano, $mes);
$jaTem = array_filter($mesesResult, fn($m) => $m['valor'] === $mesAtualStr);
if (empty($jaTem)) {
    array_unshift($mesesResult, [
        'ano'   => $ano,
        'mes'   => $mes,
        'valor' => $mesAtualStr,
    ]);
}

// Anexa rótulo legível
$mesesPt = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
foreach ($mesesResult as &$m) {
    $m['label'] = $mesesPt[intval($m['mes'])] . ' ' . $m['ano'];
}
unset($m);

$response['meses_disponiveis'] = $mesesResult;
$response['mes_selecionado']   = ['mes' => $mes, 'ano' => $ano];

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
