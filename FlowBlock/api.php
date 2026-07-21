<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/flow_block_helper.php';

date_default_timezone_set('America/Sao_Paulo');

flow_block_ensure_authenticated();
if (!flow_block_has_tables($conn)) {
    flow_block_json_response(['ok' => false, 'message' => 'O Flow Block ainda não foi instalado.'], 503);
}

$action = strtolower((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$actorId = flow_block_actor_id();

function fb_issue_select(): string
{
    return "SELECT i.*, t.nome AS tipo_nome, t.codigo AS tipo_codigo, q.nome AS fila_nome,
                   r.nome_colaborador AS responsavel_nome, cr.nome_colaborador AS criador_nome,
                   fi.status AS tarefa_status, fi.colaborador_id AS tarefa_colaborador_id,
                   f.nome_funcao, ico.idimagens_cliente_obra AS imagem_id, ico.imagem_nome, ico.obra_id, o.nomenclatura, o.nome_obra,
                   (SELECT COUNT(*) FROM flow_issue_atividade a WHERE a.issue_id = i.id AND a.tipo = 'COMENTARIO') AS comentarios_count,
                   (SELECT COUNT(*) FROM flow_issue_anexo x WHERE x.issue_id = i.id) AS anexos_count
            FROM flow_issue i
            JOIN flow_issue_tipo t ON t.id = i.tipo_id
            LEFT JOIN flow_issue_fila q ON q.id = i.fila_id
            LEFT JOIN colaborador r ON r.idcolaborador = i.responsavel_colaborador_id
            LEFT JOIN colaborador cr ON cr.idcolaborador = i.criado_por_colaborador_id
            JOIN funcao_imagem fi ON fi.idfuncao_imagem = i.funcao_imagem_id
            JOIN funcao f ON f.idfuncao = fi.funcao_id
            JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
            JOIN obra o ON o.idobra = ico.obra_id";
}

function fb_get_issue(mysqli $conn, int $issueId): ?array
{
    $sql = fb_issue_select() . ' WHERE i.id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $issueId);
    $stmt->execute();
    $issue = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $issue;
}

function fb_visible(mysqli $conn, array $issue): bool
{
    return flow_block_is_manager()
        || (int) $issue['tarefa_colaborador_id'] === flow_block_actor_id()
        || (int) ($issue['responsavel_colaborador_id'] ?? 0) === flow_block_actor_id()
        || (int) $issue['criado_por_colaborador_id'] === flow_block_actor_id()
        || flow_block_actor_was_mentioned($conn, (int) $issue['id']);
}

function fb_add_mentions(mysqli $conn, int $issueId, int $activityId, array $mentionedIds, int $taskId, string $issueCode): array
{
    $created = [];
    $actorId = flow_block_actor_id();
    $insert = $conn->prepare("INSERT IGNORE INTO flow_issue_mencao (issue_id, atividade_id, colaborador_id, mencionado_por_colaborador_id, status) VALUES (?, ?, ?, ?, 'PENDENTE')");
    $collaborator = $conn->prepare('SELECT idcolaborador FROM colaborador WHERE idcolaborador = ? AND ativo = 1 LIMIT 1');
    foreach (array_unique(array_map('intval', $mentionedIds)) as $id) {
        $recipient = (int) $id;
        if ($recipient <= 0) continue;
        $collaborator->bind_param('i', $recipient);
        $collaborator->execute();
        if (!$collaborator->get_result()->fetch_assoc()) continue;
        $insert->bind_param('iiii', $issueId, $activityId, $recipient, $actorId);
        $insert->execute();
        if ($insert->affected_rows !== 1) continue;
        $mentionId = (int) $conn->insert_id;
        if ($recipient !== $actorId) {
            flow_block_notify($conn, $recipient, $taskId, "Você foi mencionado em $issueCode no Flow Block.");
        }
        // Automenções também são eventos válidos: são úteis para testar e
        // para marcar uma pendência pessoal sem perder o alerta.
        $created[] = ['id' => $mentionId, 'issue_id' => $issueId, 'atividade_id' => $activityId, 'colaborador_id' => $recipient];
    }
    $collaborator->close();
    $insert->close();
    return $created;
}

function fb_mark_mentions_read(mysqli $conn, int $issueId, int $recipientId, ?int $mentionId = null): void
{
    if ($issueId <= 0 || $recipientId <= 0) return;
    if ($mentionId) {
        $stmt = $conn->prepare("UPDATE flow_issue_mencao SET status = 'LIDA', visualizado_em = NOW() WHERE id = ? AND issue_id = ? AND colaborador_id = ? AND status = 'PENDENTE'");
        $stmt->bind_param('iii', $mentionId, $issueId, $recipientId);
    } else {
        $stmt = $conn->prepare("UPDATE flow_issue_mencao SET status = 'LIDA', visualizado_em = NOW() WHERE issue_id = ? AND colaborador_id = ? AND status = 'PENDENTE'");
        $stmt->bind_param('ii', $issueId, $recipientId);
    }
    $stmt->execute();
    $stmt->close();
}

function fb_save_attachments(mysqli $conn, array $issue, int $activityId, array $files, int $actorId): array
{
    $issueId = (int) $issue['id'];
    $allowed = ['pdf', 'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];
    $targetDir = __DIR__ . '/../uploads/flow_block/issue_' . $issueId;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Não foi possível preparar a pasta de anexos.');
    }
    $saved = [];
    foreach (($files['name'] ?? []) as $index => $name) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Falha ao enviar o anexo.');
        $size = (int) ($files['size'][$index] ?? 0);
        if ($size <= 0 || $size > 25 * 1024 * 1024) throw new RuntimeException('Cada anexo deve ter no máximo 25 MB.');
        $original = basename((string) $name);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) throw new RuntimeException('Tipo de arquivo não permitido: ' . $extension);
        $stored = bin2hex(random_bytes(12)) . ($extension ? '.' . $extension : '');
        if (!move_uploaded_file($files['tmp_name'][$index], $targetDir . '/' . $stored)) {
            throw new RuntimeException('Não foi possível armazenar o anexo.');
        }
        $relative = 'uploads/flow_block/issue_' . $issueId . '/' . $stored;
        $mime = (string) ($files['type'][$index] ?? 'application/octet-stream');
        $insert = $conn->prepare('INSERT INTO flow_issue_anexo (issue_id,atividade_id,nome_original,caminho,tamanho,mime_type,criado_por_colaborador_id) VALUES (?,?,?,?,?,?,?)');
        $insert->bind_param('iissisi', $issueId, $activityId, $original, $relative, $size, $mime, $actorId);
        $insert->execute();
        $insert->close();
        $saved[] = ['nome' => $original, 'caminho' => $relative];
    }
    return $saved;
}

try {
    if ($action === 'options') {
        $types = $conn->query('SELECT id, codigo, nome FROM flow_issue_tipo WHERE ativo = 1 ORDER BY ordem, nome')->fetch_all(MYSQLI_ASSOC);
        $queues = $conn->query('SELECT id, codigo, nome FROM flow_issue_fila WHERE ativo = 1 ORDER BY ordem, nome')->fetch_all(MYSQLI_ASSOC);
        $collaborators = $conn->query('SELECT idcolaborador AS id, nome_colaborador AS nome FROM colaborador WHERE ativo = 1 ORDER BY nome_colaborador')->fetch_all(MYSQLI_ASSOC);
        $functions = $conn->query('SELECT idfuncao AS id, nome_funcao AS nome FROM funcao ORDER BY nome_funcao')->fetch_all(MYSQLI_ASSOC);
        flow_block_json_response(['ok' => true, 'types' => $types, 'queues' => $queues, 'collaborators' => $collaborators, 'functions' => $functions]);
    }

    if ($action === 'list') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(50, max(10, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $where = [];
        $types = '';
        $values = [];
        $visibility = flow_block_is_manager()
            ? ''
            : ' (fi.colaborador_id = ? OR i.responsavel_colaborador_id = ? OR i.criado_por_colaborador_id = ? OR EXISTS (SELECT 1 FROM flow_issue_mencao vm WHERE vm.issue_id = i.id AND vm.colaborador_id = ?)) ';
        if ($visibility !== '') {
            $where[] = $visibility;
            $types .= 'iiii';
            $values[] = $actorId;
            $values[] = $actorId;
            $values[] = $actorId;
            $values[] = $actorId;
        }
        $status = strtoupper(trim((string) ($_GET['status'] ?? '')));
        if (in_array($status, ['ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA', 'RESOLVIDA', 'CANCELADA'], true)) {
            $where[] = 'i.status = ?';
            $types .= 's';
            $values[] = $status;
        }
        $search = trim((string) ($_GET['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(i.codigo LIKE ? OR i.descricao LIKE ? OR ico.imagem_nome LIKE ? OR o.nomenclatura LIKE ? OR o.nome_obra LIKE ?)';
            $types .= 'sssss';
            for ($x = 0; $x < 5; $x++) $values[] = '%' . $search . '%';
        }
        foreach (['tipo_id' => 'i.tipo_id', 'fila_id' => 'i.fila_id', 'responsavel_id' => 'i.responsavel_colaborador_id', 'funcao_id' => 'fi.funcao_id', 'obra_id' => 'ico.obra_id', 'imagem_id' => 'ico.idimagens_cliente_obra'] as $key => $column) {
            $value = (int) ($_GET[$key] ?? 0);
            if ($value > 0) {
                $where[] = $column . ' = ?';
                $types .= 'i';
                $values[] = $value;
            }
        }
        $urgency = strtoupper(trim((string) ($_GET['urgencia'] ?? '')));
        if (in_array($urgency, ['BAIXA', 'NORMAL', 'ALTA', 'CRITICA'], true)) {
            $where[] = 'i.urgencia = ?';
            $types .= 's';
            $values[] = $urgency;
        }
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        if ($from !== '') {
            $where[] = 'DATE(i.criado_em) >= ?';
            $types .= 's';
            $values[] = $from;
        }
        if ($to !== '') {
            $where[] = 'DATE(i.criado_em) <= ?';
            $types .= 's';
            $values[] = $to;
        }
        $mentioned = (string) ($_GET['mentioned'] ?? '') === '1';
        if ($mentioned) {
            $where[] = "EXISTS (SELECT 1 FROM flow_issue_mencao pm WHERE pm.issue_id = i.id AND pm.colaborador_id = ? AND pm.status = 'PENDENTE')";
            $types .= 'i';
            $values[] = $actorId;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = 'SELECT COUNT(*) AS total FROM flow_issue i JOIN funcao_imagem fi ON fi.idfuncao_imagem=i.funcao_imagem_id JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=fi.imagem_id JOIN obra o ON o.idobra=ico.obra_id' . $whereSql;
        $countStmt = $conn->prepare($countSql);
        if ($types !== '') $countStmt->bind_param($types, ...$values);
        $countStmt->execute();
        $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
        $sql = fb_issue_select() . $whereSql . ' ORDER BY i.atualizado_em DESC, i.id DESC LIMIT ? OFFSET ?';
        $stmt = $conn->prepare($sql);
        $listTypes = $types . 'ii';
        $listValues = array_merge($values, [$perPage, $offset]);
        $stmt->bind_param($listTypes, ...$listValues);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($items as &$item) {
            $blocking = in_array($item['status'], flow_block_active_statuses(), true)
                || ($item['status'] === 'RESOLVIDA' && empty($item['confirmada_em']));
            $item['tempo_bloqueado'] = flow_block_duration_label($item['criado_em'], $blocking ? null : $item['resolvido_em']);
        }
        unset($item);
        $counts = ['TODAS' => 0, 'ABERTA' => 0, 'AGUARDANDO_ACAO' => 0, 'PAUSADA' => 0, 'RESOLVIDA' => 0, 'CANCELADA' => 0, 'MENCIONARAM_VOCE' => 0];
        $countsSql = 'SELECT i.status, COUNT(*) total FROM flow_issue i JOIN funcao_imagem fi ON fi.idfuncao_imagem=i.funcao_imagem_id' . ($visibility ? ' WHERE ' . $visibility : '') . ' GROUP BY i.status';
        $countByStatus = $conn->prepare($countsSql);
        if ($visibility) $countByStatus->bind_param('iiii', $actorId, $actorId, $actorId, $actorId);
        $countByStatus->execute();
        foreach ($countByStatus->get_result() as $row) $counts[$row['status']] = (int) $row['total'];
        $countByStatus->close();
        $counts['TODAS'] = $counts['ABERTA'] + $counts['AGUARDANDO_ACAO'] + $counts['PAUSADA'] + $counts['RESOLVIDA'] + $counts['CANCELADA'];
        $mentionsCount = $conn->prepare("SELECT COUNT(DISTINCT m.issue_id) AS total FROM flow_issue_mencao m WHERE m.colaborador_id = ? AND m.status = 'PENDENTE'");
        $mentionsCount->bind_param('i', $actorId);
        $mentionsCount->execute();
        $counts['MENCIONARAM_VOCE'] = (int) ($mentionsCount->get_result()->fetch_assoc()['total'] ?? 0);
        $mentionsCount->close();
        flow_block_json_response(['ok' => true, 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'counts' => $counts]);
    }

    if ($action === 'mention_pending') {
        $mentionId = (int) ($_GET['id'] ?? 0);
        $stmt = $conn->prepare(
            "SELECT m.id AS mention_id, m.issue_id, m.atividade_id, m.criado_em,
                    a.conteudo, i.codigo, ico.imagem_nome, f.nome_funcao,
                    actor.nome_colaborador AS autor_nome
             FROM flow_issue_mencao m
             JOIN flow_issue_atividade a ON a.id = m.atividade_id
             JOIN flow_issue i ON i.id = m.issue_id
             JOIN funcao_imagem fi ON fi.idfuncao_imagem = i.funcao_imagem_id
             JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             JOIN funcao f ON f.idfuncao = fi.funcao_id
             LEFT JOIN colaborador actor ON actor.idcolaborador = m.mencionado_por_colaborador_id
             WHERE m.id = ? AND m.colaborador_id = ? AND m.status = 'PENDENTE'
             LIMIT 1"
        );
        $stmt->bind_param('ii', $mentionId, $actorId);
        $stmt->execute();
        $mention = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$mention) flow_block_json_response(['ok' => false, 'message' => 'Menção não encontrada ou já visualizada.'], 404);
        $mention['conteudo'] = trim((string) $mention['conteudo']);
        flow_block_json_response(['ok' => true, 'mention' => $mention]);
    }

    if ($action === 'mentions_pending') {
        $stmt = $conn->prepare(
            "SELECT m.id AS mention_id, m.issue_id, m.atividade_id, m.criado_em,
                    a.conteudo, i.codigo, ico.imagem_nome, f.nome_funcao,
                    actor.nome_colaborador AS autor_nome
             FROM flow_issue_mencao m
             JOIN flow_issue_atividade a ON a.id = m.atividade_id
             JOIN flow_issue i ON i.id = m.issue_id
             JOIN funcao_imagem fi ON fi.idfuncao_imagem = i.funcao_imagem_id
             JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             JOIN funcao f ON f.idfuncao = fi.funcao_id
             LEFT JOIN colaborador actor ON actor.idcolaborador = m.mencionado_por_colaborador_id
             WHERE m.colaborador_id = ? AND m.status = 'PENDENTE'
             ORDER BY m.criado_em DESC, m.id DESC
             LIMIT 20"
        );
        $stmt->bind_param('i', $actorId);
        $stmt->execute();
        $mentions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($mentions as &$mention) $mention['conteudo'] = trim((string) $mention['conteudo']);
        unset($mention);
        flow_block_json_response(['ok' => true, 'mentions' => $mentions]);
    }

    if ($action === 'task_summary') {
        $taskId = (int) ($_GET['funcao_imagem_id'] ?? 0);
        $task = flow_block_task($conn, $taskId);
        if (!$task || !flow_block_can_access_task($task)) flow_block_json_response(['ok' => false, 'message' => 'Tarefa não encontrada.'], 404);
        $stmt = $conn->prepare(fb_issue_select() . " WHERE i.funcao_imagem_id = ? AND (i.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (i.status='RESOLVIDA' AND i.confirmada_em IS NULL)) ORDER BY i.criado_em ASC");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($issues as &$issue) $issue['tempo_bloqueado'] = flow_block_duration_label($issue['criado_em']);
        unset($issue);
        flow_block_json_response(['ok' => true, 'task' => $task, 'issues' => $issues]);
    }

    if ($action === 'tasks') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $where = "fi.status IN ('Em andamento','HOLD')";
        $types = '';
        $values = [];
        if (!flow_block_is_manager()) {
            $where .= ' AND fi.colaborador_id = ?';
            $types .= 'i';
            $values[] = $actorId;
        }
        if ($search !== '') {
            $where .= ' AND (ico.imagem_nome LIKE ? OR f.nome_funcao LIKE ? OR o.nomenclatura LIKE ? OR o.nome_obra LIKE ?)';
            $types .= 'ssss';
            for ($x = 0; $x < 4; $x++) $values[] = '%' . $search . '%';
        }
        $sql = "SELECT fi.idfuncao_imagem AS id, ico.imagem_nome, f.nome_funcao, o.nomenclatura, o.nome_obra, fi.status
                FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=fi.imagem_id
                JOIN funcao f ON f.idfuncao=fi.funcao_id JOIN obra o ON o.idobra=ico.obra_id
                WHERE $where ORDER BY o.nomenclatura, ico.imagem_nome, f.nome_funcao LIMIT 20";
        $stmt = $conn->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        flow_block_json_response(['ok' => true, 'tasks' => $tasks]);
    }

    if ($action === 'detail') {
        $issueId = (int) ($_GET['id'] ?? 0);
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        $mentionId = (int) ($_GET['mention_id'] ?? 0);
        if ($mentionId > 0) {
            fb_mark_mentions_read($conn, $issueId, $actorId, $mentionId);
        } elseif ((string) ($_GET['mark_mentions'] ?? '') === '1') {
            fb_mark_mentions_read($conn, $issueId, $actorId);
        }
        $stmt = $conn->prepare("SELECT a.*, c.nome_colaborador AS autor_nome FROM flow_issue_atividade a LEFT JOIN colaborador c ON c.idcolaborador=a.criado_por_colaborador_id WHERE a.issue_id=? ORDER BY a.criado_em ASC, a.id ASC");
        $stmt->bind_param('i', $issueId);
        $stmt->execute();
        $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $attachments = $conn->prepare('SELECT * FROM flow_issue_anexo WHERE issue_id = ? ORDER BY criado_em ASC');
        $attachments->bind_param('i', $issueId);
        $attachments->execute();
        $attachmentRows = $attachments->get_result()->fetch_all(MYSQLI_ASSOC);
        $attachments->close();
        $mentions = $conn->prepare('SELECT m.atividade_id, m.colaborador_id, m.status, m.visualizado_em, c.nome_colaborador FROM flow_issue_mencao m JOIN colaborador c ON c.idcolaborador = m.colaborador_id WHERE m.issue_id = ? ORDER BY c.nome_colaborador');
        $mentions->bind_param('i', $issueId);
        $mentions->execute();
        $mentionsByActivity = [];
        foreach ($mentions->get_result()->fetch_all(MYSQLI_ASSOC) as $mention) {
            $mentionsByActivity[(int) $mention['atividade_id']][] = [
                'id' => (int) $mention['colaborador_id'],
                'nome' => $mention['nome_colaborador'],
            ];
        }
        $mentions->close();
        foreach ($activities as &$activity) {
            $activity['metadados'] = $activity['metadados'] ? json_decode($activity['metadados'], true) : null;
            $activity['mencoes'] = $mentionsByActivity[(int) $activity['id']] ?? [];
            $activity['can_edit'] = $activity['tipo'] === 'COMENTARIO'
                && empty($activity['excluido_em'])
                && (flow_block_is_manager() || (int) $activity['criado_por_colaborador_id'] === $actorId);
        }
        unset($activity);
        foreach ($attachmentRows as &$attachment) {
            $attachment['url'] = 'anexo.php?id=' . (int) $attachment['id'];
            $attachment['is_image'] = in_array(strtolower(pathinfo((string) $attachment['nome_original'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
        }
        unset($attachment);
        $blocking = in_array($issue['status'], flow_block_active_statuses(), true)
            || ($issue['status'] === 'RESOLVIDA' && empty($issue['confirmada_em']));
        $issue['tempo_bloqueado'] = flow_block_duration_label($issue['criado_em'], $blocking ? null : $issue['resolvido_em']);
        $issue['cobranca_atrasada'] = !empty($issue['proxima_cobranca_em'])
            && in_array($issue['status'], flow_block_active_statuses(), true)
            && strtotime($issue['proxima_cobranca_em']) < time();
        $issue['can_manage'] = flow_block_is_manager();
        $issue['can_resolve'] = flow_block_can_resolve_issue($issue);
        $issue['can_confirm_resolution'] = flow_block_can_confirm_resolution($issue);
        $issue['task_ready_to_continue'] = flow_block_task_ready_to_continue($conn, (int) $issue['funcao_imagem_id']);
        $issue['current_actor_id'] = $actorId;
        flow_block_json_response(['ok' => true, 'issue' => $issue, 'activities' => $activities, 'attachments' => $attachmentRows]);
    }

    if ($action === 'upload') {
        $issueId = (int) ($_POST['id'] ?? 0);
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        $files = $_FILES['files'] ?? null;
        if (!$files || !is_array($files['name'] ?? null)) flow_block_json_response(['ok' => false, 'message' => 'Selecione ao menos um anexo.'], 422);
        $conn->begin_transaction();
        flow_block_add_activity($conn, $issueId, 'ANEXO', 'Anexou arquivo(s).');
        $activityId = (int) $conn->insert_id;
        $saved = fb_save_attachments($conn, $issue, $activityId, $files, $actorId);
        if (!$saved) throw new RuntimeException('Nenhum anexo válido foi enviado.');
        $conn->commit();
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], 'comment.attachment.created');
        flow_block_json_response(['ok' => true, 'attachments' => $saved]);
    }

    $payload = $_POST ?: flow_block_read_json();
    if ($action === 'create') {
        $taskId = (int) ($payload['funcao_imagem_id'] ?? 0);
        $typeId = (int) ($payload['tipo_id'] ?? 0);
        $queueId = (int) ($payload['fila_id'] ?? 0) ?: null;
        $responsibleId = (int) ($payload['responsavel_id'] ?? 0) ?: null;
        $description = trim((string) ($payload['descricao'] ?? ''));
        $urgency = strtoupper((string) ($payload['urgencia'] ?? 'NORMAL'));
        $task = flow_block_task($conn, $taskId);
        if (!$task || !flow_block_can_access_task($task)) flow_block_json_response(['ok' => false, 'message' => 'Você não pode bloquear esta tarefa.'], 403);
        if (($task['status'] ?? '') !== 'Em andamento' && ($task['status'] ?? '') !== 'HOLD') flow_block_json_response(['ok' => false, 'message' => 'A Issue só pode ser aberta em uma tarefa em andamento.'], 422);
        if (!$typeId || $description === '') flow_block_json_response(['ok' => false, 'message' => 'Tipo e observação são obrigatórios.'], 422);
        if (!in_array($urgency, ['BAIXA', 'NORMAL', 'ALTA', 'CRITICA'], true)) $urgency = 'NORMAL';
        $conn->begin_transaction();
        $beforeStatus = $task['status'];
        $stmt = $conn->prepare("INSERT INTO flow_issue (funcao_imagem_id,tipo_id,fila_id,responsavel_colaborador_id,descricao,urgencia,status,bloqueante,criado_por_colaborador_id,sla_atendimento_em,proxima_cobranca_em) VALUES (?,?,?,?,?,?, 'ABERTA',1,?,DATE_ADD(NOW(), INTERVAL 2 HOUR),DATE_ADD(NOW(), INTERVAL 2 HOUR))");
        $stmt->bind_param('iiiissi', $taskId, $typeId, $queueId, $responsibleId, $description, $urgency, $actorId);
        $stmt->execute();
        $issueId = $stmt->insert_id;
        $stmt->close();
        $code = 'ISSUE-' . str_pad((string) $issueId, 4, '0', STR_PAD_LEFT);
        $updateCode = $conn->prepare('UPDATE flow_issue SET codigo=? WHERE id=?');
        $updateCode->bind_param('si', $code, $issueId);
        $updateCode->execute();
        $updateCode->close();
        $cycle = $conn->prepare('INSERT INTO flow_issue_ciclo (issue_id,status_inicial) VALUES (?,?)');
        $cycle->bind_param('is', $issueId, $beforeStatus);
        $cycle->execute();
        $cycle->close();
        flow_block_add_activity($conn, $issueId, 'CRIADA', $description, ['status_tarefa_anterior' => $beforeStatus]);
        $hold = $conn->prepare("UPDATE funcao_imagem SET status='HOLD', observacao=? WHERE idfuncao_imagem=?");
        $hold->bind_param('si', $description, $taskId);
        $hold->execute();
        $hold->close();
        flow_block_notify($conn, $responsibleId ?? 0, $taskId, "$code foi atribuída a você no Flow Block.");
        $conn->commit();
        flow_block_publish($issueId, $taskId, 'issue_created');
        flow_block_json_response(['ok' => true, 'id' => $issueId, 'codigo' => $code]);
    }

    if ($action === 'comment') {
        $issueId = (int) ($payload['id'] ?? 0);
        $content = trim((string) ($payload['conteudo'] ?? ''));
        $mentionedIds = $payload['mencionados'] ?? [];
        if (is_string($mentionedIds)) $mentionedIds = json_decode($mentionedIds, true) ?: [];
        if (!is_array($mentionedIds)) $mentionedIds = [];
        $parentActivityId = (int) ($payload['atividade_pai_id'] ?? 0) ?: null;
        $files = $_FILES['files'] ?? null;
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        if ($content === '' && (!$files || !is_array($files['name'] ?? null))) flow_block_json_response(['ok' => false, 'message' => 'Escreva um comentário ou selecione um anexo.'], 422);
        if ($parentActivityId) {
            $parent = $conn->prepare("SELECT id FROM flow_issue_atividade WHERE id=? AND issue_id=? AND tipo='COMENTARIO' AND excluido_em IS NULL LIMIT 1");
            $parent->bind_param('ii', $parentActivityId, $issueId);
            $parent->execute();
            $validParent = (bool) $parent->get_result()->fetch_row();
            $parent->close();
            if (!$validParent) flow_block_json_response(['ok' => false, 'message' => 'Comentário de origem não encontrado.'], 422);
        }
        $conn->begin_transaction();
        flow_block_add_activity($conn, $issueId, $content !== '' ? 'COMENTARIO' : 'ANEXO', $content ?: 'Anexou arquivo(s).', [], $parentActivityId);
        $activityId = (int) $conn->insert_id;
        $newMentions = fb_add_mentions(
            $conn,
            $issueId,
            $activityId,
            $mentionedIds,
            (int) $issue['funcao_imagem_id'],
            (string) $issue['codigo']
        );
        $attachments = $files && is_array($files['name'] ?? null)
            ? fb_save_attachments($conn, $issue, $activityId, $files, $actorId)
            : [];
        $conn->commit();
        foreach ($newMentions as $mention) flow_block_publish_mention($mention);
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], $parentActivityId ? 'comment.replied' : 'comment.created');
        flow_block_json_response(['ok' => true, 'activity_id' => $activityId, 'attachments' => $attachments]);
    }

    if ($action === 'comment_update') {
        $issueId = (int) ($payload['id'] ?? 0);
        $activityId = (int) ($payload['atividade_id'] ?? 0);
        $content = trim((string) ($payload['conteudo'] ?? ''));
        $mentionedIds = $payload['mencionados'] ?? [];
        if (!is_array($mentionedIds)) $mentionedIds = [];
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        if ($content === '') flow_block_json_response(['ok' => false, 'message' => 'O comentário não pode ficar vazio.'], 422);
        $comment = $conn->prepare("SELECT * FROM flow_issue_atividade WHERE id=? AND issue_id=? AND tipo='COMENTARIO' AND excluido_em IS NULL LIMIT 1");
        $comment->bind_param('ii', $activityId, $issueId);
        $comment->execute();
        $activity = $comment->get_result()->fetch_assoc() ?: null;
        $comment->close();
        if (!$activity) flow_block_json_response(['ok' => false, 'message' => 'Comentário não encontrado.'], 404);
        if (!flow_block_is_manager() && (int) $activity['criado_por_colaborador_id'] !== $actorId) flow_block_json_response(['ok' => false, 'message' => 'Você não pode editar este comentário.'], 403);
        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE flow_issue_atividade SET conteudo=?, atualizado_em=NOW() WHERE id=?');
        $stmt->bind_param('si', $content, $activityId);
        $stmt->execute();
        $stmt->close();
        $newMentions = fb_add_mentions($conn, $issueId, $activityId, $mentionedIds, (int) $issue['funcao_imagem_id'], (string) $issue['codigo']);
        $conn->commit();
        foreach ($newMentions as $mention) flow_block_publish_mention($mention);
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], 'comment.updated');
        flow_block_json_response(['ok' => true]);
    }

    if ($action === 'comment_delete') {
        $issueId = (int) ($payload['id'] ?? 0);
        $activityId = (int) ($payload['atividade_id'] ?? 0);
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        $comment = $conn->prepare("SELECT * FROM flow_issue_atividade WHERE id=? AND issue_id=? AND tipo='COMENTARIO' AND excluido_em IS NULL LIMIT 1");
        $comment->bind_param('ii', $activityId, $issueId);
        $comment->execute();
        $activity = $comment->get_result()->fetch_assoc() ?: null;
        $comment->close();
        if (!$activity) flow_block_json_response(['ok' => false, 'message' => 'Comentário não encontrado.'], 404);
        if (!flow_block_is_manager() && (int) $activity['criado_por_colaborador_id'] !== $actorId) flow_block_json_response(['ok' => false, 'message' => 'Você não pode excluir este comentário.'], 403);
        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE flow_issue_atividade SET conteudo=NULL, excluido_em=NOW(), excluido_por_colaborador_id=? WHERE id=?');
        $stmt->bind_param('ii', $actorId, $activityId);
        $stmt->execute();
        $stmt->close();
        $mentions = $conn->prepare("UPDATE flow_issue_mencao SET status='LIDA', visualizado_em=COALESCE(visualizado_em, NOW()) WHERE atividade_id=? AND status='PENDENTE'");
        $mentions->bind_param('i', $activityId);
        $mentions->execute();
        $mentions->close();
        $conn->commit();
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], 'comment.deleted');
        flow_block_json_response(['ok' => true]);
    }

    if ($action === 'update') {
        $issueId = (int) ($payload['id'] ?? 0);
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        $canEditAll = flow_block_is_manager() || (int) $issue['criado_por_colaborador_id'] === $actorId;
        $canReassign = flow_block_can_resolve_issue($issue);
        if (!$canEditAll && !$canReassign) flow_block_json_response(['ok' => false, 'message' => 'Você não pode editar esta Issue.'], 403);
        $fields = [];
        $types = '';
        $values = [];
        $changes = [];
        foreach (['tipo_id' => 'i', 'fila_id' => 'i', 'responsavel_colaborador_id' => 'i', 'urgencia' => 's', 'descricao' => 's', 'status' => 's'] as $field => $type) {
            if (!array_key_exists($field, $payload)) continue;
            if (!$canEditAll && $field !== 'responsavel_colaborador_id') continue;
            $new = $payload[$field];
            if ($field === 'responsavel_colaborador_id' || $field === 'fila_id') $new = (int) $new ?: null;
            if ($field === 'tipo_id') $new = (int) $new;
            if ($field === 'responsavel_colaborador_id' && $new !== null) {
                $person = $conn->prepare('SELECT 1 FROM colaborador WHERE idcolaborador=? AND ativo=1 LIMIT 1');
                $person->bind_param('i', $new);
                $person->execute();
                $validResponsible = (bool) $person->get_result()->fetch_row();
                $person->close();
                if (!$validResponsible) flow_block_json_response(['ok' => false, 'message' => 'Responsável inválido.'], 422);
            }
            if ($field === 'urgencia') {
                $new = strtoupper((string) $new);
                if (!in_array($new, ['BAIXA', 'NORMAL', 'ALTA', 'CRITICA'], true)) continue;
            }
            if ($field === 'descricao') {
                $new = trim((string) $new);
                if ($new === '') continue;
            }
            if ($field === 'status') {
                $new = strtoupper((string) $new);
                if (!in_array($new, ['ABERTA', 'AGUARDANDO_ACAO'], true)) continue;
            }
            if ((string) $issue[$field] === (string) $new) continue;
            $fields[] = "$field = ?";
            $types .= $type;
            $values[] = $new;
            $changes[$field] = ['antes' => $issue[$field], 'depois' => $new];
        }
        if (!$fields) flow_block_json_response(['ok' => true, 'unchanged' => true]);
        if (isset($changes['responsavel_colaborador_id'])) {
            // Reatribuição é uma tratativa e reinicia a cobrança de 2h para o novo responsável.
            $fields[] = 'primeira_tratativa_em = COALESCE(primeira_tratativa_em, NOW())';
            $fields[] = 'sla_atendimento_em = DATE_ADD(NOW(), INTERVAL 2 HOUR)';
            $fields[] = 'proxima_cobranca_em = DATE_ADD(NOW(), INTERVAL 2 HOUR)';
        }
        $values[] = $issueId;
        $types .= 'i';
        $stmt = $conn->prepare('UPDATE flow_issue SET ' . implode(', ', $fields) . ' WHERE id=?');
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
        flow_block_add_activity($conn, $issueId, 'ALTERADA', null, $changes);
        if (isset($changes['responsavel_colaborador_id'])) flow_block_notify($conn, (int) $changes['responsavel_colaborador_id']['depois'], (int) $issue['funcao_imagem_id'], $issue['codigo'] . ' foi atribuída a você.');
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], 'issue_updated');
        flow_block_json_response(['ok' => true]);
    }

    if ($action === 'pause') {
        $issueId = (int) ($payload['id'] ?? 0);
        $reason = trim((string) ($payload['motivo'] ?? ''));
        $observation = trim((string) ($payload['observacao'] ?? ''));
        $returnAt = trim((string) ($payload['retorno_previsto_em'] ?? ''));
        $responsibleId = (int) ($payload['responsavel_id'] ?? 0) ?: null;
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        if (!flow_block_can_resolve_issue($issue)) flow_block_json_response(['ok' => false, 'message' => 'Somente o responsável ou a gestão pode pausar esta Issue.'], 403);
        if (!in_array($issue['status'], ['ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA'], true)) flow_block_json_response(['ok' => false, 'message' => 'Esta Issue não pode ser pausada neste estado.'], 422);
        try {
            $returnDate = new DateTimeImmutable($returnAt);
            if ($returnDate <= new DateTimeImmutable('now')) throw new RuntimeException();
            $returnSql = $returnDate->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            flow_block_json_response(['ok' => false, 'message' => 'Informe uma data e hora futura para o retorno.'], 422);
        }
        if ($reason === '') flow_block_json_response(['ok' => false, 'message' => 'Informe o motivo da pausa.'], 422);
        if ($responsibleId !== null) {
            $person = $conn->prepare('SELECT 1 FROM colaborador WHERE idcolaborador=? AND ativo=1 LIMIT 1');
            $person->bind_param('i', $responsibleId);
            $person->execute();
            $validResponsible = (bool) $person->get_result()->fetch_row();
            $person->close();
            if (!$validResponsible) flow_block_json_response(['ok' => false, 'message' => 'Responsável inválido.'], 422);
        }
        $updatingPause = $issue['status'] === 'PAUSADA';
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE flow_issue SET status='PAUSADA', primeira_tratativa_em=COALESCE(primeira_tratativa_em,NOW()), pausada_em=NOW(), pausada_por_colaborador_id=?, pausa_motivo=?, pausa_observacao=?, retorno_previsto_em=?, proxima_cobranca_em=?, responsavel_colaborador_id=? WHERE id=?");
        $effectiveResponsibleId = $responsibleId ?? ((int) ($issue['responsavel_colaborador_id'] ?? 0) ?: null);
        $stmt->bind_param('issssii', $actorId, $reason, $observation, $returnSql, $returnSql, $effectiveResponsibleId, $issueId);
        $stmt->execute();
        $stmt->close();
        $pauseMetadata = [
            'retorno_previsto_em' => $returnSql,
            'observacao' => $observation,
            'responsavel_anterior_id' => (int) ($issue['responsavel_colaborador_id'] ?? 0) ?: null,
            'responsavel_novo_id' => $effectiveResponsibleId,
        ];
        flow_block_add_activity($conn, $issueId, $updatingPause ? 'PAUSA_ATUALIZADA' : 'PAUSADA', $reason, $pauseMetadata);
        $conn->commit();
        if ($effectiveResponsibleId && $effectiveResponsibleId !== (int) ($issue['responsavel_colaborador_id'] ?? 0)) {
            flow_block_notify($conn, $effectiveResponsibleId, (int) $issue['funcao_imagem_id'], $issue['codigo'] . ' foi reatribuída durante a atualização da pausa.');
        }
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], $updatingPause ? 'issue.pause_updated' : 'issue.paused');
        flow_block_json_response(['ok' => true]);
    }

    if ($action === 'transition') {
        $issueId = (int) ($payload['id'] ?? 0);
        $target = strtoupper((string) ($payload['status'] ?? ''));
        $comment = trim((string) ($payload['comentario'] ?? ''));
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        if (!in_array($target, ['RESOLVIDA', 'CANCELADA', 'ABERTA'], true)) flow_block_json_response(['ok' => false, 'message' => 'Estado inválido.'], 422);
        if ($comment === '') flow_block_json_response(['ok' => false, 'message' => $target === 'ABERTA' ? 'Informe a justificativa para reabrir a Issue.' : 'Informe o comentário final da Issue.'], 422);
        if ($target === 'ABERTA') {
            if ($issue['status'] !== 'RESOLVIDA' || !empty($issue['confirmada_em'])) flow_block_json_response(['ok' => false, 'message' => 'Apenas uma resolução aguardando confirmação pode ser reaberta.'], 422);
            if (!flow_block_can_confirm_resolution($issue)) flow_block_json_response(['ok' => false, 'message' => 'Somente o dono da tarefa ou a gestão pode reabrir esta Issue.'], 403);
        } elseif (!in_array($issue['status'], ['ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA'], true)) {
            flow_block_json_response(['ok' => false, 'message' => 'Esta Issue não pode ser encerrada no estado atual.'], 422);
        } elseif (!flow_block_can_resolve_issue($issue)) {
            flow_block_json_response(['ok' => false, 'message' => 'Somente o responsável ou a gestão pode encerrar esta Issue.'], 403);
        }
        $conn->begin_transaction();
        if ($target === 'ABERTA') {
            $stmt = $conn->prepare("UPDATE flow_issue SET status='ABERTA', resolvido_por_colaborador_id=NULL, resolvido_em=NULL, encerramento_observacao=NULL, confirmada_por_colaborador_id=NULL, confirmada_em=NULL, confirmacao_observacao=NULL, sla_atendimento_em=DATE_ADD(NOW(), INTERVAL 2 HOUR), proxima_cobranca_em=DATE_ADD(NOW(), INTERVAL 2 HOUR), primeira_tratativa_em=NULL WHERE id=?");
            $stmt->bind_param('i', $issueId);
            $stmt->execute();
            $stmt->close();
            $cycle = $conn->prepare('INSERT INTO flow_issue_ciclo (issue_id,status_inicial) VALUES (?,?)');
            $old = $issue['tarefa_status'];
            $cycle->bind_param('is', $issueId, $old);
            $cycle->execute();
            $cycle->close();
            flow_block_add_activity($conn, $issueId, 'REABERTA', $comment ?: null);
            flow_block_refresh_task_status($conn, (int) $issue['funcao_imagem_id']);
            flow_block_notify($conn, (int) ($issue['responsavel_colaborador_id'] ?? 0), (int) $issue['funcao_imagem_id'], $issue['codigo'] . ' foi reaberta pelo dono da tarefa.');
        } elseif ($target === 'RESOLVIDA') {
            // A resolução é uma resposta do responsável, não a liberação da tarefa.
            $stmt = $conn->prepare("UPDATE flow_issue SET status='RESOLVIDA', resolvido_por_colaborador_id=?, resolvido_em=NOW(), encerramento_observacao=?, confirmada_por_colaborador_id=NULL, confirmada_em=NULL, confirmacao_observacao=NULL, proxima_cobranca_em=NULL WHERE id=?");
            $stmt->bind_param('isi', $actorId, $comment, $issueId);
            $stmt->execute();
            $stmt->close();
            flow_block_add_activity($conn, $issueId, 'RESOLVIDA', $comment, ['status_anterior' => $issue['status'], 'status_novo' => 'RESOLVIDA']);
            // RESOLVIDA sem confirmação continua bloqueante: garante HOLD mesmo
            // em tarefas que chegaram de algum fluxo legado em andamento.
            flow_block_refresh_task_status($conn, (int) $issue['funcao_imagem_id']);
            flow_block_notify($conn, (int) $issue['tarefa_colaborador_id'], (int) $issue['funcao_imagem_id'], $issue['codigo'] . ' foi marcada como resolvida e aguarda sua confirmação.');
        } else {
            $stmt = $conn->prepare("UPDATE flow_issue SET status='CANCELADA', resolvido_por_colaborador_id=?, resolvido_em=NOW(), encerramento_observacao=?, confirmada_por_colaborador_id=?, confirmada_em=NOW(), confirmacao_observacao='Cancelada', proxima_cobranca_em=NULL WHERE id=?");
            $stmt->bind_param('isii', $actorId, $comment, $actorId, $issueId);
            $stmt->execute();
            $stmt->close();
            $cycle = $conn->prepare('UPDATE flow_issue_ciclo SET finalizado_em=NOW(), status_final=? WHERE issue_id=? AND finalizado_em IS NULL ORDER BY id DESC LIMIT 1');
            $cycle->bind_param('si', $target, $issueId);
            $cycle->execute();
            $cycle->close();
            flow_block_add_activity($conn, $issueId, 'CANCELADA', $comment, ['status_anterior' => $issue['status'], 'status_novo' => 'CANCELADA']);
            flow_block_notify($conn, (int) $issue['tarefa_colaborador_id'], (int) $issue['funcao_imagem_id'], $issue['codigo'] . ' foi cancelada. A tarefa permanece em HOLD até ser reprogramada.');
        }
        $conn->commit();
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], $target === 'RESOLVIDA' ? 'issue.resolved_waiting_confirmation' : ($target === 'ABERTA' ? 'issue.reopened' : 'issue.cancelled'));
        flow_block_json_response(['ok' => true]);
    }

    if ($action === 'confirm_resolution') {
        $issueId = (int) ($payload['id'] ?? 0);
        $comment = trim((string) ($payload['comentario'] ?? ''));
        $issue = fb_get_issue($conn, $issueId);
        if (!$issue || !fb_visible($conn, $issue)) flow_block_json_response(['ok' => false, 'message' => 'Issue não encontrada.'], 404);
        if ($issue['status'] !== 'RESOLVIDA' || !empty($issue['confirmada_em'])) flow_block_json_response(['ok' => false, 'message' => 'Esta resolução não está aguardando confirmação.'], 422);
        if (!flow_block_can_confirm_resolution($issue)) flow_block_json_response(['ok' => false, 'message' => 'Somente o dono da tarefa ou a gestão pode confirmar esta resolução.'], 403);
        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE flow_issue SET confirmada_por_colaborador_id=?, confirmada_em=NOW(), confirmacao_observacao=? WHERE id=?');
        $stmt->bind_param('isi', $actorId, $comment, $issueId);
        $stmt->execute();
        $stmt->close();
        $cycle = $conn->prepare("UPDATE flow_issue_ciclo SET finalizado_em=NOW(), status_final='RESOLVIDA' WHERE issue_id=? AND finalizado_em IS NULL ORDER BY id DESC LIMIT 1");
        $cycle->bind_param('i', $issueId);
        $cycle->execute();
        $cycle->close();
        flow_block_add_activity($conn, $issueId, 'RESOLUCAO_CONFIRMADA', $comment ?: 'O dono da tarefa confirmou que pode continuar o trabalho.', ['status_anterior' => 'RESOLVIDA', 'status_novo' => 'CONFIRMADA']);
        $taskReadyToContinue = flow_block_task_ready_to_continue($conn, (int) $issue['funcao_imagem_id']);
        $conn->commit();
        flow_block_publish($issueId, (int) $issue['funcao_imagem_id'], 'issue.resolution_confirmed');
        flow_block_json_response(['ok' => true, 'task_ready_to_continue' => $taskReadyToContinue]);
    }

    if ($action === 'continue_task') {
        $taskId = (int) ($payload['funcao_imagem_id'] ?? 0);
        $newDeadline = trim((string) ($payload['prazo'] ?? ''));
        $replanNote = trim((string) ($payload['observacao'] ?? ''));
        $task = flow_block_task($conn, $taskId);
        if (!$task || !flow_block_can_access_task($task)) flow_block_json_response(['ok' => false, 'message' => 'Tarefa não encontrada.'], 404);
        if (($task['status'] ?? '') !== 'HOLD') flow_block_json_response(['ok' => false, 'message' => 'A tarefa não está em HOLD.'], 422);
        $deadline = DateTimeImmutable::createFromFormat('!Y-m-d', $newDeadline);
        if (!$deadline || $deadline->format('Y-m-d') !== $newDeadline) {
            flow_block_json_response(['ok' => false, 'message' => 'Informe um novo prazo válido para continuar a tarefa.'], 422);
        }
        if (flow_block_has_blocking_issues($conn, $taskId)) {
            flow_block_json_response(['ok' => false, 'message' => 'Ainda existem Issues que impedem a continuidade da tarefa.'], 422);
        }
        if (!flow_block_task_ready_to_continue($conn, $taskId)) {
            flow_block_json_response(['ok' => false, 'message' => 'Não há Issues encerradas e confirmadas para liberar esta tarefa.'], 422);
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE funcao_imagem SET status = 'Em andamento', prazo = ? WHERE idfuncao_imagem = ? AND status = 'HOLD'");
        $stmt->bind_param('si', $newDeadline, $taskId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('A tarefa não pôde ser atualizada; atualize a tela e tente novamente.');
        }
        $stmt->close();

        $statusBefore = 'HOLD';
        $statusAfter = 'Em andamento';
        $previousDeadline = $task['prazo'] ?? null;
        $actorUserId = (int) ($_SESSION['idusuario'] ?? 0);
        $historyOrigin = 'flow_block';
        $historyNote = $replanNote !== '' ? $replanNote : null;
        $deadlineHistory = $conn->prepare(
            'INSERT INTO funcao_imagem_prazo_historico
                (funcao_imagem_id, prazo_anterior, prazo_novo, alterado_por_colaborador_id, alterado_por_usuario_id, origem, motivo, status_anterior, status_novo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $deadlineHistory->bind_param('issiissss', $taskId, $previousDeadline, $newDeadline, $actorId, $actorUserId, $historyOrigin, $historyNote, $statusBefore, $statusAfter);
        $deadlineHistory->execute();
        $deadlineHistory->close();

        $log = $conn->prepare('INSERT INTO log_alteracoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id) VALUES (?, ?, ?, ?)');
        $log->bind_param('issi', $taskId, $statusBefore, $statusAfter, $actorId);
        $log->execute();
        $log->close();

        $lastIssue = $conn->prepare("SELECT id FROM flow_issue WHERE funcao_imagem_id = ? AND bloqueante = 1 AND (status = 'CANCELADA' OR (status = 'RESOLVIDA' AND confirmada_em IS NOT NULL)) ORDER BY COALESCE(confirmada_em, resolvido_em, atualizado_em) DESC, id DESC LIMIT 1");
        $lastIssue->bind_param('i', $taskId);
        $lastIssue->execute();
        $issueId = (int) (($lastIssue->get_result()->fetch_assoc()['id'] ?? 0));
        $lastIssue->close();
        if ($issueId > 0) {
            $content = 'Tarefa reprogramada de ' . ($previousDeadline ?: 'sem prazo') . ' para ' . $newDeadline . '.';
            if ($replanNote !== '') $content .= ' ' . $replanNote;
            flow_block_add_activity($conn, $issueId, 'TAREFA_REPROGRAMADA', $content, [
                'status_anterior' => 'HOLD',
                'status_novo' => 'Em andamento',
                'prazo_anterior' => $previousDeadline,
                'prazo_novo' => $newDeadline,
                'observacao_reprogramacao' => $replanNote,
                'reprogramado_por_colaborador_id' => $actorId,
            ]);
        }
        $conn->commit();
        flow_block_publish($issueId, $taskId, 'task.continued_replanned');
        flow_block_json_response(['ok' => true, 'issue_id' => $issueId]);
    }

    flow_block_json_response(['ok' => false, 'message' => 'Ação não encontrada.'], 404);
} catch (Throwable $e) {
    if ($conn->errno) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }
    flow_block_json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
