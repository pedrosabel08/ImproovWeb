<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../contact_architecture.php';

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
$clienteId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;

$context = null;
if ($obraId > 0) {
    $context = contact_arch_get_obra_client_context($conn, $obraId);
    if (!$context || $context['cliente_id'] <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Nao foi possivel localizar o cliente da obra.']);
        exit;
    }
    $clienteId = (int) $context['cliente_id'];
}

if ($clienteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cliente invalido.']);
    exit;
}

$clienteNome = '';
if ($context) {
    $clienteNome = (string) ($context['cliente_nome'] ?? '');
} else {
    $stmt = $conn->prepare('SELECT nome_cliente FROM cliente WHERE idcliente = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        $clienteNome = (string) ($row['nome_cliente'] ?? '');
    }
}

try {
    $contacts = contact_arch_fetch_client_contacts($conn, $clienteId, $obraId > 0 ? $obraId : null);

    echo json_encode([
        'success' => true,
        'cliente_id' => $clienteId,
        'cliente_nome' => $clienteNome,
        'obra_id' => $obraId > 0 ? $obraId : null,
        'contacts' => $contacts,
        'architecture_ready' => contact_arch_link_schema($conn)['exists'],
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
