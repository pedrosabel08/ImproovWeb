<?php
include '../conexao.php'; // Sua conexão com o banco

// Receber os dados enviados no body
$input = json_decode(file_get_contents('php://input'), true);
$gantt_id = intval($input['gantt_id']);
$colaborador_id = intval($input['colaborador_id']);

// Primeiramente, consulte se o colaborador já está atribuído a uma etapa dentro do período
// Você deve ter uma lógica que defina o "início" e o "fim" de cada etapa para esse colaborador.
// Vou assumir que você tem as colunas 'data_inicio' e 'data_fim' na tabela `gantt_prazos`, 
// onde as datas de início e fim da etapa podem ser consultadas.

$sqlCheck = "SELECT COUNT(*) as count 
    FROM etapa_colaborador ec
    JOIN gantt_prazos gp ON ec.gantt_id = gp.id
    WHERE ec.colaborador_id = ? 
    AND (
        (gp.data_inicio BETWEEN (SELECT data_inicio FROM gantt_prazos WHERE id = ?) AND (SELECT data_fim FROM gantt_prazos WHERE id = ?))
        OR (gp.data_fim BETWEEN (SELECT data_inicio FROM gantt_prazos WHERE id = ?) AND (SELECT data_fim FROM gantt_prazos WHERE id = ?))
        OR ((SELECT data_inicio FROM gantt_prazos WHERE id = ?) BETWEEN gp.data_inicio AND gp.data_fim)
        OR ((SELECT data_fim FROM gantt_prazos WHERE id = ?) BETWEEN gp.data_inicio AND gp.data_fim)
    )
";

$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("iiiiiii", $colaborador_id, $gantt_id, $gantt_id, $gantt_id, $gantt_id, $gantt_id, $gantt_id);

$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();
$row = $resultCheck->fetch_assoc();

if ($row['count'] > 0) {
    // Se o colaborador já está atribuído a alguma etapa no mesmo período
    echo json_encode([
        'success' => false,
        'message' => 'O colaborador já está atribuído a outra etapa neste período.'
    ]);
    exit;
}

// Caso contrário, fazer a inserção do colaborador na etapa
$stmt = $conn->prepare("INSERT INTO etapa_colaborador (gantt_id, colaborador_id) VALUES (?, ?)");
$stmt->bind_param("ii", $gantt_id, $colaborador_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Colaborador atribuído com sucesso.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atribuir colaborador.'
    ]);
}
