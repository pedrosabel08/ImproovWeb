<?php

require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';
require_once dirname(__DIR__, 2) . '/helpers/home_payload_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
date_default_timezone_set('America/Sao_Paulo');

function home_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    home_response(405, ['success' => false, 'error' => 'Método não permitido.']);
}

if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    home_response(401, ['success' => false, 'error' => 'Não autenticado.']);
}

$userId = (int) ($_SESSION['idusuario'] ?? 0);
$collaboratorId = (int) ($_SESSION['idcolaborador'] ?? 0);
if ($userId <= 0 || $collaboratorId <= 0) {
    home_response(403, ['success' => false, 'error' => 'Usuário ou colaborador não identificado na sessão.']);
}

try {
    $conn = conectarBanco();
    $isManager = improov_usuario_eh_gestor_sidebar($conn);

    // A Home é sempre pessoal. Parâmetros manipulados pelo cliente nunca
    // alteram o colaborador usado pela fonte canônica de tarefas.
    unset($_GET['colaborador_id']);
    if (!defined('FLOW_FUNCOES_COLABORADOR_INTERNAL')) {
        define('FLOW_FUNCOES_COLABORADOR_INTERNAL', true);
    }
    ob_start();
    require dirname(__DIR__) . '/getFuncoesPorColaborador.php';
    $unexpectedOutput = ob_get_clean();
    if ($unexpectedOutput !== '') {
        error_log('Home suprimiu saída inesperada do carregador de tarefas.');
    }
    if (!isset($response) || !is_array($response)) {
        throw new RuntimeException('Não foi possível carregar os dados operacionais.');
    }

    // O carregador canônico de tarefas mantém compatibilidade com endpoints
    // legados e pode substituir/fechar a variável $conn no escopo incluído.
    // Reabrimos somente a conexão de composição da Home após essa carga.
    $conn = conectarBanco();

    if ($isManager) {
        $home = home_payload_manager($conn, $response, $collaboratorId, $userId);
    } else {
        $home = home_payload_collaborator($conn, $response, $collaboratorId, $userId);
    }
    $conn->close();

    $payload = array_merge([
        'success' => true,
        'generated_at' => date('c'),
        'user' => [
            'id' => $userId,
            'colaborador_id' => $collaboratorId,
            'name' => (string) ($_SESSION['nome_usuario'] ?? ''),
            'mode' => $isManager ? 'manager' : 'collaborator',
        ],
    ], $home);
    home_response(200, $payload);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->close();
        } catch (Throwable $ignored) {
        }
    }
    error_log('Home API: ' . $error->getMessage());
    home_response(500, ['success' => false, 'error' => 'Não foi possível carregar a Home.']);
}
