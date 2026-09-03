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
    $conn->close();
    $colaboradorAlvo = (int) $_SESSION['idcolaborador'];

    // A mesma carga usada pelo Kanban produz as pendências através de
    // pendencias_operacionais_helper.php. A Overview apenas prioriza o payload.
    define('FLOW_FUNCOES_COLABORADOR_INTERNAL', true);
    ob_start();
    require dirname(__DIR__) . '/getFuncoesPorColaborador.php';
    ob_end_clean();
    if (!isset($response) || !is_array($response)) {
        throw new RuntimeException('Não foi possível carregar os dados operacionais.');
    }

    $conn = conectarBanco();
    if ($gestor) {
        $overview = flow_overview_v1_gestor($conn, (array) ($response['pendencias_operacionais'] ?? []), $section);
    } else {
        $overview = flow_overview_v1_colaborador($conn, $response, $colaboradorAlvo, $section);
    }
    if ($conn instanceof mysqli) {
        $conn->close();
    }
    overview_v1_response(200, ['success' => true, 'generated_at' => date('c'), 'overview' => $overview]);
} catch (Throwable $erro) {
    error_log('Overview V1: ' . $erro->getMessage());
    overview_v1_response(500, ['success' => false, 'error' => 'Não foi possível carregar a Visão Geral.']);
}
