<?php
require_once __DIR__.'/FechamentoExtrasRules.php';
require_once __DIR__.'/FechamentoRubricasEspeciais.php';
require_once __DIR__.'/FechamentoProdutividadeRules.php';

/** Composição mensal sobre os serviços 1A; liquidação não define o fixo do adendo. */
final class FechamentoMensalRules
{
    public const VERSION = 'fechamento_mensal_v1';
    public function compor(array $servicos, array $contexto): array
    {
        $b=$servicos['colaborador_id']; $ref=$servicos['competencia'];
        $tipo=$contexto['tipo_remuneracao'] ?? null; $pend=[];
        $variavel=in_array($tipo,['VARIAVEL','FIXO_VARIAVEL'],true);
        $usaFixo=in_array($tipo,['FIXO','FIXO_VARIAVEL'],true);
        $add=function(string $codigo,string $componente,string $mensagem) use (&$pend,$b,$ref) {
            $pend[]=FechamentoComposicaoSupport::pendencia($codigo,$componente,$b,$ref,[],[],$mensagem);
        };
        if (!in_array($tipo,['FIXO','VARIAVEL','FIXO_VARIAVEL'],true)) $add('REMUNERACAO_AUSENTE','CADASTRO','Forma de remuneração não configurada.');
        $fixo=0; $config=$contexto['fixo']['configurado'] ?? null;
        if ($usaFixo) {
            if ($config === null) { $fixo=null; $add('FIXO_NAO_DEFINIDO','VALOR_FIXO','Valor fixo não configurado.'); }
            else {
                try { $fixo=FechamentoComposicaoSupport::moeda($config); if ($fixo<0) throw new InvalidArgumentException(); }
                catch(Throwable $e) { $fixo=null; $add('FIXO_INVALIDO','VALOR_FIXO','Valor fixo inválido. Confira o cadastro.'); }
            }
        }
        $snapshot=FechamentoComposicaoSupport::instante($servicos['snapshot_em']);
        $raw=$contexto['extras'] ?? [];
        // Ausência e antigas decisões PENDENTE sem rubricas representam zero; não fabricar um ato humano.
        if (!$raw || (($raw['estado']??'PENDENTE')==='PENDENTE' && empty($raw['itens'])) || (($raw['estado']??'')==='SEM_BONUS' && empty($raw['decisao']))) {
            $extras=['estado'=>'SEM_BONUS','decisao'=>null,'itens'=>[],'subtotal_centavos'=>0,'subtotal_conhecido_centavos'=>0,'determinado'=>true,'pendencias'=>[]];
        } else $extras=(new FechamentoExtrasRules())->calcular($raw,$b,$ref,$snapshot);
        $imagensRetiradas=[];
        foreach($servicos['itens_analisados'] as $item) if ($item['situacao']==='RETIRADO' && $item['identidade']['origem']==='funcao_imagem' && $item['identidade']['classe']===FechamentoFinanceiroRules::TAREFA && (int)($item['descricao']['funcao_id']??0)===4) $imagensRetiradas[(int)$item['descricao']['imagem_id']]=true;
        $eventosBonus=array_values(array_filter($contexto['eventos_r0']??[],fn($e)=>!isset($imagensRetiradas[(int)$e['imagem_id']])));
        $bonus=(new FechamentoProdutividadeRules())->calcular($eventosBonus,$ref,$variavel);
        foreach ($bonus['pendencias'] as $p) $add('BONUS_R0_INDETERMINADO','BONUS_PRODUTIVIDADE',$p);
        $especial=(new FechamentoRubricasEspeciais())->obter($b,$ref);
        // No mensal, o acompanhamento da Nicolle é o próprio valor contratado do cadastro.
        // A antiga parcela contratual de 4000 não é um segundo direito a somar.
        $acompanhamentoFixo=$especial['aplicavel'] && $usaFixo;
        $especial['aplicavel']=$acompanhamentoFixo;
        $especial['valor_centavos']=$acompanhamentoFixo?$fixo:0;
        $especial['regra']=$acompanhamentoFixo?'MENSAL_ACOMPANHAMENTO_VALOR_CADASTRO':'MENSAL_SEM_ACOMPANHAMENTO_FIXO';
        $especial['origem']=$acompanhamentoFixo?'COLABORADOR_VALOR_FIXO':$especial['origem'];
        // No ciclo oficial, o que já foi pago nesta mesma competência integra o direito reconhecido.
        // Pagamentos de outras competências continuam reduzindo o saldo operacional existente.
        $creditoCompetencia=0;
        if (!empty($contexto['competencia_oficial'])) {
            foreach($servicos['itens_analisados'] as &$item) {
                $credito=0;
                if ($variavel && $item['elegibilidade']['elegivel']) foreach($item['pagamentos'] as $payment) {
                    if (($payment['competencia_pagamento']??null)===$ref) $credito=FechamentoFinanceiroRules::somar($credito,$payment['valor_centavos']);
                }
                $item['valor_reconhecido_centavos']=$variavel && $item['saldo_centavos']!==null?FechamentoFinanceiroRules::somar($item['saldo_centavos'],$credito):0;
                $creditoCompetencia=FechamentoFinanceiroRules::somar($creditoCompetencia,$credito);
            }
            unset($item);
        }
        $financeiro=$servicos;
        if (!$variavel) {
            $financeiro['servicos_devidos']=[]; $financeiro['subtotal_servicos_centavos']=0;
            $financeiro['pendencias']=[]; $financeiro['bloqueado']=false; $financeiro['subtotal_servicos_completo']=true;
        }
        $pend=array_merge($pend,$financeiro['pendencias'],$extras['pendencias']);
        $mensagens=['PAGAMENTO_SEM_LEDGER'=>'Existe indicação de pagamento sem lançamento correspondente. Confira o serviço na origem.',
            'PAGO_ACIMA_DO_DEVIDO'=>'Os pagamentos registrados superam o valor do serviço. Confira os lançamentos.',
            'CLASSE_LEDGER_NAO_RECONHECIDA'=>'Um lançamento precisa de conferência para determinar o valor variável.',
            'ANIMACAO_LEGADA_AMBIGUA'=>'O pagamento de animação precisa de conferência na origem.'];
        foreach($pend as &$p) if(isset($mensagens[$p['codigo']])) $p['mensagem']=$mensagens[$p['codigo']];
        unset($p);
        if ($variavel && ($servicos['bloqueado'] || !$servicos['subtotal_servicos_completo']) && !$servicos['pendencias']) $add('SERVICOS_INDETERMINADOS','SERVICOS','Não foi possível determinar o valor variável. Confira os serviços.');
        $componentes=['SERVICOS'=>FechamentoFinanceiroRules::somar($financeiro['subtotal_servicos_centavos'],$creditoCompetencia),'VALOR_FIXO'=>$acompanhamentoFixo?0:$fixo,
            'ACOMPANHAMENTO_ESPECIAL'=>$especial['valor_centavos'],'BONUS_EXTRAS'=>$extras['subtotal_centavos'],
            'BONUS_PRODUTIVIDADE'=>$bonus['valor_centavos']];
        $total=0; foreach($componentes as $v) $total=FechamentoFinanceiroRules::somar($total,$v??0);
        $desconto=$contexto['desconto']??null;
        if ($desconto!==null) {
            $valor=$desconto['valor_centavos']??null;
            if (!is_int($valor) || $valor<0 || trim($desconto['motivo']??'')==='' || $valor>$total) {
                $add('DESCONTO_INVALIDO','DESCONTO','Desconto inválido ou superior ao total. Confira o valor e o motivo.');
            } else { $componentes['DESCONTO']=-$valor; $total=FechamentoFinanceiroRules::somar($total,-$valor); }
        }
        $bloqueado=(bool)$pend; $determinado=!$bloqueado;
        return ['colaborador_id'=>$b,'competencia'=>$ref,'snapshot_em'=>$servicos['snapshot_em'],'timezone'=>$servicos['timezone'],
            'rule_version'=>FechamentoComposicaoRules::VERSION,'monthly_rule_version'=>self::VERSION,'servicos_rule_version'=>$servicos['rule_version'],
            'pagamentos_competencia_incluidos_centavos'=>$creditoCompetencia,'competencia_oficial'=>(bool)($contexto['competencia_oficial']??false),
            'tipo_remuneracao'=>$tipo,'participa_fechamento_mensal'=>$contexto['participa_fechamento_mensal']??null,'financeiro_servicos'=>$financeiro,'producao_consulta'=>$servicos,
            'fixo'=>['estado'=>$fixo===null?'NAO_DEFINIDO':'DEFINIDO','configurado_centavos'=>$usaFixo?$fixo:null,
                'utilizado_centavos'=>$fixo,'saldo_centavos'=>$fixo,'pago_centavos'=>null,'estado_liquidacao'=>'FORA_DO_FECHAMENTO',
                'origem'=>'COLABORADOR_VALOR_FIXO','rubrica_documental'=>$acompanhamentoFixo?'Acompanhamento':null,'override'=>null,'decisao'=>null,'evidencias_liquidacao'=>[],'pendencias'=>[]],
            'extras'=>$extras,'desconto'=>$desconto,'retiradas'=>$contexto['retiradas']??[],'bonus_produtividade'=>$bonus,'acompanhamento_especial'=>$especial,'componentes'=>$componentes,
            'componentes_indeterminados'=>array_keys(array_filter($componentes,fn($v)=>$v===null)),
            'componentes_conhecidos_centavos'=>$total,'total_final_centavos'=>$determinado?$total:null,
            'total_final_determinado'=>$determinado,'pendencias'=>$pend,'bloqueado'=>$bloqueado,'situacao'=>$determinado?'PRONTO':'ATENCAO'];
    }
}
