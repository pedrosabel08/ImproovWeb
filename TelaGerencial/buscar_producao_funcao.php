<?php
include __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/custo_tarefa.php';

$conn->query("SET SESSION group_concat_max_len = 1048576");

// Verifica os parâmetros recebidos
$mes = $_GET['mes'] ?? null;
$data = $_GET['data'] ?? null;
$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;
$dataLimitePagamento = null;

$anoSelecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

if ($mes) {


  $mesInt = (int)$mes;
  $mesRef = sprintf('%04d-%02d', $anoSelecionado, $mesInt);

  $fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mesInt, $anoSelecionado);
  $fimMesData = sprintf('%04d-%02d-%02d', $anoSelecionado, $mesInt, $fimMesDia);
  $fimMesDataTime = $fimMesData . ' 23:59:59';
  $dataLimitePagamento = $fimMesData;

  // Conta apenas itens NÃO pagos, sem "Finalização Parcial", usando COUNT(DISTINCT).
  // Alinhado com carregar_dados.php: $WHERE_NAO_PAGO + $WHERE_STATUS + $IS_PARCIAL_AT_PERIOD.
  $sql = "SELECT t.quantidade, t.nome_funcao, t.funcao_order, t.tarefas_concat
  FROM (
    SELECT
      COUNT(DISTINCT fi.idfuncao_imagem) AS quantidade,
      GROUP_CONCAT(DISTINCT CONCAT(fi.colaborador_id, ':::', fi.imagem_id, ':::', IFNULL(ico.imagem_nome, '')) SEPARATOR '|||') AS tarefas_concat,
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END AS nome_funcao,
      MIN(fi.funcao_id) AS funcao_order
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
    AND (
      fi.funcao_id <> 4
      OR LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada'
      OR EXISTS (
        SELECT 1
        FROM funcao_colaborador fc4
        JOIN colaborador c4 ON c4.idcolaborador = fc4.colaborador_id
        WHERE fc4.colaborador_id = fi.colaborador_id
          AND fc4.funcao_id = 4
          AND fc4.colaborador_id IS NOT NULL
          AND c4.ativo = 1
          AND fc4.colaborador_id NOT IN (21, 15, 30, 7, 34)
          AND NOT EXISTS (
            SELECT 1
            FROM funcao_colaborador fc7
            WHERE fc7.colaborador_id = fc4.colaborador_id
              AND fc7.funcao_id = 7
          )
      )
    )
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
    GROUP BY
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END
  ) AS t
  ORDER BY
    FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Completa', 'Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
    t.funcao_order";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    "iiiisssssss",
    $mesInt,
    $anoSelecionado,
    $mesInt,
    $anoSelecionado,
    $fimMesDataTime,
    $fimMesDataTime,
    $fimMesDataTime,
    $fimMesData,
    $fimMesData,
    $fimMesData,
    $fimMesData
  );
} elseif ($data) {
  $dataLimitePagamento = $data;
  // Filtro por dia específico - calcular nome_funcao por linha e agregar externamente
  $sql = "SELECT COUNT(*) AS quantidade, t.nome_funcao, MIN(t.funcao_id) AS funcao_order,
      GROUP_CONCAT(DISTINCT CONCAT(t.colaborador_id, ':::', t.imagem_id, ':::', IFNULL(t.imagem_nome, '')) SEPARATOR '|||') AS tarefas_concat
    FROM (
      SELECT fi.colaborador_id, fi.funcao_id, fi.imagem_id, ico.imagem_nome, fi.pagamento, f.nome_funcao,
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 AND (
            EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
            OR ico.status_id = 1
          ) THEN 'Finalização Parcial'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END AS nome_funcao
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE (
          EXISTS (
            SELECT 1
            FROM log_alteracoes la
            WHERE la.funcao_imagem_id = fi.idfuncao_imagem
              AND DATE(la.data) = ?
          )
          OR DATE(fi.prazo) = ?
        ) AND (fi.status <> 'Não iniciado' OR fi.status IS NULL) AND ico.obra_id <> 74 OR (fi.colaborador_id = 21)
    ) AS t
    GROUP BY t.nome_funcao
    ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
      funcao_order";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $data, $data);
} elseif ($inicio && $fim) {
  $dataLimitePagamento = $fim;
  // Filtro por intervalo de semana - calcular nome_funcao por linha e agregar externamente
  $sql = "SELECT COUNT(*) AS quantidade, t.nome_funcao, MIN(t.funcao_id) AS funcao_order,
      GROUP_CONCAT(DISTINCT CONCAT(t.colaborador_id, ':::', t.imagem_id, ':::', IFNULL(t.imagem_nome, '')) SEPARATOR '|||') AS tarefas_concat
    FROM (
      SELECT fi.colaborador_id, fi.funcao_id, fi.imagem_id, ico.imagem_nome, fi.pagamento, f.nome_funcao,
        CASE
          WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
          WHEN fi.funcao_id = 4 AND (
            EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
            OR ico.status_id = 1
          ) THEN 'Finalização Parcial'
          WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
          ELSE f.nome_funcao
        END AS nome_funcao
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE (
          EXISTS (
            SELECT 1
            FROM log_alteracoes la
            WHERE la.funcao_imagem_id = fi.idfuncao_imagem
              AND DATE(la.data) BETWEEN ? AND ?
          )
          OR DATE(fi.prazo) BETWEEN ? AND ?
        ) AND (fi.status <> 'Não iniciado' OR fi.status IS NULL) AND ico.obra_id <> 74
    ) AS t
    GROUP BY t.nome_funcao
    ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
      funcao_order";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ssss", $inicio, $fim, $inicio, $fim);
} else {
  // Caso nenhum parâmetro válido seja enviado
  echo json_encode(["error" => "Parâmetros inválidos"]);
  exit;
}

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();
$dados = $result->fetch_all(MYSQLI_ASSOC);

$tarefasFinalizacao = [];
foreach ($dados as $linha) {
  if ((int) ($linha['funcao_order'] ?? 0) !== 4 || empty($linha['tarefas_concat'])) {
    continue;
  }

  foreach (explode('|||', $linha['tarefas_concat']) as $item) {
    if ($item === '') {
      continue;
    }

    $partes = explode(':::', $item, 3);
    if (isset($partes[0], $partes[1]) && is_numeric($partes[0]) && is_numeric($partes[1])) {
      $tarefasFinalizacao[] = [
        'colaborador_id' => (int) $partes[0],
        'imagem_id' => (int) $partes[1],
      ];
    }
  }
}
$statusFinalizacaoMap = $dataLimitePagamento !== null
  ? custo_tarefa_carregar_status_finalizacao($conn, $tarefasFinalizacao, $dataLimitePagamento)
  : [];

$colaboradoresParaCusto = [];
foreach ($dados as $linha) {
  if (!empty($linha['tarefas_concat'])) {
    foreach (explode('|||', $linha['tarefas_concat']) as $item) {
      if ($item === '') {
        continue;
      }
      $partes = explode(':::', $item, 3);
      if (isset($partes[0]) && is_numeric($partes[0])) {
        $colaboradoresParaCusto[] = (int) $partes[0];
      }
    }
  }
}

custo_tarefa_carregar_contexto($conn, $colaboradoresParaCusto);

foreach ($dados as &$linha) {
  if ((int) ($linha['funcao_order'] ?? 0) === 6) {
    $linha['custo_total'] = 0.0;
    $linha['custo_medio'] = 0.0;
    unset($linha['tarefas_concat']);
    continue;
  }

  $custoTotalLinha = 0.0;
  $quantidadeLinha = (int) ($linha['quantidade'] ?? 0);

  if (!empty($linha['tarefas_concat'])) {
    foreach (explode('|||', $linha['tarefas_concat']) as $item) {
      if ($item === '') {
        continue;
      }
      $partes = explode(':::', $item, 3);
      $colaboradorId = isset($partes[0]) ? (int) $partes[0] : 0;
      $imagemId = isset($partes[1]) ? (int) $partes[1] : 0;
      $imagemNome = $partes[2] ?? '';
      $funcaoIdLinha = (int) ($linha['funcao_order'] ?? 0);
      $fatorCusto = 1.0;
      if ($funcaoIdLinha === 4 && $imagemId > 0) {
        $statusPagamento = $statusFinalizacaoMap[custo_tarefa_chave_finalizacao($colaboradorId, $imagemId)] ?? null;
        if (!empty($statusPagamento['parcial_pendente'])) {
          $fatorCusto = 0.5;
        }
      }
      $custoTotalLinha += calcularCustoTarefa($colaboradorId, $funcaoIdLinha, $imagemNome) * $fatorCusto;
    }
  }

  $linha['custo_total'] = round($custoTotalLinha, 2);
  $linha['custo_medio'] = $quantidadeLinha > 0 ? round($custoTotalLinha / $quantidadeLinha, 2) : 0.0;
  unset($linha['tarefas_concat']);
}
unset($linha);

$custoIndexado = [];
foreach ($dados as $linha) {
  $custoIndexado[$linha['nome_funcao']] = [
    'custo_total' => (float) ($linha['custo_total'] ?? 0),
    'custo_medio' => (float) ($linha['custo_medio'] ?? 0),
  ];
}

// Acrescenta mês anterior e recorde (mesma lógica de produção do mês)
if ($mes) {
  $mesInt = (int)$mes;
  $mesAnterior = ($mesInt === 1) ? 12 : ($mesInt - 1);
  $anoMesAnterior = ($mesInt === 1) ? ($anoSelecionado - 1) : $anoSelecionado;
  $mesRefAnterior = sprintf('%04d-%02d', $anoMesAnterior, $mesAnterior);

  $fimMesAnteriorDia = cal_days_in_month(CAL_GREGORIAN, $mesAnterior, $anoMesAnterior);
  $fimMesAnteriorData = sprintf('%04d-%02d-%02d', $anoMesAnterior, $mesAnterior, $fimMesAnteriorDia);
  $fimMesAnteriorDataTime = $fimMesAnteriorData . ' 23:59:59';

  // Mês anterior — mesma lógica do mês atual: sem pagos, sem Parcial, COUNT(DISTINCT)
  $sqlAnterior = "SELECT
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END AS nome_funcao,
      COUNT(DISTINCT fi.idfuncao_imagem) AS mes_anterior
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
      AND (
        fi.funcao_id <> 4
        OR LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada'
        OR EXISTS (
          SELECT 1
          FROM funcao_colaborador fc4
          JOIN colaborador c4 ON c4.idcolaborador = fc4.colaborador_id
          WHERE fc4.colaborador_id = fi.colaborador_id
            AND fc4.funcao_id = 4
            AND fc4.colaborador_id IS NOT NULL
            AND c4.ativo = 1
            AND fc4.colaborador_id NOT IN (21, 15, 30, 7, 34)
            AND NOT EXISTS (
              SELECT 1
              FROM funcao_colaborador fc7
              WHERE fc7.colaborador_id = fc4.colaborador_id
                AND fc7.funcao_id = 7
            )
        )
      )
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
    GROUP BY
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END";
  $stmtAnterior = $conn->prepare($sqlAnterior);
  $stmtAnterior->bind_param('iiiisssssss', $mesAnterior, $anoMesAnterior, $mesAnterior, $anoMesAnterior, $fimMesAnteriorDataTime, $fimMesAnteriorDataTime, $fimMesAnteriorDataTime, $fimMesAnteriorData, $fimMesAnteriorData, $fimMesAnteriorData, $fimMesAnteriorData);
  $stmtAnterior->execute();
  $resAnterior = $stmtAnterior->get_result();
  $dadosAnterior = $resAnterior->fetch_all(MYSQLI_ASSOC);

  $anteriorIndexado = [];
  foreach ($dadosAnterior as $linha) {
    $anteriorIndexado[$linha['nome_funcao']] = (int)$linha['mes_anterior'];
  }

  // Itens pagos no mês atual (inverso do filtro nao_pagas)
  $sqlPagas = "SELECT
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END AS nome_funcao,
      COUNT(DISTINCT fi.idfuncao_imagem) AS pagas
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
      AND (
        fi.funcao_id <> 4
        OR LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada'
        OR EXISTS (
          SELECT 1
          FROM funcao_colaborador fc4
          JOIN colaborador c4 ON c4.idcolaborador = fc4.colaborador_id
          WHERE fc4.colaborador_id = fi.colaborador_id
            AND fc4.funcao_id = 4
            AND fc4.colaborador_id IS NOT NULL
            AND c4.ativo = 1
            AND fc4.colaborador_id NOT IN (21, 15, 30, 7, 34)
            AND NOT EXISTS (
              SELECT 1
              FROM funcao_colaborador fc7
              WHERE fc7.colaborador_id = fc4.colaborador_id
                AND fc7.funcao_id = 7
            )
        )
      )
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
            EXISTS (
              SELECT 1 FROM pagamento_itens pi_full
              JOIN funcao_imagem fi_pi4f ON fi_pi4f.idfuncao_imagem = pi_full.origem_id
              WHERE pi_full.origem = 'funcao_imagem'
                AND fi_pi4f.colaborador_id = fi.colaborador_id
                AND fi_pi4f.imagem_id = fi.imagem_id
                AND fi_pi4f.funcao_id = 4
                AND DATE(pi_full.criado_em) <= ?
                AND (pi_full.observacao IS NULL OR TRIM(pi_full.observacao) = '' OR TRIM(pi_full.observacao) = 'Pago Completa')
            )
            OR (
              NOT EXISTS (
                SELECT 1 FROM pagamento_itens pi_any2
                JOIN funcao_imagem fi_pi4b ON fi_pi4b.idfuncao_imagem = pi_any2.origem_id
                WHERE pi_any2.origem = 'funcao_imagem'
                  AND fi_pi4b.colaborador_id = fi.colaborador_id
                  AND fi_pi4b.imagem_id = fi.imagem_id
              )
              AND fi.data_pagamento IS NOT NULL
              AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
              AND fi.data_pagamento <= ?
            )
          )
        )
        OR (
          fi.funcao_id <> 4
          AND (
            EXISTS (
              SELECT 1 FROM pagamento_itens pi_paid
              WHERE pi_paid.origem = 'funcao_imagem'
                AND pi_paid.origem_id = fi.idfuncao_imagem
                AND DATE(pi_paid.criado_em) <= ?
            )
            OR (
              NOT EXISTS (
                SELECT 1 FROM pagamento_itens pi_any3
                WHERE pi_any3.origem = 'funcao_imagem'
                  AND pi_any3.origem_id = fi.idfuncao_imagem
              )
              AND fi.data_pagamento IS NOT NULL
              AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
              AND fi.data_pagamento <= ?
            )
          )
        )
      )
    GROUP BY
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END";
  $stmtPagas = $conn->prepare($sqlPagas);
  $stmtPagas->bind_param('iiiisssssss', $mesInt, $anoSelecionado, $mesInt, $anoSelecionado, $fimMesDataTime, $fimMesDataTime, $fimMesDataTime, $fimMesData, $fimMesData, $fimMesData, $fimMesData);
  $stmtPagas->execute();
  $pagasIndexado = [];
  foreach ($stmtPagas->get_result()->fetch_all(MYSQLI_ASSOC) as $linha) {
    $pagasIndexado[$linha['nome_funcao']] = (int)$linha['pagas'];
  }

  // Recorde — UNION log_alteracoes + fi.prazo, janela 36 meses, sem pagos,
  // sem Finalização Parcial ($IS_PARCIAL_AT_PERIOD), exclui 2024-10, COUNT(DISTINCT).
  // Alinhado com carregar_dados.php queries Q4/Q5.
  $sqlRecorde = "SELECT nome_funcao, MAX(qtd_mes) AS recorde
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
        AND (
          fi.funcao_id <> 4
          OR LOWER(TRIM(ico.tipo_imagem)) = 'planta humanizada'
          OR EXISTS (
            SELECT 1
            FROM funcao_colaborador fc4
            JOIN colaborador c4 ON c4.idcolaborador = fc4.colaborador_id
            WHERE fc4.colaborador_id = fi.colaborador_id
              AND fc4.funcao_id = 4
              AND fc4.colaborador_id IS NOT NULL
              AND c4.ativo = 1
              AND fc4.colaborador_id NOT IN (21, 15, 30, 7, 34)
              AND NOT EXISTS (
                SELECT 1
                FROM funcao_colaborador fc7
                WHERE fc7.colaborador_id = fc4.colaborador_id
                  AND fc7.funcao_id = 7
              )
          )
        )
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
    GROUP BY nome_funcao";
  $stmtRecorde = $conn->prepare($sqlRecorde);
  $stmtRecorde->execute();
  $resRecorde = $stmtRecorde->get_result();
  $dadosRecorde = $resRecorde->fetch_all(MYSQLI_ASSOC);

  $recordeIndexado = [];
  foreach ($dadosRecorde as $linha) {
    $recordeIndexado[$linha['nome_funcao']] = (int)$linha['recorde'];
  }

  // Planta Humanizada (funcao_id=7) e Finalização de Planta Humanizada (funcao_id=4)
  // compartilham o mesmo recorde — usa o maior valor entre os dois.
  $recPH  = $recordeIndexado['Planta Humanizada'] ?? 0;
  $recFPH = $recordeIndexado['Finalização de Planta Humanizada'] ?? 0;
  $mergedRec = max($recPH, $recFPH);
  $recordeIndexado['Planta Humanizada']                = $mergedRec;
  $recordeIndexado['Finalização de Planta Humanizada'] = $mergedRec;

  // Index nao_pagas (quantidade atual = itens não pagos)
  $naoPagasIndexado = [];
  foreach ($dados as $linha) {
    $naoPagasIndexado[$linha['nome_funcao']] = (int)$linha['quantidade'];
  }

  // Reconstrói $dados garantindo que todas as funções apareçam, mesmo com produção zero
  $funcoesOrdem = ['Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Finalização Completa', 'Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'];
  $dados = [];
  foreach ($funcoesOrdem as $nomeFuncao) {
    $naoPagas = $naoPagasIndexado[$nomeFuncao] ?? 0;
    $pagas = $pagasIndexado[$nomeFuncao] ?? 0;
    $quantidade = $pagas + $naoPagas;
    $mesAnteriorVal = $anteriorIndexado[$nomeFuncao] ?? 0;
    $recordeVal = $recordeIndexado[$nomeFuncao] ?? 0;
    $recordeProducao = $recordeVal;
    $custoTotal = $custoIndexado[$nomeFuncao]['custo_total'] ?? 0.0;
    $custoMedio = $custoIndexado[$nomeFuncao]['custo_medio'] ?? 0.0;
    $dados[] = [
      'nome_funcao'     => $nomeFuncao,
      'quantidade'      => $quantidade,
      'pagas'           => $pagas,
      'nao_pagas'       => $naoPagas,
      'mes_anterior'    => $mesAnteriorVal,
      'recorde_producao'=> $recordeProducao,
      'custo_total'     => round($custoTotal, 2),
      'custo_medio'     => round($custoMedio, 2),
      'bate_recorde'    => $naoPagas > $recordeProducao,
    ];
  }
} else {
  // Mantém campos existentes nos outros filtros para não quebrar o front.
  foreach ($dados as &$linha) {
    if (!isset($linha['mes_anterior'])) {
      $linha['mes_anterior'] = 0;
    }
    if (!isset($linha['recorde_producao'])) {
      $linha['recorde_producao'] = isset($linha['quantidade']) ? (int)$linha['quantidade'] : 0;
    }
    if (!isset($linha['custo_total'])) {
      $linha['custo_total'] = 0.0;
    }
    if (!isset($linha['custo_medio'])) {
      $linha['custo_medio'] = 0.0;
    }
  }
  unset($linha);
}

// Retorna os dados em formato JSON
echo json_encode($dados);
