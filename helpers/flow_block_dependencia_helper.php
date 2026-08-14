<?php

/** Vínculo estruturado entre uma Issue e a dependência que ela declarou. */

if (!function_exists('flow_block_dependencia_ensure_schema')) {
    function flow_block_dependencia_ensure_schema(mysqli $conn): void
    {
        static $ensured = false;
        if ($ensured) return;
        $conn->query("CREATE TABLE IF NOT EXISTS flow_issue_dependencia (
            issue_id INT NOT NULL,
            requirement_code VARCHAR(100) NOT NULL,
            tarefa_bloqueada_id INT NOT NULL,
            predecessora_funcao_imagem_id INT NOT NULL,
            approval_cycle_key VARCHAR(120) NOT NULL,
            aprovacao_status VARCHAR(60) NOT NULL,
            aprovacao_historico_id INT NULL,
            aprovadores_json JSON NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            encerrada_em DATETIME NULL,
            PRIMARY KEY (issue_id),
            UNIQUE KEY uq_flow_issue_dependencia_ciclo (tarefa_bloqueada_id, requirement_code, predecessora_funcao_imagem_id, approval_cycle_key),
            KEY idx_flow_issue_dependencia_predecessora (predecessora_funcao_imagem_id, encerrada_em),
            KEY idx_flow_issue_dependencia_bloqueada (tarefa_bloqueada_id, encerrada_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    }
}

if (!function_exists('flow_block_dependencia_find_active')) {
    function flow_block_dependencia_find_active(mysqli $conn, int $taskId, int $predecessorTaskId, string $cycleKey): ?array
    {
        flow_block_dependencia_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT i.id, i.codigo, i.status, i.confirmada_em
            FROM flow_issue_dependencia d JOIN flow_issue i ON i.id=d.issue_id
            WHERE d.tarefa_bloqueada_id=? AND d.predecessora_funcao_imagem_id=?
              AND d.approval_cycle_key=? AND d.encerrada_em IS NULL
              AND (i.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (i.status='RESOLVIDA' AND i.confirmada_em IS NULL))
            ORDER BY i.id DESC LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('iis', $taskId, $predecessorTaskId, $cycleKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('flow_block_dependencia_encerrar_por_aprovacao')) {
    function flow_block_dependencia_encerrar_por_aprovacao(mysqli $conn, int $predecessorTaskId, int $actorId, int $historicoId, string $status): array
    {
        if (!in_array($status, ['Aprovado', 'Aprovado com ajustes'], true)) return [];
        $flowIssueTable = $conn->query("SHOW TABLES LIKE 'flow_issue'");
        $hasFlowIssue = $flowIssueTable && $flowIssueTable->num_rows > 0;
        if ($flowIssueTable instanceof mysqli_result) $flowIssueTable->close();
        if (!$hasFlowIssue) return [];
        flow_block_dependencia_ensure_schema($conn);
        $stmt = $conn->prepare("SELECT i.id, i.codigo, i.funcao_imagem_id
             FROM flow_issue_dependencia d JOIN flow_issue i ON i.id=d.issue_id
            WHERE d.predecessora_funcao_imagem_id=? AND d.encerrada_em IS NULL
              AND (i.status IN ('ABERTA','AGUARDANDO_ACAO','PAUSADA') OR (i.status='RESOLVIDA' AND i.confirmada_em IS NULL))
            FOR UPDATE");
        if (!$stmt) return [];
        $stmt->bind_param('i', $predecessorTaskId);
        $stmt->execute();
        $issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($issues as $issue) {
            $issueId = (int) $issue['id'];
            $message = "Dependência liberada automaticamente: a etapa anterior foi {$status}.";
            $update = $conn->prepare("UPDATE flow_issue SET status='RESOLVIDA', resolvido_por_colaborador_id=?, resolvido_em=NOW(), encerramento_observacao=?, confirmada_por_colaborador_id=?, confirmada_em=NOW(), confirmacao_observacao='Resolução automática por aprovação da dependência.', proxima_cobranca_em=NULL WHERE id=?");
            $update->bind_param('isii', $actorId, $message, $actorId, $issueId);
            $update->execute();
            $update->close();
            $close = $conn->prepare('UPDATE flow_issue_dependencia SET aprovacao_historico_id=?, aprovacao_status=?, encerrada_em=NOW() WHERE issue_id=?');
            $close->bind_param('isi', $historicoId, $status, $issueId);
            $close->execute();
            $close->close();
            $cycle = $conn->prepare("UPDATE flow_issue_ciclo SET finalizado_em=NOW(), status_final='RESOLVIDA' WHERE issue_id=? AND finalizado_em IS NULL ORDER BY id DESC LIMIT 1");
            $cycle->bind_param('i', $issueId);
            $cycle->execute();
            $cycle->close();
            $meta = json_encode(['origem' => 'aprovacao_dependencia', 'predecessora_funcao_imagem_id' => $predecessorTaskId, 'historico_aprovacao_id' => $historicoId, 'status_aprovacao' => $status], JSON_UNESCAPED_UNICODE);
            $activity = $conn->prepare("INSERT INTO flow_issue_atividade (issue_id,tipo,conteudo,metadados,criado_por_colaborador_id) VALUES (?, 'RESOLVIDA_AUTOMATICAMENTE', ?, ?, ?)");
            $activity->bind_param('issi', $issueId, $message, $meta, $actorId);
            $activity->execute();
            $activity->close();
        }
        return $issues;
    }
}
