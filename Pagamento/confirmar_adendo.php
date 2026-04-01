<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$tempRelInput = isset($data['temp_rel']) ? (string)$data['temp_rel'] : '';

// Validate against session — prevents path manipulation
$pendente = $_SESSION['adendo_pendente'] ?? null;
if (!$pendente || !isset($pendente['temp_rel']) || $pendente['temp_rel'] !== $tempRelInput) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Adendo pendente não encontrado ou inválido. Gere o adendo novamente.']);
    exit;
}

$baseGerados = realpath(__DIR__ . '/../Contratos/gerados');
if (!$baseGerados) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Diretório de gerados não encontrado.']);
    exit;
}

// Resolve temp file — reject any path that escapes the gerados directory
$tempAbs = realpath($baseGerados . '/' . $pendente['temp_rel']);
if (!$tempAbs || strpos($tempAbs, $baseGerados) !== 0 || !is_file($tempAbs)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Arquivo temporário não encontrado. Gere o adendo novamente.']);
    exit;
}

$finalDir = $pendente['final_dir'];

// Create final directory only now (user approved)
if (!is_dir($finalDir)) {
    if (!mkdir($finalDir, 0775, true) && !is_dir($finalDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Não foi possível criar a pasta de destino.']);
        exit;
    }
}

$finalPath = $finalDir . DIRECTORY_SEPARATOR . basename($tempAbs);

if (!rename($tempAbs, $finalPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao mover adendo para o destino final.']);
    exit;
}

// Clear pending state from session
unset($_SESSION['adendo_pendente']);

// Resolve the canonical path now that the file exists
$finalPathReal = realpath($finalPath);
if (!$finalPathReal || strpos($finalPathReal, $baseGerados) !== 0) {
    echo json_encode(['success' => true, 'download_url' => null]);
    exit;
}

$finalRel    = str_replace('\\', '/', substr($finalPathReal, strlen($baseGerados) + 1));
$downloadUrl = '../Contratos/download.php?arquivo=' . rawurlencode($finalRel);

echo json_encode(['success' => true, 'download_url' => $downloadUrl]);
