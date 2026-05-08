<?php

/**
 * AdminMetas/backend/carregar_dados.php
 *
 * Retorna dados completos para a tela de administração de metas por colaborador.
 *
 * GET  ?mes=5&ano=2026
 *
 * Response: {
 *   success: true,
 *   mes, ano, mes_ant, ano_ant,
 *   funcoes: [
 *     {
 *       funcao_id, nome_funcao, cor,
 *       total_parcial, total_anterior, recorde_equipe,
 *       colaboradores: [
 *         { colaborador_id, nome, parcial, mes_anterior, recorde, meta }
 *       ]
 *     }
 *   ]
 * }
 */

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/session_bootstrap.php';

    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }

    $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

    if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetros inválidos']);
        exit;
    }

    include __DIR__ . '/../../conexao.php';

    if (!$conn || $conn->connect_error) {
        throw new Exception('Falha na conexão: ' . ($conn->connect_error ?? 'conexão nula'));
    }

    // Funções exibidas — Pré-Finalização (ID 9) excluída
    $funcoesConfig = [
        1 => ['nome' => 'Caderno',                           'cor' => '#38bdf8'],
        8 => ['nome' => 'Filtro de assets',                  'cor' => '#a78bfa'],
        2 => ['nome' => 'Modelagem',                         'cor' => '#fb923c'],
        3 => ['nome' => 'Composição',                        'cor' => '#34d399'],
        4 => ['nome' => 'Finalização Completa',              'cor' => '#4ade80'],
        7 => ['nome' => 'Finalização de Planta Humanizada',  'cor' => '#2dd4bf'],
        5 => ['nome' => 'Pós-produção',                      'cor' => '#c084fc'],
    ];

    $funcaoIds = implode(',', array_map('intval', array_keys($funcoesConfig)));

    // Expressão SQL que converte funcao_id=4 + planta humanizada → effective funcao_id=7.
    // Usada em todas as queries para separar "Finalização de Planta Humanizada" de "Finalização Completa".
    $EFF_FUNCAO = "CASE WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 7 ELSE fi.funcao_id END";

    // Condição SQL: item funcao_id=4 era "Finalização Parcial" no período (p.yr, p.mo).
    // Espelha hi_snap de buscar_producao_funcao.php:
    //   1. Prioridade: historico_imagens no fim do período com status_id=1.
    //   2. Fallback (sem historico naquele período): ico.status_id=1 OU existe função pré-finalização.
    // Planta humanizada é excluída — sempre conta como Finalização de Planta Humanizada.
    $IS_PARCIAL_AT_PERIOD = "
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
    ";

    // ── Datas auxiliares — mês atual ──────────────────────────────────────────
    $fimMesDia      = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $fimMesData     = sprintf('%04d-%02d-%02d', $ano, $mes, $fimMesDia);
    $fimMesDataTime = $fimMesData . ' 23:59:59';

    // ── Mês anterior ──────────────────────────────────────────────────────────
    $mesAnt    = ($mes === 1) ? 12 : $mes - 1;
    $anoAnt    = ($mes === 1) ? $ano - 1 : $ano;
    $fimAntDia      = cal_days_in_month(CAL_GREGORIAN, $mesAnt, $anoAnt);
    $fimAntData     = sprintf('%04d-%02d-%02d', $anoAnt, $mesAnt, $fimAntDia);
    $fimAntDataTime = $fimAntData . ' 23:59:59';

    // Colaboradores excluídos globalmente (mesmo critério de buscar_producao.php)
    // IDs 21, 15 excluídos de toda a produção; funcao_id=4 exclui adicionalmente 7 e 34.
    $WHERE_STATUS = "
        (
            LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            OR EXISTS (
                SELECT 1 FROM log_alteracoes la_fin
                WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
                  AND la_fin.data <= ?
                  AND (
                      LOWER(TRIM(la_fin.status_novo))     IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                      OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                  )
            )
        )
        AND fi.colaborador_id NOT IN (21, 15)
        AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
        AND NOT (fi.funcao_id = 4 AND ico.status_id = 1)
    ";

    // ── 1. Colaboradores por função ───────────────────────────────────────────
    // funcao_id=4 + planta humanizada → effective funcao_id=7 via $EFF_FUNCAO
    $sqlColabs = "
        SELECT DISTINCT fi.colaborador_id,
            $EFF_FUNCAO AS funcao_id,
            c.nome_colaborador
        FROM funcao_imagem fi
        LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
        WHERE fi.funcao_id IN ($funcaoIds)
          AND fi.colaborador_id IS NOT NULL
          AND c.ativo = 1
          AND fi.prazo >= DATE_SUB(NOW(), INTERVAL 18 MONTH)
          AND fi.colaborador_id NOT IN (21, 15)
          AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
          AND NOT (fi.funcao_id = 4 AND ico.status_id = 1)
        UNION
        SELECT DISTINCT mc.colaborador_id, mc.funcao_id, c.nome_colaborador
        FROM meta_colaborador mc
        JOIN colaborador c ON c.idcolaborador = mc.colaborador_id
        WHERE mc.funcao_id IN ($funcaoIds)
          AND mc.mes = ? AND mc.ano = ?
          AND c.ativo = 1
        ORDER BY nome_colaborador ASC
    ";

    $stmtC = $conn->prepare($sqlColabs);
    $stmtC->bind_param('ii', $mes, $ano);
    $stmtC->execute();

    $colabsByFuncao = []; // [funcao_id][colaborador_id] = nome
    foreach ($stmtC->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $colabsByFuncao[(int) $r['funcao_id']][(int) $r['colaborador_id']] = $r['nome_colaborador'];
    }
    $stmtC->close();

    // ── Fragmento SQL de filtro por mês ───────────────────────────────────────
    // Idêntico ao usado em buscar_producao.php: log_alteracoes OU fi.prazo
    $WHERE_MES = "
        (
            EXISTS (
                SELECT 1 FROM log_alteracoes la
                WHERE la.funcao_imagem_id = fi.idfuncao_imagem
                  AND MONTH(la.data) = ? AND YEAR(la.data) = ?
            )
            OR (MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?)
        )
    ";

    // ── 2. Produção do mês atual por colaborador+funcao ───────────────────────
    // LEFT JOIN ico para calcular eff_funcao_id (planta humanizada → 7)
    $sqlCurr = "
        SELECT fi.colaborador_id,
            $EFF_FUNCAO AS funcao_id,
            COUNT(DISTINCT fi.idfuncao_imagem) AS qtd
        FROM funcao_imagem fi
        LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        WHERE fi.funcao_id IN ($funcaoIds)
          AND fi.colaborador_id IS NOT NULL
          AND $WHERE_MES
          AND $WHERE_STATUS
        GROUP BY fi.colaborador_id,
                 $EFF_FUNCAO
    ";

    $stmtCurr = $conn->prepare($sqlCurr);
    $stmtCurr->bind_param('iiiis', $mes, $ano, $mes, $ano, $fimMesDataTime);
    $stmtCurr->execute();

    $currMap = []; // [funcao_id][colaborador_id] = qtd
    foreach ($stmtCurr->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $currMap[(int) $r['funcao_id']][(int) $r['colaborador_id']] = (int) $r['qtd'];
    }
    $stmtCurr->close();

    // ── 3. Produção do mês anterior por colaborador+funcao ────────────────────
    $sqlPrev = "
        SELECT fi.colaborador_id,
            $EFF_FUNCAO AS funcao_id,
            COUNT(DISTINCT fi.idfuncao_imagem) AS qtd
        FROM funcao_imagem fi
        LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        WHERE fi.funcao_id IN ($funcaoIds)
          AND fi.colaborador_id IS NOT NULL
          AND $WHERE_MES
          AND $WHERE_STATUS
        GROUP BY fi.colaborador_id,
                 $EFF_FUNCAO
    ";

    $stmtPrev = $conn->prepare($sqlPrev);
    $stmtPrev->bind_param('iiiis', $mesAnt, $anoAnt, $mesAnt, $anoAnt, $fimAntDataTime);
    $stmtPrev->execute();

    $prevMap = []; // [funcao_id][colaborador_id] = qtd
    foreach ($stmtPrev->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $prevMap[(int) $r['funcao_id']][(int) $r['colaborador_id']] = (int) $r['qtd'];
    }
    $stmtPrev->close();

    // ── 4. Recorde histórico individual por colaborador+funcao ────────────────
    // Períodos via UNION(log_alteracoes, fi.prazo) — mesma lógica da contagem mensal.
    // LEFT JOIN ico + $EFF_FUNCAO: funcao_id=4 acumula apenas Finalização Completa;
    // planta humanizada vai para funcao_id=7.
    // Retorna o período (YYYY-MM) do mês em que o recorde foi atingido.
    $sqlRec = "
        SELECT colaborador_id, funcao_id,
               MAX(qtd_mes) AS recorde,
               SUBSTRING_INDEX(
                   GROUP_CONCAT(CONCAT(yr, '-', LPAD(mo, 2, '0')) ORDER BY qtd_mes DESC, yr DESC, mo DESC SEPARATOR ','),
                   ',', 1
               ) AS recorde_periodo
        FROM (
            SELECT
                fi.colaborador_id,
                $EFF_FUNCAO AS funcao_id,
                p.yr,
                p.mo,
                COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
            FROM funcao_imagem fi
            LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
            INNER JOIN (
                SELECT funcao_imagem_id, YEAR(data) AS yr, MONTH(data) AS mo
                FROM log_alteracoes
                WHERE data >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                GROUP BY funcao_imagem_id, YEAR(data), MONTH(data)
                UNION
                SELECT idfuncao_imagem AS funcao_imagem_id, YEAR(prazo) AS yr, MONTH(prazo) AS mo
                FROM funcao_imagem
                WHERE prazo IS NOT NULL
                  AND prazo >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                GROUP BY idfuncao_imagem, YEAR(prazo), MONTH(prazo)
            ) p ON p.funcao_imagem_id = fi.idfuncao_imagem
            WHERE fi.funcao_id IN ($funcaoIds)
              AND fi.colaborador_id IS NOT NULL
              AND (
                  LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                  OR EXISTS (
                      SELECT 1 FROM log_alteracoes la_fin
                      WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
                        AND la_fin.data <= CONCAT(
                            LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01'))),
                            ' 23:59:59'
                        )
                        AND (
                            LOWER(TRIM(la_fin.status_novo))     IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                            OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                        )
                  )
              )
              AND fi.colaborador_id NOT IN (21, 15)
              AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
              AND NOT ($IS_PARCIAL_AT_PERIOD)
              AND NOT (p.yr = ? AND p.mo = ?)
              AND NOT (p.yr = 2024 AND p.mo = 10)
            GROUP BY fi.colaborador_id, $EFF_FUNCAO, p.yr, p.mo
        ) sub
        GROUP BY colaborador_id, funcao_id
    ";

    $stmtRec = $conn->prepare($sqlRec);
    $stmtRec->bind_param('ii', $ano, $mes);
    $stmtRec->execute();

    $recMap = []; // [funcao_id][colaborador_id] = ['recorde' => N, 'periodo' => 'YYYY-MM']
    foreach ($stmtRec->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $recMap[(int) $r['funcao_id']][(int) $r['colaborador_id']] = [
            'recorde' => (int) $r['recorde'],
            'periodo' => $r['recorde_periodo'] ?? null,
        ];
    }
    $stmtRec->close();

    // ── 5. Recorde histórico por equipe (por funcao) ──────────────────────────
    // Mesma lógica do Q4 com $EFF_FUNCAO; retorna também o período do recorde.
    $sqlRecEq = "
        SELECT funcao_id,
               MAX(total_mes) AS recorde_equipe,
               SUBSTRING_INDEX(
                   GROUP_CONCAT(CONCAT(yr, '-', LPAD(mo, 2, '0')) ORDER BY total_mes DESC, yr DESC, mo DESC SEPARATOR ','),
                   ',', 1
               ) AS recorde_periodo
        FROM (
            SELECT
                $EFF_FUNCAO AS funcao_id,
                p.yr,
                p.mo,
                COUNT(DISTINCT fi.idfuncao_imagem) AS total_mes
            FROM funcao_imagem fi
            LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
            INNER JOIN (
                SELECT funcao_imagem_id, YEAR(data) AS yr, MONTH(data) AS mo
                FROM log_alteracoes
                WHERE data >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                GROUP BY funcao_imagem_id, YEAR(data), MONTH(data)
                UNION
                SELECT idfuncao_imagem AS funcao_imagem_id, YEAR(prazo) AS yr, MONTH(prazo) AS mo
                FROM funcao_imagem
                WHERE prazo IS NOT NULL
                  AND prazo >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
                GROUP BY idfuncao_imagem, YEAR(prazo), MONTH(prazo)
            ) p ON p.funcao_imagem_id = fi.idfuncao_imagem
            WHERE fi.funcao_id IN ($funcaoIds)
              AND fi.colaborador_id IS NOT NULL
              AND (
                  LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                  OR EXISTS (
                      SELECT 1 FROM log_alteracoes la_fin
                      WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
                        AND la_fin.data <= CONCAT(
                            LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo, 2, '0'), '-01'))),
                            ' 23:59:59'
                        )
                        AND (
                            LOWER(TRIM(la_fin.status_novo))     IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                            OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                        )
                  )
              )
              AND fi.colaborador_id NOT IN (21, 15)
              AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
              AND NOT ($IS_PARCIAL_AT_PERIOD)
              AND NOT (p.yr = ? AND p.mo = ?)
              AND NOT (p.yr = 2024 AND p.mo = 10)
            GROUP BY $EFF_FUNCAO, p.yr, p.mo
        ) sub
        GROUP BY funcao_id
    ";

    $stmtRecEq = $conn->prepare($sqlRecEq);
    $stmtRecEq->bind_param('ii', $ano, $mes);
    $stmtRecEq->execute();

    $recEquipeMap = []; // [funcao_id] = ['recorde' => N, 'periodo' => 'YYYY-MM']
    foreach ($stmtRecEq->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $recEquipeMap[(int) $r['funcao_id']] = [
            'recorde'  => (int) $r['recorde_equipe'],
            'periodo'  => $r['recorde_periodo'] ?? null,
        ];
    }
    $stmtRecEq->close();

    // ── 6. Metas cadastradas no mês/ano selecionado ───────────────────────────
    $sqlMetas = "
        SELECT colaborador_id, funcao_id, meta_tarefas
        FROM meta_colaborador
        WHERE mes = ? AND ano = ? AND funcao_id IN ($funcaoIds)
    ";

    $stmtMetas = $conn->prepare($sqlMetas);
    $stmtMetas->bind_param('ii', $mes, $ano);
    $stmtMetas->execute();

    $metasMap = []; // [funcao_id][colaborador_id] = meta_tarefas
    foreach ($stmtMetas->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $metasMap[(int) $r['funcao_id']][(int) $r['colaborador_id']] = (int) $r['meta_tarefas'];
    }
    $stmtMetas->close();

    $conn->close();

    // ── 7. Montar resposta ────────────────────────────────────────────────────
    $funcoes = [];

    foreach ($funcoesConfig as $funcaoId => $cfg) {
        $colabs = $colabsByFuncao[$funcaoId] ?? [];

        $totalParcial  = 0;
        $totalAnterior = 0;
        $colaboradoresList = [];

        foreach ($colabs as $colaboradorId => $nomeColab) {
            $parcial  = $currMap[$funcaoId][$colaboradorId] ?? 0;
            $anterior = $prevMap[$funcaoId][$colaboradorId] ?? 0;

            $recEntry  = $recMap[$funcaoId][$colaboradorId] ?? null;
            $recordeDb = $recEntry['recorde'] ?? 0;
            if ($recordeDb >= $anterior) {
                $recorde    = $recordeDb;
                $recordeMes = $recEntry['periodo'] ?? null;
            } else {
                $recorde    = $anterior;
                $recordeMes = sprintf('%04d-%02d', $anoAnt, $mesAnt);
            }

            $meta = isset($metasMap[$funcaoId][$colaboradorId])
                ? $metasMap[$funcaoId][$colaboradorId]
                : null;

            $totalParcial  += $parcial;
            $totalAnterior += $anterior;

            $colaboradoresList[] = [
                'colaborador_id' => $colaboradorId,
                'nome'           => $nomeColab,
                'parcial'        => $parcial,
                'mes_anterior'   => $anterior,
                'recorde'        => $recorde,
                'recorde_mes'    => $recordeMes,
                'meta'           => $meta,
            ];
        }

        // Recorde equipe = max(melhor mês histórico, mês anterior)
        $recEqEntry   = $recEquipeMap[$funcaoId] ?? null;
        $recEquipeDb  = $recEqEntry['recorde'] ?? 0;
        if ($recEquipeDb >= $totalAnterior) {
            $recEquipe    = $recEquipeDb;
            $recEquipeMes = $recEqEntry['periodo'] ?? null;
        } else {
            $recEquipe    = $totalAnterior;
            $recEquipeMes = sprintf('%04d-%02d', $anoAnt, $mesAnt);
        }

        $funcoes[] = [
            'funcao_id'          => $funcaoId,
            'nome_funcao'        => $cfg['nome'],
            'cor'                => $cfg['cor'],
            'total_parcial'      => $totalParcial,
            'total_anterior'     => $totalAnterior,
            'recorde_equipe'     => $recEquipe,
            'recorde_equipe_mes' => $recEquipeMes,
            'colaboradores'      => $colaboradoresList,
        ];
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'mes'     => $mes,
        'ano'     => $ano,
        'mes_ant' => $mesAnt,
        'ano_ant' => $anoAnt,
        'funcoes' => $funcoes,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
