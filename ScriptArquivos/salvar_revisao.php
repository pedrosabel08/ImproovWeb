<?php
header('Content-Type: application/json');
include 'conexao.php';

$arquivo_id  = $_POST['arquivo_id'] ?? null;
$completo    = $_POST['completo'] ?? null;
$substitui_id = $_POST['substitui_id'] ?? null;
$relacao     = $_POST['relacao'] ?? null;
$observacao  = $_POST['observacao'] ?? null;

if (!$arquivo_id || $completo === null || !$relacao) {
    echo json_encode(['error' => 'Campos obrigatórios não preenchidos']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO revisoes (arquivo_id, completo, substitui_id, relacao, observacao, criado_em, criado_por)
                            VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $criado_por = 'Sistema'; // ou usuário logado
    $stmt->bind_param("iiisss", $arquivo_id, $completo, $substitui_id, $relacao, $observacao, $criado_por);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
