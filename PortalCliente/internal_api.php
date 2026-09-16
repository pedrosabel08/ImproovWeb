<?php

declare(strict_types=1);
require_once __DIR__.'/lib.php';
$db = briefing_conn();
$tx = false;
try {
    $user = portal_internal_user($db);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new PortalError('Use POST.', 405);
    }
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        throw new PortalError('Envie JSON.', 415);
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
        throw new PortalError('Solicitação muito grande.', 413);
    }
    $b = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    $action = $b['action'] ?? '';
    $csrf = (string)($_SERVER['HTTP_X_PORTAL_CSRF'] ?? '');
    if (!$csrf || !hash_equals((string)($_SESSION['portal_internal_csrf'] ?? ''), $csrf)) {
        throw new PortalError('Atualize a página para continuar.', 403);
    }
    if ($action === 'bootstrap') {
        $admin = (int)$user['nivel_acesso'] === 1;
        $projects = portal_rows($db, 'SELECT o.idobra,o.nome_obra,o.nomenclatura,p.obra_id portal_obra_id FROM obra o LEFT JOIN portal_projeto p ON p.obra_id=o.idobra WHERE '.($admin ? '(o.status_obra=0 OR p.obra_id IS NOT NULL)' : 'p.curador_usuario_id=?').' ORDER BY o.nome_obra', $admin ? '' : 'i', $admin ? [] : [(int)$user['idusuario']]);
        $users = $admin ? portal_rows($db, 'SELECT idusuario,nome_usuario FROM usuario WHERE ativo=1 ORDER BY nome_usuario') : [];
        briefing_json(['ok' => true,'projects' => $projects,'users' => $users,'admin' => $admin]);
    }
    if ($action === 'project.get') {
        $p = portal_project($db, (int)($b['obra_id'] ?? 0));
        portal_internal_access($p, $user);
        briefing_json(['ok' => true,'data' => portal_data($db, $p)]);
    }
    $db->begin_transaction();
    $tx = true;
    if ($action === 'project.configure') {
        $result = portal_configure($db, $user, $b);
    } else {
        $p = portal_project($db, (int)($b['obra_id'] ?? 0), true);
        $result = portal_internal_mutate($db, $p, $user, $action, $b);
    }
    $db->commit();
    $tx = false;
    briefing_json(['ok' => true] + $result);
} catch (Throwable $e) {
    if ($tx) {
        $db->rollback();
    }
    if ($e instanceof PortalError) {
        briefing_json(['ok' => false,'message' => $e->getMessage()], $e->http);
    }
    if ($e instanceof InvalidArgumentException || $e instanceof JsonException) {
        briefing_json(['ok' => false,'message' => 'Confira os dados informados.'], 422);
    }
    error_log('[PortalCliente internal] '.$e->getMessage());
    briefing_json(['ok' => false,'message' => 'Não foi possível concluir. Confira a instalação do Portal ou tente novamente.'],500);
}
