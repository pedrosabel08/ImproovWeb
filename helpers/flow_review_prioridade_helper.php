<?php

require_once __DIR__ . '/motor_requisitos_helper.php';

if (!defined('FLOW_REVIEW_ESPERA_SEVERA_HORAS')) {
    $configuredSevereHours = getenv('FLOW_REVIEW_ESPERA_SEVERA_HORAS');
    define(
        'FLOW_REVIEW_ESPERA_SEVERA_HORAS',
        is_numeric($configuredSevereHours) && (float) $configuredSevereHours > 0
            ? (float) $configuredSevereHours
            : 48.0
    );
}
if (!defined('FLOW_REVIEW_ESPERA_ATENCAO_HORAS')) {
    $configuredAttentionHours = getenv('FLOW_REVIEW_ESPERA_ATENCAO_HORAS');
    define(
        'FLOW_REVIEW_ESPERA_ATENCAO_HORAS',
        is_numeric($configuredAttentionHours) && (float) $configuredAttentionHours > 0
            ? (float) $configuredAttentionHours
            : 24.0
    );
}
if (!defined('FLOW_REVIEW_ESPERA_CRITICA_HORAS')) {
    define('FLOW_REVIEW_ESPERA_CRITICA_HORAS', FLOW_REVIEW_ESPERA_SEVERA_HORAS);
}

/** Somente a fila de decisão de tarefas de imagem participa da V1. */
function flow_review_prioridade_elegivel(array $tarefa): bool
{
    return ($tarefa['tipo_tarefa'] ?? 'imagem') === 'imagem'
        && ($tarefa['status'] ?? null) === 'Em aprovação'
        && (int) ($tarefa['idfuncao_imagem'] ?? 0) > 0;
}

/** Classificação pura, separada das consultas e da apresentação. */
function flow_review_prioridade_classificar(array $sinais): array
{
    $minutos = isset($sinais['tempo_espera_minutos']) ? (int) $sinais['tempo_espera_minutos'] : null;
    $slaHoras = isset($sinais['sla_horas']) ? (int) $sinais['sla_horas'] : null;
    $acimaSla = $minutos !== null && $slaHoras !== null
        ? max(0, $minutos - $slaHoras * 60)
        : 0;
    $slaExcedido = $minutos !== null && $slaHoras !== null && $minutos >= $slaHoras * 60;
    $esperaSevera = $minutos !== null && $minutos >= FLOW_REVIEW_ESPERA_SEVERA_HORAS * 60;
    $esperaAtencao = $minutos !== null && $minutos >= FLOW_REVIEW_ESPERA_ATENCAO_HORAS * 60;
    $desbloqueia = !empty($sinais['aprovacao_unico_bloqueio']);
    $motivos = [];

    if ($esperaSevera) {
        $motivos[] = 'Aguardando aprovação há ' . (int) floor($minutos / 60) . 'h';
    }
    if ($desbloqueia) {
        $motivos[] = 'Libera ' . (($sinais['proxima_funcao_nome'] ?? null) ?: 'a próxima tarefa');
    }
    if ($slaExcedido) {
        $motivos[] = 'SLA de ' . $slaHoras . 'h excedido em '
            . (int) floor($acimaSla / 60) . 'h' . str_pad((string) ($acimaSla % 60), 2, '0', STR_PAD_LEFT) . 'min';
    }
    if ($esperaAtencao && !$esperaSevera && !$slaExcedido) {
        $motivos[] = 'Aguardando aprovação há ' . (int) floor($minutos / 60) . 'h';
    }

    if ($esperaSevera) {
        $nivel = 0;
        $tipo = 'ESPERA_SEVERA';
    } elseif ($desbloqueia) {
        $nivel = 1;
        $tipo = 'DESBLOQUEIA_TRABALHO';
    } elseif ($slaExcedido) {
        $nivel = 2;
        $tipo = 'SLA_EXCEDIDO';
    } elseif ($esperaAtencao) {
        $nivel = 2;
        $tipo = 'ESPERA_PROLONGADA';
    } else {
        $nivel = 3;
        $tipo = 'NORMAL';
    }

    return [
        'sla_excedido' => $slaExcedido,
        'tempo_acima_sla_minutos' => $acimaSla,
        'espera_severa' => $esperaSevera,
        'espera_atencao' => $esperaAtencao,
        'espera_critica' => $esperaSevera,
        'prioridade_nivel' => $nivel,
        'prioridade_tipo' => $tipo,
        'motivo_prioridade' => $motivos[0] ?? null,
        'motivos_prioridade' => $motivos,
    ];
}

/** Ordenação calculada para validação; não modifica a ordem recebida pela interface. */
function flow_review_prioridade_fila_recomendada(array $tarefas): array
{
    $fila = array_values(array_filter($tarefas, 'flow_review_prioridade_elegivel'));
    usort($fila, static function (array $a, array $b): int {
        $nivelA = (int) ($a['prioridade_nivel'] ?? 3);
        $nivelB = (int) ($b['prioridade_nivel'] ?? 3);
        if ($nivelA !== $nivelB) return $nivelA <=> $nivelB;

        if ($nivelA === 0) {
            $espera = (int) ($b['tempo_espera_minutos'] ?? -1) <=> (int) ($a['tempo_espera_minutos'] ?? -1);
            if ($espera !== 0) return $espera;
            $unblocks = (int) !empty($b['aprovacao_unico_bloqueio']) <=> (int) !empty($a['aprovacao_unico_bloqueio']);
            if ($unblocks !== 0) return $unblocks;
            $overSla = (int) ($b['tempo_acima_sla_minutos'] ?? 0) <=> (int) ($a['tempo_acima_sla_minutos'] ?? 0);
            if ($overSla !== 0) return $overSla;
        } elseif ($nivelA === 1) {
            $espera = (int) ($b['tempo_espera_minutos'] ?? -1) <=> (int) ($a['tempo_espera_minutos'] ?? -1);
            if ($espera !== 0) return $espera;
            $overSla = (int) ($b['tempo_acima_sla_minutos'] ?? 0) <=> (int) ($a['tempo_acima_sla_minutos'] ?? 0);
            if ($overSla !== 0) return $overSla;
        } elseif ($nivelA === 2) {
            $sla = (int) !empty($b['sla_excedido']) <=> (int) !empty($a['sla_excedido']);
            if ($sla !== 0) return $sla;
            $overSla = (int) ($b['tempo_acima_sla_minutos'] ?? 0) <=> (int) ($a['tempo_acima_sla_minutos'] ?? 0);
            if ($overSla !== 0) return $overSla;
            $espera = (int) ($b['tempo_espera_minutos'] ?? -1) <=> (int) ($a['tempo_espera_minutos'] ?? -1);
            if ($espera !== 0) return $espera;
        } else {
            $espera = (int) ($b['tempo_espera_minutos'] ?? -1) <=> (int) ($a['tempo_espera_minutos'] ?? -1);
            if ($espera !== 0) return $espera;
        }
        return (int) $a['idfuncao_imagem'] <=> (int) $b['idfuncao_imagem'];
    });
    return $fila;
}

/** Converte a avaliação oficial de uma candidata em vínculos auditáveis. */
function flow_review_prioridade_vinculos(array $candidate, array $evaluation, array $approvalIds): array
{
    $pending = array_values(array_filter(
        (array) ($evaluation['requisitos_avaliados'] ?? []),
        static fn (array $requirement): bool => !empty($requirement['bloqueia_inicio'])
    ));
    $links = [];
    foreach ($pending as $requirement) {
        if (($requirement['codigo'] ?? null) !== 'APROVACAO_ETAPA_ANTERIOR') continue;
        $originId = (int) ($requirement['origem_id'] ?? 0);
        if (!isset($approvalIds[$originId]) || $originId === (int) $candidate['idfuncao_imagem']) continue;
        $links[$originId] = [
            'candidate' => $candidate,
            'pending_count' => count($pending),
            'only_blocker' => count($pending) === 1
                && ($candidate['status'] ?? null) === 'Não iniciado'
                && !in_array((int) ($candidate['imagem_status_id'] ?? 0), [9, 16], true)
                && (int) ($candidate['imagem_substatus_id'] ?? 0) !== 7
                && empty($evaluation['erro_configuracao']),
        ];
    }
    return $links;
}

/** Retorna o status da primeira sucessora que contradiz o estado de bloqueio pendente. */
function flow_review_prioridade_estado_sucessora_inconsistente(array $links): ?string
{
    foreach ($links as $link) {
        $status = $link['candidate']['status'] ?? null;
        if ($status !== null && $status !== 'Não iniciado') {
            return (string) $status;
        }
    }
    return null;
}

/**
 * Acrescenta sinais às tarefas em aprovação. Faz dois carregamentos em lote;
 * o motor de requisitos confirma o vínculo e os demais impedimentos de início.
 */
function flow_review_prioridade_enriquecer(mysqli $conn, array &$tarefas): void
{
    $indices = [];
    $ids = [];
    $imagemIds = [];
    foreach ($tarefas as $index => $tarefa) {
        if (!flow_review_prioridade_elegivel($tarefa)) continue;
        $id = (int) $tarefa['idfuncao_imagem'];
        $indices[$id] = $index;
        $ids[] = $id;
        $imagemIds[] = (int) $tarefa['imagem_id'];
    }
    if (!$ids) return;
    $ids = array_values(array_unique($ids));
    $imagemIds = array_values(array_unique(array_filter($imagemIds)));
    $idSql = implode(',', $ids);
    $imagemSql = implode(',', $imagemIds);

    $sqlTempo = "SELECT fi.idfuncao_imagem, inicio.inicio_rodada,
                       TIMESTAMPDIFF(MINUTE, inicio.inicio_rodada, NOW()) AS espera_minutos,
                       sf.limite_horas AS sla_horas, i.tipo_imagem
                  FROM funcao_imagem fi
                  JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
             LEFT JOIN (
                       SELECT funcao_imagem_id, MAX(data_aprovacao) AS inicio_rodada
                         FROM historico_aprovacoes
                        WHERE status_novo = 'Em aprovação'
                          AND funcao_imagem_id IN ($idSql)
                        GROUP BY funcao_imagem_id
                       ) inicio ON inicio.funcao_imagem_id = fi.idfuncao_imagem
             LEFT JOIN sla_funcao sf ON sf.funcao_id = fi.funcao_id
                 WHERE fi.idfuncao_imagem IN ($idSql)";
    $tempoResult = $conn->query($sqlTempo);
    $tempoById = [];
    while ($row = $tempoResult->fetch_assoc()) {
        $tempoById[(int) $row['idfuncao_imagem']] = $row;
    }
    $tempoResult->close();

    $obraIdsModelagem = [];
    $facadeApprovalIds = [];
    foreach ($indices as $id => $index) {
        if ((int) ($tarefas[$index]['funcao_id'] ?? 0) === 2
            && ($tempoById[$id]['tipo_imagem'] ?? null) === 'Fachada') {
            $facadeApprovalIds[$id] = true;
            $obraIdsModelagem[] = (int) ($tarefas[$index]['idobra'] ?? 0);
        }
    }
    $obraIdsModelagem = array_values(array_unique(array_filter($obraIdsModelagem)));

    $conditions = ["i.idimagens_cliente_obra IN ($imagemSql)", "i.imagem_principal_id IN ($imagemSql)"];
    if ($obraIdsModelagem) {
        $obraSql = implode(',', $obraIdsModelagem);
        // A modelagem-base da Fachada pode ser predecessora de outra imagem da obra.
        $conditions[] = "(i.obra_id IN ($obraSql) AND fi.funcao_id IN (3, 4))";
    }
    $sqlCandidatas = "SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status,
                             fi.colaborador_id, c.nome_colaborador, f.nome_funcao,
                             i.status_id AS imagem_status_id, i.substatus_id AS imagem_substatus_id,
                             i.imagem_principal_id, i.obra_id
                        FROM funcao_imagem fi
                        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
                        JOIN funcao f ON f.idfuncao = fi.funcao_id
                   LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
                       WHERE (" . implode(' OR ', $conditions) . ")
                         AND fi.status IN ('Não iniciado', 'Em andamento', 'Em aprovação',
                                           'Ajuste', 'HOLD', 'Aguardando Direção')
                       ORDER BY fi.idfuncao_imagem ASC";
    $candidateResult = $conn->query($sqlCandidatas);
    $candidates = [];
    while ($row = $candidateResult->fetch_assoc()) {
        $candidates[(int) $row['idfuncao_imagem']] = $row;
    }
    $candidateResult->close();

    // A sequência apenas reduz candidatas. O motor abaixo decide se há vínculo real.
    $orderPositions = array_flip(motor_requisitos_ordem_producao());
    $approvalsByImage = [];
    $facadeApprovalObras = [];
    foreach ($indices as $id => $index) {
        $approval = $tarefas[$index];
        $approvalsByImage[(int) $approval['imagem_id']][] = (int) $approval['funcao_id'];
        if (isset($facadeApprovalIds[$id])) {
            $facadeApprovalObras[(int) $approval['idobra']] = true;
        }
    }
    $candidates = array_filter($candidates, static function (array $candidate) use ($approvalsByImage, $facadeApprovalObras, $orderPositions): bool {
        $candidatePosition = $orderPositions[(int) $candidate['funcao_id']] ?? null;
        if ($candidatePosition === null) return false;
        $relatedImageIds = [(int) $candidate['imagem_id'], (int) ($candidate['imagem_principal_id'] ?? 0)];
        foreach ($relatedImageIds as $relatedImageId) {
            foreach ($approvalsByImage[$relatedImageId] ?? [] as $approvalFunctionId) {
                if ($candidatePosition > ($orderPositions[$approvalFunctionId] ?? PHP_INT_MAX)) return true;
            }
        }
        return isset($facadeApprovalObras[(int) $candidate['obra_id']])
            && in_array((int) $candidate['funcao_id'], [3, 4], true);
    });

    // A: tarefa candidata cadastrada. B e C dependem da avaliação oficial abaixo.
    $candidateCountByApproval = array_fill_keys($ids, 0);
    foreach ($candidates as $candidate) {
        $candidatePosition = $orderPositions[(int) $candidate['funcao_id']] ?? null;
        foreach ($indices as $id => $index) {
            if ($id === (int) $candidate['idfuncao_imagem']) continue;
            $approval = $tarefas[$index];
            $sameChain = (int) $candidate['imagem_id'] === (int) $approval['imagem_id']
                || (int) ($candidate['imagem_principal_id'] ?? 0) === (int) $approval['imagem_id'];
            $later = $candidatePosition !== null
                && $candidatePosition > ($orderPositions[(int) $approval['funcao_id']] ?? PHP_INT_MAX);
            $facadeRelated = isset($facadeApprovalIds[$id])
                && (int) $candidate['obra_id'] === (int) $approval['idobra']
                && in_array((int) $candidate['funcao_id'], [3, 4], true);
            if (($sameChain && $later) || $facadeRelated) $candidateCountByApproval[$id]++;
        }
    }

    $linksByApproval = [];
    $evaluationErrors = [];
    if ($candidates) {
        motor_requisitos_preparar_lote($conn, array_keys($candidates));
        foreach ($candidates as $candidate) {
            try {
                $evaluation = motor_requisitos_avaliar_funcao_imagem($conn, (int) $candidate['idfuncao_imagem']);
            } catch (Throwable $error) {
                $evaluationErrors[] = $candidate;
                continue;
            }
            if (!empty($evaluation['erro_configuracao'])) $evaluationErrors[] = $candidate;
            foreach (flow_review_prioridade_vinculos($candidate, $evaluation, $indices) as $originId => $link) {
                $linksByApproval[$originId][] = $link;
            }
        }
    }

    foreach ($indices as $id => $index) {
        $task = &$tarefas[$index];
        $tempo = $tempoById[$id] ?? [];
        $minutes = !isset($tempo['espera_minutos'])
            ? null : max(0, (int) $tempo['espera_minutos']);
        $task['inicio_rodada'] = $tempo['inicio_rodada'] ?? null;
        $task['tempo_espera_minutos'] = $minutes;
        $task['tempo_espera_horas'] = $minutes === null ? null : round($minutes / 60, 2);
        $task['sla_horas'] = isset($tempo['sla_horas']) ? (int) $tempo['sla_horas'] : null;
        $task['prioridade_manual'] = (int) ($task['prioridade_aprovacao'] ?? 0);

        $links = $linksByApproval[$id] ?? [];
        usort($links, static function (array $a, array $b): int {
            $strong = (int) $b['only_blocker'] <=> (int) $a['only_blocker'];
            if ($strong !== 0) return $strong;
            $notStarted = (int) (($b['candidate']['status'] ?? null) === 'Não iniciado')
                <=> (int) (($a['candidate']['status'] ?? null) === 'Não iniciado');
            if ($notStarted !== 0) return $notStarted;
            return (int) $a['candidate']['idfuncao_imagem'] <=> (int) $b['candidate']['idfuncao_imagem'];
        });
        $chosen = $links[0] ?? null;
        $next = $chosen['candidate'] ?? null;
        $task['proxima_tarefa_id'] = $next ? (int) $next['idfuncao_imagem'] : null;
        $task['proxima_funcao_id'] = $next ? (int) $next['funcao_id'] : null;
        $task['proxima_funcao_nome'] = $next['nome_funcao'] ?? null;
        $task['proxima_tarefa_status'] = $next['status'] ?? null;
        $task['responsavel_proxima_tarefa_id'] = $next && $next['colaborador_id'] !== null ? (int) $next['colaborador_id'] : null;
        $task['responsavel_proxima_tarefa_nome'] = $next['nome_colaborador'] ?? null;
        $task['aprovacao_bloqueia_proxima'] = $chosen !== null;
        $task['aprovacao_unico_bloqueio'] = (bool) ($chosen['only_blocker'] ?? false);
        $task['quantidade_requisitos_pendentes'] = $chosen['pending_count'] ?? null;
        $task['quantidade_sucessoras_bloqueadas'] = count($links);
        $task['sucessora_potencial_existe'] = ($candidateCountByApproval[$id] ?? 0) > 0;
        $task['quantidade_sucessoras_candidatas'] = $candidateCountByApproval[$id] ?? 0;

        $observations = [];
        if ($minutes === null) $observations[] = 'Sem entrada válida em aprovação no histórico';
        $inconsistentSuccessorStatus = flow_review_prioridade_estado_sucessora_inconsistente($links);
        if ($inconsistentSuccessorStatus !== null) {
            $observations[] = 'Sucessora em ' . $inconsistentSuccessorStatus
                . ' apesar de requisito predecessor ainda pendente';
        }
        foreach ($evaluationErrors as $candidateWithError) {
            $sameChain = (int) $candidateWithError['imagem_id'] === (int) ($task['imagem_id'] ?? 0)
                || (int) ($candidateWithError['imagem_principal_id'] ?? 0) === (int) ($task['imagem_id'] ?? 0);
            $facadeRelated = isset($facadeApprovalIds[$id])
                && (int) $candidateWithError['obra_id'] === (int) ($task['idobra'] ?? 0);
            if ($sameChain || $facadeRelated) {
                $observations[] = 'Falha ao avaliar os requisitos de uma tarefa relacionada';
                break;
            }
        }
        $task['prioridade_inconsistente'] = (bool) $observations;
        $task['prioridade_observacao'] = $observations ? implode('; ', array_unique($observations)) : null;
        $task += flow_review_prioridade_classificar($task);
        unset($task);
    }

    // A posição é o contrato único de ordenação consumido pelas superfícies.
    // Mantém a ordem original do payload; somente acrescenta a posição calculada.
    $positionById = [];
    foreach (flow_review_prioridade_fila_recomendada($tarefas) as $position => $task) {
        $positionById[(int) $task['idfuncao_imagem']] = $position + 1;
    }
    foreach ($indices as $id => $index) {
        $tarefas[$index]['prioridade_posicao'] = $positionById[$id] ?? null;
    }
}
