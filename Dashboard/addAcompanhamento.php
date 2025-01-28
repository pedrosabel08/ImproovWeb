<?php
// Configuração do banco de dados
include '../conexao.php';

// Configura o cabeçalho para JSON
header('Content-Type: application/json');

// Obtém os dados enviados pelo cliente
$obra_id = isset($_POST['idobra']) ? intval($_POST['idobra']) : null;
$colaborador_id = 1; // ID fixo
$assunto = isset($_POST['assunto']) ? trim($_POST['assunto']) : null;
$data = isset($_POST['data']) ? $_POST['data'] : null; // Data enviada pelo cliente
$desc = isset($_POST['desc']) ? trim($_POST['desc']) : null; // Descrição (observação)

// Validações básicas
if (!$obra_id) {
    echo json_encode(["success" => false, "message" => "ID da obra não fornecido."]);
    exit;
}

// Verifica se a descrição foi fornecida
if ($desc) {
    // Se descrição foi fornecida, insere apenas na tabela observacao_obra
    $stmtObs = $conn->prepare("INSERT INTO observacao_obra (obra_id, descricao) VALUES (?, ?)");
    $stmtObs->bind_param("is", $obra_id,  $desc);

    if ($stmtObs->execute()) {
        echo json_encode(["success" => true, "message" => "Observação adicionada com sucesso."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao adicionar observação: " . $conn->error]);
    }
    $stmtObs->close();
} else {
    // Caso contrário, faz o INSERT na tabela acompanhamento_email
    if (!$assunto || !$data) {
        echo json_encode(["success" => false, "message" => "Dados incompletos."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $obra_id, $colaborador_id, $assunto, $data);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Acompanhamento adicionado com sucesso."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao inserir acompanhamento: " . $conn->error]);
    }
    $stmt->close();
}

// Fecha a conexão
$conn->close();
