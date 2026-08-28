<?php

require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
require_once dirname(__DIR__, 2) . '/helpers/dashboard_colaborador_helper.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

function dashboard_operacional_responder(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    dashboard_operacional_responder(401, ['success' => false, 'error' => 'Não autenticado.']);
}

$colaboradorId = (int) ($_SESSION['idcolaborador'] ?? 0);
if ($colaboradorId <= 0) {
    dashboard_operacional_responder(403, ['success' => false, 'error' => 'Colaborador não identificado na sessão.']);
}

try {
    // O endpoint é sempre pessoal: nunca aceita colaborador_id do navegador.
    unset($_GET['colaborador_id']);
    define('FLOW_FUNCOES_COLABORADOR_INTERNAL', true);
    ob_start();
    require dirname(__DIR__) . '/getFuncoesPorColaborador.php';
    $saidaInterna = ob_get_clean();
    if ($saidaInterna !== '') {
        error_log('Dashboard operacional suprimiu saída inesperada do carregador de tarefas.');
    }
    if (!isset($response) || !is_array($response)) {
        throw new RuntimeException('Não foi possível montar as tarefas operacionais.');
    }

    $dashboard = dashboard_colaborador_montar($response, $colaboradorId);
    dashboard_operacional_responder(200, array_merge([
        'success' => true,
        'generated_at' => date('c'),
    ], $dashboard));
} catch (Throwable $erro) {
    error_log('Erro ao gerar dashboard operacional: ' . $erro->getMessage());
    dashboard_operacional_responder(500, ['success' => false, 'error' => 'Não foi possível carregar a visão geral operacional.']);
}
