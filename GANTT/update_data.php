<?php
require_once '../conexao.php';

// Recebe dados do frontend
$ganttId = (int)$_POST['gantt_id'];
$dataInicioNova = $_POST['data_inicio']; // formato 'Y-m-d'

// Busca duração original da etapa
$stmtDuracao = $conn->prepare("SELECT dias FROM gantt_prazos WHERE id = ?");
$stmtDuracao->bind_param("i", $ganttId);
$stmtDuracao->execute();
$resultDuracao = $stmtDuracao->get_result();
$row = $resultDuracao->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Etapa não encontrada.']);
    exit;
}

$duracao = (int)$row['dias'];

// Calcula nova data fim
if ($duracao <= 1) {
    $dataFimNova = $dataInicioNova;
} else {
    $dataFimNova = date('Y-m-d', strtotime($dataInicioNova . " +" . ($duracao - 1) . " days"));
}

// Atualiza as datas
$stmtUpdate = $conn->prepare("UPDATE gantt_prazos SET data_inicio = ?, data_fim = ? WHERE id = ?");
$stmtUpdate->bind_param("ssi", $dataInicioNova, $dataFimNova, $ganttId);

if ($stmtUpdate->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Etapa atualizada com sucesso.',
        'data_fim' => $dataFimNova
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar etapa: ' . $conn->error
    ]);
}
