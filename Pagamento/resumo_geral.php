<?php

require_once __DIR__ . '/financeiro_v2.php';
require_once __DIR__ . '/../helpers/custo_tarefa.php';

/** Read model only. Competence eligibility and financial snapshots remain in financeiro_v2. */
function pagamento_projetar_itens(array $origens, array $ledger, ?mysqli $conn = null): array
{
    $paid = [];
    foreach ($ledger as $l) {
        $key = (int)$l['colaborador_id'] . ':' . $l['origem'] . ':' . $l['origem_id'] . ':' . (custos_tipo($l) === 'COMISSAO' ? '1' : '0');
        $paid[$key]['valor'] = ($paid[$key]['valor'] ?? 0) + custos_centavos($l['valor']);
        $paid[$key]['tipos'][] = custos_tipo($l);
    }
    $items = [];
    foreach ($origens as $r) {
        $id = (int)$r['colaborador_id'];
        $key = $id . ':' . $r['origem'] . ':' . $r['origem_id'] . ':' . (!empty($r['comissao_gestor']) ? '1' : '0');
        $p = $paid[$key]['valor'] ?? 0;
        $types = $paid[$key]['tipos'] ?? [];
        $hasPartialInstallment = in_array('FINALIZACAO_PARCIAL', $types, true);
        // A partially eligible finalization appears only after its first installment exists.
        if (!empty($r['parcial']) && !$hasPartialInstallment) {
            continue;
        }
        $v = !empty($r['comissao_gestor']) || $conn === null
            ? financeiro_snapshot($r)
            : financeiro_valor_previsto_centavos($conn, $r, $hasPartialInstallment);
        sort($types);
        $repeated = count($types) > 1 && $types !== ['FINALIZACAO_COMPLEMENTO', 'FINALIZACAO_PARCIAL'];
        $withoutLedger = empty($types) && (int)($r['pagamento'] ?? 0) === 1 && empty($r['comissao_gestor']);
        $tariff = calcularCustoTarefa($id, (int)($r['funcao_id'] ?? 0), $r['imagem_nome'] ?? null);
        $tariffMismatch = $r['origem'] === 'funcao_imagem' && empty($r['comissao_gestor']) && empty($r['valor_aprovado']) && $r['valor'] !== null && abs($v - custos_centavos($tariff)) >= 1;
        $financialMismatch = $p > $v || $p < 0 || $v < 0 || $repeated || $withoutLedger;
        $pending = max(0, $v - $p);
        $role = $r['nome_funcao'] ?? 'Sem função';
        if ($r['origem'] === 'funcao_imagem' && (int)$r['funcao_id'] === 4) {
            $phase = !empty($r['parcial']) ? 'Parcial' : 'Completa';
            $role = in_array($id, [12, 24], true) ? "Finalização PH $phase" : "Finalização $phase";
        }
        if (!empty($r['comissao_gestor'])) {
            $role = 'Comissão Gestor';
        }
        $items[] = [
            'chave' => $key, 'colaborador_id' => $id, 'origem' => $r['origem'], 'origem_id' => (int)$r['origem_id'],
            'obra_id' => (int)($r['obra_id'] ?? 0), 'funcao' => $role,
            'total' => $v, 'pago' => $p, 'pendente' => $pending, 'excesso' => max(0, $p - $v),
            'quitado' => $v > 0 && $p >= $v,
            'divergencia' => $tariffMismatch || $financialMismatch,
            'divergencia_tarifa' => $tariffMismatch, 'divergencia_financeira' => $financialMismatch,
        ];
    }
    return $items;
}

function pagamento_agregar_itens(array $items): array
{
    $total = ['total' => 0, 'pago' => 0, 'pendente' => 0, 'excesso' => 0, 'itens' => 0, 'itens_pagos' => 0, 'itens_pendentes' => 0, 'divergencias' => 0, 'divergencias_tarifa' => 0, 'divergencias_financeiras' => 0];
    foreach ($items as $i) {
        foreach (['total', 'pago', 'pendente', 'excesso'] as $k) {
            $total[$k] += $i[$k];
        }
        $total['itens']++;
        $total[$i['quitado'] ? 'itens_pagos' : 'itens_pendentes']++;
        $total['divergencias'] += (int)$i['divergencia'];
        $total['divergencias_tarifa'] += (int)$i['divergencia_tarifa'];
        $total['divergencias_financeiras'] += (int)$i['divergencia_financeira'];
    }
    $total['consistente'] = $total['total'] === $total['pago'] + $total['pendente'];
    $total['grafico_financeiro_disponivel'] = $total['consistente'] && $total['total'] > 0 && $total['pago'] >= 0 && $total['pendente'] >= 0 && !array_filter($items, fn ($i) => $i['total'] < 0 || $i['pago'] < 0 || $i['excesso'] > 0);
    $total['percentual_pago'] = $total['grafico_financeiro_disponivel'] ? round($total['pago'] / $total['total'] * 100, 1) : null;
    $total['percentual_pendente'] = $total['grafico_financeiro_disponivel'] ? round($total['pendente'] / $total['total'] * 100, 1) : null;
    return $total;
}

function pagamento_carregar_ledger(mysqli $conn, array $origens): array
{
    $ids = [];
    foreach ($origens as $r) {
        $ids[$r['origem']][(int)$r['origem_id']] = (int)$r['origem_id'];
    }
    if (!$ids) {
        return [];
    }
    $conditions = [];
    $args = [];
    $types = '';
    foreach ($ids as $origem => $list) {
        $conditions[] = '(pi.origem=? AND pi.origem_id IN (' . implode(',', array_fill(0, count($list), '?')) . '))';
        $args[] = $origem;
        array_push($args, ...array_values($list));
        $types .= 's' . str_repeat('i', count($list));
    }
    // All settlement months, restricted to eligible origins. No per-collaborator calls.
    return custos_query($conn, 'SELECT pi.*, p.colaborador_id FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE ' . implode(' OR ', $conditions), $types, $args);
}

function pagamento_resumo_geral(mysqli $conn, int $mes, int $ano, bool $incluirComparacao = true): array
{
    $ref = PagamentoService::competencia($mes, $ano);
    require_once __DIR__.'/resumo_competencia.php';
    if (FechamentoCompetenciaService::disponivel($conn) && pagamento_competencia_nova($ref)) {
        $payload = pagamento_resumo_competencia($conn, $ref);
        if ($incluirComparacao && ($payload['fechamento']['estado'] ?? null) === 'CONCLUIDO') {
            $previousDate = (new DateTimeImmutable($ref . '-01'))->modify('-1 month');
            $previous = pagamento_resumo_geral($conn, (int)$previousDate->format('n'), (int)$previousDate->format('Y'), false);
            $previousIsOfficial = isset($previous['fechamento']['estado']);
            $comparisonAvailable = !$previousIsOfficial || $previous['fechamento']['estado'] === 'CONCLUIDO';
            $previousFunctions = [];
            if ($comparisonAvailable) {
                foreach ($previous['funcoes'] ?? [] as $function) {
                    $key = mb_strtolower(trim((string)$function['nome']), 'UTF-8');
                    $previousFunctions[$key] = ['nome' => (string)$function['nome'],'total' => (int)$function['total']];
                }
            }
            $currentFunctions = [];
            foreach ($payload['funcoes'] as $function) {
                $key = mb_strtolower(trim((string)$function['nome']), 'UTF-8');
                $currentFunctions[$key] = $function;
            }
            foreach ($previousFunctions as $key => $function) {
                if (!isset($currentFunctions[$key])) {
                    $currentFunctions[$key] = ['nome' => $function['nome'],'total' => 0,'tarefas' => 0];
                }
            }
            foreach ($currentFunctions as $key => &$function) {
                $before = (int)($previousFunctions[$key]['total'] ?? 0);
                $function['mes_anterior_centavos'] = $comparisonAvailable ? $before : null;
                $function['variacao_percentual'] = !$comparisonAvailable ? null
                    : ($before > 0 ? round((((int)$function['total'] - $before) / $before) * 100, 1)
                        : ((int)$function['total'] > 0 ? null : 0.0));
            }
            unset($function);
            $payload['funcoes'] = array_values($currentFunctions);
            usort($payload['funcoes'], fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcasecmp($a['nome'], $b['nome']));

            $previousCollaborators = [];
            if ($comparisonAvailable) {
                foreach ($previous['colaboradores'] ?? [] as $collaborator) {
                    $previousCollaborators[(int)$collaborator['colaborador_id']] = (int)$collaborator['total'];
                }
            }
            foreach ($payload['colaboradores'] as &$collaborator) {
                $before = $previousCollaborators[(int)$collaborator['colaborador_id']] ?? 0;
                $current = (int)$collaborator['total'];
                $collaborator['mes_anterior_centavos'] = $comparisonAvailable ? $before : null;
                $collaborator['variacao_percentual'] = !$comparisonAvailable ? null
                    : ($before > 0 ? round((($current - $before) / $before) * 100, 1)
                        : ($current > 0 ? null : 0.0));
            }
            unset($collaborator);
            $payload['comparacao_mes_anterior'] = ['competencia' => $previousDate->format('Y-m'),'disponivel' => $comparisonAvailable];
        }
        return $payload;
    }
    $names = array_column(custos_query($conn, 'SELECT idcolaborador, nome_colaborador FROM colaborador'), 'nome_colaborador', 'idcolaborador');
    $origens = array_values(array_filter(financeiro_elegiveis($conn, null, $mes, $ano), fn ($r) => isset($names[$r['colaborador_id']])));
    custo_tarefa_carregar_contexto($conn, array_keys($names));
    $items = pagamento_projetar_itens($origens, pagamento_carregar_ledger($conn, $origens), $conn);
    $adendos = custos_query($conn, 'SELECT colaborador_id, status FROM adendos WHERE competencia=?', 's', [$ref]);
    $states = ['nao_gerado' => 0, 'gerado' => 0, 'enviado' => 0, 'visualizado' => 0, 'assinado' => 0, 'recusado' => 0, 'expirado' => 0];
    $adendoByColab = [];
    foreach ($adendos as $a) {
        $states[$a['status']] = ($states[$a['status']] ?? 0) + 1;
        $adendoByColab[(int)$a['colaborador_id']] = ($adendoByColab[(int)$a['colaborador_id']] ?? 0) + 1;
    }
    $grouped = [];
    $roles = [];
    foreach ($items as $i) {
        $grouped[$i['colaborador_id']][] = $i;
        $roles[$i['funcao']] ??= ['nome' => $i['funcao'],'total' => 0,'tarefas' => 0];
        $roles[$i['funcao']]['total'] += (int)$i['total'];
        if ((int)$i['total'] > 0) {
            $roles[$i['funcao']]['tarefas']++;
        }
    }
    foreach ($adendoByColab as $id => $count) {
        if (isset($names[$id]) && !isset($grouped[$id])) {
            $grouped[$id] = [];
        }
    }
    $colabs = [];
    foreach ($grouped as $id => $list) {
        $sum = pagamento_agregar_itens($list);
        $colabs[] = array_merge($sum, [
            'colaborador_id' => $id, 'nome' => $names[$id], 'adendos' => $adendoByColab[$id] ?? 0,
            'funcoes' => array_values(array_unique(array_column($list, 'funcao'))),
            'obras' => array_values(array_unique(array_column($list, 'obra_id'))),
            'situacao' => $sum['divergencias'] > 0 ? 'Divergência' : ($sum['itens_pendentes'] > 0 ? 'Pendente' : ($sum['itens'] > 0 ? 'Pago' : 'Sem itens')),
        ]);
    }
    usort($colabs, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcasecmp($a['nome'], $b['nome']));
    $roles = array_values($roles);
    usort($roles, fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcasecmp($a['nome'], $b['nome']));
    $obraIds = array_values(array_unique(array_filter(array_column($items, 'obra_id'))));
    $obras = $obraIds ? custos_query($conn, 'SELECT idobra id, nomenclatura nome FROM obra WHERE idobra IN (' . implode(',', array_fill(0, count($obraIds), '?')) . ') ORDER BY nomenclatura', str_repeat('i', count($obraIds)), $obraIds) : [];
    return [
        'competencia' => $ref, 'unidade_monetaria' => 'centavos', 'resumo' => pagamento_agregar_itens($items),
        'adendos' => ['total' => count($adendos), 'nao_assinados' => count($adendos) - $states['assinado'], 'status' => $states],
        'funcoes' => $roles,
        'colaboradores' => $colabs, 'obras' => $obras,
    ];
}
