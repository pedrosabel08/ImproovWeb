<?php
// webhook.php
header('Content-Type: application/json; charset=utf-8');

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!is_array($data)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'message' => 'Payload inválido']);
	exit;
}

include __DIR__ . '/conexao.php';
require_once __DIR__ . '/Contratos/services/ContratoStatusService.php';

$event = $data['event'] ?? $data['type'] ?? '';
$docToken = $data['document']['token'] ?? $data['doc_token'] ?? $data['document_token'] ?? '';
$signedAt = $data['document']['signed_at'] ?? $data['signed_at'] ?? null;

if (!$event || !$docToken) {
	http_response_code(422);
	echo json_encode(['ok' => false, 'message' => 'Evento ou token ausente']);
	exit;
}

$statusMap = [
	'document.sent' => 'enviado',
	'document.signed' => 'assinado',
	'document.refused' => 'recusado',
	'document.expired' => 'expirado',
];

$status = $statusMap[$event] ?? null;
if (!$status) {
	echo json_encode(['ok' => true, 'ignored' => true]);
	exit;
}

try {
	$conn = conectarBanco();
	$service = new ContratoStatusService($conn);
	$service->atualizarStatusPorToken($docToken, $status, $signedAt);
	$conn->close();

	echo json_encode(['ok' => true, 'status' => $status]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
