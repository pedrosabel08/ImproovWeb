<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Espera: { "itens": [ { "id": 123, "valor_novo": 190.00 }, ... ] }
if (empty($data['itens']) || !is_array($data['itens'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetro itens inválido']);
    exit;
}

include '../conexao.php';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE funcao_imagem SET valor = ? WHERE idfuncao_imagem = ?");
    if (!$stmt) {
        throw new Exception('Erro no prepare: ' . $conn->error);
    }

    $atualizados = 0;
    foreach ($data['itens'] as $item) {
        $id        = isset($item['id'])        ? intval($item['id'])          : 0;
        $valorNovo = isset($item['valor_novo']) ? floatval($item['valor_novo']) : null;

        if ($id <= 0 || $valorNovo === null) {
            continue;
        }

        $stmt->bind_param('di', $valorNovo, $id);
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar id=' . $id . ': ' . $stmt->error);
        }
        $atualizados++;
    }

    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'atualizados' => $atualizados]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
