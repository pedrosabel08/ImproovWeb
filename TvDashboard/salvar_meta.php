<?php

/**
 * TvDashboard/salvar_meta.php
 * UPSERT de metas por função para um mês/ano.
 * Recebe POST JSON: { mes, ano, metas: [ {funcao_id, quantidade_meta}, ... ] }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['mes'], $body['ano'], $body['metas']) || !is_array($body['metas'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

$mes = (int)$body['mes'];
$ano = (int)$body['ano'];

if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
    http_response_code(400);
    echo json_encode(['error' => 'Mês ou ano inválido']);
    exit;
}

include '../conexao.php';

// Garante unicidade — ignora erro se a key já existe
$conn->query("
    ALTER TABLE metas
    ADD UNIQUE KEY uk_funcao_mes_ano (funcao_id, mes, ano)
");

$sql = "INSERT INTO metas (funcao_id, mes, ano, quantidade_meta)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantidade_meta = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    $conn->close();
    exit;
}

$saved = 0;
foreach ($body['metas'] as $item) {
    $funcaoId = (int)($item['funcao_id'] ?? 0);
    $qtdMeta  = (int)($item['quantidade_meta'] ?? 0);

    if ($funcaoId <= 0 || $qtdMeta < 0) continue;

    $stmt->bind_param('iiiii', $funcaoId, $mes, $ano, $qtdMeta, $qtdMeta);
    if ($stmt->execute()) $saved++;
}

$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'saved' => $saved]);
