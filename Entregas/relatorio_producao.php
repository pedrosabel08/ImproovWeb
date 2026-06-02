<?php

/**
 * relatorio_producao.php
 * Returns hierarchical delivery data for an obra:
 * Obra → Etapa (P00, R00…EF) → Entrega → Imagens
 *
 * GET params:
 *   obra_id  (required)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/p00_delivery_helpers.php';

improov_p00_ensure_schema($conn);

$obra_id = isset($_GET['obra_id']) && is_numeric($_GET['obra_id'])
    ? intval($_GET['obra_id'])
    : null;

if (!$obra_id) {
    echo json_encode(['error' => 'obra_id obrigatório']);
    exit;
}

/* ── Obra info ─────────────────────────────────────────────────────────── */
$stmt = $conn->prepare(
    "SELECT idobra, nomenclatura, recebimento_arquivos, data_final,
            COALESCE(dias_uteis, 30) AS prazo_contratual_dias
     FROM obra WHERE idobra = ? LIMIT 1"
);
$stmt->bind_param('i', $obra_id);
$stmt->execute();
$obra = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$obra) {
    echo json_encode(['error' => 'Obra não encontrada']);
    exit;
}

/* ── Auxiliary: etapa order ────────────────────────────────────────────── */
$etapa_order = [
    'P00' => 0,
    'R00' => 1,
    'R01' => 2,
    'R02' => 3,
    'R03' => 4,
    'R04' => 5,
    'R05' => 6,
    'EF'  => 7
];

/* ══════════════════════════════════════════════════════════════════════════
 *  P00 block
 * ════════════════════════════════════════════════════════════════════════ */
$p00_block = null;

$sql_p00_entrega = "SELECT e.id
                    FROM entregas e
                    WHERE e.obra_id = ? AND e.tipo_entrega = 'P00'
                    LIMIT 1";
$stmt = $conn->prepare($sql_p00_entrega);
$stmt->bind_param('i', $obra_id);
$stmt->execute();
$p00_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($p00_row) {
    $p00_entrega_id = intval($p00_row['id']);

    $sql_versoes = "SELECT
            pv.id,
            pv.versao_label,
            pv.versao_num,
            pv.status,
            pv.data_prevista,
            pv.data_entregue,
            pv.data_aprovacao,
            ico.imagem_nome,
            ico.recebimento_arquivos,
            ico.prazo           AS prazo_contratual,
            ico.tipo_imagem     AS funcao,
            CASE
              WHEN pv.data_entregue IS NOT NULL AND pv.data_prevista IS NOT NULL
                   THEN DATEDIFF(DATE(pv.data_entregue), pv.data_prevista)
              WHEN pv.data_entregue IS NULL AND pv.data_prevista IS NOT NULL
                   AND pv.data_prevista < CURDATE()
                   THEN DATEDIFF(CURDATE(), pv.data_prevista)
              ELSE 0
            END AS dias_atraso
        FROM entregas_p00_versoes pv
        LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = pv.imagem_id
        WHERE pv.entrega_id = ?
        ORDER BY pv.versao_num ASC";

    $stmt = $conn->prepare($sql_versoes);
    $stmt->bind_param('i', $p00_entrega_id);
    $stmt->execute();
    $versoes_res = $stmt->get_result();
    $versoes = [];
    $p00_stats = ['total' => 0, 'entregues' => 0, 'pendentes' => 0, 'atrasadas' => 0];

    while ($v = $versoes_res->fetch_assoc()) {
        $p00_stats['total']++;
        $st = strtolower($v['status'] ?? '');
        if (in_array($st, ['aprovada', 'entregue no prazo', 'entregue com atraso', 'entrega antecipada'])) {
            $p00_stats['entregues']++;
        } elseif ($v['dias_atraso'] > 0 && !in_array($st, ['aprovada'])) {
            $p00_stats['atrasadas']++;
            $p00_stats['pendentes']++;
        } else {
            $p00_stats['pendentes']++;
        }

        $versoes[] = [
            'id'                 => intval($v['id']),
            'versao_label'       => $v['versao_label'],
            'versao_num'         => intval($v['versao_num']),
            'imagem_nome'        => $v['imagem_nome'] ?? '—',
            'funcao'             => $v['funcao'] ?? '—',
            'recebimento'        => $v['recebimento_arquivos'] ?? null,
            'prazo_contratual'   => $v['prazo_contratual'] ?? null,
            'prazo_ajustado'     => $v['data_prevista'] ?? null,
            'data_entregue'      => $v['data_entregue'] ? substr($v['data_entregue'], 0, 10) : null,
            'dias_atraso'        => intval($v['dias_atraso']),
            'status'             => $v['status'] ?? 'Pendente',
        ];
    }
    $stmt->close();

    $p00_block = [
        'etapa_codigo'  => 'P00',
        'etapa_label'   => 'P00 – Projeto Executivo',
        'tipo'          => 'p00',
        'entrega_id'    => $p00_entrega_id,
        'versoes_count' => count($versoes),
        'stats'         => $p00_stats,
        'pct'           => $p00_stats['total'] > 0
            ? round($p00_stats['entregues'] / $p00_stats['total'] * 100)
            : 0,
        'versoes'       => $versoes,
    ];
}

/* ══════════════════════════════════════════════════════════════════════════
 *  Regular entregas (R00, R01 … EF)
 * ════════════════════════════════════════════════════════════════════════ */
$sql_entregas = "SELECT
        e.id,
        e.status_id,
        e.data_prevista         AS entrega_prazo,
        e.data_conclusao,
        e.status                AS entrega_status_raw,
        e.em_hold,
        si.nome_status          AS etapa_codigo,
        COUNT(ei.id)            AS total_itens,
        SUM(CASE WHEN ei.status NOT IN ('Pendente','Entrega pendente') THEN 1 ELSE 0 END) AS entregues_count
    FROM entregas e
    JOIN status_imagem si ON si.idstatus = e.status_id
    LEFT JOIN entregas_itens ei ON ei.entrega_id = e.id
    WHERE e.obra_id = ?
      AND (e.arquivada IS NULL OR e.arquivada = 0)
      AND (e.tipo_entrega IS NULL OR e.tipo_entrega NOT IN ('P00'))
    GROUP BY e.id
    ORDER BY e.status_id ASC, e.data_prevista ASC";

$stmt = $conn->prepare($sql_entregas);
$stmt->bind_param('i', $obra_id);
$stmt->execute();
$entregas_res = $stmt->get_result();

// Group by etapa
$by_etapa = [];
$etapa_seq = []; // maintain insertion order

while ($row = $entregas_res->fetch_assoc()) {
    $codigo = $row['etapa_codigo'];
    if (!isset($by_etapa[$codigo])) {
        $by_etapa[$codigo] = [];
        $etapa_seq[] = $codigo;
    }
    $by_etapa[$codigo][] = $row;
}
$stmt->close();

/* ── For each entrega fetch its itens ──────────────────────────────────── */
$etapas = [];

foreach ($etapa_seq as $codigo) {
    $rows = $by_etapa[$codigo];
    $etapa_entries = [];

    foreach ($rows as $idx => $erow) {
        $eid = intval($erow['id']);

        $sql_itens = "SELECT
                ei.id,
                ei.status,
                ei.data_prevista    AS prazo_ajustado,
                ei.data_entregue,
                ico.imagem_nome,
                ico.recebimento_arquivos,
                ico.prazo           AS prazo_contratual,
                ico.tipo_imagem     AS funcao,
                CASE
                  WHEN ei.data_entregue IS NOT NULL AND ei.data_prevista IS NOT NULL
                       THEN DATEDIFF(DATE(ei.data_entregue), DATE(ei.data_prevista))
                  WHEN ei.data_entregue IS NULL AND ei.data_prevista IS NOT NULL
                       AND DATE(ei.data_prevista) < CURDATE()
                       THEN DATEDIFF(CURDATE(), DATE(ei.data_prevista))
                  ELSE 0
                END AS dias_atraso
            FROM entregas_itens ei
            LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ei.imagem_id
            WHERE ei.entrega_id = ?
            ORDER BY ico.imagem_nome ASC";

        $stmt2 = $conn->prepare($sql_itens);
        $stmt2->bind_param('i', $eid);
        $stmt2->execute();
        $itens_res = $stmt2->get_result();

        $itens = [];
        $stats = ['total' => 0, 'entregues' => 0, 'pendentes' => 0, 'atrasadas' => 0];

        while ($item = $itens_res->fetch_assoc()) {
            $stats['total']++;
            $st = $item['status'] ?? 'Pendente';
            if (in_array($st, ['Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada'])) {
                $stats['entregues']++;
            } else {
                $stats['pendentes']++;
                if (intval($item['dias_atraso']) > 0) {
                    $stats['atrasadas']++;
                }
            }
            $itens[] = [
                'id'               => intval($item['id']),
                'imagem_nome'      => $item['imagem_nome'] ?? '—',
                'funcao'           => $item['funcao'] ?? '—',
                'recebimento'      => $item['recebimento_arquivos'] ?? null,
                'prazo_contratual' => $item['prazo_contratual'] ?? null,
                'prazo_ajustado'   => $item['prazo_ajustado']
                    ? substr($item['prazo_ajustado'], 0, 10) : null,
                'data_entregue'    => $item['data_entregue']
                    ? substr($item['data_entregue'], 0, 10) : null,
                'dias_atraso'      => intval($item['dias_atraso']),
                'status'           => $st,
            ];
        }
        $stmt2->close();

        $pct = $stats['total'] > 0
            ? round($stats['entregues'] / $stats['total'] * 100)
            : 0;

        $etapa_entries[] = [
            'id'         => $eid,
            'label'      => $codigo . ' #' . ($idx + 1),
            'prazo'      => $erow['entrega_prazo'],
            'conclusao'  => $erow['data_conclusao'],
            'em_hold'    => (bool) intval($erow['em_hold']),
            'stats'      => $stats,
            'pct'        => $pct,
            'itens'      => $itens,
        ];
    }

    $etapa_total    = array_sum(array_column(array_column($etapa_entries, 'stats'), 'total'));
    $etapa_entregues = array_sum(array_column(array_column($etapa_entries, 'stats'), 'entregues'));

    $etapas[] = [
        'codigo'         => $codigo,
        'label'          => $codigo,
        'tipo'           => 'regular',
        'entregas_count' => count($etapa_entries),
        'total_imagens'  => $etapa_total,
        'entregues'      => $etapa_entregues,
        'pct'            => $etapa_total > 0
            ? round($etapa_entregues / $etapa_total * 100)
            : 0,
        'entregas'       => $etapa_entries,
    ];
}

/* ── Sort by canonical etapa order ─────────────────────────────────────── */
usort($etapas, function ($a, $b) use ($etapa_order) {
    $oa = $etapa_order[$a['codigo']] ?? 99;
    $ob = $etapa_order[$b['codigo']] ?? 99;
    return $oa - $ob;
});

/* ── Prepend P00 block ──────────────────────────────────────────────────── */
if ($p00_block) {
    array_unshift($etapas, $p00_block);
}

/* ── Global summary ─────────────────────────────────────────────────────── */
$total_etapas      = count($etapas);
$total_entregas    = 0;
$total_imagens_all = 0;
$total_entregues   = 0;

foreach ($etapas as $et) {
    if ($et['tipo'] === 'p00') {
        $total_entregas    += $et['versoes_count'] > 0 ? 1 : 0;
        $total_imagens_all += $et['stats']['total'];
        $total_entregues   += $et['stats']['entregues'];
    } else {
        $total_entregas    += $et['entregas_count'];
        $total_imagens_all += $et['total_imagens'];
        $total_entregues   += $et['entregues'];
    }
}

/* ── Clean up temp files ────────────────────────────────────────────────── */
$conn->close();

echo json_encode([
    'obra'    => [
        'idobra'                  => intval($obra['idobra']),
        'nomenclatura'            => $obra['nomenclatura'],
        'recebimento_arquivos'    => $obra['recebimento_arquivos'],
        'data_final'              => $obra['data_final'],
        'prazo_contratual_dias'   => intval($obra['prazo_contratual_dias']),
    ],
    'summary' => [
        'total_etapas'   => $total_etapas,
        'total_entregas' => $total_entregas,
        'total_imagens'  => $total_imagens_all,
        'entregues'      => $total_entregues,
        'pendentes'      => $total_imagens_all - $total_entregues,
        'pct'            => $total_imagens_all > 0
            ? round($total_entregues / $total_imagens_all * 100)
            : 0,
    ],
    'etapas'  => $etapas,
], JSON_UNESCAPED_UNICODE);
