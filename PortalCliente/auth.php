<?php

declare(strict_types=1);
require_once __DIR__ . '/lib.php';

function portal_registration_allowed(mysqli $db, array $p, string $email): ?array
{
    portal_open($p);
    $contact = portal_one($db, 'SELECT idcontato_cliente,ativo FROM contato_cliente WHERE email_normalizado=?', 's', [$email]);
    if ($contact) {
        if (!(int)$contact['ativo']) {
            throw new PortalError('Não foi possível autorizar este acesso. Fale com seu contato na Improov.', 403);
        }
        $cid = (int)$contact['idcontato_cliente'];
        $member = portal_one($db, 'SELECT removido_em,ingressou_em FROM portal_participante WHERE obra_id=? AND contato_id=?', 'ii', [(int)$p['obra_id'],$cid]);
        $link = portal_one($db, 'SELECT ativo FROM obra_contato WHERE obra_id=? AND contato_cliente_id=?', 'ii', [(int)$p['obra_id'],$cid]);
        if (($member && $member['removido_em']) || ($link && !(int)$link['ativo'])) {
            throw new PortalError('Não foi possível autorizar este acesso. Fale com seu contato na Improov.', 403);
        }
        if ($member && $member['ingressou_em']) {
            return $contact;
        }
    }
    if (!(int)$p['inscricoes_abertas']) {
        throw new PortalError('Novas participações estão pausadas. Fale com seu contato na Improov.', 403);
    }
    return $contact;
}

/** Must run while holding the project row lock. Sender injection is for CLI tests only. */
function portal_issue_otp(mysqli $db, array $p, array $body, ?callable $testSender = null): void
{
    if ($testSender && PHP_SAPI !== 'cli') {
        throw new LogicException('Test sender is CLI-only.');
    }
    $email = briefing_email($body['email'] ?? '');
    if (strlen($email) > 150) {
        throw new PortalError('O e-mail deve ter até 150 caracteres.');
    }
    // Uniform flow: never disclose whether the email already belongs to a contact.
    $name = portal_text($body['nome'] ?? '', 150);
    $phone = portal_text($body['telefone'] ?? '', 30);
    $contact = portal_registration_allowed($db, $p, $email);
    $obra = (int)$p['obra_id'];
    $ip = briefing_external_client_ip();
    $cfg = briefing_external_auth_config();
    $stats = portal_one($db, 'SELECT COUNT(*) n,COALESCE(TIMESTAMPDIFF(SECOND,MAX(criado_em),UTC_TIMESTAMP()),99999) elapsed FROM external_otp_challenge WHERE portal_obra_id=? AND email_normalizado=? AND criado_em>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)', 'is', [$obra,$email]);
    $ipCount = (int)briefing_scalar($db, 'SELECT COUNT(*) FROM external_otp_challenge WHERE ip_solicitacao=? AND criado_em>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)', 's', [$ip]);
    if ((int)$stats['n'] >= $cfg['request_limit'] || (int)$stats['elapsed'] < $cfg['resend_cooldown'] || $ipCount >= 20) {
        throw new PortalError('Aguarde um pouco antes de pedir outro código.', 429);
    }
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    portal_exec($db, 'UPDATE external_otp_challenge SET expira_em=UTC_TIMESTAMP() WHERE portal_obra_id=? AND email_normalizado=? AND consumido_em IS NULL', 'is', [$obra,$email]);
    portal_exec($db, "INSERT INTO external_otp_challenge(portal_obra_id,portal_convite_hash,contato_cliente_id,email_normalizado,finalidade,code_hash,expira_em,ip_solicitacao,pending_payload,criado_em,ultimo_envio_em) VALUES (?,?,?,?,'PORTAL_ACCESS',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())", 'isissss', [$obra,$p['convite_hash'],$contact ? (int)$contact['idcontato_cliente'] : null,$email,password_hash($code, PASSWORD_DEFAULT),$ip,json_encode(['nome' => $name,'telefone' => $phone], JSON_THROW_ON_ERROR)]);
    $sent = $testSender ? $testSender($email, $code) : briefing_send_otp($email, $code, $p['nome_projeto'], 'portal');
    if (!$sent) {
        throw new PortalError('Não conseguimos enviar o código agora. Tente novamente em alguns instantes.', 503);
    }
    portal_event($db, $obra, 'access.code_requested', null, null);
}
/** Returns null on wrong code so the caller COMMITs the failed attempt counter. */
function portal_verify_otp(mysqli $db, array $p, array $body): ?int
{
    $email = briefing_email($body['email'] ?? '');
    $obra = (int)$p['obra_id'];
    $contact = portal_registration_allowed($db, $p, $email);
    $c = portal_one($db, "SELECT * FROM external_otp_challenge WHERE portal_obra_id=? AND portal_convite_hash=? AND email_normalizado=? AND finalidade='PORTAL_ACCESS' AND consumido_em IS NULL AND expira_em>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE", 'iss', [$obra,$p['convite_hash'],$email]);
    if (!$c || (int)$c['tentativas'] >= briefing_external_auth_config()['max_attempts']) {
        throw new PortalError('Solicite um novo código para continuar.');
    }
    $code = (string)($body['code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $c['code_hash'])) {
        portal_exec($db, 'UPDATE external_otp_challenge SET tentativas=tentativas+1 WHERE id=?', 'i', [(int)$c['id']]);
        return null;
    }
    $pending = json_decode($c['pending_payload'], true, 512, JSON_THROW_ON_ERROR);
    if (!$contact) {
        $cid = contact_arch_save_client_contact($db, (int)$p['cliente'], ['name' => $pending['nome'],'email' => $email,'phone' => $pending['telefone'],'type' => 'OUTRO'], true);
    } else {
        $cid = (int)$contact['idcontato_cliente'];
    }
    // Existing identities are never overwritten by unverified registration data.
    // The authenticated profile screen is the explicit place to update name/phone.
    contact_arch_link_contact_to_obra($db, $obra, $cid);
    portal_exec($db,'INSERT INTO portal_participante(obra_id,contato_id,ingressou_em) VALUES (?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE ingressou_em=COALESCE(ingressou_em,UTC_TIMESTAMP())','ii',[$obra,$cid]);
    portal_exec($db,'UPDATE external_otp_challenge SET consumido_em=UTC_TIMESTAMP(),pending_payload=NULL WHERE id=?','i',[(int)$c['id']]);
    portal_event($db,$obra,'participant.identified',null,$cid);
    return $cid;
}
