<?php
include_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/custo_tarefa.php';
// Ajusta sql_mode para evitar erros com ONLY_FULL_GROUP_BY em consultas complexas
// (remove temporariamente ONLY_FULL_GROUP_BY para esta sessão)
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
$conn->query("SET SESSION group_concat_max_len = 1048576");

// Pega o mês atual selecionado
$mes = $_GET['mes'] ?? date('m');
$anoSelecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

$mes = (int)$mes;
$mesAnterior = ($mes === 1) ? 12 : ($mes - 1);
$anoMesAnterior = ($mes === 1) ? ($anoSelecionado - 1) : $anoSelecionado;
$mesRefAnterior = sprintf('%04d-%02d', $anoMesAnterior, $mesAnterior);
$mesRef = sprintf('%04d-%02d', $anoSelecionado, $mes);

$fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mes, $anoSelecionado);
$fimMesData = sprintf('%04d-%02d-%02d', $anoSelecionado, $mes, $fimMesDia);
$fimMesDataTime = $fimMesData . ' 23:59:59';

// 1. Busca os dados do mês selecionado
$sql = "SELECT
  t.colaborador_id,
  t.nome_colaborador,
  t.funcao_id,
  t.nome_funcao,
  COUNT(*) AS quantidade,
  GROUP_CONCAT(DISTINCT CONCAT(IFNULL(t.imagem_id,''), ':::', IFNULL(t.imagem_nome,''), ':::', IFNULL(t.pagamento,0)) SEPARATOR '|||') AS imagens_concat,
  SUM(CASE WHEN t.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
  SUM(CASE WHEN t.pagamento <> 1 OR t.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas
FROM (
  SELECT
    fi.colaborador_id,
    c.nome_colaborador,
    f.idfuncao AS funcao_id,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao,
    i.idimagens_cliente_obra AS imagem_id,
    i.imagem_nome AS imagem_nome,
    CASE
      WHEN fi.funcao_id = 4 AND (
        hi_snap.status_id = 1
        OR (
          hi_snap.status_id IS NULL AND (
            EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
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
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
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
      MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?
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
      hi_snap.status_id = 1
      OR (
        hi_snap.status_id IS NULL
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
GROUP BY t.colaborador_id, t.funcao_id, t.nome_funcao, t.nome_colaborador
ORDER BY
  FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
  t.nome_colaborador;";
$stmt = $conn->prepare($sql); // Usa a conexão do arquivo conexao.php
$stmt->bind_param(
  "sssssssssiiiis",
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
  $anoSelecionado,
  $mes,
  $anoSelecionado,
  $fimMesDataTime
);
$stmt->execute();
$result = $stmt->get_result();
$dadosMesAtual = $result->fetch_all(MYSQLI_ASSOC);

$tarefasFinalizacao = [];
foreach ($dadosMesAtual as $linha) {
  if ((int) ($linha['funcao_id'] ?? 0) !== 4 || empty($linha['imagens_concat'])) {
    continue;
  }

  foreach (explode('|||', $linha['imagens_concat']) as $item) {
    if ($item === '') {
      continue;
    }

    $partes = explode(':::', $item, 3);
    if (isset($partes[0]) && is_numeric($partes[0])) {
      $tarefasFinalizacao[] = [
        'colaborador_id' => (int) ($linha['colaborador_id'] ?? 0),
        'imagem_id' => (int) $partes[0],
      ];
    }
  }
}
$statusFinalizacaoMap = custo_tarefa_carregar_status_finalizacao($conn, $tarefasFinalizacao, $fimMesData);

$colaboradoresParaCusto = [];
foreach ($dadosMesAtual as $linha) {
  if (isset($linha['colaborador_id'])) {
    $colaboradoresParaCusto[] = (int) $linha['colaborador_id'];
  }
}

custo_tarefa_carregar_contexto($conn, $colaboradoresParaCusto);

foreach ($dadosMesAtual as &$linha) {
  if ((int) ($linha['funcao_id'] ?? 0) === 6) {
    $linha['custo'] = 0.0;
    $linha['custo_medio'] = 0.0;
    continue;
  }

  $custoTotalLinha = 0.0;
  $pagasRecalculadas = 0;
  $naoPagasRecalculadas = 0;
  $concat = $linha['imagens_concat'] ?? '';
  $colaboradorIdLinha = (int) ($linha['colaborador_id'] ?? 0);
  $funcaoIdLinha = (int) ($linha['funcao_id'] ?? 0);

  if ($concat !== null && $concat !== '') {
    $itens = explode('|||', $concat);
    foreach ($itens as $item) {
      if ($item === '') {
        continue;
      }
      $partes = explode(':::', $item, 3);
      $imagemIdRaw = $partes[0] ?? '';
      $imagemNome = $partes[1] ?? '';
      $pagamentoItem = isset($partes[2]) ? (int) $partes[2] : 0;

      $parcialPendente = false;
      if ($funcaoIdLinha === 4 && is_numeric($imagemIdRaw)) {
        $statusPagamento = $statusFinalizacaoMap[custo_tarefa_chave_finalizacao($colaboradorIdLinha, (int) $imagemIdRaw)] ?? null;
        if (!empty($statusPagamento['completo_pago'])) {
          $pagamentoItem = 1;
        } elseif (!empty($statusPagamento['parcial_pendente'])) {
          $pagamentoItem = 0;
          $parcialPendente = true;
        }
      }

      if ($pagamentoItem === 1) {
        $pagasRecalculadas++;
      } else {
        $naoPagasRecalculadas++;
        $fatorCusto = $parcialPendente ? 0.5 : 1.0;
        $custoTotalLinha += calcularCustoTarefa($colaboradorIdLinha, $funcaoIdLinha, $imagemNome) * $fatorCusto;
      }
    }
  }

  if ($concat !== null && $concat !== '') {
    $linha['pagas'] = $pagasRecalculadas;
    $linha['nao_pagas'] = $naoPagasRecalculadas;
  }
  $linha['custo'] = round($custoTotalLinha, 2);
  $naoPagasLinha = (int) ($linha['nao_pagas'] ?? 0);
  $linha['custo_medio'] = $naoPagasLinha > 0
    ? round($custoTotalLinha / $naoPagasLinha, 2)
    : 0.0;
}
unset($linha);

// 2. Busca as quantidades do mês anterior — sem pagos, sem Parcial, COUNT(DISTINCT), alinhado com carregar_dados.php
$fimMesAnteriorDia = cal_days_in_month(CAL_GREGORIAN, $mesAnterior, $anoMesAnterior);
$fimMesAnteriorData = sprintf('%04d-%02d-%02d', $anoMesAnterior, $mesAnterior, $fimMesAnteriorDia);
$fimMesAnteriorDataTime = $fimMesAnteriorData . ' 23:59:59';
$sqlAnterior = "SELECT
  fi.colaborador_id,
  c.nome_colaborador,
  CASE
    WHEN fi.funcao_id = 4 AND LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
    WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
    ELSE f.nome_funcao
  END AS nome_funcao,
  COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes_anterior
FROM funcao_imagem fi
JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
JOIN funcao f ON f.idfuncao = fi.funcao_id
LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
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
  AND NOT (fi.funcao_id = 4 AND i.status_id = 1)
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
GROUP BY fi.colaborador_id, nome_funcao";
$stmtAnterior = $conn->prepare($sqlAnterior);
$stmtAnterior->bind_param(
  "iiiisssss",
  $mesAnterior,
  $anoMesAnterior,
  $mesAnterior,
  $anoMesAnterior,
  $fimMesAnteriorDataTime,
  $fimMesAnteriorData,
  $fimMesAnteriorData,
  $fimMesAnteriorData,
  $fimMesAnteriorData
);
$stmtAnterior->execute();
$resultAnterior = $stmtAnterior->get_result();
$dadosAnteriores = $resultAnterior->fetch_all(MYSQLI_ASSOC);

// Indexa os dados do mês anterior para acesso rápido (usa nome_funcao para diferenciar finalizações)
$anteriorIndexado = [];
foreach ($dadosAnteriores as $linha) {
  $chave = $linha['nome_colaborador'] . '_' . $linha['nome_funcao'];
  $anteriorIndexado[$chave] = $linha['qtd_mes_anterior'];
}

// 3. Busca o recorde geral de cada colaborador
// Alinhado com carregar_dados.php: UNION log_alteracoes+fi.prazo, 36 meses, IS_PARCIAL_AT_PERIOD, nao_pago, exclui 2024-10 e mês atual
$anoAtual = (int)date('Y');
$mesAtual  = (int)date('m');
$sqlRecorde = "SELECT nome_colaborador, nome_funcao, MAX(qtd_mes) AS recorde,
  SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(ano, '-', LPAD(mes, 2, '0')) ORDER BY qtd_mes DESC SEPARATOR ','), ',', 1) AS recorde_mes_ano
FROM (
  SELECT
    fi.colaborador_id,
    c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(TRIM(i.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao,
    p.yr AS ano, p.mo AS mes,
    COUNT(DISTINCT fi.idfuncao_imagem) AS qtd_mes
  FROM funcao_imagem fi
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
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
  ) AS p ON p.funcao_imagem_id = fi.idfuncao_imagem
  WHERE (
      LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
      OR EXISTS (
        SELECT 1 FROM log_alteracoes la_fin
        WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
          AND la_fin.data <= CONCAT(LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01'))), ' 23:59:59')
          AND LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
      )
    )
    AND fi.colaborador_id NOT IN (21, 15)
    AND NOT (fi.funcao_id = 4 AND fi.colaborador_id IN (7, 34))
    AND NOT (fi.funcao_id = 4 AND i.status_id = 1)
    AND NOT (p.yr = $anoSelecionado AND p.mo = $mes)
    AND NOT (p.yr = 2024 AND p.mo = 10)
    AND NOT (p.yr = $anoAtual AND p.mo = $mesAtual)
    AND (p.yr * 12 + p.mo) >= ($anoSelecionado * 12 + $mes - 36)
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
      )
      OR (
        fi.funcao_id <> 4
        AND NOT EXISTS (
          SELECT 1 FROM pagamento_itens pi_np
          WHERE pi_np.origem = 'funcao_imagem'
            AND pi_np.origem_id = fi.idfuncao_imagem
            AND DATE(pi_np.criado_em) <= LAST_DAY(DATE(CONCAT(p.yr, '-', LPAD(p.mo,2,'0'), '-01')))
        )
      )
    )
    AND NOT (
      fi.funcao_id = 4
      AND LOWER(TRIM(i.tipo_imagem)) != 'planta humanizada'
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
            i.status_id = 1
            OR EXISTS (SELECT 1 FROM funcao f2 WHERE f2.idfuncao = fi.funcao_id AND LOWER(f2.nome_funcao) LIKE '%pre%')
          )
        )
      )
    )
  GROUP BY fi.colaborador_id, c.nome_colaborador, nome_funcao, p.yr, p.mo
) AS sub
GROUP BY nome_colaborador, nome_funcao";

$resultRecorde = $conn->query($sqlRecorde);
$recordes = $resultRecorde ? $resultRecorde->fetch_all(MYSQLI_ASSOC) : [];

// Indexa os recordes
$recordeIndexado = [];
foreach ($recordes as $linha) {
  $chave = $linha['nome_colaborador'] . '_' . $linha['nome_funcao'];
  $recordeIndexado[$chave] = [
    'qtd'     => (int)$linha['recorde'],
    'mes_ano' => $linha['recorde_mes_ano'] ?? null,
  ];
}

// Planta Humanizada (funcao_id=7) e Finalização de Planta Humanizada (funcao_id=4)
// compartilham o mesmo recorde por colaborador — usa o maior valor entre os dois.
$colaboradoresMerge = [];
foreach (array_keys($recordeIndexado) as $chave) {
  if (str_ends_with($chave, '_Planta Humanizada')) {
    $colaboradoresMerge[substr($chave, 0, -strlen('_Planta Humanizada'))] = true;
  } elseif (str_ends_with($chave, '_Finalização de Planta Humanizada')) {
    $colaboradoresMerge[substr($chave, 0, -strlen('_Finalização de Planta Humanizada'))] = true;
  }
}
foreach (array_keys($colaboradoresMerge) as $colab) {
  $keyPH  = $colab . '_Planta Humanizada';
  $keyFPH = $colab . '_Finalização de Planta Humanizada';
  $recPH  = $recordeIndexado[$keyPH]['qtd']  ?? 0;
  $recFPH = $recordeIndexado[$keyFPH]['qtd'] ?? 0;
  $mergedQtd    = max($recPH, $recFPH);
  $mergedMesAno = ($recPH >= $recFPH)
    ? ($recordeIndexado[$keyPH]['mes_ano']  ?? null)
    : ($recordeIndexado[$keyFPH]['mes_ano'] ?? null);
  $recordeIndexado[$keyPH]  = ['qtd' => $mergedQtd, 'mes_ano' => $mergedMesAno];
  $recordeIndexado[$keyFPH] = ['qtd' => $mergedQtd, 'mes_ano' => $mergedMesAno];
}

// 4. Junta tudo
$resultado = [];
foreach ($dadosMesAtual as $linha) {
  $colaborador = $linha['nome_colaborador'];
  $nomeFuncao = $linha['nome_funcao'];
  $chave = $colaborador . '_' . $nomeFuncao;

  $imagens = [];
  $concat = $linha['imagens_concat'] ?? '';
  if ($concat !== null && $concat !== '') {
    $itens = explode('|||', $concat);
    foreach ($itens as $item) {
      if ($item === '') {
        continue;
      }
      $partes = explode(':::', $item, 3);
      $imagemIdRaw = $partes[0] ?? '';
      $imagemNome = $partes[1] ?? '';
      $imagemPagoRaw = $partes[2] ?? '0';
      if ($imagemNome === '' && $imagemIdRaw === '') {
        continue;
      }
      $imagemPago = (int)$imagemPagoRaw;
      if ((int) ($linha['funcao_id'] ?? 0) === 4 && is_numeric($imagemIdRaw)) {
        $statusPagamento = $statusFinalizacaoMap[custo_tarefa_chave_finalizacao((int) ($linha['colaborador_id'] ?? 0), (int) $imagemIdRaw)] ?? null;
        if (!empty($statusPagamento['completo_pago'])) {
          $imagemPago = 1;
        } elseif (!empty($statusPagamento['parcial_pendente'])) {
          $imagemPago = 0;
        }
      }
      $imagens[] = [
        'imagem_id' => is_numeric($imagemIdRaw) ? (int)$imagemIdRaw : $imagemIdRaw,
        'imagem_nome' => $imagemNome,
        'pago' => $imagemPago,
      ];
    }
  }
  $linha['imagens'] = $imagens;
  unset($linha['imagens_concat']);

  $linha['mes_anterior'] = $anteriorIndexado[$chave] ?? 0;
  $quantidadeAtual = isset($linha['quantidade']) ? (int)$linha['quantidade'] : 0;
  // Recorde: maior quantidade em meses anteriores (exclui mês atual)
  $recordeEntry   = $recordeIndexado[$chave] ?? ['qtd' => 0, 'mes_ano' => null];
  $recordeQtd     = (int)$recordeEntry['qtd'];
  $recordeMesAno  = $recordeEntry['mes_ano'];
  $mesAnteriorQtd = (int)($linha['mes_anterior'] ?? 0);

  if ($mesAnteriorQtd > $recordeQtd) {
    // mês anterior supera o recorde histórico: data é o mês anterior
    $linha['recorde_producao'] = $mesAnteriorQtd;
    $linha['recorde_data']     = sprintf('%04d-%02d', $anoMesAnterior, $mesAnterior);
  } else {
    $linha['recorde_producao'] = $recordeQtd;
    $linha['recorde_data']     = $recordeMesAno;
  }

  $linha['bate_recorde'] = $quantidadeAtual > $linha['recorde_producao'];

  $resultado[] = $linha;
}

echo json_encode($resultado);
