<?php

/**
 * TvDashboard/buscar_gestao_vista.php
 * Retorna dados por colaborador para Perspectivas, Plantas Humanizadas e Alterações.
 * Reutiliza a lógica de pagamento e classificação de buscar_tv.php.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/fila_operacional.php';

$conn->query("SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
$finalizationQueueTotal = operacional_fetch_finalization_queue_total($conn);

$fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
$fimMesData = sprintf('%04d-%02d-%02d', $ano, $mes, $fimMesDia);
$fimMesDataTime = $fimMesData . ' 23:59:59';

// ── Colaboradores por seção ───────────────────────────────────────────────────
$perspIds = [6, 8, 33, 23, 37, 40];
$perspNames = [6 => 'Bruna', 8 => 'Marcio', 33 => 'José', 23 => 'Vitor', 37 => 'Rafael', 40 => 'Heverton'];

$plantasIds = [12, 24];
$plantasNames = [12 => 'Andressa', 24 => 'Jiulia'];

$alterIds = [34, 7];
$alterNames = [34 => 'Pedro Henrique', 7 => 'Anderson'];

// ── Query: produção atual – Finalização (funcao_id=4) por colaborador ─────────
// Alinhado com carregar_dados.php: WHERE_NAO_PAGO, AND NOT (funcao_id=4 AND ico.status_id=1), COUNT(DISTINCT)
$sqlFin = "
SELECT fi.colaborador_id,
  CASE
    WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
    WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
  END AS nome_funcao,
  COUNT(DISTINCT fi.idfuncao_imagem) AS quantidade
FROM funcao_imagem fi
JOIN funcao f ON f.idfuncao = fi.funcao_id
LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
WHERE fi.funcao_id = 4
  AND fi.colaborador_id NOT IN (21, 15, 7, 34)
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
              AND hm.data_movimento <= ?
          )
      )
      OR (
        NOT EXISTS (
          SELECT 1 FROM historico_imagens h_any
          WHERE h_any.imagem_id = fi.imagem_id
            AND h_any.data_movimento <= ?
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
  AND (
    EXISTS (
      SELECT 1 FROM log_alteracoes la
      WHERE la.funcao_imagem_id = fi.idfuncao_imagem
        AND MONTH(la.data) = ? AND YEAR(la.data) = ?
        AND LOWER(TRIM(la.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    )
    OR (
      MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?
      AND LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    )
  )
  AND (
    LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    OR EXISTS (
      SELECT 1 FROM log_alteracoes la_fin
      WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
        AND la_fin.data <= ?
        AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    )
  )
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
          AND DATE(pi_full.criado_em) <= ?
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
        OR fi.data_pagamento > ?
      )
    )
  )
GROUP BY fi.colaborador_id, nome_funcao";

// Params: fimMesDataTime, fimMesDataTime, mes, ano, mes, ano, fimMesDataTime, fimMesData, fimMesData
$stmtFin = $conn->prepare($sqlFin);
$stmtFin->bind_param(
  'ssiiiisss',
  $fimMesDataTime,
  $fimMesDataTime,
  $mes,
  $ano,
  $mes,
  $ano,
  $fimMesDataTime,
  $fimMesData,
  $fimMesData
);
$stmtFin->execute();
$finRows = $stmtFin->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtFin->close();

// Indexar por [colaborador_id][nome_funcao]
$finIdx = [];
foreach ($finRows as $r) {
  $finIdx[(int) $r['colaborador_id']][$r['nome_funcao']] = (int) $r['quantidade'];
}

// ── Query: produção atual – Alteração (funcao_id=6) por colaborador ───────────
// Alinhado com carregar_dados.php: WHERE_NAO_PAGO (funcao_id<>4), COUNT(DISTINCT)
$sqlAlt = "
SELECT fi.colaborador_id, COUNT(DISTINCT fi.idfuncao_imagem) AS quantidade
FROM funcao_imagem fi
WHERE fi.funcao_id = 6
  AND fi.colaborador_id IN (34, 7)
  AND fi.colaborador_id NOT IN (21, 15)
  AND (
    EXISTS (
      SELECT 1 FROM log_alteracoes la
      WHERE la.funcao_imagem_id = fi.idfuncao_imagem
        AND MONTH(la.data) = ? AND YEAR(la.data) = ?
    )
    OR (MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?)
  )
  AND (
    LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    OR EXISTS (
      SELECT 1 FROM log_alteracoes la_fin
      WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
        AND la_fin.data <= ?
        AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    )
  )
  AND NOT EXISTS (
    SELECT 1 FROM pagamento_itens pi_np
    WHERE pi_np.origem = 'funcao_imagem'
      AND pi_np.origem_id = fi.idfuncao_imagem
      AND DATE(pi_np.criado_em) <= ?
  )
  AND (
    fi.data_pagamento IS NULL
    OR CAST(fi.data_pagamento AS CHAR) = '0000-00-00'
    OR fi.data_pagamento > ?
  )
GROUP BY fi.colaborador_id";

$stmtAlt = $conn->prepare($sqlAlt);
$stmtAlt->bind_param(
  'iiiisss',
  $mes,
  $ano,
  $mes,
  $ano,
  $fimMesDataTime,
  $fimMesData,
  $fimMesData
);
$stmtAlt->execute();
$altIdx = [];
foreach ($stmtAlt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $altIdx[(int) $r['colaborador_id']] = (int) $r['quantidade'];
}
$stmtAlt->close();

// ── Recorde histórico por colaborador – Finalização Completa ──────────────────
// Alinhado com carregar_dados.php: UNION log_alteracoes+fi.prazo, 36 meses, IS_PARCIAL_AT_PERIOD, nao_pago, exclui 2024-10 e mês atual
$allCollabIds = array_merge($perspIds, $plantasIds);
$placeholders = implode(',', array_fill(0, count($allCollabIds), '?'));
$types = str_repeat('i', count($allCollabIds));
$anoAtual = (int)date('Y');
$mesAtual  = (int)date('m');

$sqlRecFin = "
SELECT nome_funcao, colaborador_id, MAX(qtd_mes) AS recorde
FROM (
  SELECT
    fi.colaborador_id,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
    END AS nome_funcao,
    p.yr AS ano, p.mo AS mes,
    COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
  FROM funcao_imagem fi
  LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
  INNER JOIN (
    SELECT funcao_imagem_id, YEAR(data) AS yr, MONTH(data) AS mo
    FROM log_alteracoes
    WHERE data >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
      AND LOWER(TRIM(status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    GROUP BY funcao_imagem_id, YEAR(data), MONTH(data)
    UNION
    SELECT idfuncao_imagem AS funcao_imagem_id, YEAR(prazo) AS yr, MONTH(prazo) AS mo
    FROM funcao_imagem
    WHERE prazo IS NOT NULL
      AND prazo >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
      AND LOWER(TRIM(status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
    GROUP BY idfuncao_imagem, YEAR(prazo), MONTH(prazo)
  ) AS p ON p.funcao_imagem_id = fi.idfuncao_imagem
  WHERE fi.funcao_id = 4
    AND fi.colaborador_id IN ($placeholders)
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
                AND hm.data_movimento <= CONCAT(LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01'))), ' 23:59:59')
            )
        )
        OR (
          NOT EXISTS (
            SELECT 1 FROM historico_imagens h_any
            WHERE h_any.imagem_id = fi.imagem_id
              AND h_any.data_movimento <= CONCAT(LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01'))), ' 23:59:59')
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
      LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
      OR EXISTS (
        SELECT 1 FROM log_alteracoes la_fin
        WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
          AND la_fin.data <= CONCAT(LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01'))), ' 23:59:59')
          AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
      )
    )
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
            AND DATE(pi_full.criado_em) <= LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01')))
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
          OR fi.data_pagamento > LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01')))
        )
      )
    )
  GROUP BY fi.colaborador_id, nome_funcao, p.yr, p.mo
) AS sub
WHERE nome_funcao IN ('Finalização Completa', 'Finalização de Planta Humanizada')
GROUP BY colaborador_id, nome_funcao";

$stmtRecFin = $conn->prepare($sqlRecFin);
$stmtRecFin->bind_param($types, ...$allCollabIds);
$stmtRecFin->execute();
$recFinIdx = [];
foreach ($stmtRecFin->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $recFinIdx[(int) $r['colaborador_id']][$r['nome_funcao']] = (int) $r['recorde'];
}
$stmtRecFin->close();

// ── Recorde histórico por colaborador – Alteração ─────────────────────────────
// Alinhado com carregar_dados.php: UNION log_alteracoes+fi.prazo, 36 meses, nao_pago, exclui 2024-10 e mês atual
$sqlRecAlt = "
SELECT colaborador_id, MAX(qtd_mes) AS recorde
FROM (
  SELECT fi.colaborador_id, p.yr, p.mo, COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
  FROM funcao_imagem fi
  JOIN (
    SELECT YEAR(la.data) AS yr, MONTH(la.data) AS mo, la.funcao_imagem_id
    FROM log_alteracoes la
    UNION
    SELECT YEAR(fi2.prazo) AS yr, MONTH(fi2.prazo) AS mo, fi2.idfuncao_imagem
    FROM funcao_imagem fi2
    WHERE fi2.prazo IS NOT NULL
  ) AS p ON p.funcao_imagem_id = fi.idfuncao_imagem
  WHERE fi.funcao_id = 6
    AND fi.colaborador_id IN (34, 7)
    AND NOT (p.yr = 2024 AND p.mo = 10)
    AND (p.yr * 12 + p.mo) >= ($ano * 12 + $mes - 36)
    AND (
      LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
      OR EXISTS (
        SELECT 1 FROM log_alteracoes la_fin
        WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
          AND la_fin.data <= LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01')))
          AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
      )
    )
    AND NOT EXISTS (
      SELECT 1 FROM pagamento_itens pi_np
      WHERE pi_np.origem = 'funcao_imagem'
        AND pi_np.origem_id = fi.idfuncao_imagem
        AND DATE(pi_np.criado_em) <= LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01')))
    )
  GROUP BY fi.colaborador_id, p.yr, p.mo
) AS s
GROUP BY colaborador_id";

$stmtRecAlt = $conn->prepare($sqlRecAlt);
$stmtRecAlt->execute();
$recAltIdx = [];
foreach ($stmtRecAlt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $recAltIdx[(int) $r['colaborador_id']] = (int) $r['recorde'];
}
$stmtRecAlt->close();

// ── Metas ─────────────────────────────────────────────────────────────────────
// funcao_id=4 → Finalização Completa (Perspectivas)
// funcao_id=7 → Finalização de Planta Humanizada
// funcao_id=6 → Alteração
$stmtMeta = $conn->prepare(
  "SELECT funcao_id, quantidade_meta FROM metas WHERE mes = ? AND ano = ? AND funcao_id IN (4, 6, 7)"
);
$stmtMeta->bind_param('ii', $mes, $ano);
$stmtMeta->execute();
$metaMap = [];
foreach ($stmtMeta->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $metaMap[(int) $r['funcao_id']] = (int) $r['quantidade_meta'];
}
$stmtMeta->close();

// ── Imagens dos colaboradores ─────────────────────────────────────────────────
$allCollabImageIds = array_merge($perspIds, $plantasIds, $alterIds);
$placeholdersImg = implode(',', array_fill(0, count($allCollabImageIds), '?'));
$typesImg = str_repeat('i', count($allCollabImageIds));

$sqlImg = "SELECT c.idcolaborador, COALESCE(c.imagem, iu.thumb) AS imagem
FROM
    colaborador c
    LEFT JOIN usuario u ON c.idcolaborador = u.idcolaborador
    LEFT JOIN informacoes_usuario iu ON iu.usuario_id = u.idusuario
WHERE
    c.idcolaborador IN ($placeholdersImg)";
$stmtImg = $conn->prepare($sqlImg);
$stmtImg->bind_param($typesImg, ...$allCollabImageIds);
$stmtImg->execute();
$imagemIdx = [];
foreach ($stmtImg->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $imagemIdx[(int) $r['idcolaborador']] = $r['imagem'];
}
$stmtImg->close();

// ── Meta individual por colaborador (Perspectivas) ────────────────────────────
$placeholdersPersp = implode(',', array_fill(0, count($perspIds), '?'));
$typesPersp = str_repeat('i', count($perspIds));
$stmtMetaColab = $conn->prepare(
  "SELECT colaborador_id, meta_tarefas FROM meta_colaborador WHERE funcao_id = 4 AND mes = ? AND ano = ? AND colaborador_id IN ($placeholdersPersp)"
);
$stmtMetaColab->bind_param('ii' . $typesPersp, $mes, $ano, ...$perspIds);
$stmtMetaColab->execute();
$metaColabIdx = [];
foreach ($stmtMetaColab->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $metaColabIdx[(int) $r['colaborador_id']] = (int) $r['meta_tarefas'];
}
$stmtMetaColab->close();

$conn->close();

// ── Monta seções ──────────────────────────────────────────────────────────────
function buildFuncionario(string $nome, int $id, int $qtd, int $recorde, ?int $pct_meta = null, ?string $imagem_url = null, ?int $meta_individual = null): array
{
  return ['nome' => $nome, 'colaborador_id' => $id, 'qtd_parcial' => $qtd, 'recorde_mes' => $recorde, 'pct_meta' => $pct_meta, 'imagem_url' => $imagem_url, 'meta_individual' => $meta_individual];
}

// Perspectivas
$metaPerspTotal = $metaMap[4] ?? null;

$perspFunc = [];
foreach ($perspIds as $cid) {
  $qtd = $finIdx[$cid]['Finalização Completa'] ?? 0;
  $rec = $recFinIdx[$cid]['Finalização Completa'] ?? 0;
  $metaInd = $metaColabIdx[$cid] ?? null;
  $pct = ($metaInd !== null && $metaInd > 0) ? (int) round(($qtd / $metaInd) * 100) : null;
  $imagem_url = null;
  if (!empty($imagemIdx[$cid])) {
    $rawImg = $imagemIdx[$cid];
    if (preg_match('#^(https?://|//|/|\.\./)#i', $rawImg)) {
      $imagem_url = $rawImg;
    } else {
      $rawImg = preg_replace('#^\./+#', '', $rawImg);
      $imagem_url = $rawImg;
    }
  }
  $perspFunc[] = buildFuncionario($perspNames[$cid], $cid, $qtd, $rec, $pct, $imagem_url, $metaInd);
}

// Outros = soma de todos os colaboradores fora da lista (21 já excluído pelo SQL)
$outrosQtd = 0;
$outrosRec = 0;
foreach ($finIdx as $cid => $nomes) {
  if (!in_array($cid, $perspIds) && isset($nomes['Finalização Completa'])) {
    $outrosQtd += $nomes['Finalização Completa'];
    $outrosRec  = max($outrosRec, $recFinIdx[$cid]['Finalização Completa'] ?? 0);
  }
}
$perspFunc[] = buildFuncionario('Outros', 0, $outrosQtd, $outrosRec, null, null);

// Plantas Humanizadas
$metaPlantasTotal = $metaMap[7] ?? null;
$metaPlantasInd = ($metaPlantasTotal !== null) ? (int) ceil($metaPlantasTotal / count($plantasIds)) : null;

$plantasFunc = [];
foreach ($plantasIds as $cid) {
  $qtd = $finIdx[$cid]['Finalização de Planta Humanizada'] ?? 0;
  $rec = $recFinIdx[$cid]['Finalização de Planta Humanizada'] ?? 0;
  $pct = ($metaPlantasInd !== null && $metaPlantasInd > 0) ? (int) round(($qtd / $metaPlantasInd) * 100) : null;
  $imagem_url = null;
  if (!empty($imagemIdx[$cid])) {
    $rawImg = $imagemIdx[$cid];
    if (preg_match('#^(https?://|//|/|\.\./)#i', $rawImg)) {
      $imagem_url = $rawImg;
    } else {
      $rawImg = preg_replace('#^\./+#', '', $rawImg);
      $imagem_url = $rawImg;
    }
  }
  $plantasFunc[] = buildFuncionario($plantasNames[$cid], $cid, $qtd, $rec, $pct, $imagem_url);
}

// Alterações
$metaAltTotal = $metaMap[6] ?? null;
$metaAltInd = ($metaAltTotal !== null) ? (int) ceil($metaAltTotal / count($alterIds)) : null;

$alterFunc = [];
foreach ($alterIds as $cid) {
  $qtd = $altIdx[$cid] ?? 0;
  $rec = $recAltIdx[$cid] ?? 0;
  $pct = ($metaAltInd !== null && $metaAltInd > 0) ? (int) round(($qtd / $metaAltInd) * 100) : null;
  $imagem_url = null;
  if (!empty($imagemIdx[$cid])) {
    $rawImg = $imagemIdx[$cid];
    if (preg_match('#^(https?://|//|/|\.\./)#i', $rawImg)) {
      $imagem_url = $rawImg;
    } else {
      $rawImg = preg_replace('#^\./+#', '', $rawImg);
      $imagem_url = $rawImg;
    }
  }
  $alterFunc[] = buildFuncionario($alterNames[$cid], $cid, $qtd, $rec, $pct, $imagem_url);
}

echo json_encode([
  'fila_operacional_finalizacao' => $finalizationQueueTotal,
  'perspectivas' => [
    'funcionarios'    => $perspFunc,
    'meta_total'      => $metaPerspTotal,
    'meta_individual' => null,
  ],
  'plantas_humanizadas' => [
    'funcionarios' => $plantasFunc,
    'meta_total' => $metaPlantasTotal,
    'meta_individual' => $metaPlantasInd,
  ],
  'alteracoes' => [
    'funcionarios' => $alterFunc,
    'meta_total' => $metaAltTotal,
    'meta_individual' => $metaAltInd,
  ],
], JSON_UNESCAPED_UNICODE);
