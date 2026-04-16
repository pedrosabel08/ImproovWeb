<?php
/**
 * TvDashboard/buscar_gestao_vista.php
 * Retorna dados por colaborador para Perspectivas, Plantas Humanizadas e Alterações.
 * Reutiliza a lógica de pagamento e classificação de buscar_tv.php.
 */
header('Content-Type: application/json; charset=utf-8');

include '../conexao.php';

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

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
// Replica exatamente o CASE de pagamento e nome_funcao de buscar_tv.php,
// mas agrupa por (colaborador_id, nome_funcao) em vez de só nome_funcao.
$sqlFin = "
SELECT t.colaborador_id, t.nome_funcao, COUNT(*) AS quantidade
FROM (
  SELECT fi.idfuncao_imagem, fi.funcao_id, fi.colaborador_id,
    CASE
      WHEN fi.funcao_id = 4 AND (
        hi_snap.status_id = 1
        OR (
          hi_snap.status_id IS NULL AND (
            EXISTS (
              SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
              WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
            )
            OR ico.status_id = 1
          )
        )
      ) THEN (
        CASE
          WHEN EXISTS (
            SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
            WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
          ) THEN (
            CASE
              WHEN EXISTS (
                SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
                  AND DATE(pi.criado_em) <= ? AND TRIM(pi.observacao) = 'Finalização Parcial'
              ) THEN 1 ELSE 0
            END
          )
          ELSE (
            CASE
              WHEN fi.data_pagamento IS NOT NULL AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00' AND fi.data_pagamento <= ?
              THEN 1 ELSE 0
            END
          )
        END
      )
      WHEN fi.funcao_id = 4 AND EXISTS (
        SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
        WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
          AND TRIM(pi.observacao) = 'Finalização Parcial'
      ) THEN (
        CASE
          WHEN EXISTS (
            SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
            WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
          ) THEN (
            CASE
              WHEN EXISTS (
                SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
                  AND DATE(pi.criado_em) <= ? AND TRIM(pi.observacao) = 'Pago Completa'
              ) THEN 1 ELSE 0
            END
          )
          ELSE (
            CASE
              WHEN fi.data_pagamento IS NOT NULL AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00' AND fi.data_pagamento <= ?
              THEN 1 ELSE 0
            END
          )
        END
      )
      WHEN fi.funcao_id = 4 THEN (
        CASE
          WHEN EXISTS (
            SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
            WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
          ) THEN (
            CASE
              WHEN EXISTS (
                SELECT 1 FROM pagamento_itens pi JOIN funcao_imagem fi_pi ON fi_pi.idfuncao_imagem = pi.origem_id
                WHERE pi.origem = 'funcao_imagem' AND fi_pi.colaborador_id = fi.colaborador_id AND fi_pi.imagem_id = fi.imagem_id
                  AND DATE(pi.criado_em) <= ?
                  AND (pi.observacao IS NULL OR TRIM(pi.observacao) = '' OR TRIM(pi.observacao) = 'Pago Completa')
                  AND (pi.observacao IS NULL OR TRIM(pi.observacao) <> 'Finalização Parcial')
              ) THEN 1 ELSE 0
            END
          )
          ELSE (
            CASE
              WHEN fi.data_pagamento IS NOT NULL AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00' AND fi.data_pagamento <= ?
              THEN 1 ELSE 0
            END
          )
        END
      )
      ELSE 0
    END AS pagamento,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        hi_snap.status_id = 1
        OR (
          hi_snap.status_id IS NULL AND (
            EXISTS (
              SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
              WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
            )
            OR ico.status_id = 1
          )
        )
      ) THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE NULL
    END AS nome_funcao
  FROM funcao_imagem fi
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
  LEFT JOIN (
    SELECT h1.imagem_id, h1.status_id
    FROM historico_imagens h1
    INNER JOIN (
      SELECT imagem_id, MAX(data_movimento) AS max_data
      FROM historico_imagens
      WHERE data_movimento <= ?
      GROUP BY imagem_id
    ) hm ON hm.imagem_id = h1.imagem_id AND hm.max_data = h1.data_movimento
  ) hi_snap ON hi_snap.imagem_id = ico.idimagens_cliente_obra
  WHERE fi.funcao_id = 4
    AND fi.colaborador_id NOT IN (21, 15, 7, 34)
    AND (
      EXISTS (
        SELECT 1 FROM log_alteracoes la
        WHERE la.funcao_imagem_id = fi.idfuncao_imagem AND MONTH(la.data) = ? AND YEAR(la.data) = ?
      )
      OR (MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?)
    )
    AND (
      LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
      OR EXISTS (
        SELECT 1 FROM log_alteracoes la_fin
        WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem AND la_fin.data <= ?
          AND (
            LOWER(TRIM(la_fin.status_novo))    IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
            OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
          )
      )
    )
) AS t
WHERE t.pagamento <> 1
  AND t.nome_funcao IN ('Finalização Completa', 'Finalização de Planta Humanizada')
GROUP BY t.colaborador_id, t.nome_funcao";

// Params: 6×fimMesData, 1×fimMesDataTime, mes, ano, mes, ano, fimMesDataTime
$stmtFin = $conn->prepare($sqlFin);
$stmtFin->bind_param(
  'sssssssiiiis',
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
$sqlAlt = "
SELECT t.colaborador_id, COUNT(*) AS quantidade
FROM (
  SELECT fi.colaborador_id,
    CASE
      WHEN EXISTS (
        SELECT 1 FROM pagamento_itens pi WHERE pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
      ) THEN (
        CASE
          WHEN EXISTS (
            SELECT 1 FROM pagamento_itens pi WHERE pi.origem = 'funcao_imagem' AND pi.origem_id = fi.idfuncao_imagem
              AND DATE(pi.criado_em) <= ?
          ) THEN 1 ELSE 0
        END
      )
      ELSE (
        CASE
          WHEN fi.data_pagamento IS NOT NULL AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00' AND fi.data_pagamento <= ?
          THEN 1 ELSE 0
        END
      )
    END AS pagamento
  FROM funcao_imagem fi
  WHERE fi.funcao_id = 6
    AND fi.colaborador_id IN (34, 7)
    AND fi.colaborador_id NOT IN (21, 15)
    AND (
      EXISTS (
        SELECT 1 FROM log_alteracoes la
        WHERE la.funcao_imagem_id = fi.idfuncao_imagem AND MONTH(la.data) = ? AND YEAR(la.data) = ?
      )
      OR (MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?)
    )
    AND (
      LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
      OR EXISTS (
        SELECT 1 FROM log_alteracoes la_fin
        WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem AND la_fin.data <= ?
          AND (
            LOWER(TRIM(la_fin.status_novo))    IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
            OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
          )
      )
    )
) AS t
WHERE t.pagamento <> 1
GROUP BY t.colaborador_id";

$stmtAlt = $conn->prepare($sqlAlt);
$stmtAlt->bind_param(
  'ssiiiis',
  $fimMesData,
  $fimMesData,
  $mes,
  $ano,
  $mes,
  $ano,
  $fimMesDataTime
);
$stmtAlt->execute();
$altIdx = [];
foreach ($stmtAlt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $altIdx[(int) $r['colaborador_id']] = (int) $r['quantidade'];
}
$stmtAlt->close();

// ── Recorde histórico por colaborador – Finalização Completa ──────────────────
$allCollabIds = array_merge($perspIds, $plantasIds);
$placeholders = implode(',', array_fill(0, count($allCollabIds), '?'));
$types = str_repeat('i', count($allCollabIds));

$sqlRecFin = "
SELECT colaborador_id, nome_funcao, MAX(qtd_mes) AS recorde
FROM (
  SELECT t.colaborador_id, t.nome_funcao, YEAR(t.prazo) AS yr, MONTH(t.prazo) AS mo, COUNT(*) AS qtd_mes
  FROM (
    SELECT fi.colaborador_id, fi.prazo,
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 AND (
          EXISTS (
            SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
            WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
          ) OR ico.status_id = 1
        ) THEN 'Finalização Parcial'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE NULL
      END AS nome_funcao
    FROM funcao_imagem fi
    LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    WHERE fi.funcao_id = 4
      AND fi.colaborador_id IN ($placeholders)
      AND fi.prazo IS NOT NULL
      AND NOT (YEAR(fi.prazo) = $ano AND MONTH(fi.prazo) = $mes)
      AND LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
  ) AS t
  WHERE t.nome_funcao IN ('Finalização Completa', 'Finalização de Planta Humanizada')
  GROUP BY t.colaborador_id, t.nome_funcao, YEAR(t.prazo), MONTH(t.prazo)
) AS s
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
$sqlRecAlt = "
SELECT colaborador_id, MAX(qtd_mes) AS recorde
FROM (
  SELECT fi.colaborador_id, YEAR(fi.prazo) AS yr, MONTH(fi.prazo) AS mo, COUNT(*) AS qtd_mes
  FROM funcao_imagem fi
  WHERE fi.funcao_id = 6
    AND fi.colaborador_id IN (34, 7)
    AND fi.prazo IS NOT NULL
    AND NOT (YEAR(fi.prazo) = $ano AND MONTH(fi.prazo) = $mes)
    AND LOWER(TRIM(fi.status)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
  GROUP BY fi.colaborador_id, YEAR(fi.prazo), MONTH(fi.prazo)
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
$conn->close();

// ── Monta seções ──────────────────────────────────────────────────────────────
function buildFuncionario(string $nome, int $id, int $qtd, int $recorde): array
{
  return ['nome' => $nome, 'colaborador_id' => $id, 'qtd_parcial' => $qtd, 'recorde_mes' => $recorde];
}

// Perspectivas
$metaPerspTotal = $metaMap[4] ?? null;
$metaPerspInd = ($metaPerspTotal !== null) ? (int) ceil($metaPerspTotal / count($perspIds)) : null;

$perspFunc = [];
foreach ($perspIds as $cid) {
  $qtd = $finIdx[$cid]['Finalização Completa'] ?? 0;
  $rec = $recFinIdx[$cid]['Finalização Completa'] ?? 0;
  $perspFunc[] = buildFuncionario($perspNames[$cid], $cid, $qtd, $rec);
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
$perspFunc[] = buildFuncionario('Outros', 0, $outrosQtd, $outrosRec);

// Plantas Humanizadas
$metaPlantasTotal = $metaMap[7] ?? null;
$metaPlantasInd = ($metaPlantasTotal !== null) ? (int) ceil($metaPlantasTotal / count($plantasIds)) : null;

$plantasFunc = [];
foreach ($plantasIds as $cid) {
  $qtd = $finIdx[$cid]['Finalização de Planta Humanizada'] ?? 0;
  $rec = $recFinIdx[$cid]['Finalização de Planta Humanizada'] ?? 0;
  $plantasFunc[] = buildFuncionario($plantasNames[$cid], $cid, $qtd, $rec);
}

// Alterações
$metaAltTotal = $metaMap[6] ?? null;
$metaAltInd = ($metaAltTotal !== null) ? (int) ceil($metaAltTotal / count($alterIds)) : null;

$alterFunc = [];
foreach ($alterIds as $cid) {
  $qtd = $altIdx[$cid] ?? 0;
  $rec = $recAltIdx[$cid] ?? 0;
  $alterFunc[] = buildFuncionario($alterNames[$cid], $cid, $qtd, $rec);
}

echo json_encode([
  'perspectivas' => [
    'funcionarios'    => $perspFunc,
    'meta_total'      => $metaPerspTotal,
    'meta_individual' => $metaPerspInd,
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
