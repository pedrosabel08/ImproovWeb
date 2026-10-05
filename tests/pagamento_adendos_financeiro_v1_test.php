<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../Pagamento/services/FechamentoFinanceiroRules.php';
require_once __DIR__ . '/../Pagamento/services/FechamentoFinanceiroReadOnlyConnection.php';
require_once __DIR__ . '/../scripts/diagnostico/ComparacaoFechamentoFinanceiro.php';
require_once __DIR__ . '/fixtures/pagamento_adendos_financeiro.php';

$checks=0;
function alvo_eq($expected, $actual, string $message): void
{
    global $checks; $checks++;
    if ($expected !== $actual) throw new RuntimeException($message . ': esperado ' . json_encode($expected) . ', recebido ' . json_encode($actual));
}
function alvo_throws(callable $fn, string $message): void
{
    global $checks; $checks++;
    try { $fn(); } catch (Throwable $e) { return; }
    throw new RuntimeException('Não rejeitou: ' . $message);
}
$golden=financeiro_test_golden(); $rules=new FechamentoFinanceiroRules(); $results=[];
// Expectativas ALVO explícitas; os números históricos/documentais da golden não são alterados.
$expected=[
    'CASE_NORMAL_001'=>[5000,0,5000,0,'DEVIDO',true,5000,false],
    'CASE_PARCIAL_001'=>[25000,12500,12500,0,'DEVIDO',true,12500,false],
    'CASE_PAGO_001'=>[38000,38000,0,0,'QUITADO',true,0,false],
    'CASE_COMISSAO_001'=>[8000,0,8000,0,'DEVIDO',true,8000,false],
    'CASE_COMISSAO_PAGA_001'=>[8000,8000,0,0,'QUITADO',true,0,false],
    'CASE_ANIMACAO_001'=>[10000,10000,0,0,'NAO_ELEGIVEL',false,0,false],
    'CASE_ACOMPANHAMENTO_001'=>[1000,null,null,null,'PENDENCIA',true,0,true],
    'CASE_DIVERGENCIA_001'=>[15000,27500,0,12500,'PENDENCIA',true,0,true],
    'CASE_LOG_001'=>[30000,0,30000,0,'DEVIDO',true,30000,false],
    'CASE_ENTRE_MESES_001'=>[30000,27500,2500,0,'DEVIDO',true,2500,false],
];
foreach ($expected as $id=>[$base,$pago,$saldo,$excesso,$situacao,$elegivel,$subtotal,$bloqueado]) {
    $c=financeiro_test_caso($golden,$id);
    $r=$rules->calcular($c['dados'],$c['beneficiario'],$c['competencia'],$c['snapshot']); $i=$r['itens_analisados'][0]; $results[$id]=$r;
    alvo_eq(1,count($r['itens_analisados']),$id.' cardinalidade');
    foreach (['base_centavos'=>$base,'pago_centavos'=>$pago,'saldo_centavos'=>$saldo,'excesso_centavos'=>$excesso,'situacao'=>$situacao] as $field=>$value) alvo_eq($value,$i[$field],$id.' '.$field);
    alvo_eq($elegivel,$i['elegibilidade']['elegivel'],$id.' elegibilidade');
    alvo_eq($subtotal,$r['subtotal_servicos_centavos'],$id.' subtotal');
    alvo_eq($bloqueado,$r['bloqueado'],$id.' bloqueio');
    alvo_eq($c['beneficiario'],$i['identidade']['beneficiario_id'],$id.' beneficiário');
    alvo_eq($golden['reference_cases'][$id]['origin_id'],$i['identidade']['origem_id'],$id.' PK');
    alvo_eq(FechamentoFinanceiroRules::VERSION,$r['rule_version'],$id.' regra identificável');
    alvo_eq($situacao==='DEVIDO'?1:0,count($r['servicos_devidos']),$id.' serviços');
    alvo_eq('America/Sao_Paulo',$r['timezone'],$id.' fuso');
    $shadow=ComparacaoFechamentoFinanceiro::comparar($c['legacy_screen'],$c['legacy_eligible'],$c['dados'],$r);
    alvo_eq(0,$shadow['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,$id.' shadow esperado');
}
alvo_eq(['PAGAMENTO_SEM_LEDGER'],array_column($results['CASE_ACOMPANHAMENTO_001']['pendencias'],'codigo'),'T09 sem ledger');
alvo_eq(false,$results['CASE_ACOMPANHAMENTO_001']['subtotal_servicos_completo'],'T09 subtotal incompleto');
alvo_eq(['PAGO_ACIMA_DO_DEVIDO'],array_column($results['CASE_DIVERGENCIA_001']['pendencias'],'codigo'),'T16 excesso');
alvo_eq(-12500,$results['CASE_DIVERGENCIA_001']['itens_analisados'][0]['saldo_bruto_centavos'],'T16 bruto negativo preservado');
alvo_eq(2500,$results['CASE_ENTRE_MESES_001']['itens_analisados'][0]['saldo_bruto_centavos'],'T18 bruto');
alvo_eq(['2026-03','2026-04'],array_column($results['CASE_ENTRE_MESES_001']['itens_analisados'][0]['pagamentos'],'competencia_pagamento'),'T18 entre meses');

// T05: a MESMA entidade, classes/beneficiários diferentes, com ledger real congelado.
$c=financeiro_test_caso($golden,'CASE_COMISSAO_PAGA_001');
$executor=$rules->calcular($c['dados'],40,'2026-08',$c['snapshot']); $gestor=$results['CASE_COMISSAO_PAGA_001'];
alvo_eq(30000,$executor['itens_analisados'][0]['pago_centavos'],'T05 executor recebe300');
alvo_eq(8000,$gestor['itens_analisados'][0]['pago_centavos'],'T05 gestor recebe80');
alvo_eq(FechamentoFinanceiroRules::TAREFA,$executor['itens_analisados'][0]['identidade']['classe'],'T05 classe tarefa');
alvo_eq(FechamentoFinanceiroRules::COMISSAO,$gestor['itens_analisados'][0]['identidade']['classe'],'T05 classe comissão');
alvo_eq(true,FechamentoFinanceiroRules::chave($executor['itens_analisados'][0]['identidade'])!==FechamentoFinanceiroRules::chave($gestor['itens_analisados'][0]['identidade']),'T05 chaves diferentes');
alvo_eq(1,count($executor['itens_analisados'][0]['pagamentos_nao_aplicaveis']),'T05 comissão ignorada para executor');

// T07: mesma FA real e mesmo ledger, consultada na competência financeira alvo agosto.
$c=financeiro_test_caso($golden,'CASE_ANIMACAO_001');
$r=$rules->calcular($c['dados'],13,'2026-08',$c['snapshot']);
alvo_eq(true,$r['itens_analisados'][0]['elegibilidade']['elegivel'],'T07 agosto');
alvo_eq('QUITADO',$r['itens_analisados'][0]['situacao'],'T07 sem nova dívida');
alvo_eq(0,$r['subtotal_servicos_centavos'],'T07 não cobrar100');

// T08 e limites R01: variantes declaradas de fixture em memória, nunca registros reais criados.
$dados=$c['dados'];$dados['origens'][0]['prazo']='2026-10-10';$dados['origens'][0]['data_anima']='2026-09-29';
alvo_eq(true,$rules->calcular($dados,13,'2026-10',$c['snapshot'])['itens_analisados'][0]['elegibilidade']['elegivel'],'T08 prazo outubro');
alvo_eq(false,$rules->calcular($dados,13,'2026-09',$c['snapshot'])['itens_analisados'][0]['elegibilidade']['elegivel'],'T08 data operacional não manda');
$normal=financeiro_test_caso($golden,'CASE_NORMAL_001');$o=$normal['dados']['origens'][0];
foreach (['Finalizado','Em aprovação','Ajuste','Aprovado com ajustes','Aprovado','  FINALIZADO  '] as $status) {
    $o['status']=$status; $o['prazo']='2026-09-01'; alvo_eq(true,FechamentoFinanceiroRules::elegibilidade($o,[],'2026-09')['elegivel'],'R01 status '.$status);
}
$o['prazo']='2026-10-01';alvo_eq(false,FechamentoFinanceiroRules::elegibilidade($o,[],'2026-09')['elegivel'],'fim exclusivo');
$o['status']='Em andamento'; $o['prazo']='2026-09-30';
alvo_eq(false,FechamentoFinanceiroRules::elegibilidade($o,[],'2026-09')['elegivel'],'status não acrescentado');
$log=['idlog'=>9999001,'funcao_imagem_id'=>$o['origem_id'],'data'=>'2026-09-01 00:00:00','status_novo'=>'Ajuste'];
alvo_eq(true,FechamentoFinanceiroRules::elegibilidade($o,[$log],'2026-09')['elegivel'],'log início inclusivo');
$log['data']='2026-10-01 00:00:00';alvo_eq(false,FechamentoFinanceiroRules::elegibilidade($o,[$log],'2026-09')['elegivel'],'log fim exclusivo');
$log['data']='2026-09-30';$log['funcao_imagem_id']++;alvo_eq(false,FechamentoFinanceiroRules::elegibilidade($o,[$log],'2026-09')['elegivel'],'log outra identidade não aplica');

// T22: comissão Fachada100 real, critérios80/100 e ausência de colisão entre origens.
$pair=$golden['pairs']['8/2026-08'];
$raw=array_values(array_filter($pair['eligible_v2'],fn($r)=>(int)$r['origem_id']===117304))[0];
$item=array_values(array_filter($pair['items'],fn($r)=>$r['identity']['id']===117304))[0];
$r=$rules->calcular(['origens'=>[$raw],'ledger'=>$item['ledger'],'logs'=>[]],8,'2026-08',$normal['snapshot']);
alvo_eq(10000,$r['itens_analisados'][0]['base_centavos'],'T22 Fachada real100');
alvo_eq(10000,$r['itens_analisados'][0]['pago_centavos'],'T22 ledger real100');
alvo_eq(0,$r['subtotal_servicos_centavos'],'T22 quitada');
$raw['imagem_nome']='Embasamento FACHADA';alvo_eq(8000,FechamentoFinanceiroRules::comissao($raw),'T22 embasamento');
$raw['imagem_nome']='Fachada';$raw['tipo_imagem']='fachada';alvo_eq(8000,FechamentoFinanceiroRules::comissao($raw),'T22 tipo comparação exata preservada');
$raw['tipo_imagem']='Fachada';alvo_eq(10000,FechamentoFinanceiroRules::comissao($raw),'T22 Fachada100');
alvo_eq(true,FechamentoFinanceiroRules::chave(FechamentoFinanceiroRules::identidade(13,'animacao',534,'ANIMACAO_LEGADA'))!==FechamentoFinanceiroRules::chave(FechamentoFinanceiroRules::identidade(13,'funcao_animacao',534,FechamentoFinanceiroRules::ANIMACAO)),'origens não intercambiáveis');

// Comissão não herda a flag paga da remuneração da tarefa de outro beneficiário.
$c=financeiro_test_caso($golden,'CASE_COMISSAO_001');$dados=$c['dados'];$dados['origens'][0]['pagamento']=1;
$r=$rules->calcular($dados,8,'2026-09',$c['snapshot']);alvo_eq(false,$r['bloqueado'],'flag do executor não quita/bloqueia comissão');
alvo_eq(8000,$r['subtotal_servicos_centavos'],'comissão permanece devida');
alvo_eq(30000,custos_centavos($golden['pairs']['8/2026-09']['path_comparison']['field_differences']['funcao_imagem:120172']['B']['value']),'B histórico bruto300 preservado');

// T14: nenhum campo visual se torna input financeiro, inclusive nomes/valores falsos do DOM.
$dados=$normal['dados'];$dados['ui']=['aba'=>'Pagos','filtro'=>'nenhum','itens'=>[],'offsetParent'=>null,'valor'=>999999,'nome_imagem'=>'Falso','checkbox'=>false];
alvo_eq($results['CASE_NORMAL_001'],$rules->calcular($dados,27,'2026-09',$normal['snapshot']),'T14 UI independente');
$raw=$dados['origens'][0];$raw['pago_completa_count']=999;$raw['parcial']=1;
$dados['origens']=[$raw];alvo_eq(5000,$rules->calcular($dados,27,'2026-09',$normal['snapshot'])['subtotal_servicos_centavos'],'texto/contadores ignorados');
// T17: repositório não é chamado pelo domínio e vazio permanece vazio.
$empty=$rules->calcular(['origens'=>[],'logs'=>[],'ledger'=>[]],27,'2026-09',$normal['snapshot']);
alvo_eq([],$empty['itens_analisados'],'T17 vazio');alvo_eq([],$empty['servicos_devidos'],'T17 sem fallback');alvo_eq(0,$empty['subtotal_servicos_centavos'],'T17 subtotal');
foreach (['valor_fixo','extras','total_final_adendo'] as $field) alvo_eq(false,array_key_exists($field,$empty),'fora do escopo '.$field);

// D05: pagamento legado e FA não são somados nem convertidos. Fixture sintética explícita.
$c=financeiro_test_caso($golden,'CASE_ANIMACAO_001');$dados=$c['dados'];
$dados['animacoes_legadas']=[['idanimacao'=>633,'valor'=>100,'data_anima'=>'2026-09-04','colaborador_id'=>13]];
$dados['ledger'][]=['idpagamento_item'=>9999002,'pagamento_id'=>9999003,'colaborador_id'=>13,'origem'=>'animacao','origem_id'=>633,'valor'=>100,'mes_ref'=>'2026-08'];
$r=$rules->calcular($dados,13,'2026-08',$c['snapshot']);
alvo_eq(true,$r['bloqueado'],'D05 legado ambíguo bloqueia');
alvo_eq(null,$r['itens_analisados'][0]['saldo_centavos'],'D05 não supõe saldo reconciliado');
alvo_eq(10000,$r['itens_analisados'][0]['pago_ledger_centavos'],'D05 somente ledger canônico100');
alvo_eq(0,$r['subtotal_servicos_centavos'],'D05 não cobrar duas vezes');
$dados['ledger']=array_values(array_filter($dados['ledger'],fn($p)=>$p['origem']!=='animacao'));
alvo_eq(false,$rules->calcular($dados,13,'2026-08',$c['snapshot'])['bloqueado'],'D05 parent sem pagamento legado não inventa divergência');
// FA é direito próprio: ausência de informação operacional não muda seu prazo financeiro.
unset($dados['origens'][0]['data_anima'], $dados['origens'][0]['animacao_id']);
$r=$rules->calcular($dados,13,'2026-08',$c['snapshot']);
alvo_eq(true,$r['itens_analisados'][0]['elegibilidade']['elegivel'],'R08 FA sem parent operacional conserva prazo');
alvo_eq(0,$r['itens_analisados'][0]['saldo_centavos'],'R08 ledger canônico preservado sem parent');

// Exatidão monetária usa o helper existente somente na borda.
alvo_eq(30,FechamentoFinanceiroRules::somar(FechamentoFinanceiroRules::centavos('0.10'),FechamentoFinanceiroRules::centavos('0.20')),'centavos sem float na soma');
foreach (['0.105','250.00',null,'0',150.125] as $value) alvo_eq(custos_centavos($value),FechamentoFinanceiroRules::centavos($value),'arredondamento preservado');
alvo_throws(fn()=>FechamentoFinanceiroRules::somar(PHP_INT_MAX,1),'overflow');
alvo_throws(fn()=>FechamentoFinanceiroRules::centavos('NaN'),'moeda inválida');
foreach (['2026-13','2026-00','2026-9','1999-09','x'] as $ref) alvo_throws(fn()=>FechamentoFinanceiroRules::periodo($ref),'competência '.$ref);
$duplicate=$normal['dados'];$duplicate['origens'][]=$duplicate['origens'][0];alvo_throws(fn()=>$rules->calcular($duplicate,27,'2026-09',$normal['snapshot']),'direito duplicado');
// Guarda de CLI: rejeita escrita/locks/múltiplas statements antes de conexão/execução.
foreach (['INSERT INTO x VALUES(1)','UPDATE x SET a=1','DELETE FROM x','SELECT 1; SELECT 2','SELECT 1 INTO OUTFILE "x"','SELECT * FROM x FOR UPDATE','SELECT SLEEP(1)','SET GLOBAL transaction_isolation="READ COMMITTED"'] as $sql) alvo_throws(fn()=>FechamentoFinanceiroReadOnlyConnection::validarSql($sql),'SQL proibido');
FechamentoFinanceiroReadOnlyConnection::validarSql('SELECT 1');
FechamentoFinanceiroReadOnlyConnection::validarSql('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
// Não esconder regressão sob EXPECTED_TARGET_CHANGE.
$tampered=$results['CASE_NORMAL_001'];$tampered['itens_analisados'][0]['base_centavos']=4900;
$bad=ComparacaoFechamentoFinanceiro::comparar($normal['legacy_screen'],$normal['legacy_eligible'],$normal['dados'],$tampered);
alvo_eq(1,$bad['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow denuncia base divergente');
$tampered=$results['CASE_NORMAL_001'];$tampered['subtotal_servicos_centavos']=4000;
$bad=ComparacaoFechamentoFinanceiro::comparar($normal['legacy_screen'],$normal['legacy_eligible'],$normal['dados'],$tampered);
alvo_eq(2,$bad['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0,'shadow denuncia subtotal divergente');
$tampered=$results['CASE_NORMAL_001'];$tampered['servicos_devidos']=[];$tampered['subtotal_servicos_centavos']=0;
$bad=ComparacaoFechamentoFinanceiro::comparar($normal['legacy_screen'],$normal['legacy_eligible'],$normal['dados'],$tampered);
alvo_eq(true,($bad['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0)>0,'shadow denuncia devido omitido');
$c=financeiro_test_caso($golden,'CASE_COMISSAO_001');$dados=$c['dados'];
$dados['ledger'][]=['idpagamento_item'=>9999004,'pagamento_id'=>9999005,'colaborador_id'=>8,'origem'=>'funcao_imagem','origem_id'=>120172,'valor'=>300,'observacao'=>null];
alvo_eq(8000,$rules->calcular($dados,8,'2026-09',$c['snapshot'])['subtotal_servicos_centavos'],'remuneração da mesma PK/beneficiário não quita comissão');
alvo_eq(false,$rules->calcular($normal['dados'],27,'2026-09',$normal['snapshot'])['bloqueado'],'pendência de outro colaborador não contamina motor');
echo "OK: $checks verificações alvo FASE 1A, offline; golden histórica preservada.\n";
