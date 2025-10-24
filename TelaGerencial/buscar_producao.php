<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Pega o mês atual selecionado
$mes = $_GET['mes'] ?? date('m');
$mesAnterior = ($mes == 1) ? 12 : $mes - 1;

// 1. Busca os dados do mês selecionado
$sql = "SELECT 
    c.nome_colaborador, 
    f.nome_funcao, 
    -- SUM(fi.valor) AS total_valor, 
    -- fi.data_pagamento,
    COUNT(*) AS quantidade,
    SUM(CASE WHEN fi.pagamento = 1 THEN 1 ELSE 0 END) AS pagas,
    SUM(CASE WHEN fi.pagamento <> 1 OR fi.pagamento IS NULL THEN 1 ELSE 0 END) AS nao_pagas
  FROM funcao_imagem fi 
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id 
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  WHERE MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = YEAR(CURDATE()) AND fi.colaborador_id <> 21
  GROUP BY c.nome_colaborador, f.nome_funcao";
$stmt = $conn->prepare($sql); // Usa a conexão do arquivo conexao.php
$stmt->bind_param("i", $mes);
$stmt->execute();
$result = $stmt->get_result();
$dadosMesAtual = $result->fetch_all(MYSQLI_ASSOC);

// 2. Busca as quantidades do mês anterior
$sqlAnterior = "SELECT 
    c.nome_colaborador, 
    f.nome_funcao, 
    COUNT(*) AS qtd_mes_anterior
  FROM funcao_imagem fi 
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id 
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  WHERE MONTH(fi.data_pagamento) = ? AND YEAR(fi.data_pagamento) = YEAR(CURDATE())
  GROUP BY c.nome_colaborador, f.nome_funcao";
$stmtAnterior = $conn->prepare($sqlAnterior);
$stmtAnterior->bind_param("i", $mesAnterior);
$stmtAnterior->execute();
$resultAnterior = $stmtAnterior->get_result();
$dadosAnteriores = $resultAnterior->fetch_all(MYSQLI_ASSOC);

// Indexa os dados do mês anterior para acesso rápido
$anteriorIndexado = [];
foreach ($dadosAnteriores as $linha) {
  $chave = $linha['nome_colaborador'] . '_' . $linha['nome_funcao'];
  $anteriorIndexado[$chave] = $linha['qtd_mes_anterior'];
}

// 3. Busca o recorde geral de cada colaborador
$sqlRecorde = "SELECT 
  nome_colaborador, 
  nome_funcao,
  MAX(qtd_mes) AS recorde
FROM (
  SELECT 
    c.nome_colaborador, 
    f.nome_funcao,
    COUNT(*) AS qtd_mes
  FROM funcao_imagem fi
  JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  WHERE fi.data_pagamento <> '0000-00-00' AND fi.valor > 1
  GROUP BY c.nome_colaborador, f.nome_funcao, MONTH(fi.data_pagamento), YEAR(fi.data_pagamento)
) AS sub
GROUP BY nome_colaborador, nome_funcao";


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
  $funcao = $linha['nome_funcao'];
  $chave = $colaborador . '_' . $funcao;

  $linha['mes_anterior'] = $anteriorIndexado[$chave] ?? 0;
  $linha['recorde_producao'] = $recordeIndexado[$chave] ?? '-';

  $resultado[] = $linha;
}

echo json_encode($resultado);
