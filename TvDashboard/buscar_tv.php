<?php

/**
 * TvDashboard/buscar_tv.php
 * Retorna produção por função do mês atual + metas configuradas.
 * Reutiliza a lógica de buscar_producao_funcao.php da TelaGerencial.
 */
header('Content-Type: application/json; charset=utf-8');

include '../conexao.php';

$mes          = isset($_GET['mes']) ? (int)$_GET['mes']  : (int)date('m');
$anoSelecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

$mesInt         = $mes;
$fimMesDia      = cal_days_in_month(CAL_GREGORIAN, $mesInt, $anoSelecionado);
$fimMesData     = sprintf('%04d-%02d-%02d', $anoSelecionado, $mesInt, $fimMesDia);
$fimMesDataTime = $fimMesData . ' 23:59:59';

// ── Produção do mês atual ────────────────────────────────────────────────────
// Espelha o inner SELECT de buscar_producao_funcao.php (pagamento CASE idêntico)
// e filtra WHERE t.pagamento <> 1 no outer query para obter apenas não pagas.
$sql = "SELECT
    COUNT(*) AS quantidade,
    t.nome_funcao,
    MIN(t.funcao_id) AS funcao_order
  FROM (
    SELECT fi.idfuncao_imagem, fi.funcao_id,
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
                    AND fi_pi.imagem_id = fi.imagem_id
                    AND DATE(pi.criado_em) <= ?
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
                    AND fi_pi.imagem_id = fi.imagem_id
                    AND DATE(pi.criado_em) <= ?
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
                    AND fi_pi.imagem_id = fi.imagem_id
                    AND DATE(pi.criado_em) <= ?
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
              SELECT 1 FROM pagamento_itens pi
              WHERE pi.origem = 'funcao_imagem'
                AND pi.origem_id = fi.idfuncao_imagem
            ) THEN (
              CASE
                WHEN EXISTS (
                  SELECT 1 FROM pagamento_itens pi
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
      END AS pagamento,
      fi.imagem_id, f.nome_funcao AS original_funcao, ico.status_id,
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
        ELSE f.nome_funcao
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
    WHERE (
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
            AND (
              LOWER(TRIM(la_fin.status_novo))    IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
              OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
            )
        )
      )
      AND fi.colaborador_id NOT IN (21, 15)
      AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
  ) AS t
  WHERE t.pagamento <> 1
  GROUP BY t.nome_funcao
  ORDER BY
    FIELD(t.nome_funcao,
      'Caderno','Filtro de assets','Modelagem','Composição',
      'Pré-Finalização','Finalização Parcial','Finalização Completa',
      'Finalização de Planta Humanizada','Pós-produção','Alteração'
    ), funcao_order";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
  'sssssssssiiiis',
  $fimMesData,
  $fimMesData,
  $fimMesData,
  $fimMesData,
  $fimMesData,
  $fimMesData,
  $fimMesData,
  $fimMesData,
  $fimMesDataTime,
  $mesInt,
  $anoSelecionado,
  $mesInt,
  $anoSelecionado,
  $fimMesDataTime
);
$stmt->execute();
$dados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Mês anterior ─────────────────────────────────────────────────────────────
$mesAnterior    = ($mesInt === 1) ? 12 : ($mesInt - 1);
$anoMesAnterior = ($mesInt === 1) ? ($anoSelecionado - 1) : $anoSelecionado;
$fimAntDia      = cal_days_in_month(CAL_GREGORIAN, $mesAnterior, $anoMesAnterior);
$fimAntData     = sprintf('%04d-%02d-%02d', $anoMesAnterior, $mesAnterior, $fimAntDia);
$fimAntDataTime = $fimAntData . ' 23:59:59';

$sqlAnt = "SELECT t.nome_funcao, COUNT(*) AS mes_anterior
  FROM (
    SELECT
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 AND (
          hi_prev.status_id = 1
          OR (
            hi_prev.status_id IS NULL AND (
              EXISTS (
                SELECT 1 FROM funcao_imagem fi_sub
                JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
                WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
              )
              OR ico.status_id = 1
            )
          )
        ) THEN 'Finalização Parcial'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
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
    ) hi_prev ON hi_prev.imagem_id = ico.idimagens_cliente_obra
    WHERE (
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
            AND (
              LOWER(TRIM(la_fin.status_novo))    IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
              OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')
            )
        )
      )
      AND fi.colaborador_id NOT IN (21, 15)
      AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
  ) AS t
  GROUP BY t.nome_funcao";

$stmtAnt = $conn->prepare($sqlAnt);
$stmtAnt->bind_param(
  'siiiis',
  $fimAntDataTime,
  $mesAnterior,
  $anoMesAnterior,
  $mesAnterior,
  $anoMesAnterior,
  $fimAntDataTime
);
$stmtAnt->execute();
$anteriorIndexado = [];
foreach ($stmtAnt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $anteriorIndexado[$r['nome_funcao']] = (int)$r['mes_anterior'];
}
$stmtAnt->close();

// ── Recorde histórico ─────────────────────────────────────────────────────────
$sqlRec = "SELECT nome_funcao, MAX(qtd_mes) AS recorde
  FROM (
    SELECT t.nome_funcao, YEAR(t.prazo) AS ano, MONTH(t.prazo) AS mes, COUNT(*) AS qtd_mes
    FROM (
      SELECT
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 AND (
            EXISTS (
              SELECT 1 FROM funcao_imagem fi_sub
              JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
              WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
            ) OR ico.status_id = 1
          ) THEN 'Finalização Parcial'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END AS nome_funcao,
        fi.prazo
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE fi.prazo IS NOT NULL
        AND (fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste'
             OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado')
        AND fi.colaborador_id NOT IN (21, 15)
        AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
        AND NOT (YEAR(fi.prazo) = $anoSelecionado AND MONTH(fi.prazo) = $mesInt)
    ) AS t
    GROUP BY t.nome_funcao, YEAR(t.prazo), MONTH(t.prazo)
  ) AS s
  GROUP BY nome_funcao";

$stmtRec = $conn->prepare($sqlRec);
$stmtRec->execute();
$recordeIndexado = [];
foreach ($stmtRec->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  $recordeIndexado[$r['nome_funcao']] = (int)$r['recorde'];
}
$stmtRec->close();

// ── Metas do mês ─────────────────────────────────────────────────────────────
// funcao_id=4 → "Finalização Completa" | funcao_id=7 → "Finalização de Planta Humanizada"
// "Finalização Parcial" não tem meta — fica sem referência no gráfico
$sqlMeta = "SELECT
    CASE
      WHEN m.funcao_id = 4 THEN 'Finalização Completa'
      WHEN m.funcao_id = 7 THEN 'Finalização de Planta Humanizada'
      ELSE f.nome_funcao
    END AS nome_display,
    m.quantidade_meta
  FROM metas m
  JOIN funcao f ON f.idfuncao = m.funcao_id
  WHERE m.mes = ? AND m.ano = ?";
$stmtMeta = $conn->prepare($sqlMeta);
$stmtMeta->bind_param('ii', $mesInt, $anoSelecionado);
$stmtMeta->execute();
$metaIndexado = [];
foreach ($stmtMeta->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
  if ($r['quantidade_meta'] !== null) {
    $metaIndexado[$r['nome_display']] = (int)$r['quantidade_meta'];
  }
}
$stmtMeta->close();

// ── Monta resposta final ──────────────────────────────────────────────────────
foreach ($dados as &$linha) {
  $nome      = $linha['nome_funcao'];
  $qtd       = (int)$linha['quantidade'];
  $anterior  = $anteriorIndexado[$nome] ?? 0;
  $recorde   = max($recordeIndexado[$nome] ?? 0, $anterior);
  $meta      = $metaIndexado[$nome] ?? null;

  $linha['quantidade']       = $qtd;
  $linha['mes_anterior']     = $anterior;
  $linha['recorde_producao'] = $recorde;
  $linha['bate_recorde']     = $qtd > $recorde && $recorde > 0;
  $linha['meta']             = $meta;
  $linha['pct_meta']         = ($meta && $meta > 0) ? round(($qtd / $meta) * 100) : null;
  $linha['atingiu_meta']     = ($meta !== null && $qtd >= $meta);
}
unset($linha);

$conn->close();
echo json_encode($dados, JSON_UNESCAPED_UNICODE);
