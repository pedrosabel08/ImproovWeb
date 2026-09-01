<?php

/** Regras compartilhadas de elegibilidade do Flow Review para tarefas de imagem. */

if (!function_exists('flow_review_active_approval_block_statuses')) {
    function flow_review_active_approval_block_statuses(): array
    {
        return ['ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA'];
    }
}

if (!function_exists('flow_review_hold_approval_block_sql')) {
    function flow_review_hold_approval_block_sql(string $taskAlias = 'f'): string
    {
        return "EXISTS (
            SELECT 1
            FROM flow_issue fr_issue
            INNER JOIN flow_issue_tipo fr_type ON fr_type.id = fr_issue.tipo_id
            WHERE fr_issue.funcao_imagem_id = {$taskAlias}.idfuncao_imagem
              AND fr_issue.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
              AND fr_type.codigo = 'APROVACAO_PENDENTE'
        )";
    }
}

if (!function_exists('flow_review_is_eligible')) {
    /** @param array<int, array{tipo_codigo?: string, status?: string}> $issues */
    function flow_review_is_eligible(bool $normallyEligible, string $taskStatus, array $issues = []): bool
    {
        if ($normallyEligible) return true;
        if ($taskStatus !== 'HOLD') return false;

        foreach ($issues as $issue) {
            if (($issue['tipo_codigo'] ?? '') === 'APROVACAO_PENDENTE'
                && in_array($issue['status'] ?? '', flow_review_active_approval_block_statuses(), true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('flow_review_enrich_hold_approval_blocks')) {
    /** @param array<int, array<string, mixed>> $tasks */
    function flow_review_enrich_hold_approval_blocks(mysqli $conn, array &$tasks): void
    {
        $taskIds = [];
        foreach ($tasks as $task) {
            if (($task['tipo_tarefa'] ?? 'imagem') === 'imagem' && ($task['status'] ?? '') === 'HOLD') {
                $taskId = (int) ($task['idfuncao_imagem'] ?? 0);
                if ($taskId > 0) $taskIds[$taskId] = $taskId;
            }
        }
        if (!$taskIds) return;

        $ids = array_values($taskIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT fr_issue.id, fr_issue.funcao_imagem_id, fr_issue.descricao,
                       fr_issue.criado_em, fr_issue.status, fr_type.codigo AS tipo_codigo,
                       fr_type.nome AS tipo_nome, creator.nome_colaborador AS criador_nome
                FROM flow_issue fr_issue
                INNER JOIN flow_issue_tipo fr_type ON fr_type.id = fr_issue.tipo_id
                LEFT JOIN colaborador creator ON creator.idcolaborador = fr_issue.criado_por_colaborador_id
                WHERE fr_issue.funcao_imagem_id IN ({$placeholders})
                  AND fr_issue.status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
                  AND fr_type.codigo = 'APROVACAO_PENDENTE'
                ORDER BY fr_issue.funcao_imagem_id ASC, fr_issue.atualizado_em DESC, fr_issue.id DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return;
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $blocksByTask = [];
        while ($block = $result->fetch_assoc()) {
            $taskId = (int) $block['funcao_imagem_id'];
            if (!isset($blocksByTask[$taskId])) $blocksByTask[$taskId] = $block;
        }
        $stmt->close();

        foreach ($tasks as &$task) {
            $taskId = (int) ($task['idfuncao_imagem'] ?? 0);
            if (($task['status'] ?? '') !== 'HOLD' || !isset($blocksByTask[$taskId])) continue;
            $block = $blocksByTask[$taskId];
            $task['flow_review_hold_approval'] = true;
            $task['flow_review_flow_block'] = [
                'id' => (int) $block['id'],
                'tipo_codigo' => $block['tipo_codigo'],
                'tipo_nome' => $block['tipo_nome'],
                'descricao' => $block['descricao'],
                'criador_nome' => $block['criador_nome'],
                'criado_em' => $block['criado_em'],
                'status' => $block['status'],
            ];
        }
        unset($task);
    }
}
