<?php
// Database connection

include 'conexao.php';
// Prepare and bind
$stmt = $conn->prepare("SELECT l.funcao_imagem_id, l.status_anterior, l.status_novo, l.data, i.imagem_nome, o.nome_obra, fun.nome_funcao
                        FROM log_alteracoes l
                        INNER JOIN funcao_imagem f on f.idfuncao_imagem = l.funcao_imagem_id 
                        INNER JOIN imagens_cliente_obra i on f.imagem_id = i.idimagens_cliente_obra
                        LEFT JOIN funcao fun on f.funcao_id = fun.idfuncao
                        INNER JOIN obra o on i.obra_id = o.idobra
                        WHERE l.funcao_imagem_id = ?");
$stmt->bind_param("i", $funcao_imagem_id);

// Set parameters and execute
$funcao_imagem_id = $_GET['funcao_imagem_id'];
$stmt->execute();

// Bind result variables
$stmt->bind_result($funcao_imagem_id, $status_anterior, $status_novo, $data, $imagem_nome, $nome_obra, $nome_funcao);

$logs = [];

// Fetch values
while ($stmt->fetch()) {
    $logs[] = [
        'funcao_imagem_id' => $funcao_imagem_id,
        'status_anterior' => $status_anterior,
        'status_novo' => $status_novo,
        'data' => $data,
        'imagem_nome' => $imagem_nome,
        'nome_obra' => $nome_obra,
        'nome_funcao' => $nome_funcao,
    ];
}

// Close statement and connection
$stmt->close();
$conn->close();

// Set content type to JSON and output the logs
header('Content-Type: application/json');
echo json_encode($logs);
