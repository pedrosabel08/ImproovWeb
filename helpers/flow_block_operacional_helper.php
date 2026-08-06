<?php

/**
 * Liga uma Issue do Flow Block a uma pendencia que nao e tarefa de imagem.
 * A tabela e propositalmente polimorfica: os modulos continuam donos do
 * proprio estado e apenas os dois que possuem workflow proprio (Fotografico
 * e Pre-Alteracao) recebem uma transicao de HOLD.
 */

if (!function_exists('flow_block_operacional_ensure_schema')) {
    function flow_block_operacional_ensure_schema(mysqli $conn): void
    {
        static $ensured = false;
        if ($ensured) return;

        $column = $conn->query("SHOW COLUMNS FROM flow_issue LIKE 'funcao_imagem_id'");
        $row = $column ? $column->fetch_assoc() : null;
        if ($column instanceof mysqli_result) $column->close();
        if ($row && strtoupper((string) ($row['Null'] ?? 'NO')) !== 'YES') {
            $conn->query('ALTER TABLE flow_issue MODIFY funcao_imagem_id INT NULL');
        }

        $conn->query("CREATE TABLE IF NOT EXISTS flow_issue_operacional (
            issue_id INT NOT NULL,
            source_type VARCHAR(40) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            source_title VARCHAR(255) NOT NULL,
            source_url VARCHAR(500) NULL,
            obra_id INT NULL,
            obra_nome VARCHAR(255) NULL,
            source_responsavel_id INT NULL,
            source_status_before VARCHAR(50) NULL,
            source_native_hold_id BIGINT UNSIGNED NULL,
            criado_por INT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            liberado_por INT NULL,
            liberado_em DATETIME NULL,
            PRIMARY KEY (issue_id),
            KEY idx_flow_issue_operacional_source (source_type, source_id, liberado_em),
            KEY idx_flow_issue_operacional_obra (obra_id),
            KEY idx_flow_issue_operacional_responsavel (source_responsavel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Pre-Alteracao nao tinha HOLD. Guardamos o estado para que a
        // confirmacao da Issue possa devolver o lote ao ponto exato do fluxo.
        $table = $conn->query("SHOW TABLES LIKE 'pre_alt_lote'");
        $hasPreAlt = $table && $table->num_rows > 0;
        if ($table instanceof mysqli_result) $table->close();
        if ($hasPreAlt) {
            $before = $conn->query("SHOW COLUMNS FROM pre_alt_lote LIKE 'status_antes_hold'");
            $hasBefore = $before && $before->num_rows > 0;
            if ($before instanceof mysqli_result) $before->close();
            if (!$hasBefore) {
                $conn->query("ALTER TABLE pre_alt_lote ADD COLUMN status_antes_hold VARCHAR(40) NULL AFTER status");
            }
            $conn->query("ALTER TABLE pre_alt_lote MODIFY status ENUM('EM_TRIAGEM','AGUARDANDO_CLIENTE','PRONTO_PLANEJAMENTO','PLANEJADO','CANCELADO','HOLD') NOT NULL DEFAULT 'EM_TRIAGEM'");
        }

        $ensured = true;
    }
}

if (!function_exists('flow_block_operacional_types')) {
    function flow_block_operacional_types(): array
    {
        return ['projeto', 'imagem', 'fotografico', 'fotografico_plano', 'pre_alteracao', 'links'];
    }
}

if (!function_exists('flow_block_operacional_is_active_status')) {
    function flow_block_operacional_is_active_status(?string $status): bool
    {
        return in_array((string) $status, ['ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA'], true)
            || ((string) $status === 'RESOLVIDA');
    }
}

if (!function_exists('flow_block_operacional_source_context')) {
    function flow_block_operacional_source_context(mysqli $conn, string $sourceType, int $sourceId): ?array
    {
        $sourceType = trim($sourceType);
        if (!in_array($sourceType, flow_block_operacional_types(), true) || $sourceId <= 0) return null;

        $sql = null;
        if ($sourceType === 'projeto' || $sourceType === 'imagem') {
            $sql = "SELECT co.id, co.module_key, co.obra_id, co.responsavel_id,
                           COALESCE(o.nomenclatura, o.nome_obra, CONCAT('Obra ', co.obra_id)) obra_nome,
                           CASE WHEN co.module_key='projeto' THEN CONCAT('Projeto OK - ', COALESCE(o.nomenclatura,o.nome_obra)) ELSE ico.imagem_nome END titulo
                    FROM checklist_operacional co
                    LEFT JOIN obra o ON o.idobra=co.obra_id
                    LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=co.entity_id
                    WHERE co.id=? AND co.module_key=? AND co.status='aberto' LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) return null;
            $stmt->bind_param('is', $sourceId, $sourceType);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if (!$row) return null;
            return [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'title' => (string) ($row['titulo'] ?: ($sourceType === 'projeto' ? 'Projeto OK' : 'Imagem')),
                'url' => $sourceType === 'projeto' ? 'Dashboard/obra.php?obra_id=' . (int) $row['obra_id'] : 'PaginaPrincipal/',
                'obra_id' => (int) ($row['obra_id'] ?? 0),
                'obra_nome' => (string) ($row['obra_nome'] ?? ''),
                'responsavel_id' => (int) ($row['responsavel_id'] ?? 0) ?: null,
            ];
        }

        if ($sourceType === 'links') {
            $stmt = $conn->prepare("SELECT p.id,p.obra_id,p.responsavel_id,p.tipo_link,COALESCE(o.nomenclatura,o.nome_obra) obra_nome
                                   FROM pendencias_links_obra p JOIN obra o ON o.idobra=p.obra_id
                                   WHERE p.id=? AND p.status='aberta' LIMIT 1");
            if (!$stmt) return null;
            $stmt->bind_param('i', $sourceId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if (!$row) return null;
            return ['source_type' => 'links', 'source_id' => $sourceId, 'title' => 'Link ' . (string) $row['tipo_link'] . ' - ' . (string) $row['obra_nome'], 'url' => 'Dashboard/obra.php?obra_id=' . (int)$row['obra_id'], 'obra_id' => (int)$row['obra_id'], 'obra_nome' => (string)$row['obra_nome'], 'responsavel_id' => (int)($row['responsavel_id'] ?? 0) ?: null];
        }

        if ($sourceType === 'fotografico') {
            $stmt = $conn->prepare("SELECT pe.id,pe.plano_id,pe.responsavel_id,pe.responsavel_cobranca_id,pe.titulo,p.status plano_status,p.obra_id,COALESCE(o.nomenclatura,o.nome_obra) obra_nome
                                  FROM fotografico_pendencia pe JOIN fotografico_plano p ON p.id=pe.plano_id JOIN obra o ON o.idobra=p.obra_id
                                  WHERE pe.id=? AND pe.status='ABERTA' LIMIT 1");
            if (!$stmt) return null;
            $stmt->bind_param('i', $sourceId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if (!$row) return null;
            return ['source_type' => 'fotografico', 'source_id' => $sourceId, 'title' => (string)($row['titulo'] ?: 'Pendencia fotografica'), 'url' => 'Fotografico/index.php?plano_id=' . (int)$row['plano_id'], 'obra_id' => (int)$row['obra_id'], 'obra_nome' => (string)$row['obra_nome'], 'responsavel_id' => (int)($row['responsavel_id'] ?: $row['responsavel_cobranca_id']) ?: null, 'native_id' => (int)$row['plano_id'], 'native_status' => (string)$row['plano_status']];
        }

        if ($sourceType === 'fotografico_plano') {
            $stmt = $conn->prepare("SELECT p.id,p.status,p.obra_id,COALESCE(p.responsavel_execucao_id,p.responsavel_plano_id) responsavel_id,COALESCE(o.nomenclatura,o.nome_obra) obra_nome
                                  FROM fotografico_plano p JOIN obra o ON o.idobra=p.obra_id WHERE p.id=? LIMIT 1");
            if (!$stmt) return null;
            $stmt->bind_param('i', $sourceId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if (!$row) return null;
            return ['source_type' => 'fotografico_plano', 'source_id' => $sourceId, 'title' => 'Plano fotográfico - ' . (string)$row['obra_nome'], 'url' => 'Fotografico/index.php?plano_id=' . (int)$row['id'], 'obra_id' => (int)$row['obra_id'], 'obra_nome' => (string)$row['obra_nome'], 'responsavel_id' => (int)($row['responsavel_id'] ?? 0) ?: null, 'native_id' => (int)$row['id'], 'native_status' => (string)$row['status']];
        }

        $stmt = $conn->prepare("SELECT l.id,l.obra_id,COALESCE(l.responsavel_id,l.created_by) responsavel_id,l.status,COALESCE(o.nomenclatura,o.nome_obra) obra_nome
                              FROM pre_alt_lote l JOIN obra o ON o.idobra=l.obra_id
                              WHERE l.id=? AND l.status NOT IN ('PLANEJADO','CANCELADO') LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $sourceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$row) return null;
        return ['source_type' => 'pre_alteracao', 'source_id' => $sourceId, 'title' => 'Pré-Alteração - ' . (string)$row['obra_nome'], 'url' => 'PreAlteracao/index.php', 'obra_id' => (int)$row['obra_id'], 'obra_nome' => (string)$row['obra_nome'], 'responsavel_id' => (int)($row['responsavel_id'] ?? 0) ?: null, 'native_id' => $sourceId, 'native_status' => (string)$row['status']];
    }
}

if (!function_exists('flow_block_operacional_can_access_source')) {
    function flow_block_operacional_can_access_source(mysqli $conn, array $source, int $actorId): bool
    {
        if ($actorId <= 0) return false;
        // Os cinco modulos ja controlam quem consegue visualizar suas
        // pendencias. A regra de negocio permite que qualquer visualizador
        // autenticado registre o impedimento, nao apenas o responsavel.
        return true;
    }
}

if (!function_exists('flow_block_operacional_find_active_issue')) {
    function flow_block_operacional_find_active_issue(mysqli $conn, string $sourceType, int $sourceId): ?array
    {
        flow_block_operacional_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT i.id,i.codigo,i.status,i.confirmada_em
                              FROM flow_issue_operacional op JOIN flow_issue i ON i.id=op.issue_id
                              WHERE op.source_type=? AND op.source_id=? AND op.liberado_em IS NULL
                                AND (i.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (i.status='RESOLVIDA' AND i.confirmada_em IS NULL))
                              ORDER BY i.id DESC LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('si', $sourceType, $sourceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('flow_block_operacional_apply_hold')) {
    function flow_block_operacional_apply_hold(mysqli $conn, array $source, string $holdCode, string $details, int $actorId): array
    {
        $type = (string)$source['source_type'];
        $before = (string)($source['native_status'] ?? '');
        $nativeId = null;
        if (in_array($type, ['fotografico', 'fotografico_plano'], true)) {
            $planId = (int)($source['native_id'] ?? 0);
            if ($planId <= 0 || in_array($before, ['HOLD', 'CONCLUIDO', 'CANCELADO'], true)) throw new RuntimeException('O plano fotografico nao pode receber HOLD neste estado.');
            $allowed = ['CLIMA', 'INFORMACAO_INCOMPLETA', 'ALTERACAO_PLANO', 'REAGENDAMENTO'];
            if (!in_array($holdCode, $allowed, true)) $holdCode = 'INFORMACAO_INCOMPLETA';
            $stmt = $conn->prepare("INSERT INTO fotografico_hold (plano_id,codigo,detalhes,origem,responsavel_id,aberto_por,status_retorno,afeta_sla) VALUES (?, ?, ?, 'MANUAL', ?, ?, ?, 1)");
            $responsavel = (int)($source['responsavel_id'] ?? 0) ?: null;
            $stmt->bind_param('issiis', $planId, $holdCode, $details, $responsavel, $actorId, $before);
            $stmt->execute();
            $nativeId = (int)$stmt->insert_id;
            $stmt->close();
            $stmt = $conn->prepare("INSERT IGNORE INTO fotografico_sla_pausa (sla_id,hold_id,iniciado_em) SELECT id,?,NOW() FROM fotografico_sla WHERE plano_id=? AND completed_at IS NULL AND resultado='EM_ANDAMENTO'");
            if ($stmt) {
                $stmt->bind_param('ii', $nativeId, $planId);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $conn->prepare("UPDATE fotografico_plano SET status='HOLD',status_antes_hold=?,lock_version=lock_version+1 WHERE id=?");
            $stmt->bind_param('si', $before, $planId);
            $stmt->execute();
            $stmt->close();
        } elseif ($type === 'pre_alteracao') {
            if ($before === 'HOLD') throw new RuntimeException('Este lote ja esta em HOLD.');
            $stmt = $conn->prepare("UPDATE pre_alt_lote SET status='HOLD',status_antes_hold=? WHERE id=?");
            $id = (int)$source['source_id'];
            $stmt->bind_param('si', $before, $id);
            $stmt->execute();
            $stmt->close();
            $nativeId = $id;
        }
        return ['status_before' => $before ?: null, 'native_hold_id' => $nativeId];
    }
}

if (!function_exists('flow_block_operacional_release_hold')) {
    function flow_block_operacional_release_hold(mysqli $conn, int $issueId, int $actorId): void
    {
        flow_block_operacional_ensure_schema($conn);
        $stmt = $conn->prepare('SELECT * FROM flow_issue_operacional WHERE issue_id=? AND liberado_em IS NULL FOR UPDATE');
        if (!$stmt) return;
        $stmt->bind_param('i', $issueId);
        $stmt->execute();
        $op = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$op) return;
        if (in_array($op['source_type'], ['fotografico', 'fotografico_plano'], true) && (int)$op['source_native_hold_id'] > 0) {
            $holdId = (int)$op['source_native_hold_id'];
            $plan = (int)$op['source_id'];
            $lookup = $conn->prepare('SELECT plano_id,status_retorno FROM fotografico_hold WHERE id=? AND encerrado_em IS NULL LIMIT 1 FOR UPDATE');
            if ($lookup) {
                $lookup->bind_param('i', $holdId);
                $lookup->execute();
                $hold = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if ($hold) {
                    $planId = (int)$hold['plano_id'];
                    $return = (string)$hold['status_retorno'];
                    $close = $conn->prepare('UPDATE fotografico_hold SET encerrado_por=?,encerrado_em=NOW() WHERE id=?');
                    $close->bind_param('ii', $actorId, $holdId);
                    $close->execute();
                    $close->close();
                    $pause = $conn->prepare("UPDATE fotografico_sla_pausa sp JOIN fotografico_sla s ON s.id=sp.sla_id SET sp.encerrado_em=NOW(),sp.duracao_segundos=TIMESTAMPDIFF(SECOND,sp.iniciado_em,NOW()),s.total_paused_seconds=s.total_paused_seconds+TIMESTAMPDIFF(SECOND,sp.iniciado_em,NOW()),s.due_at_effective=DATE_ADD(s.due_at_effective,INTERVAL TIMESTAMPDIFF(SECOND,sp.iniciado_em,NOW()) SECOND) WHERE sp.hold_id=? AND sp.encerrado_em IS NULL");
                    if ($pause) {
                        $pause->bind_param('i', $holdId);
                        $pause->execute();
                        $pause->close();
                    }
                    $restore = $conn->prepare("UPDATE fotografico_plano SET status=?,status_antes_hold=NULL,lock_version=lock_version+1 WHERE id=? AND status='HOLD'");
                    $restore->bind_param('si', $return, $planId);
                    $restore->execute();
                    $restore->close();
                    // A confirmacao da Issue e a unica liberacao valida do
                    // HOLD operacional. Registramos isso no historico proprio
                    // do plano, para a aba Historico explicar o retorno.
                    $event = $conn->prepare('INSERT INTO fotografico_evento (plano_id,tipo,status_anterior,status_novo,ator_id,origem,dados_json) VALUES (?,?,?,?,?,?,?)');
                    if (!$event) throw new RuntimeException('Falha ao registrar o encerramento do HOLD fotografico.');
                    $eventType = 'HOLD_ENCERRADO_POR_ISSUE';
                    $previous = 'HOLD';
                    $origin = 'FlowBlock/api.php';
                    $eventData = json_encode(['hold_id' => $holdId, 'issue_id' => $issueId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $event->bind_param('isssiss', $planId, $eventType, $previous, $return, $actorId, $origin, $eventData);
                    $event->execute();
                    $event->close();
                }
            }
        } elseif ($op['source_type'] === 'pre_alteracao') {
            $id = (int)$op['source_id'];
            $return = (string)($op['source_status_before'] ?: 'EM_TRIAGEM');
            $restore = $conn->prepare("UPDATE pre_alt_lote SET status=?,status_antes_hold=NULL WHERE id=? AND status='HOLD'");
            $restore->bind_param('si', $return, $id);
            $restore->execute();
            $restore->close();
        }
        $update = $conn->prepare('UPDATE flow_issue_operacional SET liberado_por=?,liberado_em=NOW() WHERE issue_id=?');
        $update->bind_param('ii', $actorId, $issueId);
        $update->execute();
        $update->close();
    }
}
