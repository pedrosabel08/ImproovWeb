<?php
// Inclui o arquivo de conexão
include 'conexao.php';

header('Content-Type: application/json');

$result = $conn->query("
    SELECT obra_prazo.prazo, obra_prazo.tipo_entrega, obra.nome_obra 
    FROM obra_prazo 
    JOIN obra ON obra_prazo.obra_id = obra.idobra
");

$events = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'title' => $row['nome_obra'] . ' - ' . $row['tipo_entrega'],
            'start' => $row['prazo'],      
            'allDay' => true
        ];
    }
} else {
    echo json_encode(["error" => "Erro ao buscar os dados: " . $conn->error]);
    exit;
}

echo json_encode($events);

$conn->close();