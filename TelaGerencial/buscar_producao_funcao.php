<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Verifica os parâmetros recebidos
$mes = $_GET['mes'] ?? null;
$data = $_GET['data'] ?? null;
$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;

$anoSelecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

if ($mes) {
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
    SELECT fi.idfuncao_imagem, fi.funcao_id, fi.pagamento, fi.imagem_id, f.nome_funcao AS original_funcao, ico.status_id,
      CASE
        WHEN fi.funcao_id = 4 AND LOWER(ico.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
        WHEN fi.funcao_id = 4 AND (
          EXISTS (
            SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao
            WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%'
          ) OR ico.status_id = 1
        ) THEN 'Finalização Parcial'
        WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
        ELSE f.nome_funcao
      END AS nome_funcao

    FROM funcao_imagem fi
    JOIN funcao f ON f.idfuncao = fi.funcao_id
    JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    WHERE MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = ? AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
  ) AS t
  GROUP BY t.nome_funcao
  ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
    funcao_order";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $mes, $anoSelecionado);
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
      JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE DATE(fi.prazo) = ? AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
    ) AS t
    GROUP BY t.nome_funcao
    ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
      funcao_order";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $data);
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
      JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE DATE(fi.prazo) BETWEEN ? AND ? AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
    ) AS t
    GROUP BY t.nome_funcao
    ORDER BY
      FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
      funcao_order";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ss", $inicio, $fim);
} else {
  // Caso nenhum parâmetro válido seja enviado
  echo json_encode(["error" => "Parâmetros inválidos"]);
  exit;
}

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();
$dados = $result->fetch_all(MYSQLI_ASSOC);

// Acrescenta mês anterior e recorde (com base em fi.data_pagamento)
// Obs: mantemos a contagem "atual" conforme o filtro (prazo/data/intervalo),
// mas mês anterior e recorde usam data_pagamento.
if ($mes) {
  $mesInt = (int)$mes;
  $mesAnterior = ($mesInt === 1) ? 12 : ($mesInt - 1);
  $anoMesAnterior = ($mesInt === 1) ? ($anoSelecionado - 1) : $anoSelecionado;
  $mesRefAnterior = sprintf('%04d-%02d', $anoMesAnterior, $mesAnterior);

  $nomeFuncaoCase = "CASE
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

  // Mês anterior por data_pagamento
  $sqlAnterior = "SELECT t.nome_funcao, COUNT(*) AS mes_anterior
    FROM (
      SELECT $nomeFuncaoCase AS nome_funcao
      FROM funcao_imagem fi
      JOIN funcao f ON f.idfuncao = fi.funcao_id
      JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
      WHERE fi.data_pagamento IS NOT NULL
        AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
        AND MONTH(fi.data_pagamento) = ?
        AND YEAR(fi.data_pagamento) = ?
        AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
    ) AS t
    GROUP BY t.nome_funcao";
  $stmtAnterior = $conn->prepare($sqlAnterior);
  $stmtAnterior->bind_param('ii', $mesAnterior, $anoMesAnterior);
  $stmtAnterior->execute();
  $resAnterior = $stmtAnterior->get_result();
  $dadosAnterior = $resAnterior->fetch_all(MYSQLI_ASSOC);

  $anteriorIndexado = [];
  foreach ($dadosAnterior as $linha) {
    $anteriorIndexado[$linha['nome_funcao']] = (int)$linha['mes_anterior'];
  }

  // Regra especial: "Finalização Parcial" deve vir de pagamento_itens.observacao
  // (conforme mark_finalizacao_parcial.php), usando pagamentos.mes_ref
  $sqlAnteriorParcial = "SELECT COUNT(*) AS mes_anterior
    FROM pagamento_itens pi
    JOIN pagamentos p ON p.idpagamento = pi.pagamento_id
    WHERE p.mes_ref = ?
      AND pi.origem = 'funcao_imagem'
      AND pi.observacao = 'Finalização Parcial'";
  $stmtAnteriorParcial = $conn->prepare($sqlAnteriorParcial);
  if ($stmtAnteriorParcial) {
    $stmtAnteriorParcial->bind_param('s', $mesRefAnterior);
    $stmtAnteriorParcial->execute();
    $resParcial = $stmtAnteriorParcial->get_result();
    $rowParcial = $resParcial ? $resParcial->fetch_assoc() : null;
    $anteriorIndexado['Finalização Parcial'] = isset($rowParcial['mes_anterior']) ? (int)$rowParcial['mes_anterior'] : 0;
    $stmtAnteriorParcial->close();
  }

  // Recorde por data_pagamento (maior produção em um mês, por função)
  $sqlRecorde = "SELECT nome_funcao, MAX(qtd_mes) AS recorde
    FROM (
      SELECT t.nome_funcao,
             YEAR(t.data_pagamento) AS ano,
             MONTH(t.data_pagamento) AS mes,
             COUNT(*) AS qtd_mes
      FROM (
        SELECT $nomeFuncaoCase AS nome_funcao, fi.data_pagamento
        FROM funcao_imagem fi
        JOIN funcao f ON f.idfuncao = fi.funcao_id
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        WHERE fi.data_pagamento IS NOT NULL
          AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
          AND (fi.status <> 'Não iniciado' OR fi.status IS NULL)
      ) AS t
      GROUP BY t.nome_funcao, YEAR(t.data_pagamento), MONTH(t.data_pagamento)
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

  // Regra especial: recorde de "Finalização Parcial" via pagamento_itens.observacao por mês (pagamentos.mes_ref)
  $sqlRecordeParcial = "SELECT MAX(qtd_mes) AS recorde
    FROM (
      SELECT p.mes_ref, COUNT(*) AS qtd_mes
      FROM pagamento_itens pi
      JOIN pagamentos p ON p.idpagamento = pi.pagamento_id
      WHERE pi.origem = 'funcao_imagem'
        AND pi.observacao = 'Finalização Parcial'
      GROUP BY p.mes_ref
    ) AS x";
  $stmtRecordeParcial = $conn->prepare($sqlRecordeParcial);
  if ($stmtRecordeParcial) {
    $stmtRecordeParcial->execute();
    $resRecParcial = $stmtRecordeParcial->get_result();
    $rowRecParcial = $resRecParcial ? $resRecParcial->fetch_assoc() : null;
    $recordeIndexado['Finalização Parcial'] = isset($rowRecParcial['recorde']) ? (int)$rowRecParcial['recorde'] : 0;
    $stmtRecordeParcial->close();
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
