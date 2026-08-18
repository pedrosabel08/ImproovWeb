<?php

declare(strict_types=1);

require_once __DIR__ . '/../Briefing/lib.php';

$conn = briefing_conn();
$body = briefing_body();
$action = (string) ($body['action'] ?? $_GET['action'] ?? '');

if ($action === '') {
    briefing_json(['ok' => false, 'message' => 'Ação obrigatória.'], 400);
}

function ext_question(mysqli $conn, int $briefingId, int $questionId): ?array
{
    $stmt = briefing_stmt($conn, 'SELECT q.* FROM briefing_question q JOIN briefing_section s ON s.id=q.briefing_section_id WHERE q.id=? AND s.briefing_id=? AND s.ativa=1 AND q.ativa=1', 'ii', [$questionId, $briefingId]);
    $question = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $question;
}

function ext_answer_snapshot(mysqli $conn, int $briefingId, int $questionId): array
{
    $stmt = briefing_stmt($conn, 'SELECT a.id,a.valor_json,a.nao_aplica,a.versao,a.atualizado_em,p.id atualizado_por_id,p.nome atualizado_por_nome FROM briefing_answer a JOIN briefing_question q ON q.id=a.briefing_question_id JOIN briefing_section s ON s.id=q.briefing_section_id LEFT JOIN briefing_participant p ON p.id=a.atualizado_por_participant_id WHERE a.briefing_question_id=? AND s.briefing_id=? AND s.ativa=1 AND q.ativa=1 LIMIT 1', 'ii', [$questionId, $briefingId]);
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!$row) {
        return [
            'id' => null,
            'value' => null,
            'not_applicable' => false,
            'version' => 0,
            'updated_at' => null,
            'updated_by' => null,
        ];
    }
    return [
        'id' => (int) $row['id'],
        'value' => briefing_json_decode($row['valor_json']),
        'not_applicable' => (bool) $row['nao_aplica'],
        'version' => (int) $row['versao'],
        'updated_at' => $row['atualizado_em'],
        'updated_by' => $row['atualizado_por_nome'] ? [
            'participant_id' => (int) $row['atualizado_por_id'],
            'name' => (string) $row['atualizado_por_nome'],
        ] : null,
    ];
}

function ext_public_link(mysqli $conn, array $body): array
{
    $link = briefing_external_access_link_v2($conn, (string) ($body['token'] ?? ''));
    if (!$link) {
        briefing_json(['ok' => false, 'message' => 'Este link expirou ou foi revogado.'], 404);
    }
    return $link;
}

function ext_otp_verify(mysqli $conn, array $link, string $email, string $code): array
{
    $config = briefing_external_auth_config();
    if (!preg_match('/^\d{' . $config['code_length'] . '}$/', $code)) {
        throw new InvalidArgumentException('Código inválido.');
    }

    $conn->begin_transaction();
    try {
        $link = briefing_external_access_link_v2($conn, (string) ($GLOBALS['body']['token'] ?? ''));
        if (!$link) {
            throw new RuntimeException('Este link expirou ou foi revogado.');
        }
        $stmt = briefing_stmt($conn, 'SELECT * FROM external_otp_challenge WHERE briefing_access_link_id=? AND email_normalizado=? AND consumido_em IS NULL AND expira_em>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE', 'is', [(int) $link['id'], $email]);
        $challenge = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$challenge) {
            throw new InvalidArgumentException('Solicite um novo código.');
        }
        if ((int) $challenge['tentativas'] >= $config['max_attempts']) {
            throw new InvalidArgumentException('Limite de tentativas atingido. Solicite outro código.');
        }
        if (!password_verify($code, (string) $challenge['code_hash'])) {
            briefing_stmt($conn, 'UPDATE external_otp_challenge SET tentativas=tentativas+1 WHERE id=?', 'i', [(int) $challenge['id']])->close();
            $conn->commit();
            briefing_json(['ok' => false, 'message' => 'Código inválido.'], 422);
        }

        $contact = null;
        $purpose = (string) $challenge['finalidade'];
        if ($purpose === 'CREATE_CONTACT') {
            $pending = briefing_json_decode((string) $challenge['pending_payload'], []);
            if (!is_array($pending)) {
                throw new RuntimeException('Não foi possível recuperar seus dados de cadastro.');
            }
            $contact = contact_arch_find_unique_contact_by_email($conn, $email);
            if (!$contact) {
                $context = contact_arch_get_obra_client_context($conn, (int) $link['obra_id']);
                if (!$context || (int) $context['cliente_id'] <= 0) {
                    briefing_event($conn, (int) $link['briefing_id'], 'external.registration_blocked', 'SISTEMA', null, ['reason' => 'obra_without_client']);
                    throw new RuntimeException('Não foi possível concluir seu cadastro neste momento. Entre em contato com a equipe responsável.');
                }
                $contactId = contact_arch_save_client_contact($conn, (int) $context['cliente_id'], [
                    'name' => $pending['name'] ?? '',
                    'email' => $email,
                    'role' => $pending['role'] ?? '',
                    'phone' => $pending['phone'] ?? '',
                    'type' => 'OUTRO',
                ]);
                $contact = contact_arch_fetch_contact_row($conn, $contactId);
            }
        } else {
            $contact = contact_arch_find_unique_contact_by_email($conn, $email);
        }
        if (!$contact) {
            throw new RuntimeException('Não foi possível autorizar este acesso.');
        }
        if (!briefing_external_contact_has_obra($conn, (int) $contact['contact_id'], (int) $link['obra_id'])) {
            contact_arch_link_contact_to_obra($conn, (int) $link['obra_id'], (int) $contact['contact_id']);
        }

        $participant = briefing_external_participant($conn, (int) $link['briefing_id'], $contact);
        briefing_stmt($conn, 'UPDATE external_otp_challenge SET consumido_em=UTC_TIMESTAMP() WHERE id=?', 'i', [(int) $challenge['id']])->close();
        briefing_stmt($conn, 'UPDATE briefing_access_link SET ultimo_uso_em=UTC_TIMESTAMP() WHERE id=?', 'i', [(int) $link['id']])->close();
        briefing_stmt($conn, 'UPDATE briefing_participant SET verificado_em=COALESCE(verificado_em,UTC_TIMESTAMP()),primeiro_acesso_em=COALESCE(primeiro_acesso_em,UTC_TIMESTAMP()),ultimo_acesso_em=UTC_TIMESTAMP(),ultima_atividade_em=UTC_TIMESTAMP() WHERE id=?', 'i', [(int) $participant['id']])->close();
        briefing_event($conn, (int) $link['briefing_id'], 'participant.verified', 'PARTICIPANTE', (int) $participant['id']);
        $session = briefing_external_create_auth_session($conn, (int) $contact['contact_id']);
        $conn->commit();
        briefing_publish_realtime((int) $link['briefing_id'], 'participant.presence_changed');
        return $session;
    } catch (Throwable $e) {
        if ($conn->errno === 0) {
            // no-op; transaction state is not exposed by mysqli
        }
        $conn->rollback();
        throw $e;
    }
}

try {
    if ($action === 'access.inspect') {
        $link = ext_public_link($conn, $body);
        $auth = briefing_external_current_auth($conn);
        $authorized = $auth && briefing_external_contact_has_obra($conn, (int) $auth['contact_id'], (int) $link['obra_id']);
        briefing_json(['ok' => true, 'briefing' => ['title' => $link['titulo'], 'status' => $link['status']], 'authenticated' => (bool) $authorized]);
    }

    if ($action === 'access.start') {
        $link = ext_public_link($conn, $body);
        $email = contact_arch_normalize_email($body['email'] ?? '');
        $contact = contact_arch_find_unique_contact_by_email($conn, $email);
        if (!$contact) {
            briefing_json(['ok' => true, 'next' => 'register']);
        }
        $purpose = briefing_external_contact_has_obra($conn, (int) $contact['contact_id'], (int) $link['obra_id']) ? 'LOGIN' : 'LINK_CONTACT';
        briefing_external_issue_otp($conn, $link, $email, $purpose, (int) $contact['contact_id']);
        briefing_json(['ok' => true, 'next' => 'otp', 'message' => 'Enviamos um código de acesso para seu e-mail.']);
    }

    if ($action === 'access.register') {
        $link = ext_public_link($conn, $body);
        $email = contact_arch_normalize_email($body['email'] ?? '');
        $name = briefing_clean_text($body['name'] ?? '', 180);
        if ($name === '') {
            throw new InvalidArgumentException('Informe seu nome.');
        }
        $contact = contact_arch_find_unique_contact_by_email($conn, $email);
        if ($contact) {
            $purpose = briefing_external_contact_has_obra($conn, (int) $contact['contact_id'], (int) $link['obra_id']) ? 'LOGIN' : 'LINK_CONTACT';
            briefing_external_issue_otp($conn, $link, $email, $purpose, (int) $contact['contact_id']);
        } else {
            $context = contact_arch_get_obra_client_context($conn, (int) $link['obra_id']);
            if (!$context || (int) $context['cliente_id'] <= 0) {
                briefing_event($conn, (int) $link['briefing_id'], 'external.registration_blocked', 'SISTEMA', null, ['reason' => 'obra_without_client']);
                briefing_json(['ok' => false, 'message' => 'Não foi possível concluir seu cadastro neste momento. Entre em contato com a equipe responsável.'], 422);
            }
            briefing_external_issue_otp($conn, $link, $email, 'CREATE_CONTACT', null, [
                'name' => $name,
                'role' => briefing_clean_text($body['role'] ?? '', 120),
                'phone' => briefing_clean_text($body['phone'] ?? '', 60),
            ]);
        }
        briefing_json(['ok' => true, 'next' => 'otp', 'message' => 'Enviamos um código de acesso para seu e-mail.']);
    }

    if ($action === 'access.verify') {
        $link = ext_public_link($conn, $body);
        $email = contact_arch_normalize_email($body['email'] ?? '');
        $code = preg_replace('/\D/', '', (string) ($body['code'] ?? ''));
        $session = ext_otp_verify($conn, $link, $email, $code);
        briefing_json(['ok' => true] + $session);
    }

    if ($action === 'csrf.refresh') {
        $link = ext_public_link($conn, $body);
        $access = briefing_external_access($conn, (string) ($body['token'] ?? ''), false);
        briefing_json(['ok' => true, 'csrf' => briefing_external_csrf_token($access['auth'])]);
    }

    $token = (string) ($body['token'] ?? $_GET['t'] ?? '');
    $mutation = in_array($action, ['answer.save', 'briefing.submit', 'ws.ticket'], true);
    $access = briefing_external_access($conn, $token, $mutation);
    $link = $access['link'];
    $briefingId = (int) $link['briefing_id'];
    $participantId = (int) $access['participant']['id'];

    if ($action === 'briefing.bootstrap') {
        if ($link['status'] === 'AGUARDANDO_CLIENTE') {
            briefing_stmt($conn, "UPDATE briefing_online SET status='EM_PREENCHIMENTO' WHERE id=? AND status='AGUARDANDO_CLIENTE'", 'i', [$briefingId])->close();
            briefing_event($conn, $briefingId, 'briefing.started', 'PARTICIPANTE', $participantId);
            briefing_publish_realtime($briefingId, 'briefing.status_updated', ['status' => 'EM_PREENCHIMENTO']);
        }
        $briefing = briefing_fetch($conn, $briefingId, true);
        foreach ($briefing['sections'] as &$section) {
            foreach ($section['questions'] as &$question) {
                $question['editable'] = briefing_external_question_editable($conn, $briefingId, (string) $briefing['status'], (int) $question['id']);
            }
            unset($question);
        }
        unset($section);
        $csrf = briefing_external_csrf_token($access['auth']);
        briefing_stmt($conn, 'UPDATE external_auth_session SET csrf_hash=? WHERE id=?', 'si', [hash('sha256', $csrf), (int) $access['auth']['id']])->close();
        briefing_json(['ok' => true, 'csrf' => $csrf, 'participant' => ['name' => $access['contact']['name'], 'email' => $access['contact']['email']], 'briefing' => $briefing]);
    }

    if ($action === 'answer.get') {
        $questionId = (int) ($body['question_id'] ?? 0);
        if (!ext_question($conn, $briefingId, $questionId)) {
            throw new InvalidArgumentException('Pergunta inválida.');
        }
        briefing_json(['ok' => true, 'question_id' => $questionId, 'answer' => ext_answer_snapshot($conn, $briefingId, $questionId), 'progress' => briefing_progress($conn, $briefingId)]);
    }

    if ($action === 'answer.save') {
        $questionId = (int) ($body['question_id'] ?? 0);
        if (!briefing_external_question_editable($conn, $briefingId, (string) $link['status'], $questionId)) {
            briefing_json(['ok' => false, 'message' => 'Esta pergunta não está disponível para edição.'], 409);
        }
        $operation = (string) ($body['operation_uuid'] ?? '');
        if (!preg_match('/^[0-9a-f-]{36}$/i', $operation)) {
            throw new InvalidArgumentException('Identificador de operação inválido.');
        }
        $question = ext_question($conn, $briefingId, $questionId);
        if (!$question) {
            throw new InvalidArgumentException('Pergunta inválida.');
        }
        $notApplicable = (bool) ($body['not_applicable'] ?? false);
        $value = briefing_valid_answer($question, $body['value'] ?? null, $notApplicable);
        $encoded = $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expected = (int) ($body['expected_version'] ?? -1);
        $conn->begin_transaction();
        $previous = briefing_scalar($conn, 'SELECT resultado_json FROM briefing_answer_operation WHERE briefing_id=? AND operacao_uuid=?', 'is', [$briefingId, $operation]);
        if ($previous !== null) {
            $conn->commit();
            briefing_json(['ok' => true, 'idempotent' => true, 'answer' => briefing_json_decode($previous)]);
        }
        $stmt = briefing_stmt($conn, 'SELECT a.*,p.id atualizado_por_id,p.nome atualizado_por_nome FROM briefing_answer a LEFT JOIN briefing_participant p ON p.id=a.atualizado_por_participant_id WHERE a.briefing_question_id=? FOR UPDATE', 'i', [$questionId]);
        $answer = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($answer && (int) $answer['versao'] !== $expected) {
            $conn->rollback();
            $currentVersion = (int) $answer['versao'];
            briefing_json([
                'ok' => false,
                'code' => 'VERSION_CONFLICT',
                'message' => 'A resposta foi alterada por outra pessoa.',
                'question_id' => $questionId,
                'expected_version' => $expected,
                'current_version' => $currentVersion,
                'current_value' => briefing_json_decode($answer['valor_json']),
                'updated_by' => $answer['atualizado_por_nome'] ? ['participant_id' => (int) $answer['atualizado_por_id'], 'name' => (string) $answer['atualizado_por_nome']] : null,
                'updated_at' => $answer['atualizado_em'],
                'conflict' => ['value' => briefing_json_decode($answer['valor_json']), 'not_applicable' => (bool) $answer['nao_aplica'], 'version' => $currentVersion, 'updated_by' => $answer['atualizado_por_nome'] ? ['participant_id' => (int) $answer['atualizado_por_id'], 'name' => (string) $answer['atualizado_por_nome']] : null, 'updated_at' => $answer['atualizado_em']],
            ], 409);
        }
        if (!$answer) {
            if ($expected !== 0) {
                $conn->rollback();
                briefing_json(['ok' => false, 'message' => 'A resposta foi alterada por outra pessoa.'], 409);
            }
            briefing_stmt($conn, 'INSERT INTO briefing_answer (briefing_question_id,valor_json,nao_aplica,versao,respondido_por_participant_id,respondido_em,atualizado_por_participant_id) VALUES (?,?,?,1,?,UTC_TIMESTAMP(),?)', 'isiii', [$questionId, $encoded, (int) $notApplicable, $participantId, $participantId])->close();
            $answerId = (int) $conn->insert_id;
            $version = 1;
        } else {
            $answerId = (int) $answer['id'];
            $version = (int) $answer['versao'] + 1;
            briefing_stmt($conn, 'UPDATE briefing_answer SET valor_json=?,nao_aplica=?,versao=?,atualizado_por_participant_id=?,atualizado_em=UTC_TIMESTAMP() WHERE id=?', 'siiii', [$encoded, (int) $notApplicable, $version, $participantId, $answerId])->close();
        }
        briefing_stmt($conn, "UPDATE briefing_question_request SET status='RESPONDIDO_AGUARDANDO_CONFERENCIA',respondido_em=UTC_TIMESTAMP() WHERE briefing_question_id=? AND status='SOLICITADO'", 'i', [$questionId])->close();
        $result = ['id' => $answerId, 'value' => $value, 'not_applicable' => $notApplicable, 'version' => $version];
        briefing_stmt($conn, 'INSERT INTO briefing_answer_operation (briefing_id,operacao_uuid,answer_id,resultado_json) VALUES (?,?,?,?)', 'isis', [$briefingId, $operation, $answerId, json_encode($result, JSON_UNESCAPED_UNICODE)])->close();
        briefing_event($conn, $briefingId, 'answer.updated', 'PARTICIPANTE', $participantId, ['question_id' => $questionId, 'version' => $version]);
        $conn->commit();
        briefing_publish_realtime($briefingId, 'answer.updated', ['question_id' => $questionId, 'version' => $version]);
        briefing_json(['ok' => true, 'answer' => $result, 'progress' => briefing_progress($conn, $briefingId)]);
    }

    if ($action === 'briefing.submit') {
        if (!in_array($link['status'], BRIEFING_OPEN_STATUSES, true)) {
            briefing_json(['ok' => false, 'message' => 'Este briefing não está disponível para envio.'], 409);
        }
        $missing = (int) briefing_scalar($conn, 'SELECT COUNT(*) FROM briefing_question q JOIN briefing_section s ON s.id=q.briefing_section_id LEFT JOIN briefing_answer a ON a.briefing_question_id=q.id WHERE s.briefing_id=? AND s.ativa=1 AND q.ativa=1 AND q.obrigatoria=1 AND (a.id IS NULL OR (a.nao_aplica=0 AND a.valor_json IS NULL))', 'i', [$briefingId]);
        if ($missing > 0) {
            briefing_json(['ok' => false, 'message' => "Ainda há {$missing} pergunta(s) obrigatória(s) sem resposta."], 422);
        }
        $briefing = briefing_fetch($conn, $briefingId, true);
        $conn->begin_transaction();
        briefing_snapshot($conn, $briefingId, 'ENVIADO', 'PARTICIPANTE', $participantId);
        $status = (int) $briefing['exige_conferencia_interna'] ? 'EM_CONFERENCIA' : 'APROVADO';
        briefing_stmt($conn, $status === 'APROVADO' ? "UPDATE briefing_online SET status='APROVADO',enviado_em=UTC_TIMESTAMP(),aprovado_em=UTC_TIMESTAMP() WHERE id=?" : "UPDATE briefing_online SET status='EM_CONFERENCIA',enviado_em=UTC_TIMESTAMP() WHERE id=?", 'i', [$briefingId])->close();
        briefing_event($conn, $briefingId, $status === 'APROVADO' ? 'briefing.auto_approved' : 'briefing.client_submitted', 'PARTICIPANTE', $participantId);
        $conn->commit();
        briefing_publish_realtime($briefingId, $status === 'APROVADO' ? 'briefing.approved' : 'briefing.submitted', ['status' => $status]);
        briefing_json(['ok' => true, 'status' => $status]);
    }

    if ($action === 'ws.ticket') {
        briefing_json(['ok' => true, 'ticket' => briefing_ws_ticket($briefingId, $participantId, 'CLIENTE')]);
    }
    briefing_json(['ok' => false, 'message' => 'Ação desconhecida.'], 400);
} catch (InvalidArgumentException $e) {
    $conn->rollback();
    briefing_json(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    $conn->rollback();
    briefing_json(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[Briefing external api] ' . $e->getMessage());
    briefing_json(['ok' => false, 'message' => 'Não foi possível concluir a operação.'], 500);
}
