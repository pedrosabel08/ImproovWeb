<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../Pagamento/services/FechamentoComposicaoRules.php';
require_once __DIR__ . '/pagamento_adendos_financeiro.php';

/** Cenários sintéticos aprovados. Nunca inseridos no banco. */
function composicao_fixture_servicos(int $b = 7, int $subtotal = 200000, int $ac = 0): array
{
    $decimal = fn(int $v) => intdiv($v,100) . '.' . str_pad((string)($v%100),2,'0',STR_PAD_LEFT);
    $origens = [];
    if ($subtotal-$ac > 0) $origens[] = ['origem'=>'funcao_imagem','origem_id'=>9900001,'colaborador_id'=>$b,'funcao_id'=>2,
        'valor'=>$decimal($subtotal-$ac),'prazo'=>'2026-08-31','status'=>'Finalizado','pagamento'=>0];
    if ($ac > 0) $origens[] = ['origem'=>'acompanhamento','origem_id'=>9900002,'colaborador_id'=>$b,
        'valor'=>$decimal($ac),'data'=>'2026-08-20','pagamento'=>0];
    return (new FechamentoFinanceiroRules())->calcular(['origens'=>$origens,'logs'=>[],'ledger'=>[]],$b,'2026-08',new DateTimeImmutable('2026-10-02T12:00:00-03:00'));
}

function composicao_fixture_registro(array $s, string $classe, string $referencia, array $campos = []): array
{
    return array_merge(['colaborador_id'=>$s['colaborador_id'],'competencia'=>$s['competencia'],'classe'=>$classe,
        'autor_id'=>1,'registrado_em'=>'2026-09-30T12:00:00-03:00','referencia'=>$referencia,'motivo'=>'Cenário sintético aprovado, sem gravação.'],$campos);
}

function composicao_fixture_contexto(array $s, $configurado, string $bonus = 'SEM_BONUS'): array
{
    return ['colaborador_id'=>$s['colaborador_id'],'competencia'=>$s['competencia'],'snapshot_em'=>$s['snapshot_em'],
        'fixo'=>['configurado'=>$configurado,'evidencias_liquidacao'=>[]],
        'extras'=>['estado'=>$bonus,'itens'=>[], 'decisao'=>$bonus==='PENDENTE'?null:
            composicao_fixture_registro($s,'BONUS_EXTRAS','decisao-bonus',['estado'=>$bonus])]];
}

/** Prova EXPLÍCITA em memória do saldo de fixo sem pagamento; não é ausência de ledger. */
function composicao_fixture_apuracao_sem_pagamento(array $s): array
{
    return composicao_fixture_registro($s,'VALOR_FIXO','apuracao-fixo-sintetica',
        ['tipo'=>'APURACAO_FIXO_SEM_PAGAMENTO','valor_centavos'=>0]);
}

function composicao_fixture_golden_contexto(array $g, string $pair): array
{
    $p=$g['pairs'][$pair]; [$b,$ref]=explode('/',$pair); $adendos=[];
    foreach($p['existing_adendo'] as $a) {
        $payload=json_decode($a['payload_enviado']??'',true);
        $adendos[]=['id'=>$a['id'],'colaborador_id'=>(int)$b,'competencia'=>$ref,'status'=>$a['status'],
            'payload_utilizavel'=>is_array($payload),'payload_financeiro'=>is_array($payload)?array_intersect_key($payload,array_flip(['COMPETENCIA','VALOR_FIXO','VALOR_TOTAL'])):[]];
    }
    return ['colaborador_id'=>(int)$b,'competencia'=>$ref,
        'fixo'=>['configurado'=>$p['colaborador']['valor_fixo'],'evidencias_liquidacao'=>[],
            'evidencias_legadas'=>['adendos'=>$adendos,'pagamentos_agregados'=>[]]],
        'extras'=>['estado'=>'PENDENTE','itens'=>[]],
        'observacao'=>'Fatos congelados de complementos. Não reconstrói serviços/total do mês; bônus não recuperáveis.'];
}
