<?php

require_once __DIR__ . '/../../Pagamento/services/FechamentoFinanceiroRules.php';
require_once __DIR__ . '/../../helpers/custo_tarefa.php';
require_once __DIR__ . '/../../Pagamento/financeiro_v2.php';
require_once __DIR__ . '/../../Contratos/services/AdendoLocalService.php';

function fechamento_shadow_log(string $message): bool { return true; }
function fechamento_shadow_error(array $response, int $status = 200): void
{ throw new RuntimeException('Controller legado: HTTP ' . $status); }

/** Diagnóstico isolado; a projeção do documento é estática, nunca regra do motor. */
final class ComparacaoFechamentoFinanceiro
{
    // Corpos auditados somente leitura: início da 1A e alteração concorrente
    // observada em 02/10/2026. Mudança futura exige nova auditoria, nunca eval cego.
    private const CONTROLLERS_AUDITADOS = [
        '710f76524b8ad7c3ff3729b21bbd7846124cc255ecf45c3fe96f2ab8d7f09026',
        'f39fd452d5eda4a6645f941f28a5d4f8d7cecddf625134d187ae8222595306fa',
        // FASE 1B: diff auditado; inclui resolução legada de tarifa para origem zerada.
        '21661cc051542744e507b15d2b4dcb38cb9388c39d0e57a61e936b9a1cbe4560',
    ];

    public static function lerLegadoA(mysqli $conn, int $colab, string $competencia): array
    {
        $source = file_get_contents(__DIR__ . '/../../Pagamento/getColaborador.php');
        $v2Hash = hash_file('sha256', __DIR__ . '/../../Pagamento/financeiro_v2.php');
        if (!in_array($v2Hash, ['aadf783fb5ed53891f6a8405cb704ebc8a0cea608a0dbb15e5e77c0d660a026c',
            '15d9815e905b379a551c8b97da09637dbc61bb04ae6cdbbf190776d85f72d1c8'], true)) {
            throw new RuntimeException('V2 legado mudou; auditar o novo SHA-256 antes de executar shadow.');
        }
        if (!in_array(hash('sha256', $source), self::CONTROLLERS_AUDITADOS, true)) {
            throw new RuntimeException('Controller legado mudou; auditar o novo SHA-256 antes de executar shadow.');
        }
        $start = strpos($source, '$colaboradorId = intval');
        $end = strpos($source, 'echo json_encode($response);');
        if ($start === false || $end === false || $end <= $start) throw new RuntimeException('Controller mudou; auditar o shadow.');
        $body = substr($source, $start, $end - $start);
        $body = str_replace("require_once __DIR__ . '/financeiro_v2.php';", '', $body);
        $body = preg_replace('/\berror_log\s*\(/', 'fechamento_shadow_log(', $body);
        $body = preg_replace('/\bpagamento_json\s*\(/', 'fechamento_shadow_error(', $body);
        if (preg_match('/\b(require|include|session_start|file_put_contents|fopen|fwrite|mkdir|rename|unlink|curl_exec|exec|financeiro_pagar|financeiro_lancar|financeiro_total)\s*(\(|_once\b)/', $body)) {
            throw new RuntimeException('Corpo legado contém operação não auditada.');
        }
        [$ano, $mes] = array_map('intval', explode('-', $competencia));
        $previous = $_GET;
        $_GET = ['colaborador_id' => $colab, 'mes_id' => $mes, 'ano' => $ano];
        try {
            $screen = (static function(mysqli $conn, string $body): array {
                eval($body);
                $stmt->close();
                return ['funcoes' => $response['funcoes']];
            })($conn, $body);
        } finally { $_GET = $previous; }
        return ['screen' => $screen, 'eligible' => financeiro_elegiveis($conn, $colab, $mes, $ano),
            'source_sha256' => hash('sha256', $source)];
    }

    private static function classe(array $row): string
    {
        if (!empty($row['comissao_gestor'])) return FechamentoFinanceiroRules::COMISSAO;
        return match ($row['origem']) {
            'funcao_imagem' => FechamentoFinanceiroRules::TAREFA,
            'funcao_animacao' => FechamentoFinanceiroRules::ANIMACAO,
            'acompanhamento' => FechamentoFinanceiroRules::ACOMPANHAMENTO,
        };
    }

    private static function documentoLegado(array $screen): array
    {
        $service = (new ReflectionClass(AdendoLocalService::class))->newInstanceWithoutConstructor();
        $build = new ReflectionMethod($service, 'buildRows');
        $normalize = new ReflectionMethod($service, 'normalizeItensInput');
        $result = [];
        foreach ($screen['funcoes'] as $row) {
            $norm = strtr(mb_strtolower($row['nome_funcao'] ?? ''), ['ã'=>'a','á'=>'a','â'=>'a','ç'=>'c']);
            $complete = str_contains($norm,'finalizacao') && str_contains($norm,'completa');
            $paid = (int)$row['pagamento'] === 1 && !($complete && (int)($row['pago_parcial_count'] ?? 0)>0
                && (int)($row['pago_completa_count'] ?? 0)===0);
            if ($paid) continue; // Comparação documental da aba A pagar, sem filtros.
            $payload = ['imagem_nome'=>$row['imagem_nome'] ?? '', 'nome_funcao'=>$row['nome_funcao'] ?? '',
                'valor'=>$row['valor_exibido'], 'data_pagamento'=>null,
                'pago_parcial_count'=>(int)($row['pago_parcial_count'] ?? 0),
                'pago_completa_count'=>(int)($row['pago_completa_count'] ?? 0)];
            $rows = $build->invoke($service, $normalize->invoke($service, [$payload]), true);
            if ($rows) $result[$row['origem'].':'.$row['identificador'].':'.self::classe($row)] = custos_centavos($rows[0]['valor_num']);
        }
        return $result;
    }

    private static function motivo(string $campo, ?array $antes, ?array $depois, ?array $eligible, ?array $origem): ?array
    {
        if ($campo === 'elegivel' && $depois) {
            if ($depois['identidade']['origem'] === 'funcao_imagem' && !empty($eligible['parcial']) && $depois['elegibilidade']['elegivel']) {
                return ['R05', 'Parcial auxiliar antes removido do controller agora reconhecido.'];
            }
            if ($depois['identidade']['origem'] === 'funcao_animacao'
                && substr((string)($origem['prazo'] ?? ''),0,7) !== substr((string)($origem['data_anima'] ?? ''),0,7)) {
                return ['R08', 'Competência da função usa prazo, não data operacional.'];
            }
        }
        if ($depois && in_array($campo, ['pago_centavos','saldo_centavos'], true)) {
            if ($depois[$campo] === null && array_filter($depois['divergencias'], fn($d) => in_array($d['codigo'],
                ['PAGAMENTO_SEM_LEDGER','ANIMACAO_LEGADA_AMBIGUA','CLASSE_LEDGER_NAO_RECONHECIDA'],true))) {
                return ['R15/D05', 'Sem evidência compatível, valor não é presumido; pendência explícita.'];
            }
        }
        if (in_array($campo, ['servico_documental','valor_servico_documental_centavos'], true) && $depois) {
            if (!$depois['elegibilidade']['elegivel'] && $depois['identidade']['origem']==='funcao_animacao') return ['R08','Função fora da competência financeira.'];
            if ($depois['divergencias']) return ['R15','Pendência não libera serviço financeiro definitivo.'];
            if ($depois['saldo_centavos']===0) return ['R06','Saldo zero não cria linha financeira devida.'];
            if ($depois['situacao']==='DEVIDO' && (!$antes || (int)($antes['pago_completa_count'] ?? 0)>0 || !empty($eligible['parcial']))) {
                return ['R05','Saldo positivo não desaparece por parcial/completa auxiliar.'];
            }
        }
        return null;
    }

    public static function comparar(array $screen, array $eligible, array $dados, array $target): array
    {
        $b = $target['colaborador_id'];
        $dueMap=[]; $dueSum=0; $internal=[];
        foreach ($target['servicos_devidos'] as $due) {
            $id=$due['identidade']; $k=$id['origem'].':'.$id['origem_id'].':'.$id['classe'];
            if (isset($dueMap[$k]) || $due['situacao']!=='DEVIDO' || !is_int($due['saldo_centavos']) || $due['saldo_centavos']<=0) {
                $internal[]=['identidade'=>$id,'campo'=>'servicos_devidos','antes'=>'DIREITO_UNICO_DEVIDO_POSITIVO','depois'=>'CONJUNTO_INCONSISTENTE',
                    'classificacao'=>'UNEXPECTED_DIFFERENCE','regra'=>null,'motivo'=>'Conjunto devido inválido.'];
            }
            $dueMap[$k]=$due; $dueSum=FechamentoFinanceiroRules::somar($dueSum,$due['saldo_centavos']??0);
        }
        $old = []; $eligibleMap = []; $origens = []; $new = [];
        foreach ($eligible as $r) $eligibleMap[$r['origem'].':'.$r['origem_id'].':'.self::classe($r)]=$r;
        foreach ($dados['origens'] as $r) $origens[$r['origem'].':'.$r['origem_id']]=$r;
        foreach ($screen['funcoes'] as $r) $old[$r['origem'].':'.$r['identificador'].':'.self::classe($r)]=$r;
        foreach ($target['itens_analisados'] as $r) {
            $id=$r['identidade']; $key=$id['origem'].':'.$id['origem_id'].':'.$id['classe'];
            if ($r['elegibilidade']['elegivel'] || isset($old[$key])) $new[$key]=$r;
        }
        $doc=self::documentoLegado($screen); $keys=array_unique(array_merge(array_keys($old),array_keys($new))); sort($keys);
        $diff=$internal; $rights=[];
        foreach ($keys as $key) {
            $a=$old[$key]??null; $n=$new[$key]??null; $rawKey=$n?$n['identidade']['origem'].':'.$n['identidade']['origem_id']:$a['origem'].':'.$a['identificador'];
            $e=$eligibleMap[$key]??null; $o=$origens[$rawKey]??null;
            $paid=0;
            if ($a) foreach ($dados['ledger'] as $p) {
                if ((int)($p['beneficiario_id']??$p['colaborador_id']??0)===$b && $p['origem']===$a['origem']
                    && (int)$p['origem_id']===(int)$a['identificador']
                    && (custos_tipo($p)==='COMISSAO')===(!empty($a['comissao_gestor']))) $paid+=custos_centavos($p['valor']);
            }
            $before=['elegivel'=>$a!==null,'base_centavos'=>$a?custos_centavos($a['custo']):null,
                'pago_centavos'=>$a?$paid:null,'saldo_centavos'=>$a?custos_centavos($a['valor_exibido']):null,
                'servico_documental'=>array_key_exists($key,$doc), 'valor_servico_documental_centavos'=>$doc[$key]??0];
            $after=['elegivel'=>$n? $n['elegibilidade']['elegivel']:false,'base_centavos'=>$n?$n['base_centavos']:null,
                'pago_centavos'=>$n?$n['pago_centavos']:null,'saldo_centavos'=>$n?$n['saldo_centavos']:null,
                'servico_documental'=>isset($dueMap[$key]), 'valor_servico_documental_centavos'=>$dueMap[$key]['saldo_centavos']??0];
            if ($n && ($n['situacao']==='DEVIDO')!==isset($dueMap[$key])) {
                $diff[]=['identidade'=>$n['identidade'],'campo'=>'servicos_devidos','antes'=>$n['situacao'],
                    'depois'=>isset($dueMap[$key]),'classificacao'=>'UNEXPECTED_DIFFERENCE','regra'=>null,
                    'motivo'=>'Lista devidos diverge da situação do direito analisado.'];
            }
            foreach ($before as $field=>$value) {
                if ($value===$after[$field]) continue;
                // Valores de direito adicionado/removido são expostos, mas não comparados a um null inexistente.
                if (in_array($field,['base_centavos','pago_centavos','saldo_centavos'],true) && (!$a || !$n)) continue;
                $reason=self::motivo($field,$a,$n,$e,$o);
                $diff[]=['identidade'=>$n?$n['identidade']:['beneficiario_id'=>$b,'origem'=>$a['origem'],'origem_id'=>(int)$a['identificador'],'classe'=>self::classe($a)],
                    'campo'=>$field,'antes'=>$value,'depois'=>$after[$field],
                    'classificacao'=>$reason?'EXPECTED_TARGET_CHANGE':'UNEXPECTED_DIFFERENCE',
                    'regra'=>$reason[0]??null,'motivo'=>$reason[1]??'Diferença não explicada pelas regras autorizadas.'];
            }
            $rights[]=['chave'=>$key,'legado_a'=>$before,'canonico'=>$after,'motivo_exclusao'=>$n['motivo_exclusao']??null];
        }
        foreach ($target['pendencias'] as $p) {
            $known=in_array($p['codigo'],['PAGAMENTO_SEM_LEDGER','PAGO_ACIMA_DO_DEVIDO','ANIMACAO_LEGADA_AMBIGUA','CLASSE_LEDGER_NAO_RECONHECIDA'],true);
            $diff[]=['identidade'=>$p['identidade'],'campo'=>'pendencia_estruturada',
            'antes'=>null,'depois'=>$p['codigo'],'classificacao'=>'EXPECTED_TARGET_CHANGE','regra'=>'R15/D05',
            'motivo'=>'Diagnóstico bloqueante estruturado conforme contrato; não altera os dados.'];
            if (!$known) { $last=array_key_last($diff);$diff[$last]['classificacao']='UNEXPECTED_DIFFERENCE';$diff[$last]['regra']=null; }
        }
        if ($dueSum!==$target['subtotal_servicos_centavos']) {
            $diff[]=['identidade'=>['beneficiario_id'=>$b],'campo'=>'subtotal_servicos_centavos',
                'antes'=>$dueSum,'depois'=>$target['subtotal_servicos_centavos'],'classificacao'=>'UNEXPECTED_DIFFERENCE',
                'regra'=>null,'motivo'=>'Subtotal declarado diverge da soma dos serviços retornados.'];
        }
        if (array_sum($doc)!==$target['subtotal_servicos_centavos']) {
            $unexpected=count(array_filter($diff,fn($d)=>$d['classificacao']==='UNEXPECTED_DIFFERENCE'))>0;
            $diff[]=['identidade'=>['beneficiario_id'=>$b],'campo'=>'subtotal_documental_a_vs_servicos',
                'antes'=>array_sum($doc),'depois'=>$target['subtotal_servicos_centavos'],
                'classificacao'=>$unexpected?'UNEXPECTED_DIFFERENCE':'EXPECTED_TARGET_CHANGE',
                'regra'=>$unexpected?null:'R05/R06/R08/R15',
                'motivo'=>'Subtotal confrontado com as mudanças de cada direito, sem alterar a projeção histórica.'];
        }
        return ['colaborador_id'=>$b,'competencia'=>$target['competencia'],'snapshot_em'=>$target['snapshot_em'],
            'rule_version'=>$target['rule_version'],'legacy_a_direitos'=>count($old),'canonico_direitos_elegiveis'=>count(array_filter($new,fn($i)=>$i['elegibilidade']['elegivel'])),
            'canonico_itens_analisados'=>count($target['itens_analisados']),
            'legacy_a_documento_projetado_servicos_centavos'=>array_sum($doc),
            'canonico_subtotal_servicos_centavos'=>$target['subtotal_servicos_centavos'],
            'subtotal_completo'=>$target['subtotal_servicos_completo'],'bloqueado'=>$target['bloqueado'],
            'direitos'=>$rights,'pendencias'=>$target['pendencias'],'diferencas'=>$diff,
            'resumo_classificacoes'=>array_count_values(array_column($diff,'classificacao')),
            'limites'=>'Comparação A/V2 + projeção estática da aba A pagar sem filtros; não executa UI/PDF, fixo/extras ou motor B.'];
    }
}
