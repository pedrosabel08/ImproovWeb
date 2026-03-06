<?php
/**
 * getSufixos.php
 * GET  ?tipo_arquivo=DWG  → returns array of suffix strings
 * POST {tipo_arquivo, valor}  → inserts new suffix, returns {ok, valor}
 */
require_once '../conexao.php';
header('Content-Type: application/json; charset=utf-8');

// ── GET: list ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tipo = trim($_GET['tipo_arquivo'] ?? '');
    if (!$tipo) { echo json_encode([]); exit; }

    $stmt = $conn->prepare("SELECT valor FROM sufixos WHERE tipo_arquivo = ? ORDER BY valor ASC");
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(array_column($rows, 'valor'));
    exit;
}

// ── POST: insert new ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $tipo = trim($body['tipo_arquivo'] ?? '');
    $raw  = trim($body['valor'] ?? '');

    if (!$tipo || !$raw) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios: tipo_arquivo, valor']);
        exit;
    }

    // Normalize: uppercase, spaces → underscore, strip invalid chars
    $valor = strtoupper(preg_replace('/\s+/', '_', $raw));
    $valor = preg_replace('/[^A-Z0-9_]/', '', $valor);

    // Validate: max 2 parts separated by exactly one underscore
    $parts = explode('_', $valor);
    if (count($parts) > 2 || strlen($valor) === 0) {
        http_response_code(422);
        echo json_encode(['error' => 'Sufixo inválido. Máximo 2 palavras separadas por _']);
        exit;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO sufixos (tipo_arquivo, valor) VALUES (?, ?)");
    $stmt->bind_param('ss', $tipo, $valor);
    $stmt->execute();

    echo json_encode(['ok' => true, 'valor' => $valor]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
