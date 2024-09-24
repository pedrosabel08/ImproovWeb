<?php
// Inclui o arquivo de conexão
include 'conexao.php';

header('Content-Type: application/json');

$result = $conn->query("SELECT imagem_nome, prazo FROM imagens_cliente_obra");

$events = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'title' => $row['imagem_nome'], 
            'start' => $row['prazo']       
        ];
    }
} else {
    echo json_encode(["error" => "Erro ao buscar os dados: " . $conn->error]);
    exit;
}

echo json_encode($events);

$conn->close();
