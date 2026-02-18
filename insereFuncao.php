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

function intToNull($value)
{
    if ($value === '' || $value === null) {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (int)$value;
}

$data = $_POST;

$imagem_id = isset($data['imagem_id']) ? intToNull($data['imagem_id']) : null;

$funcao_id = isset($data['funcao_id'])
    ? intToNull($data['funcao_id'])
    : null;

$colaborador_id = isset($data['colaborador_id'])
    ? intToNull($data['colaborador_id'])
    : (isset($data['alteracao_id']) ? intToNull($data['alteracao_id']) : null);

$prazo = isset($data['prazo'])
    ? emptyToNull($data['prazo'])
    : (isset($data['prazo_alteracao']) ? emptyToNull($data['prazo_alteracao']) : null);

$status = isset($data['status'])
    ? emptyToNull($data['status'])
    : (isset($data['status_alteracao']) ? emptyToNull($data['status_alteracao']) : null);

$observacao = isset($data['observacao'])
    ? emptyToNull($data['observacao'])
    : (isset($data['obs_alteracao']) ? emptyToNull($data['obs_alteracao']) : null);

$status_id = isset($data['status_id']) ? intToNull($data['status_id']) : null;

if ($funcao_id === null && (isset($data['status_alteracao']) || isset($data['prazo_alteracao']) || isset($data['obs_alteracao']) || isset($data['alteracao_id']))) {
    $funcao_id = 6;
}

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
