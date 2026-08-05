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
if (empty($data['ids']) || !is_array($data['ids']) || !isset($data['valor']) || !is_numeric($data['valor'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'IDs ou valor inválidos.']);
    exit;
}

$valor = (float) $data['valor'];
if (!is_finite($valor) || $valor < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'O valor deve ser um número maior ou igual a zero.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$conn->begin_transaction();
try {
    foreach ($data['ids'] as $item) {
        $id = intval($item['id'] ?? 0);
        $origem = (string) ($item['origem'] ?? '');
        $funcaoId = intval($item['funcao_id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException('ID de item inválido.');
        }

        if ($origem === 'funcao_imagem') {
            // Função 6 mantém a regra existente de soma; as demais substituem.
            $sql = $funcaoId === 6
                ? 'UPDATE funcao_imagem SET valor = valor + ? WHERE idfuncao_imagem = ?'
                : 'UPDATE funcao_imagem SET valor = ? WHERE idfuncao_imagem = ?';
        } elseif ($origem === 'acompanhamento') {
            $sql = 'UPDATE acompanhamento SET valor = ? WHERE idacompanhamento = ?';
        } elseif ($origem === 'funcao_animacao') {
            $sql = 'UPDATE funcao_animacao SET valor = ? WHERE id = ?';
        } else {
            throw new InvalidArgumentException('Origem de item inválida.');
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Não foi possível preparar a atualização de valor.');
        }

        $stmt->bind_param('di', $valor, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Não foi possível atualizar um dos valores.');
        }
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Payment value update failed: ' . $e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'success' => false,
        'error' => $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Não foi possível atualizar os valores.'
    ]);
}

$conn->close();
