<?php
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli

// Pega o mês atual selecionado
$mes = $_GET['mes'] ?? date('m');

// 1. Busca os dados do mês selecionado
$sql = "SELECT 
    f.nome_funcao, 
    COUNT(*) AS quantidade
  FROM funcao_imagem fi 
  JOIN funcao f ON f.idfuncao = fi.funcao_id
  WHERE MONTH(fi.prazo) = ? AND YEAR(fi.prazo) = YEAR(CURDATE()) AND (status = 'Finalizado' OR status = 'Em aprovação' OR status = 'Ajuste' OR status = 'Aprovado')
  GROUP BY f.nome_funcao";
$stmt = $conn->prepare($sql); // Usa a conexão do arquivo conexao.php
$stmt->bind_param("i", $mes);
$stmt->execute();
$result = $stmt->get_result();
$dadosMesAtual = $result->fetch_all(MYSQLI_ASSOC);

$resultado = [];
foreach ($dadosMesAtual as $linha) {
    $colaborador = $linha['nome_colaborador'];
    $funcao = $linha['nome_funcao'];
    $chave = $colaborador . '_' . $funcao;


    $resultado[] = $linha;
}

echo json_encode($resultado);
