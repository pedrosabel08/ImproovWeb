<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado.']);
    exit;
}

$allowedCollaborators = [1, 9, 21];
$actorColaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : 0;
if (!in_array($actorColaboradorId, $allowedCollaborators, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissao para editar contatos.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../contact_architecture.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON invalido.']);
    exit;
}

$contactId = isset($payload['contact_id']) ? (int) $payload['contact_id'] : 0;
$contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

if ($contactId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de contato invalido.']);
    exit;
}

$name = trim((string) ($contact['name'] ?? ''));
if ($name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Informe o nome do contato.']);
    exit;
}

try {
    contact_arch_update_client_contact_by_id($conn, $contactId, $contact);
    $updatedContact = contact_arch_fetch_contact_row($conn, $contactId);

    if (!$updatedContact) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contato nao encontrado apos atualizacao.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'contact' => [
            'contact_id' => (int)    $updatedContact['contact_id'],
            'name'       => (string) $updatedContact['name'],
            'email'      => (string) $updatedContact['email'],
            'phone'      => (string) $updatedContact['phone'],
            'role'       => (string) $updatedContact['role'],
            'type'       => (string) $updatedContact['type'],
            'notes'      => (string) $updatedContact['notes'],
        ],
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
