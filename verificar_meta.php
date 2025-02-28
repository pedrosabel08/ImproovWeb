<?php

include 'conexao.php';

// Consulta para verificar se existe alguma nova meta registrada
$sql = "SELECT m.*, c.nome_colaborador, f.nome_funcao FROM metas_registradas m 
    JOIN colaborador c ON m.colaborador_id = c.idcolaborador 
    JOIN funcao f ON m.funcao_id = f.idfuncao 
    WHERE data_registro >= NOW() - INTERVAL 5 MINUTE";  // Ajuste o intervalo conforme necessário
$result = $conn->query($sql);

$response = [];
if ($result->num_rows > 0) {
    // Se existe, significa que uma meta foi inserida recentemente
    $row = $result->fetch_assoc();

    // Monta a frase com as informações do colaborador, função e meta atingida
    $message = "A meta de {$row['total']} foi atingida por {$row['nome_colaborador']} na função {$row['nome_funcao']}.";

    $response = ["success" => true, "message" => $message];
} else {
    $response = ["success" => false, "message" => "Nenhuma nova meta"];
}

echo json_encode($response);  // Retorna a resposta para o front-end

$conn->close();
