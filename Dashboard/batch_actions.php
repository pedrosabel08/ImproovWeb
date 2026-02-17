<?php
header('Content-Type: application/json');
require '../conexao.php'; // sua conexão mysqli ($conn)

// Recebe os dados enviados via AJAX
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['ids']) || !isset($data['campos'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']);
    exit;
}

$ids = array_map('intval', $data['ids']); // garante que os IDs são inteiros
$campos = $data['campos'];
$holdJustificativa = trim((string)($data['hold_justificativa'] ?? ''));

if (empty($ids)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhuma imagem selecionada']);
    exit;
}

$destinoHold = isset($campos['substatus_id']) && (int)$campos['substatus_id'] === 7;
if ($destinoHold && $holdJustificativa === '') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Justificativa de HOLD é obrigatória']);
    exit;
}

// Monta a parte SET da query
$set = [];
foreach ($campos as $col => $valor) {
    $valor = mysqli_real_escape_string($conn, $valor);
    $set[] = "`$col` = '$valor'";
}

// Monta a lista de IDs para o IN
$idsList = implode(',', $ids);

// Query única
$sql = "UPDATE imagens_cliente_obra SET " . implode(", ", $set) . " WHERE idimagens_cliente_obra IN ($idsList)";

mysqli_begin_transaction($conn);

try {
    if (!mysqli_query($conn, $sql)) {
        throw new Exception(mysqli_error($conn));
    }

    if ($destinoHold) {
        $sqlObras = "SELECT idimagens_cliente_obra, obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra IN ($idsList)";
        $resObras = mysqli_query($conn, $sqlObras);
        if (!$resObras) {
            throw new Exception(mysqli_error($conn));
        }

        $stmtHold = $conn->prepare("INSERT INTO status_hold (justificativa, imagem_id, obra_id) VALUES (?, ?, ?)");
        if (!$stmtHold) {
            throw new Exception(mysqli_error($conn));
        }

        while ($row = mysqli_fetch_assoc($resObras)) {
            $imagemId = (int)$row['idimagens_cliente_obra'];
            $obraId = isset($row['obra_id']) ? (int)$row['obra_id'] : null;
            $stmtHold->bind_param('sii', $holdJustificativa, $imagemId, $obraId);
            if (!$stmtHold->execute()) {
                $stmtHold->close();
                throw new Exception($stmtHold->error);
            }
        }
        $stmtHold->close();
    }

    mysqli_commit($conn);
    echo json_encode(['sucesso' => true]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}

mysqli_close($conn);
