<?php

include '../conexao.php';

// Lê os dados enviados pelo JavaScript
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Nenhum dado recebido.']);
    exit;
}

// Prepara a consulta SQL para atualizar as informações
$stmt = $conn->prepare("
    UPDATE imagens_cliente_obra 
    SET recebimento_arquivos = ?, 
        data_inicio = ?, 
        prazo = ?, 
        imagem_nome = ?, 
        tipo_imagem = ? 
    WHERE idimagens_cliente_obra = ?
");

// Verifica se a preparação da consulta foi bem-sucedida
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro ao preparar a consulta SQL.']);
    exit;
}

// Itera pelos dados e executa as atualizações
$success = true;
foreach ($data as $image) {
    $recebimento_arquivos = $image['recebimento_arquivos'];
    $data_inicio = $image['data_inicio'];
    $prazo = $image['prazo'];
    $imagem_nome = $image['imagem_nome'];
    $tipo_imagem = $image['tipo_imagem'];
    $idimagem = $image['idimagem'];

    // Executa a consulta
    if (
        !$stmt->bind_param("sssssi", $recebimento_arquivos, $data_inicio, $prazo, $imagem_nome, $tipo_imagem, $idimagem) ||
        !$stmt->execute()
    ) {
        $success = false;
        break; // Interrompe o loop se uma atualização falhar
    }
}

// Responde ao cliente
if ($success) {
    echo json_encode(['success' => true, 'message' => 'Alterações salvas com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar alterações.']);
}

// Fecha a consulta e a conexão
$stmt->close();
$conn->close();
