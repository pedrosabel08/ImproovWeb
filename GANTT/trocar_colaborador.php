<?php
header('Content-Type: application/json');

include '../conexao.php';


// Recebe e valida os dados
$dados = json_decode(file_get_contents('php://input'), true);

$antigoId = isset($dados['antigoId']) ? intval($dados['antigoId']) : null;
$novoId   = isset($dados['novoId']) ? intval($dados['novoId']) : null;
$etapaId  = isset($dados['etapaId']) ? intval($dados['etapaId']) : null;

if (!$antigoId || !$novoId || !$etapaId) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetros inválidos.']);
    exit;
}

// Inicia transação
$conn->begin_transaction();

try {
    // 1. Atualiza a etapa do colaborador antigo para o novo colaborador
    $sqlUpdate1 = "UPDATE etapa_colaborador 
                   SET colaborador_id = ? 
                   WHERE colaborador_id = ? AND gantt_id = ?";
    $stmt1 = $conn->prepare($sqlUpdate1);
    $stmt1->bind_param("iii", $novoId, $antigoId, $etapaId);
    $stmt1->execute();

    // 2. Atualiza qualquer etapa do novo colaborador para o antigo (opcional, dependendo do contexto)
    $sqlUpdate2 = "UPDATE etapa_colaborador 
                   SET colaborador_id = ? 
                   WHERE colaborador_id = ? AND gantt_id != ?";
    $stmt2 = $conn->prepare($sqlUpdate2);
    $stmt2->bind_param("iii", $antigoId, $novoId, $etapaId);
    $stmt2->execute();

    // Confirma a transação
    $conn->commit();

    echo json_encode(['sucesso' => true, 'mensagem' => 'Troca realizada com sucesso.']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao realizar a troca: ' . $e->getMessage()]);
}

// Fecha
$conn->close();
