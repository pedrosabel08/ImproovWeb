<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(true);

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

require_once __DIR__ . '/../conexao.php';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE funcao_imagem SET valor = ? WHERE idfuncao_imagem = ?");
    if (!$stmt) {
        throw new Exception('Falha ao preparar a correção de valores.');
    }

    $atualizados = 0;
    foreach ($data['itens'] as $item) {
        $id        = isset($item['id'])        ? intval($item['id'])          : 0;
        $valorNovo = isset($item['valor_novo']) ? floatval($item['valor_novo']) : null;

        if ($id <= 0 || $valorNovo === null) {
            continue;
        }
        if (!is_finite($valorNovo) || $valorNovo < 0) {
            throw new InvalidArgumentException('Valor de correção inválido.');
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
    error_log('Payment value correction failed: ' . $e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível corrigir os valores.']);
}

$conn->close();
