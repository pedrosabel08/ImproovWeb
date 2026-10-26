<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SESSION['logado'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/p00_delivery_helpers.php';
require_once __DIR__ . '/../helpers/obra_nas_path_helper.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Entrega inválida.']);
    exit;
}
try {
    $stmt = $conn->prepare('SELECT e.id, e.obra_id, e.tipo_entrega, o.nomenclatura, s.nome_status FROM entregas e JOIN obra o ON o.idobra=e.obra_id JOIN status_imagem s ON s.idstatus=e.status_id WHERE e.id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $entrega = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$entrega || !improov_usuario_pode_acessar_obra($conn, (int) $entrega['obra_id'])) {
        http_response_code($entrega ? 403 : 404);
        echo json_encode(['error' => 'Entrega indisponível.']);
        exit;
    }
    $p00 = ($entrega['tipo_entrega'] ?? '') === 'P00' ? (improov_p00_fetch_latest_version($conn, $id) ?? []) : null;
    session_write_close(); // NAS lookup must not block other authenticated requests.
    $base = flow_obra_nas_base((int) $entrega['obra_id'], $entrega['nomenclatura']);
    $caminho = $base['base'] ? flow_entrega_nas_caminho($base['base'], $entrega['nome_status'], $p00) : null;
    echo json_encode(['caminho_entrega' => $caminho, 'estado' => $caminho ? 'IDENTIFICADO' : $base['estado'],
        'mensagem' => $base['estado'] === 'AMBIGUO' ? 'Mais de uma pasta encontrada para esta obra.'
            : ($caminho ? null : 'Caminho não identificado.')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível consultar o caminho.']);
}
