<?php
// Inclui o arquivo de conexão
include 'conexao.php';

header('Content-Type: application/json');

$result = $conn->query("
    SELECT obra_prazo.prazo, obra_prazo.tipo_entrega, obra.nome_obra, obra_prazo.assunto_entrega
    FROM obra_prazo 
    JOIN obra ON obra_prazo.obra_id = obra.idobra
");

$events = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Definir cor do evento com base no tipo de entrega
        $color = '';
        if ($row['tipo_entrega'] == 'Primeira Entrega') {
            $color = '#03b6fc'; // Verde para "Primeira Entrega"
        } elseif ($row['tipo_entrega'] == 'Entrega Final') {
            $color = '#28a745'; // Vermelho para "Entrega Final"
        } elseif ($row['tipo_entrega'] == 'Alteração') {
            $color = '#ffc107'; // Amarelo para "Alteração"
        }

        $events[] = [
            'title' => $row['nome_obra'] . ' - ' . $row['assunto_entrega'],
            'start' => $row['prazo'],
            'color' => $color,  // Definir cor do evento
            'allDay' => true
        ];
    }
} else {
    echo json_encode(["error" => "Erro ao buscar os dados: " . $conn->error]);
    exit;
}

echo json_encode($events);

$conn->close();
