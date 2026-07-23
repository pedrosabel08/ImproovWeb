<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/pendencias_operacionais_helper.php';

header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => ['code' => 'NAO_AUTENTICADO']]);
    exit;
}
$obraId = (int) ($_GET['obra_id'] ?? 0);
$conn = conectarBanco();
if ($obraId <= 0 || !improov_usuario_pode_acessar_obra($conn, $obraId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => ['code' => 'SEM_ACESSO']]);
    $conn->close();
    exit;
}
$colaboradorId = (int) ($_SESSION['idcolaborador'] ?? 0);
$nivel = (int) ($_SESSION['nivel_acesso'] ?? 0);
$modules = pendencias_operacionais_fetch($conn, $colaboradorId, $nivel, [], [
    'obra_id' => $obraId,
    'include_all_for_obra' => true,
]);
$items = [];
$groups = [];
foreach ($modules as $module) {
    foreach ((array) ($module['items'] ?? []) as $item) {
        if ((int) ($item['obra_id'] ?? 0) !== $obraId) {
            continue;
        }
        $item['module_key'] = (string) ($module['key'] ?? 'operacional');
        $item['module_name'] = (string) ($module['name'] ?? 'Operacional');
        $url = trim((string) ($item['url_destino'] ?? $item['action_url'] ?? ''));
        if ($url !== '' && !str_starts_with($url, '/')) {
            $url = '/ImproovWeb/' . ltrim($url, '/');
        }
        $item['url_destino'] = $url;
        $items[] = $item;
        $key = $item['module_key'];
        $groups[$key] = ($groups[$key] ?? 0) + 1;
    }
}
usort($items, static fn(array $a, array $b): int => strcmp((string) ($a['due_at'] ?? '9999'), (string) ($b['due_at'] ?? '9999')));
echo json_encode(['success' => true, 'data' => ['total' => count($items), 'groups' => $groups, 'items' => $items]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$conn->close();
