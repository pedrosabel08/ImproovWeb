<?php

declare(strict_types=1);

/** Shared, deliberately small domain layer for Briefing Online. */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/secure_env.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../FlowConnect/bootstrap.php';
require_once __DIR__ . '/../contact_architecture.php';

const BRIEFING_QUESTION_TYPES = ['SHORT_TEXT', 'LONG_TEXT', 'YES_NO', 'SINGLE_SELECT', 'MULTI_SELECT', 'NUMBER', 'DATE', 'LINK', 'REFERENCE'];
const BRIEFING_OPEN_STATUSES = ['AGUARDANDO_CLIENTE', 'EM_PREENCHIMENTO', 'AJUSTES_SOLICITADOS'];

function briefing_conn(): mysqli
{
    global $conn;
    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Banco de dados indisponível.');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function briefing_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function briefing_body(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function briefing_base_path(): string
{
    return improov_app_base_path();
}
function briefing_clean_text(mixed $value, int $max = 5000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\p{C}+/u', '', $text) ?? '';
    return mb_substr($text, 0, $max, 'UTF-8');
}
function briefing_email(mixed $value): string
{
    $email = mb_strtolower(trim((string) $value), 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        throw new InvalidArgumentException('E-mail inválido.');
    }
    return $email;
}
function briefing_actor_id(): int
{
    return (int) ($_SESSION['idcolaborador'] ?? 0);
}
function briefing_internal_require(): int
{
    if (($_SESSION['logado'] ?? false) !== true || briefing_actor_id() <= 0) {
        briefing_json(['ok' => false, 'message' => 'Sessão interna inválida.'], 401);
    }
    return briefing_actor_id();
}
function briefing_csrf_token(): string
{
    if (empty($_SESSION['briefing_csrf'])) {
        $_SESSION['briefing_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['briefing_csrf'];
}
function briefing_require_internal_csrf(): void
{
    $provided = (string) ($_SERVER['HTTP_X_BRIEFING_CSRF'] ?? '');
    if ($provided === '' || !hash_equals(briefing_csrf_token(), $provided)) {
        briefing_json(['ok' => false, 'message' => 'Token CSRF inválido.'], 403);
    }
}
function briefing_is_mutation(string $action): bool
{
    return !in_array($action, ['bootstrap', 'template.list', 'template.get', 'briefing.list', 'briefing.detail'], true);
}

function briefing_stmt(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar consulta.');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        throw new RuntimeException($message);
    }
    return $stmt;
}
function briefing_scalar(mysqli $conn, string $sql, string $types = '', array $params = []): mixed
{
    $stmt = briefing_stmt($conn, $sql, $types, $params);
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row[0] ?? null;
}
function briefing_json_decode(?string $json, mixed $default = null): mixed
{
    $value = json_decode((string)$json, true);
    return json_last_error() === JSON_ERROR_NONE ? $value : $default;
}

function briefing_event(mysqli $conn, int $briefingId, string $type, string $actorType = 'SISTEMA', ?int $actorId = null, array $metadata = []): void
{
    $json = $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = briefing_stmt($conn, 'INSERT INTO briefing_event (briefing_id,tipo,ator_tipo,ator_colaborador_id,ator_participant_id,metadata_json) VALUES (?,?,?,?,?,?)', 'issiis', [$briefingId, $type, $actorType, $actorType === 'COLABORADOR' ? $actorId : null, $actorType === 'PARTICIPANTE' ? $actorId : null, $json]);
    $stmt->close();
}

function briefing_publish_realtime(int $briefingId, string $event, array $metadata = []): void
{
    try {
        if (!class_exists('Predis\\Client')) {
            return;
        }
        improov_load_env_once();
        $url = improov_env('REDIS_URL', 'redis://127.0.0.1:6379');
        $redis = new Predis\Client($url);
        $redis->publish('briefing:' . $briefingId, json_encode(['briefing_id' => $briefingId, 'event' => $event, 'at' => gmdate('c'), 'metadata' => $metadata], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $redis->disconnect();
    } catch (Throwable $e) {
        error_log('[Briefing] realtime publish failed: ' . $e->getMessage());
    }
}

function briefing_temporal_state(?string $dueAt): string
{
    if (!$dueAt) {
        return 'SEM_PRAZO';
    }
    $due = new DateTimeImmutable($dueAt);
    $now = new DateTimeImmutable('now');
    if ($due < $now) {
        return 'VENCIDO';
    }
    return $due->format('Y-m-d') === $now->format('Y-m-d') ? 'VENCE_HOJE' : 'NO_PRAZO';
}

function briefing_progress(mysqli $conn, int $briefingId): array
{
    $row = briefing_stmt($conn, "SELECT COUNT(*) total, SUM(CASE WHEN a.id IS NOT NULL AND (a.nao_aplica=1 OR a.valor_json IS NOT NULL) THEN 1 ELSE 0 END) answered FROM briefing_question q JOIN briefing_section s ON s.id=q.briefing_section_id LEFT JOIN briefing_answer a ON a.briefing_question_id=q.id WHERE s.briefing_id=? AND s.ativa=1 AND q.ativa=1", 'i', [$briefingId])->get_result()->fetch_assoc() ?: ['total' => 0, 'answered' => 0];
    $total = (int)$row['total'];
    $answered = (int)$row['answered'];
    return ['total' => $total, 'answered' => $answered, 'percent' => $total ? (int)round($answered * 100 / $total) : 0];
}

function briefing_fetch(mysqli $conn, int $briefingId, bool $external = false): ?array
{
    $stmt = briefing_stmt($conn, "SELECT b.*,o.nome_obra, t.nome template_nome, c.nome_colaborador revisor_nome FROM briefing_online b LEFT JOIN obra o ON o.idobra=b.obra_id LEFT JOIN briefing_template t ON t.id=b.template_id LEFT JOIN colaborador c ON c.idcolaborador=b.revisor_colaborador_id WHERE b.id=?", 'i', [$briefingId]);
    $briefing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$briefing) {
        return null;
    }
    $briefing['id'] = (int)$briefing['id'];
    $briefing['temporal_status'] = briefing_temporal_state($briefing['prazo_em']);
    $briefing['progress'] = briefing_progress($conn, $briefingId);
    $sections = [];
    $stmt = briefing_stmt($conn, 'SELECT * FROM briefing_section WHERE briefing_id=? AND ativa=1 ORDER BY ordem,id', 'i', [$briefingId]);
    $result = $stmt->get_result();
    while ($section = $result->fetch_assoc()) {
        $section['id'] = (int)$section['id'];
        $section['questions'] = [];
        $sections[(int)$section['id']] = $section;
    }
    $stmt->close();
    if ($sections) {
        $ids = array_keys($sections);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT q.*,a.id answer_id,a.valor_json,a.nao_aplica,a.versao,a.atualizado_em,a.respondido_em,p.id resposta_por_id,p.nome resposta_por FROM briefing_question q LEFT JOIN briefing_answer a ON a.briefing_question_id=q.id LEFT JOIN briefing_participant p ON p.id=a.atualizado_por_participant_id WHERE q.briefing_section_id IN ($placeholders) AND q.ativa=1 ORDER BY q.ordem,q.id");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($q = $r->fetch_assoc()) {
            $q['id'] = (int)$q['id'];
            $q['answer'] = ['id' => $q['answer_id'] ? (int)$q['answer_id'] : null, 'value' => briefing_json_decode($q['valor_json']), 'not_applicable' => (bool)$q['nao_aplica'], 'version' => (int)($q['versao'] ?? 0), 'updated_at' => $q['atualizado_em'], 'author' => $q['resposta_por'], 'author_id' => $q['resposta_por_id'] ? (int)$q['resposta_por_id'] : null];
            unset($q['answer_id'], $q['valor_json'], $q['nao_aplica'], $q['versao'], $q['atualizado_em'], $q['respondido_em'], $q['resposta_por_id'], $q['resposta_por']);
            $sections[(int)$q['briefing_section_id']]['questions'][] = $q;
        }
        $stmt->close();
        $questionIds = [];
        foreach ($sections as $section) {
            foreach ($section['questions'] as $q) {
                $questionIds[] = $q['id'];
            }
        }
        if ($questionIds) {
            $marks = implode(',', array_fill(0, count($questionIds), '?'));
            $types = str_repeat('i', count($questionIds));
            $stmt = $conn->prepare("SELECT * FROM briefing_question_option WHERE question_id IN ($marks) ORDER BY ordem,id");
            $stmt->bind_param($types, ...$questionIds);
            $stmt->execute();
            $r = $stmt->get_result();
            $options = [];
            while ($o = $r->fetch_assoc()) {
                $options[(int)$o['question_id']][] = ['value' => $o['valor'], 'label' => $o['rotulo']];
            }
            $stmt->close();
            foreach ($sections as &$section) {
                foreach ($section['questions'] as &$q) {
                    $q['options'] = $options[$q['id']] ?? [];
                }
            }
            unset($q);
            unset($section);
        }
    }
    $briefing['sections'] = array_values($sections);
    $stmt = briefing_stmt($conn, "SELECT r.*,q.pergunta FROM briefing_question_request r JOIN briefing_question q ON q.id=r.briefing_question_id JOIN briefing_section s ON s.id=q.briefing_section_id WHERE s.briefing_id=? AND r.status<>'RESOLVIDO' ORDER BY r.criado_em", 'i', [$briefingId]);
    $requests = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
    $briefing['requests'] = $requests;
    $stmt = briefing_stmt($conn, 'SELECT p.nome,p.email,p.ultima_atividade_em,COUNT(a.id) respostas_count FROM briefing_participant p LEFT JOIN briefing_answer a ON a.atualizado_por_participant_id=p.id WHERE p.briefing_id=? AND p.ativo=1 GROUP BY p.id,p.nome,p.email,p.ultima_atividade_em ORDER BY p.ultima_atividade_em DESC', 'i', [$briefingId]);
    $participants = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $participants[] = $row;
    }
    $stmt->close();
    $briefing['participants'] = $participants;
    if (!$external) {
        $stmt = briefing_stmt($conn, "SELECT e.*,COALESCE(c.nome_colaborador,p.nome,'Sistema') ator_nome FROM briefing_event e LEFT JOIN colaborador c ON c.idcolaborador=e.ator_colaborador_id LEFT JOIN briefing_participant p ON p.id=e.ator_participant_id WHERE e.briefing_id=? ORDER BY e.criado_em DESC LIMIT 100", 'i', [$briefingId]);
        $briefing['events'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return $briefing;
}

function briefing_snapshot(mysqli $conn, int $briefingId, string $kind, string $actorType, ?int $actorId): void
{
    $content = briefing_fetch($conn, $briefingId, false);
    if (!$content) {
        throw new RuntimeException('Briefing não encontrado.');
    }
    unset($content['events']);
    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $version = (int)briefing_scalar($conn, 'SELECT COALESCE(MAX(versao_snapshot),0)+1 FROM briefing_snapshot WHERE briefing_id=?', 'i', [$briefingId]);
    $hash = hash('sha256', $json);
    $stmt = briefing_stmt($conn, 'INSERT INTO briefing_snapshot (briefing_id,versao_snapshot,tipo,conteudo_json,hash_sha256,criado_por_tipo,criado_por_id) VALUES (?,?,?,?,?,?,?)', 'iissssi', [$briefingId, $version, $kind, $json, $hash, $actorType, $actorId]);
    $stmt->close();
}

function briefing_clone_template(mysqli $conn, int $templateId, int $obraId, string $title, ?string $dueAt, ?int $reviewerId, ?bool $requiresReview, int $actorId): int
{
    $stmt = briefing_stmt($conn, 'SELECT * FROM briefing_template WHERE id=? AND ativo=1', 'i', [$templateId]);
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$template) {
        throw new InvalidArgumentException('Template não encontrado ou inativo.');
    }
    $review = $requiresReview === null ? (int)$template['exige_conferencia_interna'] : (int)$requiresReview;
    $reviewer = $reviewerId ?: ($template['revisor_padrao_colaborador_id'] ?: null);
    $stmt = briefing_stmt($conn, 'INSERT INTO briefing_online (obra_id,template_id,template_versao,titulo,exige_conferencia_interna,revisor_colaborador_id,criado_por_colaborador_id,prazo_em) VALUES (?,?,?,?,?,?,?,?)', 'iiisiiis', [$obraId, $templateId, (int)$template['versao'], $title, $review, $reviewer, $actorId, $dueAt]);
    $briefingId = (int)$conn->insert_id;
    $stmt->close();
    $sections = briefing_stmt($conn, 'SELECT * FROM briefing_template_section WHERE template_id=? AND ativo=1 ORDER BY ordem,id', 'i', [$templateId])->get_result();
    while ($s = $sections->fetch_assoc()) {
        $stmt = briefing_stmt($conn, 'INSERT INTO briefing_section (briefing_id,template_section_id,titulo,descricao,ordem) VALUES (?,?,?,?,?)', 'iissi', [$briefingId, (int)$s['id'], $s['titulo'], $s['descricao'], (int)$s['ordem']]);
        $sectionId = (int)$conn->insert_id;
        $stmt->close();
        $questions = briefing_stmt($conn, 'SELECT * FROM briefing_template_question WHERE section_id=? AND ativo=1 ORDER BY ordem,id', 'i', [(int)$s['id']])->get_result();
        while ($q = $questions->fetch_assoc()) {
            $stmt = briefing_stmt($conn, 'INSERT INTO briefing_question (briefing_section_id,template_question_id,codigo,pergunta,ajuda,tipo,obrigatoria,permite_nao_aplica,ordem,validacao_json) VALUES (?,?,?,?,?,?,?,?,?,?)', 'iissssiiis', [$sectionId, (int)$q['id'], $q['codigo'], $q['pergunta'], $q['ajuda'], $q['tipo'], (int)$q['obrigatoria'], (int)$q['permite_nao_aplica'], (int)$q['ordem'], $q['validacao_json']]);
            $questionId = (int)$conn->insert_id;
            $stmt->close();
            $opts = briefing_stmt($conn, 'SELECT * FROM briefing_template_question_option WHERE question_id=? AND ativo=1 ORDER BY ordem,id', 'i', [(int)$q['id']])->get_result();
            while ($o = $opts->fetch_assoc()) {
                $stmt = briefing_stmt($conn, 'INSERT INTO briefing_question_option (question_id,rotulo,valor,ordem) VALUES (?,?,?,?)', 'issi', [$questionId, $o['rotulo'], $o['valor'], (int)$o['ordem']]);
                $stmt->close();
            }
        }
    }
    briefing_event($conn, $briefingId, 'briefing.created', 'COLABORADOR', $actorId, ['template_id' => $templateId]);
    return $briefingId;
}

function briefing_can_review(array $briefing, int $actorId): bool
{
    return empty($briefing['revisor_colaborador_id']) || (int)$briefing['revisor_colaborador_id'] === $actorId;
}
function briefing_valid_answer(array $question, mixed $value, bool $notApplicable): mixed
{
    if ($notApplicable) {
        if (!(int)$question['permite_nao_aplica']) {
            throw new InvalidArgumentException('Esta pergunta não permite “Não se aplica”.');
        }
        return null;
    }
    $type = $question['tipo'];
    if ($type === 'MULTI_SELECT') {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Selecione uma ou mais opções.');
        }
        $value = array_values(array_unique(array_map(fn ($v) => briefing_clean_text($v, 255), $value)));
    } elseif ($type === 'YES_NO') {
        if (!in_array($value, [true, false, 'yes', 'no', 'sim', 'nao'], true)) {
            throw new InvalidArgumentException('Resposta sim/não inválida.');
        }
        $value = in_array($value, [true, 'yes', 'sim'], true);
    } elseif ($type === 'NUMBER') {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Informe um número válido.');
        }
        $value = (string)$value;
    } elseif ($type === 'DATE') {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
    } elseif ($type === 'LINK') {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Informe um link válido.');
        }
    } else {
        $value = briefing_clean_text($value, $type === 'LONG_TEXT' ? 10000 : 2000);
    }
    if ($question['obrigatoria'] && (($type === 'MULTI_SELECT' && $value === []) || ($type !== 'MULTI_SELECT' && ($value === '' || $value === null)))) {
        throw new InvalidArgumentException('Esta pergunta é obrigatória.');
    }
    return $value;
}

function briefing_external_auth_config(): array
{
    return [
        'code_length' => 6,
        'otp_ttl' => 600,
        'max_attempts' => 5,
        'resend_cooldown' => 60,
        'request_limit' => 5,
        'request_window' => 600,
        'session_ttl' => 30 * 86400,
        'link_ttl' => 30 * 86400,
    ];
}

function briefing_external_client_ip(): string
{
    return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45, 'UTF-8');
}

function briefing_external_user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8');
}

function briefing_external_auth_cookie_name(): string
{
    return 'improov_external_auth';
}

function briefing_external_auth_cookie_path(): string
{
    return briefing_base_path() . '/';
}

function briefing_external_set_auth_cookie(string $token, int $expires): void
{
    setcookie(briefing_external_auth_cookie_name(), $token, [
        'expires' => $expires,
        'path' => briefing_external_auth_cookie_path(),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function briefing_external_current_auth(mysqli $conn): ?array
{
    $token = (string) ($_COOKIE[briefing_external_auth_cookie_name()] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $schema = contact_arch_contact_schema($conn);
    $sql = 'SELECT s.*, cc.' . $schema['id'] . ' AS contact_id, cc.' . $schema['email'] . ' AS email, cc.' . $schema['name'] . ' AS nome'
        . ' FROM external_auth_session s JOIN contato_cliente cc ON cc.' . $schema['id'] . '=s.contato_cliente_id'
        . ' WHERE s.token_hash=? AND s.revogado_em IS NULL AND s.expira_em>UTC_TIMESTAMP()';
    if ($schema['active']) {
        $sql .= ' AND cc.' . $schema['active'] . '=1';
    }
    $stmt = briefing_stmt($conn, $sql, 's', [hash('sha256', $token)]);
    $auth = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if ($auth) {
        briefing_stmt($conn, 'UPDATE external_auth_session SET ultimo_uso_em=UTC_TIMESTAMP() WHERE id=?', 'i', [(int) $auth['id']])->close();
    }
    return $auth;
}

function briefing_external_csrf_token(array $auth): string
{
    $sessionToken = (string) ($_COOKIE[briefing_external_auth_cookie_name()] ?? '');
    return hash_hmac('sha256', 'briefing-external-csrf:' . (int) ($auth['id'] ?? 0), $sessionToken);
}

function briefing_external_csrf_valid(array $auth, string $provided): bool
{
    if ($provided === '') {
        return false;
    }
    $providedHash = hash('sha256', $provided);
    $stored = (string) ($auth['csrf_hash'] ?? '');
    $stable = briefing_external_csrf_token($auth);
    return ($stored !== '' && hash_equals($stored, $providedHash)) || hash_equals($stable, $provided);
}

function briefing_external_access_link_v2(mysqli $conn, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = briefing_stmt($conn, "SELECT l.*, b.obra_id,b.status,b.titulo,b.exige_conferencia_interna FROM briefing_access_link l JOIN briefing_online b ON b.id=l.briefing_id WHERE l.token_hash=? AND l.revogado_em IS NULL AND l.expira_em>UTC_TIMESTAMP()", 's', [hash('sha256', $token)]);
    $link = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $link;
}

function briefing_external_contact_has_obra(mysqli $conn, int $contactId, int $obraId): bool
{
    $link = contact_arch_link_schema($conn);
    if (!$link['exists'] || !$link['obra'] || !$link['contact']) {
        return false;
    }
    $sql = 'SELECT 1 FROM ' . $link['table'] . ' WHERE ' . $link['obra'] . '=? AND ' . $link['contact'] . '=?';
    if ($link['active']) {
        $sql .= ' AND ' . $link['active'] . '=1';
    }
    return (bool) briefing_scalar($conn, $sql, 'ii', [$obraId, $contactId]);
}

function briefing_external_participant(mysqli $conn, int $briefingId, array $contact): array
{
    $contactId = (int) ($contact['contact_id'] ?? 0);
    $stmt = briefing_stmt($conn, 'SELECT * FROM briefing_participant WHERE briefing_id=? AND contato_cliente_id=? AND ativo=1 LIMIT 1', 'ii', [$briefingId, $contactId]);
    $participant = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if ($participant) {
        return $participant;
    }
    $stmt = briefing_stmt($conn, 'INSERT INTO briefing_participant (briefing_id,contato_cliente_id,email,nome,empresa,verificado_em,primeiro_acesso_em,ultima_atividade_em) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())', 'iisss', [$briefingId, $contactId, (string) $contact['email'], (string) $contact['name'], null]);
    $id = (int) $conn->insert_id;
    $stmt->close();
    return ['id' => $id, 'briefing_id' => $briefingId, 'contato_cliente_id' => $contactId, 'email' => $contact['email'], 'nome' => $contact['name']];
}

function briefing_external_access(mysqli $conn, string $token, bool $csrf = false): array
{
    $link = briefing_external_access_link_v2($conn, $token);
    if (!$link) {
        briefing_json(['ok' => false, 'message' => 'Este link expirou ou foi revogado.'], 404);
    }
    $auth = briefing_external_current_auth($conn);
    if (!$auth || !briefing_external_contact_has_obra($conn, (int) $auth['contact_id'], (int) $link['obra_id'])) {
        briefing_json(['ok' => false, 'message' => 'Sessão externa necessária.'], 401);
    }
    if ($csrf) {
        $provided = (string) ($_SERVER['HTTP_X_BRIEFING_CSRF'] ?? '');
        if (!briefing_external_csrf_valid($auth, $provided)) {
            briefing_json(['ok' => false, 'message' => 'Token CSRF inválido.'], 403);
        }
    }
    $contact = contact_arch_fetch_contact_row($conn, (int) $auth['contact_id']);
    if (!$contact) {
        briefing_json(['ok' => false, 'message' => 'Sessão externa necessária.'], 401);
    }
    $participant = briefing_external_participant($conn, (int) $link['briefing_id'], $contact);
    return ['link' => $link, 'auth' => $auth, 'contact' => $contact, 'participant' => $participant];
}

function briefing_external_question_editable(mysqli $conn, int $briefingId, string $status, int $questionId): bool
{
    if (in_array($status, ['AGUARDANDO_CLIENTE', 'EM_PREENCHIMENTO'], true)) {
        return true;
    }
    if ($status !== 'AJUSTES_SOLICITADOS') {
        return false;
    }
    return (bool) briefing_scalar($conn, "SELECT 1 FROM briefing_question_request WHERE briefing_question_id=? AND status='SOLICITADO' AND EXISTS (SELECT 1 FROM briefing_question q JOIN briefing_section s ON s.id=q.briefing_section_id WHERE q.id=? AND s.briefing_id=?)", 'iii', [$questionId, $questionId, $briefingId]);
}

function briefing_external_create_auth_session(mysqli $conn, int $contactId): array
{
    $token = bin2hex(random_bytes(32));
    $initialCsrf = bin2hex(random_bytes(32));
    $expires = time() + briefing_external_auth_config()['session_ttl'];
    $stmt = briefing_stmt($conn, 'INSERT INTO external_auth_session (contato_cliente_id,token_hash,csrf_hash,criado_em,expira_em,ultimo_uso_em,ip_criacao,user_agent_criacao) VALUES (?,?,?,UTC_TIMESTAMP(),FROM_UNIXTIME(?),UTC_TIMESTAMP(),?,?)', 'ississ', [$contactId, hash('sha256', $token), hash('sha256', $initialCsrf), $expires, briefing_external_client_ip(), briefing_external_user_agent()]);
    $sessionId = (int) $conn->insert_id;
    $stmt->close();
    $csrf = hash_hmac('sha256', 'briefing-external-csrf:' . $sessionId, $token);
    briefing_stmt($conn, 'UPDATE external_auth_session SET csrf_hash=? WHERE id=?', 'si', [hash('sha256', $csrf), $sessionId])->close();
    briefing_external_set_auth_cookie($token, $expires);
    return ['csrf' => $csrf, 'expires_at' => gmdate('c', $expires)];
}

function briefing_external_issue_otp(mysqli $conn, array $link, string $email, string $purpose, ?int $contactId = null, ?array $pending = null): void
{
    $config = briefing_external_auth_config();
    $email = contact_arch_normalize_email($email);
    if (!in_array($purpose, ['LOGIN', 'LINK_CONTACT', 'CREATE_CONTACT'], true)) {
        throw new InvalidArgumentException('Finalidade de acesso inválida.');
    }
    $count = (int) briefing_scalar($conn, 'SELECT COUNT(*) FROM external_otp_challenge WHERE briefing_access_link_id=? AND (email_normalizado=? OR ip_solicitacao=?) AND criado_em>DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)', 'iss', [(int) $link['id'], $email, briefing_external_client_ip()]);
    if ($count >= $config['request_limit']) {
        briefing_json(['ok' => false, 'message' => 'Muitas tentativas. Aguarde alguns minutos.'], 429);
    }
    $last = briefing_scalar($conn, 'SELECT UNIX_TIMESTAMP(ultimo_envio_em) FROM external_otp_challenge WHERE briefing_access_link_id=? AND email_normalizado=? ORDER BY id DESC LIMIT 1', 'is', [(int) $link['id'], $email]);
    if ($last && (time() - (int) $last) < $config['resend_cooldown']) {
        briefing_json(['ok' => false, 'message' => 'Aguarde um minuto antes de solicitar outro código.'], 429);
    }
    $code = str_pad((string) random_int(0, 999999), $config['code_length'], '0', STR_PAD_LEFT);
    if (!briefing_send_otp($email, $code, (string) $link['titulo'])) {
        briefing_json(['ok' => false, 'message' => 'Não foi possível enviar o código agora. Tente novamente mais tarde.'], 503);
    }
    $pendingJson = $pending ? json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    briefing_stmt($conn, 'UPDATE external_otp_challenge SET expira_em=UTC_TIMESTAMP() WHERE briefing_access_link_id=? AND email_normalizado=? AND consumido_em IS NULL', 'is', [(int) $link['id'], $email])->close();
    $stmt = briefing_stmt($conn, 'INSERT INTO external_otp_challenge (briefing_access_link_id,contato_cliente_id,email_normalizado,finalidade,code_hash,tentativas,criado_em,expira_em,ultimo_envio_em,ip_solicitacao,pending_payload) VALUES (?,?,?,?,?,0,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE),UTC_TIMESTAMP(),?,?)', 'iisssss', [(int) $link['id'], $contactId, $email, $purpose, password_hash($code, PASSWORD_DEFAULT), briefing_external_client_ip(), $pendingJson]);
    $stmt->close();
    briefing_event($conn, (int) $link['briefing_id'], 'otp.requested', 'SISTEMA', null, ['purpose' => $purpose]);
}

function briefing_external_cookie_name(): string
{
    return 'improov_briefing_ext';
}
function briefing_ext_cookie_path(): string
{
    return briefing_base_path() . '/BriefingExt/';
}
function briefing_set_ext_cookie(string $token, int $expires): void
{
    setcookie(briefing_external_cookie_name(), $token, ['expires' => $expires, 'path' => briefing_ext_cookie_path(), 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Lax']);
}
function briefing_external_session(mysqli $conn, bool $csrf = false): array
{
    $token = (string)($_COOKIE[briefing_external_cookie_name()] ?? '');
    if ($token === '') {
        briefing_json(['ok' => false, 'message' => 'Sessão externa necessária.'], 401);
    }
    $hash = hash('sha256', $token);
    $stmt = briefing_stmt($conn, "SELECT s.*,p.nome,p.email,p.papel,b.status,b.link_expira_em FROM briefing_external_session s JOIN briefing_participant p ON p.id=s.participant_id JOIN briefing_online b ON b.id=s.briefing_id WHERE s.token_hash=? AND s.revogado_em IS NULL AND s.expira_em>UTC_TIMESTAMP() AND p.ativo=1", 's', [$hash]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        briefing_json(['ok' => false, 'message' => 'Sessão externa expirada.'], 401);
    }
    if ($csrf) {
        $provided = (string)($_SERVER['HTTP_X_BRIEFING_CSRF'] ?? '');
        if ($provided === '' || !hash_equals($row['csrf_hash'], hash('sha256', $provided))) {
            briefing_json(['ok' => false, 'message' => 'Token CSRF inválido.'], 403);
        }
    }
    briefing_stmt($conn, 'UPDATE briefing_external_session SET ultimo_acesso_em=UTC_TIMESTAMP() WHERE id=?', 'i', [(int)$row['id']])->close();
    return $row;
}
function briefing_external_link(mysqli $conn, string $token): ?array
{
    $stmt = briefing_stmt($conn, "SELECT l.*,b.obra_id,b.status,b.link_expira_em FROM briefing_access_link l JOIN briefing_online b ON b.id=l.briefing_id WHERE l.token_hash=? AND l.revogado_em IS NULL AND l.expira_em>UTC_TIMESTAMP()", 's', [hash('sha256', $token)]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
function briefing_contact_by_email(mysqli $conn, int $obraId, string $email): ?array
{
    $stmt = briefing_stmt($conn, 'SELECT cc.idcontato_cliente,cc.nome,cl.nome_cliente FROM obra_contato oc JOIN contato_cliente cc ON cc.idcontato_cliente=oc.contato_cliente_id AND cc.ativo=1 JOIN cliente cl ON cl.idcliente=cc.cliente_id WHERE oc.obra_id=? AND oc.ativo=1 AND LOWER(cc.email)=? LIMIT 1', 'is', [$obraId, $email]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
function briefing_rate_limit(mysqli $conn, string $key, int $limit, int $seconds): bool
{
    try {
        if (!class_exists('Predis\\Client')) {
            return true;
        }
        $redis = new Predis\Client(improov_env('REDIS_URL', 'redis://127.0.0.1:6379'));
        $k = 'briefing:rate:' . hash('sha256', $key);
        $n = (int)$redis->incr($k);
        if ($n === 1) {
            $redis->expire($k, $seconds);
        }
        $redis->disconnect();
        return $n <= $limit;
    } catch (Throwable) {
        return true;
    }
}
function briefing_send_otp(string $email, string $code, string $briefingTitle): bool
{
    improov_load_env_once();
    $host = trim((string)improov_env('BRIEFING_SMTP_HOST', ''));
    $from = trim((string)improov_env('BRIEFING_SMTP_FROM', ''));
    $port = max(1, (int)improov_env('BRIEFING_SMTP_PORT', '587'));
    $user = trim((string)improov_env('BRIEFING_SMTP_USER', ''));
    $password = (string)improov_env('BRIEFING_SMTP_PASS', '');
    if ($host === '' || $from === '') {
        error_log('[Briefing] SMTP is not configured; OTP was not delivered.');
        return false;
    }
    $read = static function ($socket): string {
        $line = '';
        while (!feof($socket)) {
            $part = fgets($socket, 1024);
            if ($part === false) {
                break;
            }
            $line .= $part;
            if (preg_match('/^\d{3} /', $part)) {
                break;
            }
        }
        return $line;
    };
    $send = static function ($socket, string $command) use ($read): string {
        fwrite($socket, $command . "\r\n");
        return $read($socket);
    };
    $ok = static fn (string $reply, array $codes): bool => preg_match('/^(\d{3})/', $reply, $m) === 1 && in_array((int)$m[1], $codes, true);
    $socket = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log('[Briefing] SMTP connection failed.');
        return false;
    }
    stream_set_timeout($socket, 10);
    try {
        if (!$ok($read($socket), [220])) {
            throw new RuntimeException('greeting');
        }
        if (!$ok($send($socket, 'EHLO flow-briefing'), [250])) {
            throw new RuntimeException('ehlo');
        }
        if ($port !== 465) {
            if (!$ok($send($socket, 'STARTTLS'), [220])) {
                throw new RuntimeException('starttls');
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('tls');
            }
            if (!$ok($send($socket, 'EHLO flow-briefing'), [250])) {
                throw new RuntimeException('ehlo-tls');
            }
        }
        if ($user !== '' || $password !== '') {
            if (!$ok($send($socket, 'AUTH LOGIN'), [334]) || !$ok($send($socket, base64_encode($user)), [334]) || !$ok($send($socket, base64_encode($password)), [235])) {
                throw new RuntimeException('auth');
            }
        }
        if (!$ok($send($socket, 'MAIL FROM:<' . $from . '>'), [250]) || !$ok($send($socket, 'RCPT TO:<' . $email . '>'), [250, 251]) || !$ok($send($socket, 'DATA'), [354])) {
            throw new RuntimeException('envelope');
        }
        $subject = 'Código de acesso — ' . briefing_clean_text($briefingTitle, 160);
        $body = "Seu código de acesso ao briefing é: {$code}\r\n\r\nEle expira em 10 minutos.";
        $message = "From: {$from}\r\nTo: {$email}\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
        if (!$ok($send($socket, $message), [250])) {
            throw new RuntimeException('data');
        }
        $send($socket, 'QUIT');
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('[Briefing] SMTP delivery failed: ' . $e->getMessage());
        fclose($socket);
        return false;
    }
}
function briefing_ws_ticket(int $briefingId, int $participantId, string $scope): string
{
    if (!class_exists('Predis\\Client')) {
        throw new RuntimeException('Realtime indisponível.');
    }
    $ticket = bin2hex(random_bytes(32));
    $payload = json_encode(['briefing_id' => $briefingId, 'participant_id' => $participantId, 'scope' => $scope, 'exp' => time() + 300]);
    $redis = new Predis\Client(improov_env('REDIS_URL', 'redis://127.0.0.1:6379'));
    $redis->setex('briefing_ws_ticket:' . hash('sha256', $ticket), 300, $payload);
    $redis->disconnect();
    return $ticket;
}
function briefing_flow_event(mysqli $conn, array $briefing, string $action, ?int $actorId = null): void
{
    if (flow_connect_operational_mode('briefing', 'business') === 'off') {
        return;
    }
    $event = \FlowConnect\Contracts\EventEnvelope::normalize([
        'event_type' => 'briefing.briefing.' . $action,
        'source_module' => 'briefing',
        'entity_type' => 'briefing',
        'entity_id' => (string)$briefing['id'],
        'actor_id' => $actorId,
        'idempotency_key' => 'briefing:' . $briefing['id'] . ':' . $action . ':' . \FlowConnect\Contracts\EventEnvelope::uuidV4(),
        'payload' => ['titulo' => (string)$briefing['titulo'], 'obra_id' => (int)$briefing['obra_id'], 'responsavel_id' => (int)($briefing['revisor_colaborador_id'] ?? 0)],
        'metadata' => ['flow_connect_mode' => flow_connect_operational_mode('briefing', 'business'), 'producer' => 'briefing'],
    ]);
    $logs = [];
    flow_connect_publish_if_enabled($conn, 'briefing', $event, $logs);
}
