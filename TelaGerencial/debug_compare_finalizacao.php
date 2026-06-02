<?php
header('Content-Type: text/plain; charset=utf-8');

include __DIR__ . '/../conexao.php';

if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "Falha conexao: " . ($conn->connect_error ?? 'n/a') . PHP_EOL);
    exit(1);
}

$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

$mes = isset($argv[1]) ? (int)$argv[1] : (int)date('n');
$ano = isset($argv[2]) ? (int)$argv[2] : (int)date('Y');

$fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
$fimMesData = sprintf('%04d-%02d-%02d', $ano, $mes, $fimMesDia);
$fimMesDataTime = $fimMesData . ' 23:59:59';

echo "Comparando mes={$mes} ano={$ano}" . PHP_EOL;

$sqlMetasIds = "
SELECT DISTINCT
  t.idfuncao_imagem,
  t.colaborador_id,
  t.imagem_id,
  t.imagem_nome,
  t.tipo_imagem,
  t.status_atual
FROM (
  SELECT
    fi.idfuncao_imagem,
    fi.colaborador_id,
    fi.imagem_id,
    f.idfuncao AS funcao_id,
    fi.status AS status_atual,
    i.imagem_nome,
    i.tipo_imagem,
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
) t
WHERE t.funcao_id = 4
  AND t.nome_funcao = 'Finalização Completa'
  AND (t.pagamento <> 1 OR t.pagamento IS NULL)
";

$stmtA = $conn->prepare($sqlMetasIds);
$stmtA->bind_param(
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
    $mes,
    $ano,
    $mes,
    $ano,
    $fimMesDataTime
);
$stmtA->execute();
$rowsA = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);

$sqlFuncaoIds = "
SELECT DISTINCT
  fi.idfuncao_imagem,
  fi.colaborador_id,
  fi.imagem_id,
  ico.imagem_nome,
  ico.tipo_imagem,
  fi.status AS status_atual
FROM funcao_imagem fi
JOIN funcao f ON f.idfuncao = fi.funcao_id
LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
WHERE (
    EXISTS (
      SELECT 1 FROM log_alteracoes la
      WHERE la.funcao_imagem_id = fi.idfuncao_imagem
        AND MONTH(la.data) = ? AND YEAR(la.data) = ?
        AND LOWER(TRIM(la.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
    )
    OR (
      MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?
      AND LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
    )
  )
  AND (
    LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
    OR EXISTS (
      SELECT 1 FROM log_alteracoes la_fin
      WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
        AND la_fin.data <= ?
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
    )
    OR (
      fi.funcao_id <> 4
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
    )
  )
  AND fi.funcao_id = 4
  AND (
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END
  ) = 'Finalização Completa'
";

$stmtB = $conn->prepare($sqlFuncaoIds);
$stmtB->bind_param(
    'iiiisssssss',
    $mes,
    $ano,
    $mes,
    $ano,
    $fimMesDataTime,
    $fimMesDataTime,
    $fimMesDataTime,
    $fimMesData,
    $fimMesData,
    $fimMesData,
    $fimMesData
);
$stmtB->execute();
$rowsB = $stmtB->get_result()->fetch_all(MYSQLI_ASSOC);

$mapA = [];
foreach ($rowsA as $r) {
    $mapA[(int)$r['idfuncao_imagem']] = $r;
}
$mapB = [];
foreach ($rowsB as $r) {
    $mapB[(int)$r['idfuncao_imagem']] = $r;
}

echo "buscar_metas (nao pagas) = " . count($mapA) . PHP_EOL;
echo "buscar_producao_funcao (nao pagas) = " . count($mapB) . PHP_EOL;

$onlyA = array_diff_key($mapA, $mapB);
$onlyB = array_diff_key($mapB, $mapA);

echo PHP_EOL . "IDs so em buscar_metas: " . count($onlyA) . PHP_EOL;
foreach ($onlyA as $id => $r) {
    echo "  idfuncao_imagem={$id} | colaborador={$r['colaborador_id']} | imagem_id={$r['imagem_id']} | imagem={$r['imagem_nome']} | tipo={$r['tipo_imagem']} | status={$r['status_atual']}" . PHP_EOL;
}

echo PHP_EOL . "IDs so em buscar_producao_funcao: " . count($onlyB) . PHP_EOL;
foreach ($onlyB as $id => $r) {
    echo "  idfuncao_imagem={$id} | colaborador={$r['colaborador_id']} | imagem_id={$r['imagem_id']} | imagem={$r['imagem_nome']} | tipo={$r['tipo_imagem']} | status={$r['status_atual']}" . PHP_EOL;
}
