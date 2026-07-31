<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/sire_helpers.php';

sire_require_auth();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sire_json(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$referenceId = (int) ($payload['referencia_id'] ?? 0);
if ($referenceId <= 0) {
    sire_json(['success' => false, 'message' => 'Referência inválida.'], 422);
}

require_once __DIR__ . '/../conexaoMain.php';
$conn = conectarBanco();
$stmt = $conn->prepare('SELECT id, referencia_imagem_id, golden_sample FROM sire_referencia WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $referenceId);
$stmt->execute();
$reference = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$reference) {
    $conn->close();
    sire_json(['success' => false, 'message' => 'Referência não encontrada.'], 404);
}

$newValue = (int) !$reference['golden_sample'];
$stmt = $conn->prepare('UPDATE sire_referencia SET golden_sample = ? WHERE id = ?');
$stmt->bind_param('ii', $newValue, $referenceId);
$stmt->execute();
$stmt->close();

// Mantém a coluna histórica sincronizada para as referências importadas do Flow.
if (!empty($reference['referencia_imagem_id'])) {
    $flowId = (int) $reference['referencia_imagem_id'];
    $stmt = $conn->prepare('UPDATE referencias_imagens SET golden_sample = ? WHERE id = ?');
    $stmt->bind_param('ii', $newValue, $flowId);
    $stmt->execute();
    $stmt->close();
}
$conn->close();
sire_json(['success' => true, 'golden_sample' => $newValue]);
