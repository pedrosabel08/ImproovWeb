<?php

include '../conexao.php';

$data = json_decode(file_get_contents("php://input"), true);

$tipoImagem = $data['tipoImagem'];
$imagemId = (int)$data['imagemId'];
$etapas = $data['etapas'];

if (!is_array($etapas)) {
    echo json_encode(['success' => false, 'message' => 'Formato de etapas inválido.']);
    exit;
}

$stmt = $conn->prepare("UPDATE gantt_prazos 
    SET data_inicio = ?, data_fim = ? 
    WHERE tipo_imagem = ? AND etapa = ? AND imagem_id = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar statement: ' . $conn->error]);
    exit;
}

foreach ($etapas as $etapa) {
    $inicio = $etapa['data_inicio'];
    $fim = $etapa['data_fim'];
    $etapaNome = $etapa['etapa'];

    $stmt->bind_param("ssssi", $inicio, $fim, $tipoImagem, $etapaNome, $imagemId);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $stmt->error]);
        exit;
    }
}

echo json_encode(['success' => true]);
