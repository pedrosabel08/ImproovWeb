<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Ajusta sql_mode para evitar erros com ONLY_FULL_GROUP_BY em consultas complexas
// (remove temporariamente ONLY_FULL_GROUP_BY para esta sessão)
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

// Pega o mês atual selecionado
$mes = $_GET['mes'] ?? date('m');
$mesAnterior = ($mes == 1) ? 12 : $mes - 1;

// 1. Busca os dados do mês selecionado
$sql = "SELECT
  t.nome_colaborador,
  t.funcao_id,
  t.nome_funcao,
  COUNT(*) AS quantidade,
  SUM(CASE WHEN t.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
  SUM(CASE WHEN t.pagamento <> 1 OR t.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas
FROM (
  SELECT
    c.nome_colaborador,
    f.idfuncao AS funcao_id,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
        OR i.status_id = 1
      ) THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao,
    fi.pagamento
  FROM funcao_imagem fi
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
  WHERE MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = YEAR(CURDATE()) AND fi.colaborador_id <> 21
) AS t
GROUP BY t.funcao_id, t.nome_funcao, t.nome_colaborador
ORDER BY
  FIELD(t.nome_funcao, 'Caderno', 'Filtro de assets', 'Modelagem', 'Composição', 'Pré-finalização', 'Finalização Parcial','Finalização Completa','Finalização de Planta Humanizada', 'Pós-produção', 'Alteração'),
  t.nome_colaborador;";
$stmt = $conn->prepare($sql); // Usa a conexão do arquivo conexao.php
$stmt->bind_param("i", $mes);
$stmt->execute();
$result = $stmt->get_result();
$dadosMesAtual = $result->fetch_all(MYSQLI_ASSOC);

// 2. Busca as quantidades do mês anterior (usando data_pagamento) e com mesmas categorias de finalização
$sqlAnterior = "SELECT
  t.nome_colaborador,
  t.nome_funcao,
  COUNT(*) AS qtd_mes_anterior
FROM (
  SELECT
    c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
        OR i.status_id = 1
      ) THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao
  FROM funcao_imagem fi
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
  WHERE MONTH(fi.data_pagamento) = ? AND YEAR(fi.data_pagamento) = YEAR(CURDATE()) AND fi.colaborador_id <> 21
) AS t
GROUP BY t.nome_funcao, t.nome_colaborador;";
$stmtAnterior = $conn->prepare($sqlAnterior);
$stmtAnterior->bind_param("i", $mesAnterior);
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
// Recorde: calcular usando a mesma categorização (Finalização de Planta Humanizada / Parcial / Completa)
$sqlRecorde = "SELECT nome_colaborador, nome_funcao, MAX(qtd_mes) AS recorde
FROM (
  SELECT
    c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
        OR i.status_id = 1
      ) THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END AS nome_funcao,
    COUNT(*) AS qtd_mes
  FROM funcao_imagem fi
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra
  WHERE fi.data_pagamento IS NOT NULL
    AND CAST(fi.data_pagamento AS CHAR) <> '0000-00-00'
    AND fi.valor > 1
  GROUP BY c.nome_colaborador,
    CASE
      WHEN fi.funcao_id = 4 AND LOWER(i.tipo_imagem) = 'planta humanizada' THEN 'Finalização de Planta Humanizada'
      WHEN fi.funcao_id = 4 AND (
        EXISTS (SELECT 1 FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND LOWER(f_sub.nome_funcao) LIKE '%pre%')
        OR i.status_id = 1
      ) THEN 'Finalização Parcial'
      WHEN fi.funcao_id = 4 THEN 'Finalização Completa'
      ELSE f.nome_funcao
    END,
    MONTH(fi.data_pagamento), YEAR(fi.data_pagamento)
) AS sub
GROUP BY nome_colaborador, nome_funcao;";


$resultRecorde = $conn->query($sqlRecorde);
$recordes = $resultRecorde->fetch_all(MYSQLI_ASSOC);

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

  $linha['mes_anterior'] = $anteriorIndexado[$chave] ?? 0;
  $linha['recorde_producao'] = $recordeIndexado[$chave] ?? '-';

  $resultado[] = $linha;
}

echo json_encode($resultado);
