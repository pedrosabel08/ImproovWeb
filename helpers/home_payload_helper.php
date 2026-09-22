<?php

/**
 * Composição enxuta da Home.
 *
 * Esta camada não contém SQL próprio. Ela transforma contratos canônicos e,
 * no modo gestor, delega as consultas aos helpers existentes da Overview V1.
 */

require_once __DIR__ . '/dashboard_colaborador_helper.php';
require_once __DIR__ . '/overview_v1_helper.php';

function home_payload_severity(string $severity): string
{
    $severity = strtolower(trim($severity));
    if ($severity === 'critical') {
        return 'critical';
    }
    if (in_array($severity, ['high', 'warning'], true)) {
        return 'warning';
    }
    return 'info';
}

function home_payload_task_risk_level(array $task): string
{
    if (($task['flow_block']['cobranca_atrasada'] ?? false) === true) {
        return 'critical';
    }
    if (!empty($task['flow_block']) || ($task['status_temporal'] ?? '') === 'ATRASADO') {
        return 'critical';
    }
    if (!empty($task['bloqueada']) || in_array((string) ($task['status_temporal'] ?? ''), ['PRAZO_HOJE', 'PRAZO_PROXIMO'], true)) {
        return 'warning';
    }
    return 'info';
}

function home_payload_task_cta(int $taskId, string $label = 'Continuar tarefa'): array
{
    return [
        'label' => $label,
        'type' => 'open_task',
        'target' => 'inicio.php?focus_task=' . $taskId,
        'entity_id' => $taskId,
    ];
}

function home_payload_task_preview_url(array $task): ?string
{
    $source = trim((string) ($task['ultima_imagem'] ?? ''));
    if ($source === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $source)) {
        return $source;
    }
    return '../../thumb.php?path=' . rawurlencode($source) . '&w=360&q=70';
}

function home_payload_task(array $task, string $ctaLabel = 'Continuar tarefa'): array
{
    $taskId = (int) ($task['id'] ?? 0);
    $result = [
        'task_id' => $taskId,
        'obra' => [
            'id' => (int) ($task['obra_id'] ?? 0),
            'nome' => (string) ($task['obra'] ?? ''),
        ],
        'imagem' => [
            'id' => (int) ($task['imagem_id'] ?? 0),
            'nome' => (string) ($task['imagem'] ?? ''),
        ],
        'funcao' => [
            'id' => (int) ($task['funcao_id'] ?? 0),
            'nome' => (string) ($task['funcao'] ?? ''),
        ],
        'status' => (string) ($task['status'] ?? ''),
        'substatus' => (string) ($task['substatus'] ?? ''),
        'deadline' => $task['prazo'] ?? null,
        'risk_level' => home_payload_task_risk_level($task),
        'priority' => (int) ($task['prioridade_manual'] ?? 3),
        'queue_position' => isset($task['fila_posicao']) ? (int) $task['fila_posicao'] : null,
        'cta' => home_payload_task_cta($taskId, $ctaLabel),
    ];

    $deadlineSource = (string) ($task['prazo_origem'] ?? '');
    if ($deadlineSource !== '' && $deadlineSource !== 'indisponivel') {
        $result['deadline_source'] = $deadlineSource;
    }
    if (($task['status_temporal'] ?? '') === 'ATRASADO' && isset($task['dias_prazo'])) {
        $result['days_overdue'] = max(0, (int) $task['dias_prazo']);
    }
    $previewUrl = home_payload_task_preview_url($task);
    if ($previewUrl !== null) {
        $result['preview_url'] = $previewUrl;
    }

    return $result;
}

function home_payload_normalized_tasks(array $payloadKanban): array
{
    $tasks = [];
    $originals = [];
    foreach ((array) ($payloadKanban['funcoes'] ?? []) as $original) {
        $task = dashboard_colaborador_normalizar_tarefa((array) $original);
        $taskId = (int) ($task['id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }
        $tasks[$taskId] = $task;
        $originals[$taskId] = (array) $original;
    }
    return ['tasks' => $tasks, 'originals' => $originals];
}

function home_payload_pending_cta(array $item): array
{
    $sourceType = (string) ($item['type'] ?? 'pending');
    $entityId = (int) ($item['entity_id'] ?? 0);
    $projectId = (int) ($item['project_id'] ?? 0);
    $target = trim((string) (($item['action']['url'] ?? '')));
    $label = 'Ver pendência';
    $type = 'open_pending';

    if ($sourceType === 'flow_review') {
        $label = 'Revisar';
        $type = 'flow_review';
    } elseif ($sourceType === 'render') {
        $label = 'Revisar render';
        $type = 'render_review';
    } elseif ($sourceType === 'flow_block') {
        $label = 'Resolver bloqueio';
        $type = 'flow_block';
    } elseif (in_array($sourceType, ['projeto', 'imagem'], true) && $projectId > 0) {
        $label = 'Abrir projeto';
        $type = 'open_project';
        if ($target === '') {
            $target = 'Dashboard/obra.php?obra_id=' . $projectId;
        }
    }

    $cta = ['label' => $label, 'type' => $type];
    if ($target !== '') {
        $cta['target'] = $target;
    }
    if ($entityId > 0) {
        $cta['entity_id'] = $entityId;
    }
    return $cta;
}

function home_payload_pending_item(array $item): array
{
    $result = [
        'type' => (string) ($item['type'] ?? 'pending'),
        'severity' => home_payload_severity((string) ($item['severity'] ?? 'warning')),
        'title' => (string) ($item['title'] ?? 'Pendência operacional'),
        'description' => (string) ($item['detail'] ?? 'Ação necessária.'),
        'cta' => home_payload_pending_cta($item),
    ];
    $entityId = (int) ($item['entity_id'] ?? 0);
    if ($entityId > 0) {
        $result['entity_id'] = $entityId;
    }
    $projectId = (int) ($item['project_id'] ?? 0);
    if ($projectId > 0) {
        $result['obra'] = ['id' => $projectId];
    }
    return $result;
}

function home_payload_attention_rank(array $item): array
{
    $type = (string) ($item['type'] ?? '');
    $severity = (string) ($item['severity'] ?? 'info');
    $approvalTypes = ['flow_review', 'render', 'requirement', 'requisito', 'pre_alteracao'];
    $group = 5;
    if ($type === 'flow_block' && $severity === 'critical') {
        $group = 0;
    } elseif (in_array($type, ['overdue', 'overdue_task', 'late_task', 'planning_conflict'], true)) {
        $group = 1;
    } elseif ($type === 'due_today') {
        $group = 2;
    } elseif (in_array($type, $approvalTypes, true)) {
        $group = 3;
    } elseif ($type === 'adjustment') {
        $group = 4;
    }
    $severityRank = ['critical' => 0, 'warning' => 1, 'info' => 2];
    return [$group, $severityRank[$severity] ?? 9, (string) ($item['title'] ?? '')];
}

function home_payload_sort_attention(array &$items): void
{
    usort($items, static fn (array $a, array $b): int => home_payload_attention_rank($a) <=> home_payload_attention_rank($b));
}

function home_payload_task_attention(array $task, array $original, array $exception): array
{
    $type = (string) ($exception['state'] ?? 'task_attention');
    $severity = home_payload_severity((string) ($exception['severity'] ?? 'warning'));
    $taskContract = home_payload_task($task, 'Abrir tarefa');
    $taskContract['type'] = $type;
    $taskContract['severity'] = $severity;
    $taskContract['title'] = dashboard_colaborador_titulo($task);
    $taskContract['description'] = (string) ($exception['label'] ?? 'Ação necessária.');

    if ($type === 'flow_block') {
        $issueId = (int) ($task['flow_block']['issue_id'] ?? 0);
        if ($issueId > 0) {
            $taskContract['cta'] = [
                'label' => 'Resolver bloqueio',
                'type' => 'flow_block',
                'target' => 'FlowBlock/issue.php?id=' . $issueId,
                'entity_id' => $issueId,
            ];
        }
    }
    return $taskContract;
}

function home_payload_collaborator_attention(array $payloadKanban, int $collaboratorId, array $normalized): array
{
    $byKey = [];
    $issueToTask = [];
    foreach ($normalized['tasks'] as $taskId => $task) {
        if (!empty($task['concluida'])) {
            continue;
        }
        $issueId = (int) ($task['flow_block']['issue_id'] ?? 0);
        if ($issueId > 0) {
            $issueToTask[$issueId] = $taskId;
        }
        $exception = flow_overview_v1_excecao_tarefa($task, $normalized['originals'][$taskId] ?? []);
        if (!$exception && dashboard_colaborador_status((string) ($task['status'] ?? '')) === 'ajuste') {
            $exception = ['state' => 'adjustment', 'severity' => 'warning', 'label' => 'Ajuste solicitado.'];
        }
        if (!$exception) {
            continue;
        }
        $byKey['task:' . $taskId . ':' . (string) $exception['state']] = home_payload_task_attention(
            $task,
            $normalized['originals'][$taskId] ?? [],
            $exception
        );
    }

    $modules = flow_overview_v1_modulos_atencao((array) ($payloadKanban['pendencias_operacionais'] ?? []), $collaboratorId);
    foreach (flow_overview_v1_atencao_pendencias($modules, $collaboratorId, 50) as $pending) {
        $sourceType = (string) ($pending['type'] ?? 'pending');
        $entityId = (int) ($pending['entity_id'] ?? 0);
        if ($sourceType === 'flow_block' && isset($issueToTask[$entityId])) {
            $taskKey = 'task:' . $issueToTask[$entityId] . ':flow_block';
            if (isset($byKey[$taskKey])) {
                $pendingContract = home_payload_pending_item($pending);
                $byKey[$taskKey]['cta'] = $pendingContract['cta'];
                if ($pendingContract['severity'] === 'critical') {
                    $byKey[$taskKey]['severity'] = 'critical';
                }
                continue;
            }
        }
        $key = 'pending:' . $sourceType . ':' . $entityId;
        $byKey[$key] = home_payload_pending_item($pending);
    }

    $items = array_values($byKey);
    home_payload_sort_attention($items);
    return array_slice($items, 0, 8);
}

function home_payload_work(array $dashboard): array
{
    $current = isset($dashboard['day']['current']) && is_array($dashboard['day']['current'])
        ? home_payload_task($dashboard['day']['current'])
        : null;
    $next = array_map(
        static fn (array $task): array => home_payload_task($task, 'Abrir tarefa'),
        array_slice((array) ($dashboard['day']['next'] ?? []), 0, 3)
    );
    return ['current' => $current, 'next' => $next];
}

function home_payload_collaborator(mysqli $conn, array $payloadKanban, int $collaboratorId): array
{
    $normalized = home_payload_normalized_tasks($payloadKanban);
    $dashboard = dashboard_colaborador_montar($payloadKanban, $collaboratorId);
    return [
        'attention' => home_payload_collaborator_attention($payloadKanban, $collaboratorId, $normalized),
        'work' => home_payload_work($dashboard),
        'performance' => flow_overview_v1_metricas_conclusao($conn, $collaboratorId),
    ];
}

function home_payload_is_decision(array $item): bool
{
    if (!empty($item['operational_hold'])) {
        return true;
    }
    return in_array((string) ($item['type'] ?? ''), [
        'flow_review',
        'render',
        'flow_block',
        'pre_alteracao',
        'projeto',
        'imagem',
    ], true);
}

function home_payload_manager_exception(array $row): array
{
    $taskId = (int) ($row['idfuncao_imagem'] ?? 0);
    $issueId = (int) ($row['issue_id'] ?? 0);
    $status = (string) ($row['status'] ?? '');
    $deadline = dashboard_colaborador_data_valida($row['prazo'] ?? null);
    $isHold = dashboard_colaborador_status($status) === 'hold';
    $type = $issueId > 0 ? 'flow_block' : ($isHold ? 'hold' : 'overdue_task');
    $severity = $issueId > 0 && strtoupper((string) ($row['issue_urgency'] ?? '')) === 'CRITICA'
        ? 'critical'
        : ($type === 'overdue_task' ? 'critical' : 'warning');
    $cta = home_payload_task_cta($taskId, 'Abrir tarefa');
    if ($issueId > 0) {
        $cta = [
            'label' => 'Resolver bloqueio',
            'type' => 'flow_block',
            'target' => 'FlowBlock/issue.php?id=' . $issueId,
            'entity_id' => $issueId,
        ];
    }
    $item = [
        'type' => $type,
        'severity' => $severity,
        'task_id' => $taskId,
        'title' => trim((string) ($row['nomenclatura'] ?? '') . ' · ' . (string) ($row['imagem_nome'] ?? '')),
        'description' => $issueId > 0
            ? (string) ($row['issue_reason'] ?? 'Bloqueio operacional ativo.')
            : ($isHold ? 'Tarefa em HOLD.' : 'Tarefa crítica com prazo ultrapassado.'),
        'obra' => ['nome' => (string) ($row['nomenclatura'] ?? '')],
        'imagem' => ['id' => (int) ($row['imagem_id'] ?? 0), 'nome' => (string) ($row['imagem_nome'] ?? '')],
        'funcao' => ['nome' => (string) ($row['nome_funcao'] ?? '')],
        'status' => $status,
        'deadline' => $deadline,
        'deadline_source' => 'funcao_imagem',
        'risk_level' => $severity,
        'cta' => $cta,
    ];
    if ($deadline && $deadline < date('Y-m-d')) {
        $item['days_overdue'] = (int) (new DateTimeImmutable($deadline))->diff(new DateTimeImmutable(date('Y-m-d')))->days;
    }
    if ($issueId > 0) {
        $item['issue_id'] = $issueId;
    }
    return $item;
}

function home_payload_manager_team(array $team): array
{
    $result = [];
    foreach ($team as $person) {
        $state = (string) ($person['state'] ?? 'normal');
        if ($state === 'normal') {
            continue;
        }
        $overdue = (int) ($person['overdue_count'] ?? 0);
        $holds = (int) ($person['hold_count'] ?? 0);
        $wip = (int) ($person['wip'] ?? 0);
        $peak = round((float) ($person['peak_percent'] ?? 0), 1);
        $parts = [];
        if ($peak > 100) $parts[] = 'carga prevista em ' . $peak . '%';
        if ($overdue > 0) $parts[] = $overdue . ' tarefa(s) vencida(s)';
        if ($holds > 0) $parts[] = $holds . ' tarefa(s) em HOLD';
        if ($wip > 0) $parts[] = $wip . ' unidade(s) em WIP';
        $result[] = [
            'colaborador_id' => (int) ($person['id'] ?? 0),
            'name' => (string) ($person['name'] ?? ''),
            'state' => $state,
            'severity' => $state === 'overload' ? 'critical' : 'warning',
            'summary' => implode(', ', $parts),
            'metrics' => [
                'wip' => $wip,
                'overdue' => $overdue,
                'hold' => $holds,
                'peak_load_percent' => $peak,
            ],
            'cta' => [
                'label' => 'Ver capacidade',
                'type' => 'open_capacity',
                'target' => 'PlanejamentoCapacidade/',
            ],
        ];
    }
    return array_slice($result, 0, 8);
}

function home_payload_manager_risks(array $overview): array
{
    $risks = [];
    foreach ((array) ($overview['risks'] ?? []) as $risk) {
        $projectId = (int) ($risk['entity_id'] ?? 0);
        $deliveryId = (int) ($risk['delivery_id'] ?? 0);
        $query = array_filter(['obra_id' => $projectId, 'entrega_id' => $deliveryId], static fn (int $value): bool => $value > 0);
        $risks[] = [
            'type' => 'delivery_projection',
            'severity' => home_payload_severity((string) ($risk['severity'] ?? 'warning')),
            'title' => (string) ($risk['title'] ?? 'Risco de entrega'),
            'description' => (string) ($risk['detail'] ?? ''),
            'obra' => ['id' => $projectId, 'nome' => (string) ($risk['project'] ?? '')],
            'delivery_id' => $deliveryId ?: null,
            'cta' => [
                'label' => 'Ver planejamento',
                'type' => 'open_planning',
                'target' => 'PlanejamentoProducao/' . ($query ? '?' . http_build_query($query) : ''),
            ],
        ];
    }
    foreach (array_slice((array) ($overview['original_deadlines'] ?? []), 0, 3) as $item) {
        $taskId = (int) ($item['task_id'] ?? 0);
        $risks[] = [
            'type' => 'original_deadline_overdue',
            'severity' => 'critical',
            'title' => 'Prazo original vencido',
            'description' => (int) ($item['days_overdue'] ?? 0) . ' dia(s) após o prazo original.',
            'task_id' => $taskId,
            'obra' => ['nome' => (string) ($item['project'] ?? '')],
            'imagem' => ['nome' => (string) ($item['image_name'] ?? '')],
            'original_deadline' => $item['original_deadline'] ?? null,
            'days_overdue' => (int) ($item['days_overdue'] ?? 0),
            'cta' => home_payload_task_cta($taskId, 'Abrir tarefa'),
        ];
    }
    $capacityRisks = 0;
    foreach ((array) ($overview['capacity'] ?? []) as $capacity) {
        $classification = strtoupper((string) ($capacity['classification'] ?? ''));
        if (!in_array($classification, ['CONFLITO', 'NECESSITA_APOIO', 'SEM_CAPACIDADE_CONFIGURADA', 'SEM_PRINCIPAIS_CONFIGURADOS'], true)) {
            continue;
        }
        $risks[] = [
            'type' => 'capacity',
            'severity' => $classification === 'CONFLITO' ? 'critical' : 'warning',
            'title' => 'Risco de capacidade em ' . (string) ($capacity['name'] ?? 'etapa'),
            'description' => 'Classificação atual: ' . $classification . '.',
            'capacity_code' => (string) ($capacity['code'] ?? ''),
            'cta' => [
                'label' => 'Ver capacidade',
                'type' => 'open_capacity',
                'target' => 'PlanejamentoCapacidade/',
            ],
        ];
        $capacityRisks++;
        if ($capacityRisks >= 3) {
            break;
        }
    }
    home_payload_sort_attention($risks);
    return array_slice($risks, 0, 8);
}

function home_payload_manager(mysqli $conn, array $payloadKanban, int $collaboratorId): array
{
    // A Home usa equipe, atenção e riscos. Capacidade detalhada e métricas de
    // produção pertencem à Overview e não devem atrasar este payload enxuto.
    $overview = flow_overview_v1_gestor(
        $conn,
        (array) ($payloadKanban['pendencias_operacionais'] ?? []),
        'all',
        ['include_capacity' => false, 'include_metrics' => false]
    );
    $pending = flow_overview_v1_atencao_pendencias((array) ($overview['attention_modules'] ?? []), null, 100);
    $attention = [];
    $decisions = [];
    $decisionIssueIds = [];
    foreach ($pending as $item) {
        $contract = home_payload_pending_item($item);
        if (home_payload_is_decision($item)) {
            $decisions[] = $contract;
            if (($item['type'] ?? '') === 'flow_block') {
                $decisionIssueIds[(int) ($item['entity_id'] ?? 0)] = true;
            }
        } else {
            $attention[] = $contract;
        }
    }

    foreach (flow_overview_v1_excecoes_tarefas_gestor($conn) as $row) {
        $contract = home_payload_manager_exception($row);
        $issueId = (int) ($contract['issue_id'] ?? 0);
        if ($issueId > 0 && isset($decisionIssueIds[$issueId])) {
            continue;
        }
        if (in_array((string) ($contract['type'] ?? ''), ['flow_block', 'hold'], true)) {
            $decisions[] = $contract;
        } else {
            $attention[] = $contract;
        }
    }

    home_payload_sort_attention($attention);
    home_payload_sort_attention($decisions);
    $dashboard = dashboard_colaborador_montar($payloadKanban, $collaboratorId);
    $personalWork = home_payload_work($dashboard);
    if ($personalWork['current'] === null && $personalWork['next'] === []) {
        $personalWork = null;
    }

    return [
        'attention' => array_slice($attention, 0, 8),
        'decisions' => array_slice($decisions, 0, 8),
        'team' => home_payload_manager_team((array) ($overview['team'] ?? [])),
        'risks' => home_payload_manager_risks($overview),
        'personal_work' => $personalWork,
        'performance' => flow_overview_v1_metricas_conclusao($conn, $collaboratorId),
    ];
}
