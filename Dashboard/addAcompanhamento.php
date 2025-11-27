<?php
include '../conexao.php';
header('Content-Type: application/json');

// Dados recebidos
$obra_id = isset($_POST['idobra']) ? intval($_POST['idobra']) : null;
$colaborador_id = 1; // fixo; optionally replace with session user id if available
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

    // allow optional 'tipo' param for inserts (default 'manual')
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : 'manual';

    if ($id) {
        // UPDATE (allow updating assunto, data and tipo)
        $stmt = $conn->prepare("UPDATE acompanhamento_email SET assunto = ?, data = ?, tipo = ? WHERE idacompanhamento_email = ? AND obra_id = ?");
        $stmt->bind_param("sssis", $assunto, $data, $tipo, $id, $obra_id);
    } else {
        // INSERT: compute next 'ordem' for this obra
        if (!$data) {
            echo json_encode(["success" => false, "message" => "Data não fornecida para novo acompanhamento."]);
            exit;
        }

        $next_ordem = 1;
        $stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?");
        if ($stmtOrdem) {
            $stmtOrdem->bind_param('i', $obra_id);
            $stmtOrdem->execute();
            $r = $stmtOrdem->get_result()->fetch_assoc();
            if ($r && isset($r['next_ordem'])) $next_ordem = intval($r['next_ordem']);
            $stmtOrdem->close();
        }

        $stmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, tipo, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')");
        $stmt->bind_param("iissis", $obra_id, $colaborador_id, $assunto, $data, $next_ordem, $tipo);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => $id ? "Acompanhamento atualizado." : "Acompanhamento adicionado."]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao salvar acompanhamento: " . $conn->error]);
    }

    $stmt->close();
}

$conn->close();
