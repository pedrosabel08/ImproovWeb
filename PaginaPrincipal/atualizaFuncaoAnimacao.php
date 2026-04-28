<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include '../conexao.php';

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

$idFuncaoAnimacao = isset($_POST['cardId']) ? (int) $_POST['cardId'] : 0;
$status = isset($_POST['status']) ? emptyToNull($_POST['status']) : null;
$prazo = isset($_POST['prazo']) ? emptyToNull($_POST['prazo']) : null;
$observacao = isset($_POST['observacao']) ? emptyToNull($_POST['observacao']) : null;

if ($idFuncaoAnimacao <= 0) {
    echo json_encode(['error' => 'Parâmetro cardId é obrigatório']);
    exit;
}

$stmt = $conn->prepare("UPDATE funcao_animacao SET status = ?, prazo = ?, observacao = ? WHERE id = ?");
if ($stmt === false) {
    echo json_encode(['error' => 'Erro no prepare: ' . $conn->error]);
    exit;
}

$stmt->bind_param('sssi', $status, $prazo, $observacao, $idFuncaoAnimacao);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'Erro ao atualizar funcao_animacao: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'message' => 'Função de animação atualizada com sucesso']);
