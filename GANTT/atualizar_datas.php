<?php

include '../conexao.php';

// Receber os dados
$data = json_decode(file_get_contents("php://input"), true);

$tipoImagem = $data['tipoImagem'];
$etapas = $data['etapas'];

// Atualizar cada etapa
foreach ($etapas as $etapa) {
    $etapaNome = $conn->real_escape_string($etapa['etapa']);
    $inicio = $conn->real_escape_string($etapa['data_inicio']);
    $fim = $conn->real_escape_string($etapa['data_fim']);

    $sql = "UPDATE gantt_prazos SET data_inicio = '$inicio', data_fim = '$fim' 
            WHERE tipo_imagem = '$tipoImagem' AND etapa = '$etapaNome'";

    if (!$conn->query($sql)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $conn->error]);
        exit;
    }
}

echo json_encode(['success' => true]);
