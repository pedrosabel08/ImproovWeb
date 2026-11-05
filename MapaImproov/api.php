<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$conn = null;
try {
    if (($_SESSION['logado'] ?? false) !== true) throw new RuntimeException('Sua sessão expirou. Entre novamente.', 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        throw new RuntimeException('Método não permitido.', 405);
    }
    if (array_diff(array_keys($_GET), ['obra_id'])) throw new InvalidArgumentException('Parâmetro desconhecido.');
    $id = null;
    if (isset($_GET['obra_id'])) {
        if (!is_string($_GET['obra_id']) || !preg_match('/^[1-9][0-9]{0,9}$/D', $_GET['obra_id'])) throw new InvalidArgumentException('Obra inválida.');
        $id = (int) $_GET['obra_id'];
    }
    require_once __DIR__ . '/../conexaoMain.php';
    require_once __DIR__ . '/../helpers/referencia_arquitetonica_helper.php';
    require_once __DIR__ . '/../helpers/mapa_improov_helper.php';
    $conn = conectarBanco();
    $allowed = obterObras($conn, 'all');
    $viewerIds = array_map('intval', array_column(obterObras($conn), 'idobra'));
    if ($id !== null && !in_array($id, array_map('intval', array_column($allowed, 'idobra')), true)) {
        throw new RuntimeException('Obra indisponível para este usuário.', 403);
    }
    if ($id !== null) $allowed = array_values(array_filter($allowed, static fn ($o) => (int) $o['idobra'] === $id));
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $projects = mi_projects($conn, $allowed, $viewerIds);
    $features = [];
    foreach ($projects as $project) {
        if ($project['coordinates'] === null) continue;
        $properties = $project;
        unset($properties['coordinates']);
        $features[] = ['type' => 'Feature', 'id' => $project['obra_id'], 'geometry' => ['type' => 'Point', 'coordinates' => $project['coordinates']], 'properties' => $properties];
    }
    echo json_encode(['sucesso' => true, 'type' => 'FeatureCollection', 'features' => $features, 'obras' => $projects], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    $code = $error instanceof InvalidArgumentException ? 400 : (in_array($error->getCode(), [401,403,405], true) ? $error->getCode() : 500);
    http_response_code($code);
    if ($code === 500) error_log('Mapa Improov: ' . $error->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => $code === 500 ? 'Não foi possível consultar os empreendimentos. Tente novamente.' : $error->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    if ($conn instanceof mysqli) $conn->close();
}
