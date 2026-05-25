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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON invalido.']);
    exit;
}

$obraId = isset($payload['obra_id']) ? (int) $payload['obra_id'] : 0;
$clienteId = isset($payload['cliente_id']) ? (int) $payload['cliente_id'] : 0;
$linkToObra = !empty($payload['link_to_obra']);
$contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

if ($obraId > 0) {
    $allowedCollaborators = [1, 9, 21];
    $actorColaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : 0;
    if (!in_array($actorColaboradorId, $allowedCollaborators, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissao para cadastrar contatos pela obra.']);
        exit;
    }
}

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

try {
    $conn->begin_transaction();

    $contactId = contact_arch_save_client_contact($conn, $clienteId, $contact);

    if ($linkToObra && $obraId > 0) {
        $activeContacts = contact_arch_fetch_linked_contacts($conn, $obraId);
        $activeContactIds = array_map(static function ($linkedContact) {
            return (int) ($linkedContact['contact_id'] ?? 0);
        }, $activeContacts);
        $activeContactIds[] = $contactId;
        contact_arch_sync_obra_contacts($conn, $obraId, $activeContactIds);
    }

    $savedContact = contact_arch_fetch_contact_row($conn, $contactId);
    $conn->commit();

    echo json_encode([
        'success' => true,
        'cliente_id' => $clienteId,
        'obra_id' => $obraId > 0 ? $obraId : null,
        'contact' => [
            'contact_id' => (int) ($savedContact['contact_id'] ?? $contactId),
            'name' => (string) ($savedContact['name'] ?? ''),
            'email' => (string) ($savedContact['email'] ?? ''),
            'phone' => (string) ($savedContact['phone'] ?? ''),
            'role' => (string) ($savedContact['role'] ?? ''),
            'type' => (string) ($savedContact['type'] ?? 'OUTRO'),
            'notes' => (string) ($savedContact['notes'] ?? ''),
            'obra_selected' => $linkToObra && $obraId > 0,
        ],
    ]);
} catch (Throwable $throwable) {
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
