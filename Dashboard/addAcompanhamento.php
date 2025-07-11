<?php
include '../conexao.php';
header('Content-Type: application/json');

// Dados recebidos
$obra_id = isset($_POST['idobra']) ? intval($_POST['idobra']) : null;
$colaborador_id = 1; // fixo
$assunto = isset($_POST['assunto']) ? trim($_POST['assunto']) : null;
$data = isset($_POST['data']) ? $_POST['data'] : null;
$desc = isset($_POST['desc']) ? trim($_POST['desc']) : null;
$id = isset($_POST['id']) ? intval($_POST['id']) : null;

// Verificação mínima
if (!$obra_id) {
    echo json_encode(["success" => false, "message" => "ID da obra não fornecido."]);
    exit;
}

if ($desc) {
    // Observação
    if ($id) {
        $stmtObs = $conn->prepare("UPDATE observacao_obra SET descricao = ? WHERE id = ? AND obra_id = ?");
        $stmtObs->bind_param("sii", $desc, $id, $obra_id);
    } else {
        $stmtObs = $conn->prepare("INSERT INTO observacao_obra (obra_id, descricao) VALUES (?, ?)");
        $stmtObs->bind_param("is", $obra_id, $desc);
    }

    if ($stmtObs->execute()) {
        echo json_encode(["success" => true, "message" => "Observação salva com sucesso."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao salvar observação: " . $conn->error]);
    }

    $stmtObs->close();
} else {
    // Acompanhamento
    if (!$assunto) {
        echo json_encode(["success" => false, "message" => "Assunto não fornecido."]);
        exit;
    }

    if ($id) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE acompanhamento_email SET assunto = ? WHERE idacompanhamento_email = ? AND obra_id = ?");
        $stmt->bind_param("sii", $assunto, $id, $obra_id);
    } else {
        // INSERT
        if (!$data) {
            echo json_encode(["success" => false, "message" => "Data não fornecida para novo acompanhamento."]);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $obra_id, $colaborador_id, $assunto, $data);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => $id ? "Acompanhamento atualizado." : "Acompanhamento adicionado."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao salvar acompanhamento: " . $conn->error]);
    }

    $stmt->close();
}

$conn->close();
