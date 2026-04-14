<?php

/**
 * TvDashboard/salvar_meta.php
 * UPSERT de metas por função para um mês/ano.
 * Recebe POST JSON: { mes, ano, metas: [ {funcao_id, quantidade_meta}, ... ] }
 */
ob_start(); // captura qualquer output espúrio (warnings, notices)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Converte erros PHP em exceções para facilitar o catch
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

header('Content-Type: application/json; charset=utf-8');

try {
    // ── 1. Autenticação ──────────────────────────────────────────────────────
    require_once __DIR__ . '/../config/session_bootstrap.php';

    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }

    // ── 2. Parse do body ─────────────────────────────────────────────────────
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || !isset($body['mes'], $body['ano'], $body['metas']) || !is_array($body['metas'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }

    $mes = (int)$body['mes'];
    $ano = (int)$body['ano'];

    if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Mês ou ano inválido']);
        exit;
    }

    // ── 3. Conexão ───────────────────────────────────────────────────────────
    include '../conexao.php';

    if (!$conn || $conn->connect_error) {
        throw new Exception('Falha na conexão: ' . ($conn->connect_error ?? 'conexão nula'));
    }

    // ── 5. Prepared statement ────────────────────────────────────────────────
    $sql = "INSERT INTO metas (funcao_id, mes, ano, quantidade_meta)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quantidade_meta = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Falha no prepare: ' . $conn->error);
    }

    // ── 6. Iteração ──────────────────────────────────────────────────────────
    $saved = 0;
    foreach ($body['metas'] as $item) {
        $funcaoId = (int)($item['funcao_id'] ?? 0);
        $qtdMeta  = (int)($item['quantidade_meta'] ?? 0);

        if ($funcaoId <= 0 || $qtdMeta < 0) continue;

        $stmt->bind_param('iiiii', $funcaoId, $mes, $ano, $qtdMeta, $qtdMeta);
        if ($stmt->execute()) {
            $saved++;
        } else {
            throw new Exception("Falha ao salvar funcao_id={$funcaoId}: " . $stmt->error);
        }
    }

    $stmt->close();
    $conn->close();

    ob_end_clean();
    echo json_encode(['ok' => true, 'saved' => $saved]);

} catch (Throwable $e) {
    $spurious = ob_get_clean();
    http_response_code(500);
    echo json_encode([
        'error'    => $e->getMessage(),
        'file'     => basename($e->getFile()),
        'line'     => $e->getLine(),
        'spurious' => $spurious ?: null,
    ]);
}
