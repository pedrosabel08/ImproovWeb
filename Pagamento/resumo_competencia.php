<?php

require_once __DIR__.'/services/FechamentoCompetenciaService.php';
require_once __DIR__.'/../helpers/custos_helper.php';

function pagamento_resumo_competencia(mysqli $conn, string $ref, int $u = 0): array
{
    $c = (new FechamentoCompetenciaService($conn, $u))->resumo($ref);
    $closed = $c['estado'] === 'CONCLUIDO';
    $rows = [];
    $functions = [];
    $projects = [];
    $costsWithoutProject = 0;
    $reconciledAdjustments = 0;
    $imageIdsWithoutSnapshotProject = [];
    foreach ($c['colaboradores'] as $p) {
        foreach ($p['funcoes'] as $task) {
            $description = $task['descricao'] ?? [];
            if ((int)($description['obra_id'] ?? 0) <= 0 && (int)($description['imagem_id'] ?? 0) > 0) {
                $imageIdsWithoutSnapshotProject[(int)$description['imagem_id']] = (int)$description['imagem_id'];
            }
        }
    }
    // Revisões antigas podem ter guardado a imagem sem duplicar nela o obra_id.
    // A relação canônica continua sendo imagem -> obra; recupere-a pelo vínculo atual.
    $projectsByImage = [];
    if ($imageIdsWithoutSnapshotProject) {
        $imageRows = custos_query(
            $conn,
            'SELECT ico.idimagens_cliente_obra AS imagem_id, ico.obra_id,
                COALESCE(NULLIF(o.nomenclatura,\'\'), NULLIF(o.nome_obra,\'\')) AS obra_nome
             FROM imagens_cliente_obra ico
             JOIN obra o ON o.idobra = ico.obra_id
             WHERE ico.idimagens_cliente_obra IN (' . implode(',', array_fill(0, count($imageIdsWithoutSnapshotProject), '?')) . ')',
            str_repeat('i', count($imageIdsWithoutSnapshotProject)),
            array_values($imageIdsWithoutSnapshotProject)
        );
        foreach ($imageRows as $imageRow) {
            $projectsByImage[(int)$imageRow['imagem_id']] = $imageRow;
        }
    }
    foreach ($c['colaboradores'] as $p) {
        $personFunctions = [];
        $personProjects = [];
        foreach ($p['funcoes'] as $task) {
            $description = $task['descricao'] ?? [];
            $isManagerCommission = ($task['identidade']['classe'] ?? null) === FechamentoFinanceiroRules::COMISSAO;
            $functionName = $isManagerCommission ? 'Comissão Gestor' : (trim((string)($description['funcao'] ?? '')) ?: 'Sem função');
            $personFunctions[$functionName] = true;
            $amount = (int)($task['valor_reconhecido_centavos'] ?? $task['valor_centavos'] ?? 0);
            $projectId = (int)($description['obra_id'] ?? 0);
            $imageProject = $projectsByImage[(int)($description['imagem_id'] ?? 0)] ?? null;
            if ($projectId <= 0 && $imageProject) {
                $projectId = (int)$imageProject['obra_id'];
            }
            $projectName = trim((string)($description['obra_nome'] ?? ''));
            if ($projectName === '' && $imageProject) {
                $projectName = trim((string)($imageProject['obra_nome'] ?? ''));
            }
            if ($closed && $amount > 0) {
                $functions[$functionName] ??= ['nome' => $functionName,'total' => 0,'tarefas' => 0];
                $functions[$functionName]['total'] += $amount;
                $functions[$functionName]['tarefas']++;
            }
            if ($projectId > 0) {
                $personProjects[$projectId] = true;
                if ($closed && $amount > 0) {
                    $projects[$projectId]['id'] = $projectId;
                    $projects[$projectId]['nome'] = $projectName ?: 'Obra #' . $projectId;
                    $projects[$projectId]['total'] = ($projects[$projectId]['total'] ?? 0) + $amount;
                    $projects[$projectId]['tarefas'] = ($projects[$projectId]['tarefas'] ?? 0) + 1;
                }
            } elseif ($closed && $amount > 0) {
                $costsWithoutProject += $amount;
            }
        }
        // Créditos reconciliados após a revisão não carregam identidade de tarefa/obra
        // suficiente para atribuí-los a um projeto com segurança.
        $reconciledCredit = $closed ? (int)($p['reconciliacao']['credito_historico_centavos'] ?? 0) : 0;
        if ($reconciledCredit > 0) {
            $costsWithoutProject += $reconciledCredit;
            $reconciledAdjustments += $reconciledCredit;
        }
        $i = ['total' => $closed ? $p['total_centavos'] : 0,'pago' => $p['pago_centavos'],'pendente' => $closed ? $p['pendente_centavos'] : 0,'excesso' => 0,
            'quitado' => $p['pagamento_status'] === 'PAGO','divergencia' => false,'divergencia_tarifa' => false,'divergencia_financeira' => false];
        $sum = pagamento_agregar_itens([$i]);
        $rows[] = $sum + ['colaborador_id' => $p['colaborador_id'],'nome' => $p['nome'],'pago_em' => $p['pago_em'],'adendos' => $p['status'] === 'CONFIRMADO' ? 1 : 0,'funcoes' => array_keys($personFunctions),'obras' => array_map('intval', array_keys($personProjects)),
            'situacao' => $closed ? ($i['quitado'] ? 'Pago' : 'Pendente') : ($p['status'] === 'CONFIRMADO' ? 'Revisado · aguardando fechamento' : 'Pendente de revisão'),
            'parcial_centavos' => $p['total_centavos']];
    }
    $all = [];
    foreach ($rows as $r) {
        $all[] = $r + ['quitado' => $r['situacao'] === 'Pago','divergencia' => false,'divergencia_tarifa' => false,'divergencia_financeira' => false];
    }
    $summary = pagamento_agregar_itens($all);
    $summary['grafico_financeiro_disponivel'] = $closed && $summary['grafico_financeiro_disponivel'];
    $functions = array_values($functions);
    $projects = array_values($projects);
    usort($functions, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcasecmp($a['nome'], $b['nome']));
    usort($projects, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcasecmp($a['nome'], $b['nome']));
    return ['competencia' => $ref,'fonte_financeira' => 'FECHAMENTO','unidade_monetaria' => 'centavos','fechamento' => $c,'resumo' => $summary,
        'colaboradores' => $rows,'obras' => $projects,'funcoes' => $functions,'custos_sem_obra_centavos' => $costsWithoutProject,
        'creditos_reconciliados_sem_obra_centavos' => $reconciledAdjustments,
        'adendos' => ['total' => $c['quantidade'],'nao_assinados' => 0,'status' => ['confirmado' => $c['contagens']['CONFIRMADO'],'nao_gerado' => $c['quantidade'] - $c['contagens']['CONFIRMADO']]]];
}
