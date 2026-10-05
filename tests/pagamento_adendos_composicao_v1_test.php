<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/fixtures/pagamento_adendos_composicao.php';
require_once __DIR__ . '/../scripts/diagnostico/ComparacaoComposicaoFinanceira.php';

$checks=0;
function comp_eq($e,$a,string $m):void { global $checks;$checks++;if($e!==$a)throw new RuntimeException($m.': '.json_encode([$e,$a])); }
function comp_throws(callable $fn,string $m):void {global $checks;$checks++;try{$fn();}catch(Throwable $e){return;}throw new RuntimeException('Não rejeitou: '.$m);}
function comp_codes(array $r):array {return array_column($r['pendencias'],'codigo');}
$rules=new FechamentoComposicaoRules(); $s=composicao_fixture_servicos();
$c=composicao_fixture_contexto($s,'1500');$c['fixo']['evidencias_liquidacao']=[composicao_fixture_apuracao_sem_pagamento($s)];
$r=$rules->compor($s,$c);
comp_eq(350000,$r['total_final_centavos'],'A/T12 serviços+fixo evidenciado');comp_eq(true,$r['total_final_determinado'],'A determinado');
comp_eq('PRONTO',$r['situacao'],'A pronto');comp_eq($s,$r['financeiro_servicos'],'consome 1A sem mudar nada');comp_eq([],$r['pendencias'],'SEM_BONUS explícito sem pendência');
// B/T15: zero conhecido de extras é diferente de não haver decisão.
$b=$c;$b['extras']=['estado'=>'PENDENTE','itens'=>[]];$r=$rules->compor($s,$b);
comp_eq(350000,$r['componentes_conhecidos_centavos'],'B componentes conhecidos');comp_eq(null,$r['total_final_centavos'],'B total desconhecido');
comp_eq(false,$r['total_final_determinado'],'B não determinado');comp_eq('PENDENTE_BONUS',$r['situacao'],'B estado');
comp_eq(['BONUS_PENDENTE'],comp_codes($r),'B pendência');comp_eq(null,$r['extras']['subtotal_centavos'],'bônus não vira zero');
// C/T13 NULL/ausência e D/zero conhecido.
foreach([null,'AUSENTE'] as $v){$x=composicao_fixture_contexto($s,$v);if($v==='AUSENTE')unset($x['fixo']['configurado']);$r=$rules->compor($s,$x);
 comp_eq('NAO_DEFINIDO',$r['fixo']['estado'],'fixo ausente');comp_eq(null,$r['fixo']['configurado_centavos'],'NULL não é zero');
 comp_eq(null,$r['total_final_centavos'],'NULL indeterminado');comp_eq(true,in_array('FIXO_NAO_DEFINIDO',comp_codes($r),true),'pendência NULL');}
$zero=composicao_fixture_contexto($s,'0.00');$r=$rules->compor($s,$zero);
comp_eq('DEFINIDO',$r['fixo']['estado'],'D zero definido');comp_eq(0,$r['fixo']['saldo_centavos'],'D zero saldo');
comp_eq(200000,$r['total_final_centavos'],'D zero sem obrigação');comp_eq('NAO_APLICAVEL_VALOR_ZERO',$r['fixo']['estado_liquidacao'],'D não requer inferir pagamento');
$x=composicao_fixture_contexto($s,null);$x['fixo']['decisao']=composicao_fixture_registro($s,'VALOR_FIXO','sem-fixo',['estado'=>'SEM_VALOR_FIXO','original_centavos'=>null]);
$r=$rules->compor($s,$x);comp_eq('SEM_VALOR_FIXO',$r['fixo']['estado'],'decisão explícita sem fixo');comp_eq(200000,$r['total_final_centavos'],'SEM_VALOR_FIXO determina zero');
// Configuração/assinatura/status/total/PDF não provam saldo positivo nem quitação.
$x=composicao_fixture_contexto($s,'4600');$x['fixo']['evidencias_legadas']=['pagamentos_agregados'=>[['status'=>'pago','valor_total'=>4600]],'adendos'=>[['status'=>'assinado','VALOR_FIXO'=>4600,'VALOR_TOTAL'=>4600,'pdf'=>'existente']]];
$r=$rules->compor($s,$x);comp_eq(460000,$r['fixo']['configurado_centavos'],'configurado não é pendente');
comp_eq(null,$r['fixo']['pago_centavos'],'legado não prova pago');comp_eq(null,$r['fixo']['saldo_centavos'],'legado não prova saldo');
comp_eq('LIQUIDACAO_INDETERMINADA',$r['fixo']['estado_liquidacao'],'legado indeterminado');comp_eq(200000,$r['componentes_conhecidos_centavos'],'configuração não aumenta devido conhecido');
comp_eq(['FIXO_LIQUIDACAO_INDETERMINADA'],comp_codes($r),'D01 diagnóstico');comp_eq(null,$r['total_final_centavos'],'D01 total desconhecido');
// T11: dados e composição já calculada são cópias congeladas em memória.
$frozen=$rules->compor($s,$c);$changed=$c;$changed['fixo']['configurado']='2000';
comp_eq(150000,$frozen['fixo']['utilizado_centavos'],'T11 valor congelado');comp_eq(350000,$frozen['total_final_centavos'],'T11 total congelado');
comp_eq(400000,$rules->compor($s,$changed)['total_final_centavos'],'T11 nova composição explícita usa novo cadastro');
// T12 pagamento discriminado total/parcial, evidência errada/conflitante/duplicada.
$paid=$c;$ev=composicao_fixture_registro($s,'VALOR_FIXO','pagamento-fixo-1',['tipo'=>'PAGAMENTO_FIXO','valor_centavos'=>150000]);
$paid['fixo']['evidencias_liquidacao']=[$ev];$r=$rules->compor($s,$paid);
comp_eq(150000,$r['fixo']['pago_centavos'],'T12 fixo pago comprovado');comp_eq(0,$r['fixo']['saldo_centavos'],'T12 quitado');comp_eq(200000,$r['total_final_centavos'],'T12 não recobra fixo');
$paid['fixo']['evidencias_liquidacao'][0]['valor_centavos']=50000;$r=$rules->compor($s,$paid);
comp_eq(100000,$r['fixo']['saldo_centavos'],'T12 parcial');comp_eq(300000,$r['total_final_centavos'],'T12 total parcial');
foreach(['colaborador_id'=>8,'competencia'=>'2026-09','classe'=>'REMUNERACAO_TAREFA','registrado_em'=>'2026-10-03T12:00:00-03:00','autor_id'=>0,'tipo'=>'STATUS_PAGO','valor_centavos'=>-1] as $field=>$v){
 $bad=$c;$bad['fixo']['evidencias_liquidacao']=[array_replace($ev,[$field=>$v])];$r=$rules->compor($s,$bad);
 comp_eq(null,$r['fixo']['pago_centavos'],'evidência errada '.$field);comp_eq(null,$r['total_final_centavos'],'bloqueio evidência '.$field);}
$bad=$c;$bad['fixo']['evidencias_liquidacao']=[$ev,$ev];comp_eq(null,$rules->compor($s,$bad)['total_final_centavos'],'duplicação não soma pagamento');
$bad=$c;$bad['fixo']['evidencias_liquidacao']=[$ev,composicao_fixture_apuracao_sem_pagamento($s)];comp_eq(null,$rules->compor($s,$bad)['total_final_centavos'],'apuração zero conflita com pago');
$bad=$c;$bad['fixo']['evidencias_liquidacao']=[array_replace($ev,['valor_centavos'=>160000])];$r=$rules->compor($s,$bad);
comp_eq(10000,$r['fixo']['excesso_centavos'],'fixo excesso');comp_eq(null,$r['total_final_centavos'],'excesso bloqueia total');
// T13 override auditável sem alterar cadastro; NULL pode receber valor explícito.
$ov=$c;$ov['fixo']['override']=composicao_fixture_registro($s,'VALOR_FIXO','override-1',['original_centavos'=>150000,'substituto_centavos'=>180000]);
$r=$rules->compor($s,$ov);comp_eq(150000,$r['fixo']['configurado_centavos'],'override conserva original');comp_eq(180000,$r['fixo']['utilizado_centavos'],'override substitui');
comp_eq(380000,$r['total_final_centavos'],'override não soma duas bases');comp_eq('1500',$ov['fixo']['configurado'],'cadastro input intocado');comp_eq($ov['fixo']['override'],$r['fixo']['override'],'conserva autor/instante/motivo');
foreach(['motivo'=>'','original_centavos'=>999,'substituto_centavos'=>-1,'colaborador_id'=>8] as $field=>$v){$bad=$ov;$bad['fixo']['override'][$field]=$v;comp_eq(null,$rules->compor($s,$bad)['total_final_centavos'],'override inválido '.$field);}
$ov['fixo']['configurado']=null;$ov['fixo']['override']['original_centavos']=null;comp_eq(380000,$rules->compor($s,$ov)['total_final_centavos'],'NULL com override explícito');
// T10/E Nicolle limpo: subtotal2415 contém acompanhamento individual de1000 uma vez.
$n=composicao_fixture_servicos(1,241500,100000);$nc=composicao_fixture_contexto($n,'3000','DEFINIDO');
$nc['fixo']['evidencias_liquidacao']=[composicao_fixture_apuracao_sem_pagamento($n)];
$extra=composicao_fixture_registro($n,'BONUS_EXTRAS','extra-1',['categoria'=>'Bônus aprovado','valor'=>'R$ 500,00']);$nc['extras']['itens']=[$extra];
$r=$rules->compor($n,$nc);comp_eq(991500,$r['total_final_centavos'],'E/Nicolle9915');comp_eq(241500,$r['financeiro_servicos']['subtotal_servicos_centavos'],'individual já incluído');
comp_eq(400000,$r['acompanhamento_especial']['valor_centavos'],'R09 especial4000');comp_eq(50000,$r['extras']['subtotal_centavos'],'R09 não elimina bônus');
comp_eq(2,count($r['financeiro_servicos']['servicos_devidos']),'não duplica acompanhamento');
$nb=$nc;$nb['extras']=composicao_fixture_contexto($n,'3000')['extras'];$r=$rules->compor($n,$nb);
comp_eq(400000,$r['acompanhamento_especial']['valor_centavos'],'SEM_BONUS preserva4000');comp_eq(941500,$r['total_final_centavos'],'Nicolle SEM_BONUS');
// Validações de rubricas, incluindo decisão explícita, escopo, autoria e moeda.
foreach(['valor'=>0,'valor_negativo'=>-50,'categoria'=>'','autor_id'=>0,'colaborador_id'=>7,'competencia'=>'2026-09','classe'=>'VALOR_FIXO','registrado_em'=>'2026-10-03T12:00:00-03:00','referencia'=>''] as $field=>$v){
 $bad=$nc;$bad['extras']['itens'][0][$field==='valor_negativo'?'valor':$field]=$v;$r=$rules->compor($n,$bad);
 comp_eq(null,$r['total_final_centavos'],'extra inválido '.$field);comp_eq(true,in_array('EXTRA_INVALIDO',comp_codes($r),true),'pendência extra '.$field);}
$bad=$nc;$bad['extras']['itens']=[];comp_eq(null,$rules->compor($n,$bad)['extras']['subtotal_centavos'],'DEFINIDO vazio não é SEM_BONUS');
$bad=$nc;$bad['extras']['itens'][]=$extra;comp_eq(0,$rules->compor($n,$bad)['extras']['subtotal_conhecido_centavos'],'duplicados não presumem qual rubrica vale');
$bad=$nc;unset($bad['extras']['decisao']);comp_eq(0,$rules->compor($n,$bad)['extras']['subtotal_conhecido_centavos'],'rubrica sem decisão não autorizada');
$bad=$nc;$bad['extras']['itens'][]=array_replace($extra,['referencia'=>'extra-2','valor'=>-100]);$r=$rules->compor($n,$bad);
comp_eq(50000,$r['extras']['subtotal_conhecido_centavos'],'extra válido conhecido preservado');comp_eq(null,$r['total_final_centavos'],'extra inválido não vira desconto');
$two=$nc;$two['extras']['itens'][]=array_replace($extra,['referencia'=>'extra-2','valor'=>'100,35']);
comp_eq(60035,$rules->compor($n,$two)['extras']['subtotal_centavos'],'rubricas válidas são somadas em centavos');
comp_eq(1001535,$rules->compor($n,$two)['total_final_centavos'],'total com dois extras exatos');
$bad=$c;unset($bad['extras']);comp_eq('PENDENTE',$rules->compor($s,$bad)['extras']['estado'],'ausência não equivale a sem bônus');
$bad=$c;unset($bad['extras']['decisao']);comp_eq(null,$rules->compor($s,$bad)['total_final_centavos'],'SEM_BONUS sem registro não é explícito');
$bad=$nc;$bad['extras']['estado']='SEM_BONUS';comp_eq(null,$rules->compor($n,$bad)['total_final_centavos'],'SEM_BONUS com rubrica conflita');
// F propagação completa do resultado 1A: conhecido500, AC desconhecido, fixo1000.
$partial=composicao_fixture_servicos(7,50000);$origem=['origem'=>'acompanhamento','origem_id'=>9900003,'colaborador_id'=>7,'data'=>'2026-08-20','valor'=>10,'pagamento'=>1];
$partial=(new FechamentoFinanceiroRules())->calcular(['origens'=>[
 ['origem'=>'funcao_imagem','origem_id'=>9900001,'colaborador_id'=>7,'funcao_id'=>2,'valor'=>'500.00','prazo'=>'2026-08-31','status'=>'Finalizado','pagamento'=>0],$origem],
 'ledger'=>[]],7,'2026-08',new DateTimeImmutable($partial['snapshot_em']));
$pc=composicao_fixture_contexto($partial,'1000');$pc['fixo']['evidencias_liquidacao']=[composicao_fixture_apuracao_sem_pagamento($partial)];$r=$rules->compor($partial,$pc);
comp_eq(150000,$r['componentes_conhecidos_centavos'],'F conhecido1500');comp_eq(null,$r['total_final_centavos'],'F nunca declara total');
comp_eq($partial['pendencias'][0],$r['pendencias'][0],'pendência original preservada');comp_eq($partial,$r['financeiro_servicos'],'1A não recalculada');
$partial['subtotal_servicos_completo']=true;$r=$rules->compor($partial,$pc);comp_eq(false,$r['total_final_determinado'],'bloqueio mesmo com subtotal completo');
comp_eq(350000,$rules->compor($s,$c)['total_final_centavos'],'pendência individual não contamina outros');
// Casos reais congelados: nenhuma assinatura/total é prova de pagamento de fixo.
$g=financeiro_test_golden();
foreach(['7/2026-08','4/2026-08','1/2026-08'] as $pair){$cx=composicao_fixture_golden_contexto($g,$pair);$ss=composicao_fixture_servicos($cx['colaborador_id'],0);$r=$rules->compor($ss,$cx);
 comp_eq(null,$r['total_final_centavos'],'real bônus desconhecido '.$pair);comp_eq('PENDENTE',$r['extras']['estado'],'real não inferir ausência bônus '.$pair);
 if($cx['colaborador_id']!==4)comp_eq(null,$r['fixo']['saldo_centavos'],'real liquidação desconhecida '.$pair);else comp_eq(0,$r['fixo']['saldo_centavos'],'real zero conhecido');}
// Fronteira monetária exata, formatos brasileiros, rejeição de ambiguidades e overflow.
foreach(['1500'=>150000,'1500.50'=>150050,'1500,50'=>150050,'1.500,50'=>150050,'R$ 1.500,50'=>150050,'R$ 1.500'=>150000,'0,10'=>10,'0.20'=>20,'12.34'=>1234] as $valor=>$cents)comp_eq($cents,FechamentoComposicaoSupport::moeda((string)$valor),'moeda '.$valor);
comp_eq(1234,FechamentoComposicaoSupport::moeda(12.34),'número JSON na borda');
comp_eq(30,FechamentoFinanceiroRules::somar(FechamentoComposicaoSupport::moeda('0,10'),FechamentoComposicaoSupport::moeda('0.20')),'centavos exatos');
foreach([null,'','1.500','1,500.00','2.005','NaN','1e3','1 500,00','1.50,00',true,INF,(string)PHP_INT_MAX] as $v)comp_throws(fn()=>FechamentoComposicaoSupport::moeda($v),'moeda inválida');
comp_throws(fn()=>FechamentoComposicaoSupport::valor(['valor'=>'1.00','valor_centavos'=>99]),'valores conflitantes');
comp_throws(fn()=>FechamentoComposicaoSupport::instante('2026-02-30T12:00:00-03:00'),'data inválida');
comp_throws(fn()=>FechamentoComposicaoSupport::instante('2026-09-30T12:00:00+99:99'),'fuso inválido');
$wrong=$c;$wrong['competencia']='2026-09';comp_throws(fn()=>$rules->compor($s,$wrong),'competência misturada');
$wrong=$c;$wrong['snapshot_em']='2026-10-03T12:00:00-03:00';comp_throws(fn()=>$rules->compor($s,$wrong),'snapshot misturado');
$wrong=$s;$wrong['rule_version']='outra';comp_throws(fn()=>$rules->compor($wrong,$c),'versão desconhecida');
$overflow=$s;$overflow['subtotal_servicos_centavos']=PHP_INT_MAX;comp_throws(fn()=>$rules->compor($overflow,$c),'overflow da composição');
foreach($rules->compor($s,composicao_fixture_contexto($s,null,'PENDENTE'))['pendencias'] as $p) foreach(['codigo','bloqueante','componente','valores','evidencias','mensagem','identidade'] as $key)comp_eq(true,array_key_exists($key,$p),'pendência explicável '.$key);
// Diagnóstico não esconde configuração/rubrica especial/total adulterado.
$goldCx=composicao_fixture_golden_contexto($g,'7/2026-08');$goldS=composicao_fixture_servicos(7,0);$goldR=$rules->compor($goldS,$goldCx);
$legacyRule=ComparacaoComposicaoFinanceira::regraEspecialLegada(7);
$ok=ComparacaoComposicaoFinanceira::comparar($goldCx,$goldR,$legacyRule);
comp_eq(0,$ok['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow correto');
$tampered=$goldR;$tampered['fixo']['configurado_centavos']=1;
comp_eq(1,ComparacaoComposicaoFinanceira::comparar($goldCx,$tampered,$legacyRule)['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow detecta configuração');
$tampered=$goldR;$tampered['acompanhamento_especial']['valor_centavos']=1;
comp_eq(1,ComparacaoComposicaoFinanceira::comparar($goldCx,$tampered,$legacyRule)['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow detecta especial');
$tampered=$goldR;$tampered['fixo']['pago_centavos']=0;
comp_eq(1,ComparacaoComposicaoFinanceira::comparar($goldCx,$tampered,$legacyRule)['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow não aceita ausência de ledger como zero');
$tampered=$goldR;$tampered['total_final_determinado']=true;$tampered['total_final_centavos']=0;
comp_eq(1,ComparacaoComposicaoFinanceira::comparar($goldCx,$tampered,$legacyRule)['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow total indevido');
// Recorte AC617 real conserva pendência. Fixo apurado abaixo é cenário em memória.
$acCase=financeiro_test_caso($g,'CASE_ACOMPANHAMENTO_001');
$acS=(new FechamentoFinanceiroRules())->calcular($acCase['dados'],1,'2025-01',$acCase['snapshot']);
$acC=composicao_fixture_contexto($acS,'3000');$acC['fixo']['evidencias_liquidacao']=[composicao_fixture_apuracao_sem_pagamento($acS)];$acR=$rules->compor($acS,$acC);
comp_eq(null,$acR['total_final_centavos'],'AC617 histórico não é fixture limpo');comp_eq($acS['pendencias'][0],$acR['pendencias'][0],'AC617 evidência real preservada');
echo "OK: $checks verificações de composição FASE 1B, offline; nenhum DB/PDF/adendo.\n";
