<?php
header('Content-Type: application/json; charset=utf-8');

// Simple endpoint to update obra status (0 = active, 1 = inactive)
// Expects JSON body: { obra_id: <int>, status: <0|1> }

// load DB connector
if (file_exists(__DIR__ . '/conexaoMain.php')) {
    include_once __DIR__ . '/conexaoMain.php';
} elseif (file_exists(__DIR__ . '/conexao.php')) {
    include_once __DIR__ . '/conexao.php';
}

if (!function_exists('conectarBanco')) {
    echo json_encode(['success' => false, 'message' => 'Função conectarBanco() não encontrada.']);
    exit;
}

$input = null;
// Accept JSON body
$raw = file_get_contents('php://input');
if ($raw) {
    $input = json_decode($raw, true);
}

// Fallback to form-encoded POST
if (!$input) $input = $_POST;

$obra_id = isset($input['obra_id']) ? (int)$input['obra_id'] : 0;
$status = isset($input['status']) ? (int)$input['status'] : null;

if (!$obra_id || ($status !== 0 && $status !== 1)) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$conn = conectarBanco();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Erro ao conectar ao banco.']);
    exit;
}

$sql = "UPDATE obra SET status_obra = ? WHERE idobra = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro no prepare: '. $conn->error]);
    exit;
}
$stmt->bind_param('ii', $status, $obra_id);
$ok = $stmt->execute();
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar obra: '. $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso.']);

?>
