<?php
// Configuração de cabeçalhos para o retorno JSON
header('Content-Type: application/json');

include '../conexao.php';

// Obtém o ID do colaborador passado via query string
$idcolaborador = isset($_GET['idcolaborador']) ? intval($_GET['idcolaborador']) : 0;

if ($idcolaborador <= 0) {
    echo json_encode(['error' => 'ID do colaborador inválido']);
    exit;
}

// Consulta SQL para obter os dados do gráfico
$sql_grafico = "SELECT 
        COUNT(*) AS imagens, 
        SUM(valor) AS total, 
        MONTH(prazo) AS mes 
    FROM funcao_imagem 
    WHERE colaborador_id = ? 
    GROUP BY MONTH(prazo)
";

$stmt_grafico = $conn->prepare($sql_grafico);
if (!$stmt_grafico) {
    echo json_encode(['error' => 'Erro na preparação da consulta SQL: ' . $conn->error]);
    exit;
}

// Vincula o parâmetro e executa a consulta
$stmt_grafico->bind_param('i', $idcolaborador);
$stmt_grafico->execute();
$result_grafico = $stmt_grafico->get_result();

// Processa os resultados
$data = [];
while ($row = $result_grafico->fetch_assoc()) {
    $data[] = [
        'mes' => $row['mes'],
        'imagens' => $row['imagens'],
        'total' => $row['total'],
    ];
}

// Retorna os dados em formato JSON
echo json_encode($data);

// Fecha a conexão
$stmt_grafico->close();
$conn->close();
