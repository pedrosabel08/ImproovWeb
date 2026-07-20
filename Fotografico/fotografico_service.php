<?php

declare(strict_types=1);

const FOTOGRAFICO_RESPONSAVEL_PLANO_ID = 2;
const FOTOGRAFICO_TODO_SUBSTATUS_ID = 2;
const FOTOGRAFICO_HOLD_SUBSTATUS_ID = 7;

function fotografico_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($conn) . ':' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    $cache[$key] = $exists;
    return $exists;
}

function fotografico_schema_ready(mysqli $conn): bool
{
    return fotografico_table_exists($conn, 'fotografico_plano')
        && fotografico_table_exists($conn, 'fotografico_plano_versao')
        && fotografico_table_exists($conn, 'fotografico_evento')
        && fotografico_table_exists($conn, 'fotografico_execucao_conferencia');
}

function fotografico_actor_id(): ?int
{
    $id = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : 0;
    return $id > 0 ? $id : null;
}

function fotografico_user_id(): ?int
{
    $id = isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : 0;
    return $id > 0 ? $id : null;
}

function fotografico_json_encode(?array $data): ?string
{
    if ($data === null) {
        return null;
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? null : $json;
}

function fotografico_evento(
    mysqli $conn,
    int $planoId,
    string $tipo,
    ?string $statusAnterior,
    ?string $statusNovo,
    ?int $atorId,
    string $origem,
    ?array $dados = null
): void {
    $stmt = $conn->prepare(
        'INSERT INTO fotografico_evento
            (plano_id, tipo, status_anterior, status_novo, ator_id, origem, dados_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar evento fotografico: ' . $conn->error);
    }
    $json = fotografico_json_encode($dados);
    $stmt->bind_param('isssiss', $planoId, $tipo, $statusAnterior, $statusNovo, $atorId, $origem, $json);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Falha ao registrar evento fotografico: ' . $error);
    }
    $stmt->close();
}

function fotografico_next_business_due(mysqli $conn, DateTimeImmutable $startedAt): DateTimeImmutable
{
    $date = $startedAt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    for ($attempt = 0; $attempt < 370; $attempt++) {
        $date = $date->modify('+1 day');
        if ((int) $date->format('N') >= 6) {
            continue;
        }

        $ymd = $date->format('Y-m-d');
        $holiday = false;
        if (fotografico_table_exists($conn, 'fotografico_calendario_feriado')) {
            $stmt = $conn->prepare(
                'SELECT 1 FROM fotografico_calendario_feriado
                 WHERE data_feriado = ? AND bloqueia_dia_util = 1 LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('s', $ymd);
                $stmt->execute();
                $holiday = (bool) $stmt->get_result()->fetch_row();
                $stmt->close();
            }
        }
        if (!$holiday) {
            return $date->setTime(23, 59, 59);
        }
    }
    throw new RuntimeException('Nao foi possivel calcular o proximo dia util.');
}

function fotografico_execution_due(DateTimeImmutable $publishedAt): DateTimeImmutable
{
    return $publishedAt
        ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
        ->modify('+7 days')
        ->setTime(23, 59, 59);
}

function fotografico_colaborador_ativo(mysqli $conn, int $colaboradorId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM colaborador WHERE idcolaborador = ? AND ativo = 1 LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $active = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $active;
}

function fotografico_notificar_colaborador(
    mysqli $conn,
    int $planoId,
    int $colaboradorId,
    string $chave,
    string $titulo,
    string $mensagem,
    string $tipo = 'info'
): void {
    if (
        !fotografico_table_exists($conn, 'notificacoes')
        || !fotografico_table_exists($conn, 'notificacoes_destinatarios')
        || !fotografico_table_exists($conn, 'fotografico_notificacao_envio')
    ) {
        return;
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM fotografico_notificacao_envio WHERE plano_id = ? AND chave = ? LIMIT 1'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('is', $planoId, $chave);
    $stmt->execute();
    $alreadySent = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    if ($alreadySent) {
        return;
    }

    $stmt = $conn->prepare('SELECT idusuario FROM usuario WHERE idcolaborador = ? AND ativo = 1 LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $usuarioId = (int) ($row['idusuario'] ?? 0);
    if ($usuarioId <= 0) {
        return;
    }

    $ctaUrl = '/ImproovWeb/Fotografico/index.php?plano_id=' . $planoId;
    $payload = fotografico_json_encode(['modulo' => 'fotografico', 'plano_id' => $planoId]);
    $criadoPor = fotografico_user_id();
    $canal = 'card';
    $segmentacao = 'pessoa';
    $ctaLabel = 'Abrir plano';
    $stmt = $conn->prepare(
        "INSERT INTO notificacoes
            (titulo, mensagem, tipo, canal, segmentacao_tipo, prioridade, ativa, inicio_em,
             fixa, fechavel, exige_confirmacao, cta_label, cta_url, payload_json, criado_por)
         VALUES (?, ?, ?, ?, ?, 10, 1, NOW(), 0, 1, 0, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        error_log('[Fotografico] Notificacao indisponivel: ' . $conn->error);
        return;
    }
    $stmt->bind_param(
        'ssssssssi',
        $titulo,
        $mensagem,
        $tipo,
        $canal,
        $segmentacao,
        $ctaLabel,
        $ctaUrl,
        $payload,
        $criadoPor
    );
    if (!$stmt->execute()) {
        error_log('[Fotografico] Falha ao criar notificacao: ' . $stmt->error);
        $stmt->close();
        return;
    }
    $notificacaoId = (int) $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        'INSERT INTO notificacoes_destinatarios (notificacao_id, usuario_id) VALUES (?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('ii', $notificacaoId, $usuarioId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare(
        'INSERT IGNORE INTO fotografico_notificacao_envio (plano_id, chave, notificacao_id) VALUES (?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('isi', $planoId, $chave, $notificacaoId);
        $stmt->execute();
        $stmt->close();
    }
}

function fotografico_criar_plano_automatico(
    mysqli $conn,
    int $imagemId,
    ?int $atorId,
    string $origem
): ?int {
    if (!fotografico_schema_ready($conn)) {
        error_log('[Fotografico] Migration pendente; gatilho ignorado temporariamente.');
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT idimagens_cliente_obra, obra_id, tipo_imagem, substatus_id
         FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? FOR UPDATE'
    );
    if (!$stmt) {
        throw new RuntimeException('Falha ao consultar imagem-gatilho: ' . $conn->error);
    }
    $stmt->bind_param('i', $imagemId);
    $stmt->execute();
    $image = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$image || mb_strtolower(trim((string) $image['tipo_imagem']), 'UTF-8') !== 'fachada') {
        return null;
    }
    if ((int) $image['substatus_id'] !== FOTOGRAFICO_TODO_SUBSTATUS_ID) {
        return null;
    }

    $obraId = (int) $image['obra_id'];
    $triggerKey = 'obra:' . $obraId . ':FACHADA_TODO_INICIAL';
    $stmt = $conn->prepare('SELECT id FROM fotografico_plano WHERE chave_gatilho = ? LIMIT 1');
    $stmt->bind_param('s', $triggerKey);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        return (int) $existing['id'];
    }

    $stmt = $conn->prepare('SELECT idobra FROM obra WHERE idobra = ? FOR UPDATE');
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $obraExists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$obraExists) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT COALESCE(MAX(campanha_numero), 0) + 1 AS next_number
         FROM fotografico_plano WHERE obra_id = ? AND origem <> 'LEGADO'"
    );
    $stmt->bind_param('i', $obraId);
    $stmt->execute();
    $nextCampaign = (int) ($stmt->get_result()->fetch_assoc()['next_number'] ?? 1);
    $stmt->close();

    $plannerId = fotografico_colaborador_ativo($conn, FOTOGRAFICO_RESPONSAVEL_PLANO_ID)
        ? FOTOGRAFICO_RESPONSAVEL_PLANO_ID
        : null;
    $status = 'PLANO_A_FAZER';
    $origin = 'AUTOMATICO';
    $stmt = $conn->prepare(
        'INSERT INTO fotografico_plano
            (obra_id, campanha_numero, origem, chave_gatilho, imagem_gatilho_id, status,
             responsavel_plano_id, criado_por, disparado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar criacao do plano: ' . $conn->error);
    }
    $stmt->bind_param(
        'iissisii',
        $obraId,
        $nextCampaign,
        $origin,
        $triggerKey,
        $imagemId,
        $status,
        $plannerId,
        $atorId
    );
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        if ((int) $conn->errno === 1062) {
            $stmt = $conn->prepare('SELECT id FROM fotografico_plano WHERE chave_gatilho = ? LIMIT 1');
            $stmt->bind_param('s', $triggerKey);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ? (int) $row['id'] : null;
        }
        throw new RuntimeException('Falha ao criar plano fotografico: ' . $error);
    }
    $planoId = (int) $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO fotografico_plano_versao (plano_id, numero, status, motivo, criado_por)
         VALUES (?, 1, 'RASCUNHO', 'Criacao automatica pela primeira fachada em TO-DO', ?)"
    );
    $stmt->bind_param('ii', $planoId, $atorId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Falha ao criar a primeira versao: ' . $stmt->error);
    }
    $versaoId = (int) $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO fotografico_plano_imagem (versao_id, imagem_id, decisao, pavimento_referencia, ordem)
         SELECT ?, i.idimagens_cliente_obra, 'PENDENTE', s.nome,
                ROW_NUMBER() OVER (ORDER BY i.idimagens_cliente_obra)
           FROM imagens_cliente_obra i
      LEFT JOIN subtipo_imagem s ON s.id = i.subtipo_id
          WHERE i.obra_id = ?
            AND LOWER(TRIM(COALESCE(i.tipo_imagem, ''))) <> 'planta humanizada'"
    );
    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar imagens do plano: ' . $conn->error);
    }
    $stmt->bind_param('ii', $versaoId, $obraId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Falha ao incluir imagens no plano: ' . $stmt->error);
    }
    $stmt->close();

    $startedAt = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $dueAt = fotografico_next_business_due($conn, $startedAt);
    $startedSql = $startedAt->format('Y-m-d H:i:s');
    $dueSql = $dueAt->format('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "INSERT INTO fotografico_sla
            (plano_id, tipo, started_at, due_at_original, due_at_effective)
         VALUES (?, 'CRIACAO', ?, ?, ?)"
    );
    $stmt->bind_param('isss', $planoId, $startedSql, $dueSql, $dueSql);
    if (!$stmt->execute()) {
        throw new RuntimeException('Falha ao iniciar SLA de criacao: ' . $stmt->error);
    }
    $stmt->close();

    fotografico_evento(
        $conn,
        $planoId,
        'PLANO_CRIADO_AUTOMATICAMENTE',
        null,
        'PLANO_A_FAZER',
        $atorId,
        $origem,
        ['imagem_gatilho_id' => $imagemId, 'obra_id' => $obraId, 'versao_id' => $versaoId]
    );

    if ($plannerId === null) {
        $title = 'Responsavel pelo plano indisponivel';
        $details = 'O colaborador configurado para o planejamento nao esta ativo. Atribua um responsavel.';
        $stmt = $conn->prepare(
            "INSERT INTO fotografico_pendencia
                (plano_id, codigo, titulo, detalhes, status, criado_por)
             VALUES (?, 'ATRIBUICAO_RESPONSAVEL', ?, ?, 'ABERTA', ?)"
        );
        $stmt->bind_param('issi', $planoId, $title, $details, $atorId);
        $stmt->execute();
        $stmt->close();
    } else {
        fotografico_notificar_colaborador(
            $conn,
            $planoId,
            $plannerId,
            'PLANO_CRIADO',
            'Novo plano fotografico',
            'Uma fachada entrou em TO-DO. O plano fotografico foi criado e precisa ser elaborado.'
        );
    }

    return $planoId;
}

function fotografico_open_auto_hold(
    mysqli $conn,
    int $planoId,
    ?int $atorId,
    string $origem
): void {
    $stmt = $conn->prepare(
        "SELECT status, responsavel_plano_id FROM fotografico_plano WHERE id = ? FOR UPDATE"
    );
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$plan || in_array($plan['status'], ['CONCLUIDO', 'CANCELADO'], true)) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT id FROM fotografico_hold
         WHERE plano_id = ? AND codigo = 'FACHADA_EM_HOLD' AND encerrado_em IS NULL LIMIT 1"
    );
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $open = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    if ($open) {
        return;
    }

    $previous = (string) $plan['status'];
    $stmt = $conn->prepare(
        "INSERT INTO fotografico_hold
            (plano_id, codigo, detalhes, origem, responsavel_id, aberto_por, status_retorno, afeta_sla)
         VALUES (?, 'FACHADA_EM_HOLD', 'A imagem-gatilho entrou em HOLD.', 'AUTOMATICO', ?, ?, ?, 1)"
    );
    $responsavelId = $plan['responsavel_plano_id'] !== null ? (int) $plan['responsavel_plano_id'] : null;
    $stmt->bind_param('iiis', $planoId, $responsavelId, $atorId, $previous);
    if (!$stmt->execute()) {
        throw new RuntimeException('Falha ao abrir HOLD fotografico: ' . $stmt->error);
    }
    $holdId = (int) $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT IGNORE INTO fotografico_sla_pausa (sla_id, hold_id, iniciado_em)
         SELECT id, ?, NOW() FROM fotografico_sla
          WHERE plano_id = ? AND completed_at IS NULL AND resultado = 'EM_ANDAMENTO'"
    );
    $stmt->bind_param('ii', $holdId, $planoId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "UPDATE fotografico_plano
            SET status_antes_hold = ?, status = 'HOLD', lock_version = lock_version + 1
          WHERE id = ?"
    );
    $stmt->bind_param('si', $previous, $planoId);
    $stmt->execute();
    $stmt->close();

    fotografico_evento($conn, $planoId, 'HOLD_ABERTO', $previous, 'HOLD', $atorId, $origem, [
        'hold_id' => $holdId,
        'codigo' => 'FACHADA_EM_HOLD',
    ]);
}

function fotografico_close_auto_hold(
    mysqli $conn,
    int $planoId,
    ?int $atorId,
    string $origem
): void {
    $stmt = $conn->prepare(
        "SELECT id, status_retorno FROM fotografico_hold
         WHERE plano_id = ? AND codigo = 'FACHADA_EM_HOLD' AND encerrado_em IS NULL
         ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $hold = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$hold) {
        return;
    }

    $holdId = (int) $hold['id'];
    $returnStatus = (string) $hold['status_retorno'];
    $stmt = $conn->prepare(
        'UPDATE fotografico_hold SET encerrado_por = ?, encerrado_em = NOW() WHERE id = ?'
    );
    $stmt->bind_param('ii', $atorId, $holdId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "UPDATE fotografico_sla_pausa sp
         JOIN fotografico_sla s ON s.id = sp.sla_id
            SET sp.encerrado_em = NOW(),
                sp.duracao_segundos = TIMESTAMPDIFF(SECOND, sp.iniciado_em, NOW()),
                s.total_paused_seconds = s.total_paused_seconds + TIMESTAMPDIFF(SECOND, sp.iniciado_em, NOW()),
                s.due_at_effective = DATE_ADD(s.due_at_effective, INTERVAL TIMESTAMPDIFF(SECOND, sp.iniciado_em, NOW()) SECOND)
          WHERE sp.hold_id = ? AND sp.encerrado_em IS NULL"
    );
    $stmt->bind_param('i', $holdId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'UPDATE fotografico_plano
            SET status = ?, status_antes_hold = NULL, lock_version = lock_version + 1
          WHERE id = ? AND status = \'HOLD\''
    );
    $stmt->bind_param('si', $returnStatus, $planoId);
    $stmt->execute();
    $stmt->close();

    fotografico_evento($conn, $planoId, 'HOLD_ENCERRADO', 'HOLD', $returnStatus, $atorId, $origem, [
        'hold_id' => $holdId,
        'codigo' => 'FACHADA_EM_HOLD',
    ]);
}

function fotografico_open_revision_issue(
    mysqli $conn,
    int $planoId,
    ?int $atorId,
    int $newSubstatus,
    string $origem
): void {
    $stmt = $conn->prepare(
        "SELECT 1 FROM fotografico_pendencia
         WHERE plano_id = ? AND codigo = 'FACHADA_REVISAO' AND status = 'ABERTA' LIMIT 1"
    );
    $stmt->bind_param('i', $planoId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$exists) {
        $title = 'Revisar plano apos alteracao da fachada';
        $details = 'A imagem-gatilho saiu de TO-DO. Confirme se o plano precisa de uma nova versao.';
        $stmt = $conn->prepare(
            "INSERT INTO fotografico_pendencia
                (plano_id, codigo, titulo, detalhes, status, responsavel_id, criado_por)
             SELECT id, 'FACHADA_REVISAO', ?, ?, 'ABERTA', responsavel_plano_id, ?
               FROM fotografico_plano WHERE id = ?"
        );
        $stmt->bind_param('ssii', $title, $details, $atorId, $planoId);
        $stmt->execute();
        $stmt->close();
    }
    fotografico_evento($conn, $planoId, 'FACHADA_SAIU_TODO', null, null, $atorId, $origem, [
        'substatus_novo' => $newSubstatus,
    ]);
}

function fotografico_sync_imagem_substatus(
    mysqli $conn,
    int $imagemId,
    ?int $substatusAnterior,
    int $substatusNovo,
    ?int $atorId,
    string $origem
): ?int {
    if (!fotografico_schema_ready($conn)) {
        return null;
    }

    $planId = null;
    if (
        $substatusNovo === FOTOGRAFICO_TODO_SUBSTATUS_ID
        && $substatusAnterior !== FOTOGRAFICO_TODO_SUBSTATUS_ID
    ) {
        $planId = fotografico_criar_plano_automatico($conn, $imagemId, $atorId, $origem);
    }

    $stmt = $conn->prepare(
        "SELECT id FROM fotografico_plano
         WHERE imagem_gatilho_id = ? AND status NOT IN ('CONCLUIDO','CANCELADO')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('i', $imagemId);
    $stmt->execute();
    $triggerPlan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$triggerPlan) {
        return $planId;
    }
    $planId = (int) $triggerPlan['id'];

    if ($substatusNovo === FOTOGRAFICO_HOLD_SUBSTATUS_ID) {
        fotografico_open_auto_hold($conn, $planId, $atorId, $origem);
    } elseif (
        $substatusNovo === FOTOGRAFICO_TODO_SUBSTATUS_ID
        && $substatusAnterior === FOTOGRAFICO_HOLD_SUBSTATUS_ID
    ) {
        fotografico_close_auto_hold($conn, $planId, $atorId, $origem);
    } elseif (
        $substatusAnterior === FOTOGRAFICO_TODO_SUBSTATUS_ID
        && $substatusNovo !== FOTOGRAFICO_TODO_SUBSTATUS_ID
    ) {
        fotografico_open_revision_issue($conn, $planId, $atorId, $substatusNovo, $origem);
    }

    return $planId;
}

function fotografico_complete_sla(mysqli $conn, int $planoId, string $tipo): void
{
    $stmt = $conn->prepare(
        "UPDATE fotografico_sla
            SET completed_at = NOW(),
                resultado = CASE WHEN NOW() <= due_at_effective THEN 'NO_PRAZO' ELSE 'ATRASADO' END
          WHERE plano_id = ? AND tipo = ? AND completed_at IS NULL"
    );
    $stmt->bind_param('is', $planoId, $tipo);
    $stmt->execute();
    $stmt->close();
}

function fotografico_start_execution_sla(mysqli $conn, int $planoId, DateTimeImmutable $publishedAt): void
{
    $startedSql = $publishedAt->format('Y-m-d H:i:s');
    $dueSql = fotografico_execution_due($publishedAt)->format('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "INSERT INTO fotografico_sla
            (plano_id, tipo, started_at, due_at_original, due_at_effective)
         VALUES (?, 'EXECUCAO', ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            started_at = VALUES(started_at),
            due_at_original = VALUES(due_at_original),
            due_at_effective = VALUES(due_at_effective),
            completed_at = NULL,
            total_paused_seconds = 0,
            resultado = 'EM_ANDAMENTO'"
    );
    $stmt->bind_param('isss', $planoId, $startedSql, $dueSql, $dueSql);
    if (!$stmt->execute()) {
        throw new RuntimeException('Falha ao iniciar SLA de execucao: ' . $stmt->error);
    }
    $stmt->close();
}
