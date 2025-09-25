<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include 'conexao.php';

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

$data = $_POST;

$imagem_id = isset($data['imagem_id']) ? (int)$data['imagem_id'] : null;
$funcao_id = isset($data['funcao_id']) ? (int)$data['funcao_id'] : null;
$colaborador_id = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : null;
$prazo = isset($data['prazo']) ? emptyToNull($data['prazo']) : null;
$status = isset($data['status']) ? emptyToNull($data['status']) : null;
$observacao = isset($data['observacao']) ? emptyToNull($data['observacao']) : null;
$status_id = isset($data['status_id']) ? (int)$data['status_id'] : null;

if (!$imagem_id) {
    echo json_encode(['error' => 'Parâmetro imagem_id é obrigatório']);
    exit;
}

$conn->begin_transaction();

try {
    // Atualiza o status da imagem se enviado
    if ($status_id !== null) {
        $stmtStatus = $conn->prepare(
            "UPDATE imagens_cliente_obra SET status_id = ? WHERE idimagens_cliente_obra = ?"
        );
        $stmtStatus->bind_param("ii", $status_id, $imagem_id);
        $stmtStatus->execute();
        $stmtStatus->close();
    }


    // Monta campos e valores dinâmicos para funcao_imagem
    $campos = ['imagem_id'];
    $valores = [$imagem_id];
    $updates = [];

    if ($colaborador_id !== null) {
        $campos[] = 'colaborador_id';
        $valores[] = $colaborador_id;
        $updates[] = 'colaborador_id = VALUES(colaborador_id)';
    }

    if ($funcao_id !== null) {
        $campos[] = 'funcao_id';
        $valores[] = $funcao_id;
        $updates[] = 'funcao_id = VALUES(funcao_id)';
    }

    if ($prazo !== null) {
        $campos[] = 'prazo';
        $valores[] = $prazo;
        $updates[] = 'prazo = VALUES(prazo)';
    }

    if ($status !== null) {
        $campos[] = 'status';
        $valores[] = $status;
        $updates[] = 'status = VALUES(status)';
    }

    if ($observacao !== null) {
        $campos[] = 'observacao';
        $valores[] = $observacao;
        $updates[] = 'observacao = VALUES(observacao)';
    }

    $sql = "INSERT INTO funcao_imagem (" . implode(',', $campos) . ") VALUES (" . implode(',', array_fill(0, count($valores), '?')) . ")";
    if (!empty($updates)) {
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates);
    }

    $stmt = $conn->prepare($sql);

    // Monta tipos de bind_param
    $tipos = '';
    foreach ($valores as $v) {
        $tipos .= is_int($v) ? 'i' : 's';
    }

    $stmt->bind_param($tipos, ...$valores);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => 'Dados inseridos/atualizados com sucesso!']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Erro ao executar a transação: ' . $e->getMessage()]);
}

$conn->close();
