<?php

require_once __DIR__.'/services/FechamentoCompetenciaService.php';

function pagamento_resumo_competencia(mysqli $conn, string $ref, int $u = 0): array
{
    $c = (new FechamentoCompetenciaService($conn, $u))->resumo($ref);
    $closed = $c['estado'] === 'CONCLUIDO';
    $rows = [];
    $components = [];
    $labels = ['SERVICOS' => 'Adendos / tarefas','VALOR_FIXO' => 'Fixo','ACOMPANHAMENTO_ESPECIAL' => 'Acompanhamento','BONUS_EXTRAS' => 'Extras','BONUS_PRODUTIVIDADE' => 'Bônus produtividade','DESCONTO' => 'Descontos'];
    foreach ($c['colaboradores'] as $p) {
        $i = ['total' => $closed ? $p['total_centavos'] : 0,'pago' => $p['pago_centavos'],'pendente' => $closed ? $p['pendente_centavos'] : 0,'excesso' => 0,
            'quitado' => $p['pagamento_status'] === 'PAGO','divergencia' => false,'divergencia_tarifa' => false,'divergencia_financeira' => false];
        $sum = pagamento_agregar_itens([$i]);
        $rows[] = $sum + ['colaborador_id' => $p['colaborador_id'],'nome' => $p['nome'],'adendos' => $p['status'] === 'CONFIRMADO' ? 1 : 0,'funcoes' => array_values(array_map(fn($k)=>$labels[$k]??$k,array_keys(array_filter($p['resumo'],fn($v)=>(int)$v!==0)))),'obras' => [],
            'situacao' => $closed ? ($i['quitado'] ? 'Pago' : 'Pendente') : ($p['status'] === 'CONFIRMADO' ? 'Revisado · aguardando fechamento' : 'Pendente de revisão'),
            'parcial_centavos' => $p['total_centavos']];
        if ($closed) {
            foreach ($p['resumo'] as $k => $v) {
                $components[$k] = ($components[$k] ?? 0) + (int)$v;
            }
            $components['SERVICOS'] = ($components['SERVICOS'] ?? 0) + (int)($p['reconciliacao']['credito_historico_centavos'] ?? 0);
        }
    }
    $all = [];
    foreach ($rows as $r) {
        $all[] = $r + ['quitado' => $r['situacao'] === 'Pago','divergencia' => false,'divergencia_tarifa' => false,'divergencia_financeira' => false];
    }
    $summary = pagamento_agregar_itens($all);
    $summary['grafico_financeiro_disponivel'] = $closed && $summary['grafico_financeiro_disponivel'];
    return ['competencia' => $ref,'fonte_financeira' => 'FECHAMENTO','unidade_monetaria' => 'centavos','fechamento' => $c,'resumo' => $summary,
        'colaboradores' => $rows,'obras' => [],'funcoes' => array_map(fn ($k, $v) => ['nome' => $labels[$k] ?? $k,'total' => $v], array_keys($components), array_values($components)),
        'adendos' => ['total' => $c['quantidade'],'nao_assinados' => 0,'status' => ['confirmado' => $c['contagens']['CONFIRMADO'],'nao_gerado'=>$c['quantidade']-$c['contagens']['CONFIRMADO']]]];
}
