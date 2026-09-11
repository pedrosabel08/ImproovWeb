<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function custos_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function custos_auth(bool $write = false): void
{
    if (($_SESSION['logado'] ?? false) !== true) custos_json(['error' => 'Não autenticado.'], 401);
    if (!in_array((int)($_SESSION['nivel_acesso'] ?? 0), [1, 5], true)) custos_json(['error' => 'Acesso financeiro restrito à gestão.'], 403);
    if ($write) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') custos_json(['error' => 'Use POST.'], 405);
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
        if (empty($_SESSION['custos_csrf']) || !hash_equals($_SESSION['custos_csrf'], $token)) custos_json(['error' => 'Token CSRF inválido. Atualize a página.'], 419);
    }
}
function custos_obra(mysqli $conn, int $id): array
{
    require_once __DIR__ . '/../conexaoMain.php';
    require_once __DIR__ . '/../helpers/custos_helper.php';
    if ($id <= 0 || !improov_usuario_pode_acessar_obra($conn, $id)) custos_json(['error' => 'Sem acesso à obra.'], 403);
    $obra = custos_query($conn, 'SELECT idobra,nomenclatura,nome_obra,status_obra FROM obra WHERE idobra=?', 'i', [$id])[0] ?? null;
    if (!$obra) custos_json(['error' => 'Obra não encontrada.'], 404);
    return $obra;
}
