<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Verifica os parâmetros recebidos
$mes = $_GET['mes'] ?? null;
$data = $_GET['data'] ?? null;
$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;

$anoSelecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

if ($mes) {
  $mesInt = (int)$mes;
  $mesRef = sprintf('%04d-%02d', $anoSelecionado, $mesInt);

  $fimMesDia = cal_days_in_month(CAL_GREGORIAN, $mesInt, $anoSelecionado);
  $fimMesData = sprintf('%04d-%02d-%02d', $anoSelecionado, $mesInt, $fimMesDia);
  $fimMesDataTime = $fimMesData . ' 23:59:59';
  // Filtro por mês
  // Usamos uma subquery para calcular "nome_funcao" por linha (inclui a verificação de Pré-Finalização)
  // e então agregamos por esse nome no nível externo. Isso evita problemas com ONLY_FULL_GROUP_BY
  $sql = "SELECT 
    COUNT(*) AS quantidade,
    SUM(CASE WHEN t.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
    SUM(CASE WHEN t.pagamento <> 1 OR t.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas,
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
          SELECT 1
          FROM log_alteracoes la
          WHERE la.funcao_imagem_id = fi.idfuncao_imagem
            AND MONTH(la.data) = ?
            AND YEAR(la.data) = ?
        )
        OR (MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?)
      )
      AND (
        LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
        OR EXISTS (
          SELECT 1
          FROM log_alteracoes la_fin
          WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
            AND la_fin.data <= ?
            AND (
              LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
              OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            )
        )
      )
      AND fi.colaborador_id NOT IN (21, 15)
  ) AS t
  GROUP BY t.nome_funcao
  ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
    funcao_order";
  $stmt = $conn->prepare($sql);
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
} elseif ($data) {
  // Filtro por dia específico - calcular nome_funcao por linha e agregar externamente
  $sql = "SELECT COUNT(*) AS quantidade, t.nome_funcao, MIN(t.funcao_id) AS funcao_order
    FROM (
      SELECT fi.funcao_id, fi.imagem_id, fi.pagamento, f.nome_funcao,
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
  // Filtro por intervalo de semana - calcular nome_funcao por linha e agregar externamente
  $sql = "SELECT COUNT(*) AS quantidade, t.nome_funcao, MIN(t.funcao_id) AS funcao_order
    FROM (
      SELECT fi.funcao_id, fi.imagem_id, fi.pagamento, f.nome_funcao,
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

// Acrescenta mês anterior e recorde (mesma lógica de produção do mês)
if ($mes) {
  $mesInt = (int)$mes;
  $mesAnterior = ($mesInt === 1) ? 12 : ($mesInt - 1);
  $anoMesAnterior = ($mesInt === 1) ? ($anoSelecionado - 1) : $anoSelecionado;
  $mesRefAnterior = sprintf('%04d-%02d', $anoMesAnterior, $mesAnterior);

  $fimMesAnteriorDia = cal_days_in_month(CAL_GREGORIAN, $mesAnterior, $anoMesAnterior);
  $fimMesAnteriorData = sprintf('%04d-%02d-%02d', $anoMesAnterior, $mesAnterior, $fimMesAnteriorDia);
  $fimMesAnteriorDataTime = $fimMesAnteriorData . ' 23:59:59';

  $nomeFuncaoCaseAnterior = "CASE
        WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 AND (
          hi_prev.status_id = 1
          OR (
            hi_prev.status_id IS NULL AND (
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
      END";

  // Mês anterior (mesma regra do mês atual: log_alteracoes OU prazo)
  $sqlAnterior = "SELECT t.nome_funcao, COUNT(*) AS mes_anterior
    FROM (
      SELECT $nomeFuncaoCaseAnterior AS nome_funcao
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
            SELECT 1
            FROM log_alteracoes la
            WHERE la.funcao_imagem_id = fi.idfuncao_imagem
              AND MONTH(la.data) = ?
              AND YEAR(la.data) = ?
          )
          OR (MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ?)
        )
        AND (
          LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          OR EXISTS (
            SELECT 1
            FROM log_alteracoes la_fin
            WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
              AND la_fin.data <= ?
              AND (
                LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
                OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
              )
          )
        )
        AND fi.colaborador_id NOT IN (21, 15)
    ) AS t
    GROUP BY t.nome_funcao";
  $stmtAnterior = $conn->prepare($sqlAnterior);
  $stmtAnterior->bind_param('siiiis', $fimMesAnteriorDataTime, $mesAnterior, $anoMesAnterior, $mesAnterior, $anoMesAnterior, $fimMesAnteriorDataTime);
  $stmtAnterior->execute();
  $resAnterior = $stmtAnterior->get_result();
  $dadosAnterior = $resAnterior->fetch_all(MYSQLI_ASSOC);

  $anteriorIndexado = [];
  foreach ($dadosAnterior as $linha) {
    $anteriorIndexado[$linha['nome_funcao']] = (int)$linha['mes_anterior'];
  }

  $nomeFuncaoCaseRecorde = "CASE
        WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 AND (
          EXISTS (
            SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
            WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
          ) OR ico.status_id = 1
        ) THEN 'Finalização Parcial'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END";

  // Recorde: maior produção em um mês (por prazo, para manter consistência)
  $sqlRecorde = "SELECT nome_funcao, MAX(qtd_mes) AS recorde
    FROM (
      SELECT t.nome_funcao,
             YEAR(t.prazo) AS ano,
             MONTH(t.prazo) AS mes,
             COUNT(*) AS qtd_mes
      FROM (
        SELECT $nomeFuncaoCaseRecorde AS nome_funcao, fi.prazo
        FROM funcao_imagem fi
        JOIN funcao f ON f.idfuncao = fi.funcao_id
        LEFT JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        WHERE fi.prazo IS NOT NULL
          AND (
            fi.status = 'Finalizado' OR fi.status = 'Em aprovação' OR fi.status = 'Ajuste' OR fi.status = 'Aprovado com ajustes' OR fi.status = 'Aprovado'
          )
          AND fi.colaborador_id NOT IN (21, 15)
      ) AS t
      GROUP BY t.nome_funcao, YEAR(t.prazo), MONTH(t.prazo)
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

  foreach ($dados as &$linha) {
    $nomeFuncao = $linha['nome_funcao'];
    $quantidadeAtual = isset($linha['quantidade']) ? (int)$linha['quantidade'] : 0;
    $mesAnteriorVal = $anteriorIndexado[$nomeFuncao] ?? 0;
    $recordeVal = $recordeIndexado[$nomeFuncao] ?? 0;

    $linha['mes_anterior'] = $mesAnteriorVal;
    $linha['recorde_producao'] = max($recordeVal, $quantidadeAtual);
  }
  unset($linha);
} else {
  // Mantém campos existentes nos outros filtros para não quebrar o front.
  foreach ($dados as &$linha) {
    if (!isset($linha['mes_anterior'])) {
      $linha['mes_anterior'] = 0;
    }
    if (!isset($linha['recorde_producao'])) {
      $linha['recorde_producao'] = isset($linha['quantidade']) ? (int)$linha['quantidade'] : 0;
    }
  }
  unset($linha);
}

// Retorna os dados em formato JSON
echo json_encode($dados);
