<?php

declare(strict_types=1);
require_once __DIR__ . '/lib.php';

$conn = briefing_conn();
$actorId = briefing_internal_require();
$body = briefing_body();
$action = (string)($body['action'] ?? $_GET['action'] ?? '');
if ($action === '') briefing_json(['ok' => false, 'message' => 'Ação obrigatória.'], 400);
if (briefing_is_mutation($action)) briefing_require_internal_csrf();

function internal_obras(mysqli $conn): array
{
    $r = $conn->query('SELECT idobra,nome_obra FROM obra ORDER BY nome_obra');
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function internal_collaborators(mysqli $conn): array
{
    $r = $conn->query('SELECT idcolaborador,nome_colaborador FROM colaborador WHERE ativo=1 ORDER BY nome_colaborador');
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function template_full(mysqli $conn, int $id): ?array
{
    $stmt = briefing_stmt($conn, 'SELECT * FROM briefing_template WHERE id=?', 'i', [$id]);
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) return null;
    $t['id'] = (int)$t['id'];
    $t['sections'] = [];
    $stmt = briefing_stmt($conn, 'SELECT * FROM briefing_template_section WHERE template_id=? ORDER BY ordem,id', 'i', [$id]);
    $rs = $stmt->get_result();
    while ($s = $rs->fetch_assoc()) {
        $s['id'] = (int)$s['id'];
        $s['questions'] = [];
        $t['sections'][$s['id']] = $s;
    }
    $stmt->close();
    if ($t['sections']) {
        $ids = array_keys($t['sections']);
        $m = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM briefing_template_question WHERE section_id IN ($m) ORDER BY ordem,id");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $rq = $stmt->get_result();
        $qids = [];
        while ($q = $rq->fetch_assoc()) {
            $q['id'] = (int)$q['id'];
            $q['options'] = [];
            $qids[] = $q['id'];
            $t['sections'][(int)$q['section_id']]['questions'][$q['id']] = $q;
        }
        $stmt->close();
        if ($qids) {
            $m = implode(',', array_fill(0, count($qids), '?'));
            $stmt = $conn->prepare("SELECT * FROM briefing_template_question_option WHERE question_id IN ($m) ORDER BY ordem,id");
            $types = str_repeat('i', count($qids));
            $stmt->bind_param($types, ...$qids);
            $stmt->execute();
            $ro = $stmt->get_result();
            while ($o = $ro->fetch_assoc()) {
                foreach ($t['sections'] as &$s) {
                    if (isset($s['questions'][(int)$o['question_id']])) {
                        $s['questions'][(int)$o['question_id']]['options'][] = $o;
                        break;
                    }
                }
                unset($s);
            }
            $stmt->close();
        }
    }
    foreach ($t['sections'] as &$s) {
        $s['questions'] = array_values($s['questions']);
    }
    unset($s);
    $t['sections'] = array_values($t['sections']);
    return $t;
}
function save_template(mysqli $conn, array $data, int $actorId): int
{
    $name = briefing_clean_text($data['name'] ?? '', 180);
    if ($name === '') throw new InvalidArgumentException('Nome do template obrigatório.');
    $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
    if ($sections === []) throw new InvalidArgumentException('Inclua ao menos uma seção.');
    $id = (int)($data['template_id'] ?? 0);
    $requires = (int)!empty($data['requires_internal_review']);
    $reviewer = (int)($data['default_reviewer_id'] ?? 0) ?: null;
    if ($id > 0) {
        $stmt = briefing_stmt($conn, 'UPDATE briefing_template SET nome=?,versao=versao+1,exige_conferencia_interna=?,revisor_padrao_colaborador_id=? WHERE id=?', 'siii', [$name, $requires, $reviewer, $id]);
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new InvalidArgumentException('Template não encontrado.');
        }
        $stmt->close();
        briefing_stmt($conn, 'DELETE FROM briefing_template_section WHERE template_id=?', 'i', [$id])->close();
    } else {
        $stmt = briefing_stmt($conn, 'INSERT INTO briefing_template (nome,exige_conferencia_interna,revisor_padrao_colaborador_id,criado_por_colaborador_id) VALUES (?,?,?,?)', 'siii', [$name, $requires, $reviewer, $actorId]);
        $id = (int)$conn->insert_id;
        $stmt->close();
    }
    foreach ($sections as $si => $section) {
        $title = briefing_clean_text($section['title'] ?? '', 180);
        if ($title === '') throw new InvalidArgumentException('Toda seção precisa de título.');
        $stmt = briefing_stmt($conn, 'INSERT INTO briefing_template_section (template_id,titulo,descricao,ordem) VALUES (?,?,?,?)', 'issi', [$id, $title, briefing_clean_text($section['description'] ?? '', 2000), (int)$si]);
        $sectionId = (int)$conn->insert_id;
        $stmt->close();
        $questions = is_array($section['questions'] ?? null) ? $section['questions'] : [];
        foreach ($questions as $qi => $question) {
            $type = (string)($question['type'] ?? '');
            if (!in_array($type, BRIEFING_QUESTION_TYPES, true)) throw new InvalidArgumentException('Tipo de pergunta inválido.');
            $text = briefing_clean_text($question['text'] ?? '', 10000);
            if ($text === '') throw new InvalidArgumentException('Toda pergunta precisa de texto.');
            $validation = json_encode(is_array($question['validation'] ?? null) ? $question['validation'] : []);
            $stmt = briefing_stmt($conn, 'INSERT INTO briefing_template_question (section_id,codigo,pergunta,ajuda,tipo,obrigatoria,permite_nao_aplica,ordem,validacao_json) VALUES (?,?,?,?,?,?,?,?,?)', 'issssiiis', [$sectionId, briefing_clean_text($question['code'] ?? '', 100), $text, briefing_clean_text($question['help'] ?? '', 2000), $type, (int)!empty($question['required']), (int)!empty($question['allow_not_applicable']), (int)$qi, $validation]);
            $questionId = (int)$conn->insert_id;
            $stmt->close();
            foreach ((array)($question['options'] ?? []) as $oi => $option) {
                $label = briefing_clean_text(is_array($option) ? ($option['label'] ?? '') : $option, 255);
                if ($label === '') continue;
                $value = briefing_clean_text(is_array($option) ? ($option['value'] ?? $label) : $label, 255);
                $stmt = briefing_stmt($conn, 'INSERT INTO briefing_template_question_option (question_id,rotulo,valor,ordem) VALUES (?,?,?,?)', 'issi', [$questionId, $label, $value, (int)$oi]);
                $stmt->close();
            }
        }
    }
    return $id;
}
function briefing_cycle(mysqli $conn, array $briefing, string $action, ?int $actorId): void
{
    $logs = [];
    flow_connect_publish_operational_pending($conn, 'briefing', 'client_response', $action, 'briefing', (int)$briefing['id'], ['titulo' => $briefing['titulo'], 'obra_id' => (int)$briefing['obra_id'], 'due_at' => $briefing['prazo_em'], 'responsavel_id' => (int)($briefing['revisor_colaborador_id'] ?? 0)], $actorId, $logs);
}

try {
    if ($action === 'bootstrap') briefing_json(['ok' => true, 'csrf' => briefing_csrf_token(), 'obras' => internal_obras($conn), 'collaborators' => internal_collaborators($conn), 'templates' => array_map(fn($x) => ['id' => (int)$x['id'], 'name' => $x['nome'], 'version' => (int)$x['versao']], $conn->query('SELECT id,nome,versao FROM briefing_template WHERE ativo=1 ORDER BY nome')->fetch_all(MYSQLI_ASSOC))]);
    if ($action === 'template.list') {
        $r = $conn->query('SELECT id,nome,versao,ativo,atualizado_em FROM briefing_template ORDER BY nome');
        briefing_json(['ok' => true, 'templates' => $r ? $r->fetch_all(MYSQLI_ASSOC) : []]);
    }
    if ($action === 'template.get') {
        $template = template_full($conn, (int)($body['template_id'] ?? 0));
        if (!$template) briefing_json(['ok' => false, 'message' => 'Template não encontrado.'], 404);
        briefing_json(['ok' => true, 'template' => $template]);
    }
    if ($action === 'template.save') {
        $conn->begin_transaction();
        $id = save_template($conn, $body, $actorId);
        $conn->commit();
        briefing_json(['ok' => true, 'template_id' => $id, 'template' => template_full($conn, $id)]);
    }
    if ($action === 'briefing.create') {
        $obra = (int)($body['obra_id'] ?? 0);
        if ($obra <= 0 || !briefing_scalar($conn, 'SELECT idobra FROM obra WHERE idobra=?', 'i', [$obra])) throw new InvalidArgumentException('Obra inválida.');
        $title = briefing_clean_text($body['title'] ?? '', 180);
        if ($title === '') throw new InvalidArgumentException('Título obrigatório.');
        $due = trim((string)($body['due_at'] ?? ''));
        $due = $due === '' ? null : date('Y-m-d H:i:s', strtotime($due));
        $conn->begin_transaction();
        $id = briefing_clone_template($conn, (int)($body['template_id'] ?? 0), $obra, $title, $due, (int)($body['reviewer_id'] ?? 0) ?: null, array_key_exists('requires_internal_review', $body) ? (bool)$body['requires_internal_review'] : null, $actorId);
        $conn->commit();
        briefing_publish_realtime($id, 'briefing.created');
        briefing_json(['ok' => true, 'briefing_id' => $id]);
    }
    if ($action === 'briefing.list') {
        $rows = $conn->query("SELECT b.*,o.nome_obra,c.nome_colaborador revisor_nome FROM briefing_online b LEFT JOIN obra o ON o.idobra=b.obra_id LEFT JOIN colaborador c ON c.idcolaborador=b.revisor_colaborador_id ORDER BY b.atualizado_em DESC")->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['temporal_status'] = briefing_temporal_state($row['prazo_em']);
            $row['progress'] = briefing_progress($conn, (int)$row['id']);
        }
        unset($row);
        briefing_json(['ok' => true, 'briefings' => $rows]);
    }
    if ($action === 'briefing.detail') {
        $briefing = briefing_fetch($conn, (int)($body['briefing_id'] ?? 0));
        if (!$briefing) briefing_json(['ok' => false, 'message' => 'Briefing não encontrado.'], 404);
        $stmt = briefing_stmt($conn, 'SELECT id,expira_em,criado_em,ultimo_uso_em FROM briefing_access_link WHERE briefing_id=? AND revogado_em IS NULL AND expira_em>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1', 'i', [(int) $briefing['id']]);
        $briefing['external_access'] = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        briefing_json(['ok' => true, 'briefing' => $briefing]);
    }
    if ($action === 'briefing.prepare') {
        $id = (int)($body['briefing_id'] ?? 0);
        $conn->begin_transaction();
        $stmt = briefing_stmt($conn, "UPDATE briefing_online SET status='PRONTO_PARA_ENVIO' WHERE id=? AND status='RASCUNHO'", 'i', [$id]);
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new InvalidArgumentException('Somente rascunhos podem ser preparados.');
        }
        $stmt->close();
        briefing_event($conn, $id, 'briefing.prepared', 'COLABORADOR', $actorId);
        $conn->commit();
        briefing_publish_realtime($id, 'briefing.status_updated', ['status' => 'PRONTO_PARA_ENVIO']);
        briefing_json(['ok' => true]);
    }
    if ($action === 'briefing.create_link') {
        $id = (int)($body['briefing_id'] ?? 0);
        $briefing = briefing_fetch($conn, $id);
        if (!$briefing) throw new InvalidArgumentException('Briefing não encontrado.');
        if (!in_array($briefing['status'], ['PRONTO_PARA_ENVIO', 'AGUARDANDO_CLIENTE', 'EM_PREENCHIMENTO'], true)) throw new InvalidArgumentException('O briefing não está disponível para envio.');
        $hours = max(1, min(24 * 365, (int)($body['expires_hours'] ?? 720)));
        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + $hours * 3600);
        $conn->begin_transaction();
        briefing_stmt($conn, 'UPDATE briefing_access_link SET revogado_em=UTC_TIMESTAMP(),revogado_motivo=?,revogado_por_colaborador_id=? WHERE briefing_id=? AND revogado_em IS NULL AND expira_em>UTC_TIMESTAMP()', 'sii', ['SUBSTITUIDO', $actorId, $id])->close();
        $stmt = briefing_stmt($conn, 'INSERT INTO briefing_access_link (briefing_id,token_hash,expira_em,criado_por_colaborador_id) VALUES (?,?,?,?)', 'issi', [$id, hash('sha256', $token), $expires, $actorId]);
        $stmt->close();
        briefing_stmt($conn, "UPDATE briefing_online SET status='AGUARDANDO_CLIENTE',link_expira_em=? WHERE id=?", 'si', [$expires, $id])->close();
        briefing_event($conn, $id, 'briefing.link_issued', 'COLABORADOR', $actorId, ['expires_at' => $expires, 'replaced_previous' => true]);
        briefing_cycle($conn, $briefing + ['id' => $id], 'criada', $actorId);
        briefing_flow_event($conn, $briefing + ['id' => $id], 'link_issued', $actorId);
        $conn->commit();
        briefing_publish_realtime($id, 'briefing.status_updated', ['status' => 'AGUARDANDO_CLIENTE']);
        briefing_json(['ok' => true, 'url' => briefing_base_path() . '/BriefingExt/?t=' . rawurlencode($token), 'expires_at' => $expires]);
    }
    if ($action === 'briefing.revoke_link') {
        $id = (int) ($body['briefing_id'] ?? 0);
        $briefing = briefing_fetch($conn, $id);
        if (!$briefing) throw new InvalidArgumentException('Briefing não encontrado.');
        $conn->begin_transaction();
        $stmt = briefing_stmt($conn, 'UPDATE briefing_access_link SET revogado_em=UTC_TIMESTAMP(),revogado_motivo=?,revogado_por_colaborador_id=? WHERE briefing_id=? AND revogado_em IS NULL AND expira_em>UTC_TIMESTAMP()', 'sii', ['REVOGADO_MANUALMENTE', $actorId, $id]);
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed < 1) throw new InvalidArgumentException('Não há link ativo para revogar.');
        briefing_event($conn, $id, 'briefing.link_revoked', 'COLABORADOR', $actorId, ['reason' => 'REVOGADO_MANUALMENTE']);
        $conn->commit();
        briefing_publish_realtime($id, 'briefing.access.revoked');
        briefing_json(['ok' => true]);
    }
    if ($action === 'briefing.invite') {
        throw new InvalidArgumentException('Convites diretos foram substituídos pelo link de acesso seguro.');
        $id = (int)($body['briefing_id'] ?? 0);
        $email = briefing_email($body['email'] ?? '');
        $name = briefing_clean_text($body['name'] ?? '', 180);
        $briefing = briefing_fetch($conn, $id);
        if (!$briefing) throw new InvalidArgumentException('Briefing não encontrado.');
        $contact = briefing_contact_by_email($conn, (int)$briefing['obra_id'], $email);
        if ($name === '') $name = $contact['nome'] ?? $email;
        $cid = $contact['idcontato_cliente'] ?? null;
        $company = $contact['nome_cliente'] ?? null;
        $stmt = briefing_stmt($conn, 'INSERT INTO briefing_participant (briefing_id,contato_cliente_id,email,nome,empresa) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome),empresa=COALESCE(VALUES(empresa),empresa),ativo=1', 'iisss', [$id, $cid, $email, $name, $company]);
        $stmt->close();
        briefing_event($conn, $id, 'participant.invited', 'COLABORADOR', $actorId, ['email' => $email]);
        briefing_json(['ok' => true]);
    }
    if ($action === 'briefing.request_complement') {
        $id = (int)($body['briefing_id'] ?? 0);
        $question = (int)($body['question_id'] ?? 0);
        $message = briefing_clean_text($body['message'] ?? '', 5000);
        $briefing = briefing_fetch($conn, $id);
        if (!$briefing || !briefing_can_review($briefing, $actorId)) briefing_json(['ok' => false, 'message' => 'Sem permissão para conferência.'], 403);
        if ($message === '') throw new InvalidArgumentException('Explique o complemento necessário.');
        if (!briefing_scalar($conn, 'SELECT q.id FROM briefing_question q JOIN briefing_section s ON s.id=q.briefing_section_id WHERE q.id=? AND s.briefing_id=?', 'ii', [$question, $id])) throw new InvalidArgumentException('Pergunta inválida.');
        $conn->begin_transaction();
        briefing_stmt($conn, 'INSERT INTO briefing_question_request (briefing_question_id,mensagem,solicitado_por_colaborador_id) VALUES (?,?,?)', 'isi', [$question, $message, $actorId])->close();
        briefing_stmt($conn, "UPDATE briefing_online SET status='AJUSTES_SOLICITADOS' WHERE id=?", 'i', [$id])->close();
        briefing_event($conn, $id, 'question.complement_requested', 'COLABORADOR', $actorId, ['question_id' => $question]);
        briefing_cycle($conn, $briefing, 'criada', $actorId);
        briefing_flow_event($conn, $briefing, 'complement_requested', $actorId);
        $conn->commit();
        briefing_publish_realtime($id, 'question.complement_requested', ['question_id' => $question]);
        briefing_json(['ok' => true]);
    }
    if ($action === 'briefing.approve') {
        $id = (int)($body['briefing_id'] ?? 0);
        $briefing = briefing_fetch($conn, $id);
        if (!$briefing || !briefing_can_review($briefing, $actorId)) briefing_json(['ok' => false, 'message' => 'Sem permissão para aprovar.'], 403);
        if ($briefing['status'] !== 'EM_CONFERENCIA') throw new InvalidArgumentException('O briefing não está em conferência.');
        $conn->begin_transaction();
        briefing_stmt($conn, "UPDATE briefing_online SET status='APROVADO',aprovado_em=UTC_TIMESTAMP(),aprovado_por_colaborador_id=? WHERE id=?", 'ii', [$actorId, $id])->close();
        briefing_snapshot($conn, $id, 'APROVADO', 'COLABORADOR', $actorId);
        briefing_event($conn, $id, 'briefing.approved', 'COLABORADOR', $actorId);
        briefing_cycle($conn, $briefing, 'resolvida', $actorId);
        briefing_flow_event($conn, $briefing, 'approved', $actorId);
        $conn->commit();
        briefing_publish_realtime($id, 'briefing.approved', ['status' => 'APROVADO']);
        briefing_json(['ok' => true]);
    }
    if ($action === 'briefing.ws_ticket') {
        $id = (int)($body['briefing_id'] ?? 0);
        if (!briefing_fetch($conn, $id)) throw new InvalidArgumentException('Briefing não encontrado.');
        briefing_json(['ok' => true, 'ticket' => briefing_ws_ticket($id, $actorId, 'INTERNAL')]);
    }
    briefing_json(['ok' => false, 'message' => 'Ação desconhecida.'], 400);
} catch (InvalidArgumentException $e) {
    if ($conn->errno) $conn->rollback();
    briefing_json(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($conn->errno) $conn->rollback();
    error_log('[Briefing api] ' . $e->getMessage());
    briefing_json(['ok' => false, 'message' => 'Não foi possível concluir esta operação.'], 500);
}
