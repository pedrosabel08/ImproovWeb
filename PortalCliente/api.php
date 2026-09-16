<?php

declare(strict_types=1);
require_once __DIR__ . '/auth.php';
header('Referrer-Policy: no-referrer');
$db = briefing_conn();
$transaction = false;
$rateLock = null;
try {
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
    if (!is_array($b)) {
        throw new PortalError('Solicitação inválida.', 400);
    }
    $action = (string)($b['action'] ?? '');
    $token = (string)($b['token'] ?? '');
    if (in_array($action, ['access.start','access.verify'], true)) {
        $csrf = (string)($_SERVER['HTTP_X_PORTAL_CSRF'] ?? '');
        if (!$csrf || !hash_equals((string)($_SESSION['portal_entry_csrf'] ?? ''), $csrf)) {
            throw new PortalError('Atualize a página para continuar.', 403);
        }
        // Serialize OTP issuance by source IP across projects as well as per project.
        if ($action === 'access.start') {
            $rateLock = 'portal_otp_'.substr(hash('sha256', briefing_external_client_ip()), 0, 40);
            if (!(int)briefing_scalar($db, 'SELECT GET_LOCK(?,5)', 's', [$rateLock])) {
                throw new PortalError('Aguarde um pouco e tente novamente.', 429);
            }
        }
        $db->begin_transaction();
        $transaction = true;
        $p = portal_link($db, $token, true);
        if ($action === 'access.start') {
            portal_issue_otp($db, $p, $b);
            $result = ['message' => 'Enviamos um código para seu e-mail.'];
        } else {
            $cid = portal_verify_otp($db, $p, $b);
            $result = ['verified' => $cid !== null];
        }
        $db->commit();
        $transaction = false;
        if ($rateLock) {
            briefing_scalar($db, 'SELECT RELEASE_LOCK(?)', 's', [$rateLock]);
            $rateLock = null;
        }
        if ($action === 'access.verify') {
            if (!$cid) {
                throw new PortalError('Código inválido. Confira os seis números.');
            }
            $result += briefing_external_create_auth_session($db, $cid);
        }
        briefing_json(['ok' => true] + $result);
    }
    $p = portal_link($db, $token);
    // Never accept a caller-controlled project in place of the invitation scope.
    if (isset($b['obra_id']) && (int)$b['obra_id'] !== (int)$p['obra_id']) {
        throw new PortalError('Acesso negado.', 403);
    }
    $auth = briefing_external_current_auth($db);
    if ($action === 'access.inspect') {
        $joined = false;
        if ($auth) {
            try {
                portal_member($db, (int)$p['obra_id'], (int)$auth['contact_id']);
                $joined = true;
            } catch (PortalError) {
            }
        }
        briefing_json(['ok' => true,'authenticated' => $joined,'project' => ['nome' => $p['nome_projeto'],'cliente' => $p['nome_cliente'],'local' => $p['local']],'closed' => $p['estado'] !== 'ABERTO' || (int)$p['status_obra'] !== 0]);
    }
    if (!$auth) {
        throw new PortalError('Identifique-se para continuar.', 401);
    }
    $cid = (int)$auth['contact_id'];
    portal_member($db, (int)$p['obra_id'], $cid);
    if ($action === 'project.get') {
        briefing_json(['ok' => true,'csrf' => briefing_external_csrf_token($auth),'data' => portal_data($db, $p, $cid)]);
    }
    if (!briefing_external_csrf_valid($auth, (string)($_SERVER['HTTP_X_PORTAL_CSRF'] ?? ''))) {
        throw new PortalError('Atualize a página para continuar.', 403);
    }
    $db->begin_transaction();
    $transaction = true;
    $p = portal_link($db, $token, true);
    portal_member($db, (int)$p['obra_id'], $cid);
    if ($action === 'preparation.save') {
        portal_prepare($db, $p, $cid, $b);
        $message = 'Projeto preparado. Nossa equipe vai organizar os materiais necessários.';
    } elseif ($action === 'profile.save') {
        portal_profile($db, $p, $cid, $b);
        $message = 'Sua participação foi registrada. Você já pode acompanhar o projeto.';
    } elseif ($action === 'invite.share') {
        portal_open($p);
        if (!(int)$p['inscricoes_abertas']) {
            throw new PortalError('Novas participações estão pausadas.', 409);
        }
        portal_event($db, (int)$p['obra_id'], 'invite.shared', null, $cid);
        $message = 'Convite pronto para compartilhar. Cada pessoa confirmará sua identidade ao entrar.';
    } elseif ($action === 'access.logout') {
        portal_exec($db, 'UPDATE external_auth_session SET revogado_em=UTC_TIMESTAMP() WHERE id=?', 'i', [(int)$auth['id']]);
        briefing_external_set_auth_cookie('', time() - 3600);
        $message = 'Você saiu do Portal.';
    } else {
        throw new PortalError('Ação indisponível.', 404);
    }
    $db->commit();
    $transaction = false;
    briefing_json(['ok' => true,'message' => $message]);
} catch (Throwable $e) {
    if ($transaction) {
        $db->rollback();
    }
    if ($rateLock) {
        briefing_scalar($db, 'SELECT RELEASE_LOCK(?)', 's', [$rateLock]);
    }
    if ($e instanceof PortalError) {
        briefing_json(['ok' => false,'message' => $e->getMessage()], $e->http);
    }
    if ($e instanceof InvalidArgumentException || $e instanceof JsonException) {
        briefing_json(['ok' => false,'message' => 'Confira os dados informados.'],422);
    }
    error_log('[PortalCliente] '.$e->getMessage());
    briefing_json(['ok' => false,'message' => 'Não foi possível concluir agora. Tente novamente em instantes.'],500);
}
