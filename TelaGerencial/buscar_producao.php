<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Ajusta sql_mode para evitar erros com ONLY_FULL_GROUP_BY em consultas complexas
// (remove temporariamente ONLY_FULL_GROUP_BY para esta sessão)
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

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
  t.nome_colaborador,
  t.funcao_id,
  t.nome_funcao,
  COUNT(*) AS quantidade,
  GROUP_CONCAT(DISTINCT CONCAT(IFNULL(t.imagem_id,''), ':::', IFNULL(t.imagem_nome,''), ':::', IFNULL(t.pagamento,0)) SEPARATOR '|||') AS imagens_concat,
  SUM(CASE WHEN t.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
  SUM(CASE WHEN t.pagamento <> 1 OR t.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas
FROM (
  SELECT
    c.nome_colaborador,
    f.idfuncao AS funcao_id,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        hi_snap.status_id = 1
        OR (
          hi_snap.status_id IS NULL AND (
            EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
            OR i.status_id = 1
          )
        )
      ) THEN 'Finalização Parcial'
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
GROUP BY t.funcao_id, t.nome_funcao, t.nome_colaborador
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

// 2. Busca as quantidades do mês anterior (mesma regra de produção do mês: log_alteracoes OU prazo)
$fimMesAnteriorDia = cal_days_in_month(CAL_GREGORIAN, $mesAnterior, $anoMesAnterior);
$fimMesAnteriorData = sprintf('%04d-%02d-%02d', $anoMesAnterior, $mesAnterior, $fimMesAnteriorDia);
$fimMesAnteriorDataTime = $fimMesAnteriorData . ' 23:59:59';
$sqlAnterior = "SELECT
  t.nome_colaborador,
  t.nome_funcao,
  COUNT(*) AS qtd_mes_anterior
FROM (
  SELECT
    c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND hi_prev.status_id = 1 THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao
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
  ) hi_prev ON hi_prev.imagem_id = i.idimagens_cliente_obra
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
GROUP BY t.nome_funcao, t.nome_colaborador;";
$stmtAnterior = $conn->prepare($sqlAnterior);
$stmtAnterior->bind_param(
  "siiiis",
  $fimMesAnteriorDataTime,
  $mesAnterior,
  $anoMesAnterior,
  $mesAnterior,
  $anoMesAnterior,
  $fimMesAnteriorDataTime
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
// Recorde: maior quantidade em um mês (por prazo, para ficar consistente com o filtro mensal)
$sqlRecorde = "SELECT nome_colaborador, nome_funcao, MAX(qtd_mes) AS recorde
FROM (
  SELECT
    c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        SELECT h.status_id
        FROM historico_imagens h
        WHERE h.imagem_id = fi.imagem_id
          AND h.data_movimento <= CONCAT(LAST_DAY(DATE(CONCAT(YEAR(fi.prazo), '-', LPAD(MONTH(fi.prazo),2,'0'), '-01'))), ' 23:59:59')
        ORDER BY h.data_movimento DESC
        LIMIT 1
      ) = 1 THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao,
    YEAR(fi.prazo) AS ano,
    MONTH(fi.prazo) AS mes,
    COUNT(*) AS qtd_mes
  FROM funcao_imagem fi
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
  WHERE fi.prazo IS NOT NULL
    AND (
      LOWER(TRIM(fi.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
      OR EXISTS (
        SELECT 1
        FROM log_alteracoes la_fin
        WHERE la_fin.funcao_imagem_id = fi.idfuncao_imagem
          AND la_fin.data <= CONCAT(LAST_DAY(DATE(CONCAT(YEAR(fi.prazo), '-', LPAD(MONTH(fi.prazo),2,'0'), '-01'))), ' 23:59:59')
          AND (
            LOWER(TRIM(la_fin.status_novo)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
            OR LOWER(TRIM(la_fin.status_anterior)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
          )
      )
    )
    AND fi.colaborador_id NOT IN (21, 15)
  GROUP BY c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        SELECT h.status_id
        FROM historico_imagens h
        WHERE h.imagem_id = fi.imagem_id
          AND h.data_movimento <= CONCAT(LAST_DAY(DATE(CONCAT(YEAR(fi.prazo), '-', LPAD(MONTH(fi.prazo),2,'0'), '-01'))), ' 23:59:59')
        ORDER BY h.data_movimento DESC
        LIMIT 1
      ) = 1 THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END,
    YEAR(fi.prazo), MONTH(fi.prazo)
) AS sub
GROUP BY nome_colaborador, nome_funcao;";


$resultRecorde = $conn->query($sqlRecorde);
$recordes = $resultRecorde ? $resultRecorde->fetch_all(MYSQLI_ASSOC) : [];

// Indexa os recordes
$recordeIndexado = [];
foreach ($recordes as $linha) {
  $chave = $linha['nome_colaborador'] . '_' . $linha['nome_funcao'];
  $recordeIndexado[$chave] = $linha['recorde'];
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
      $imagens[] = [
        'imagem_id' => is_numeric($imagemIdRaw) ? (int)$imagemIdRaw : $imagemIdRaw,
        'imagem_nome' => $imagemNome,
        'pago' => (int)$imagemPagoRaw,
      ];
    }
  }
  $linha['imagens'] = $imagens;
  unset($linha['imagens_concat']);

  $linha['mes_anterior'] = $anteriorIndexado[$chave] ?? 0;
  $quantidadeAtual = isset($linha['quantidade']) ? (int)$linha['quantidade'] : 0;
  // Pegue o maior entre: recorde histórico, mês anterior e mês atual
  $recorde = $recordeIndexado[$chave] ?? 0;
  $linha['recorde_producao'] = max((int)$recorde, (int)($linha['mes_anterior'] ?? 0), $quantidadeAtual);

  $resultado[] = $linha;
}

echo json_encode($resultado);
