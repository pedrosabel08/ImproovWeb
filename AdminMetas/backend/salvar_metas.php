<?php

/**
 * AdminMetas/backend/salvar_metas.php
 *
 * Recebe POST JSON com as metas alteradas/novas e persiste na tabela meta_colaborador.
 * Executa INSERT ou UPDATE individualmente conforme existência prévia.
 *
 * Body: { mes, ano, metas: [ { colaborador_id, funcao_id, meta_tarefas }, ... ] }
 *
 * Response: { success, message, updated, inserted, skipped }
 */

ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/session_bootstrap.php';

    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (
        !$body
        || !isset($body['mes'], $body['ano'], $body['metas'])
        || !is_array($body['metas'])
    ) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Payload inválido']);
        exit;
    }

    $mes = (int) $body['mes'];
    $ano = (int) $body['ano'];

    if ($mes < 1 || $mes > 12 || $ano < 2020 || $ano > 2100) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['error' => 'Mês ou ano inválido']);
        exit;
    }

    // Funções permitidas — Pré-Finalização (9) excluída
    $funcoesPermitidas = [1, 2, 3, 4, 5, 7, 8];

    include __DIR__ . '/../../conexao.php';

    if (!$conn || $conn->connect_error) {
        throw new Exception('Falha na conexão: ' . ($conn->connect_error ?? 'conexão nula'));
    }

    // ── Carregar registros existentes para distinguir INSERT vs UPDATE ────────
    $existentes = []; // [funcao_id][colaborador_id] = true
    $stmtEx = $conn->prepare(
        "SELECT colaborador_id, funcao_id
           FROM meta_colaborador
          WHERE mes = ? AND ano = ?"
    );
    $stmtEx->bind_param('ii', $mes, $ano);
    $stmtEx->execute();
    foreach ($stmtEx->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $existentes[(int) $r['funcao_id']][(int) $r['colaborador_id']] = true;
    }
    $stmtEx->close();

    // ── Prepared statements ────────────────────────────────────────────────────
    $stmtInsert = $conn->prepare(
        "INSERT INTO meta_colaborador (colaborador_id, funcao_id, mes, ano, meta_tarefas)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmtInsert) {
        throw new Exception('Prepare INSERT falhou: ' . $conn->error);
    }

    $stmtUpdate = $conn->prepare(
        "UPDATE meta_colaborador
            SET meta_tarefas = ?
          WHERE colaborador_id = ? AND funcao_id = ? AND mes = ? AND ano = ?"
    );
    if (!$stmtUpdate) {
        throw new Exception('Prepare UPDATE falhou: ' . $conn->error);
    }

    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;

    foreach ($body['metas'] as $item) {
        $colaboradorId = (int) ($item['colaborador_id'] ?? 0);
        $funcaoId      = (int) ($item['funcao_id']      ?? 0);
        $metaTarefas   = (int) ($item['meta_tarefas']   ?? 0);

        // Validação básica
        if ($colaboradorId <= 0 || $funcaoId <= 0 || $metaTarefas < 0) {
            $skipped++;
            continue;
        }

        if (!in_array($funcaoId, $funcoesPermitidas, true)) {
            $skipped++;
            continue;
        }

        $jaExiste = isset($existentes[$funcaoId][$colaboradorId]);

        if ($jaExiste) {
            $stmtUpdate->bind_param('iiiii', $metaTarefas, $colaboradorId, $funcaoId, $mes, $ano);
            if ($stmtUpdate->execute()) {
                $updated++;
            }
        } else {
            $stmtInsert->bind_param('iiiii', $colaboradorId, $funcaoId, $mes, $ano, $metaTarefas);
            if ($stmtInsert->execute()) {
                $inserted++;
            }
        }
    }

    $stmtInsert->close();
    $stmtUpdate->close();
    $conn->close();

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'message'  => 'Metas salvas com sucesso',
        'updated'  => $updated,
        'inserted' => $inserted,
        'skipped'  => $skipped,
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
