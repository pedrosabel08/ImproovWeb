<?php

require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';
require_once dirname(__DIR__, 2) . '/helpers/overview_v1_helper.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

function overview_v1_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['logado']) || empty($_SESSION['idcolaborador'])) {
    overview_v1_response(401, ['success' => false, 'error' => 'Sessão inválida.']);
}

$section = in_array((string) ($_GET['section'] ?? 'all'), ['critical', 'secondary', 'all'], true)
    ? (string) ($_GET['section'] ?? 'all') : 'all';

try {
    $conn = conectarBanco();
    $gestor = improov_usuario_eh_gestor_sidebar($conn);
    if ($gestor) {
        $overview = flow_overview_v1_gestor($conn, $section);
    } else {
        // O carregador canônico do Kanban permanece a fonte de verdade das
        // regras de liberação, Flow Block, requisitos e pendências.
        define('FLOW_FUNCOES_COLABORADOR_INTERNAL', true);
        ob_start();
        require dirname(__DIR__) . '/getFuncoesPorColaborador.php';
        ob_end_clean();
        if (!isset($response) || !is_array($response)) {
            throw new RuntimeException('Não foi possível carregar as tarefas operacionais.');
        }
        // O carregador fecha sua própria conexão. Reabre-se uma conexão curta
        // somente para carga planejada e histórico secundários.
        $conn = conectarBanco();
        $overview = flow_overview_v1_colaborador($conn, $response, (int) $_SESSION['idcolaborador'], $section);
    }
    if ($conn instanceof mysqli) {
        $conn->close();
    }
    overview_v1_response(200, ['success' => true, 'generated_at' => date('c'), 'overview' => $overview]);
} catch (Throwable $erro) {
    error_log('Overview V1: ' . $erro->getMessage());
    overview_v1_response(500, ['success' => false, 'error' => 'Não foi possível carregar a Visão Geral.']);
}
