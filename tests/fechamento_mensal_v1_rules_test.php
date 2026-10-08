<?php
require_once __DIR__.'/../Pagamento/services/FechamentoComposicaoRules.php';
require_once __DIR__.'/../Colaborador/FechamentoCadastroRules.php';
$checks=0;
function eq($a,$b,string $label):void { global $checks; if($a!==$b) throw new RuntimeException($label.': '.json_encode([$a,$b])); $checks++; }
function events(int $n,int $tarifa=380):array {
 $out=[]; for($i=1;$i<=$n;$i++)$out[]=['imagem_id'=>$i,'funcao_id'=>4,'ciclo'=>2,'status_novo'=>'Finalizado','data'=>'2026-09-10 10:00:00','valor'=>(string)$tarifa]; return $out;
}
$rules=new FechamentoProdutividadeRules();
foreach([0=>0,20=>0,21=>1,31=>1,32=>2,40=>2] as $n=>$units) {
 $r=$rules->calcular(events($n),'2026-09',true); eq($r['quantidade_bonus'],$units,"Faixa $n"); eq($r['valor_centavos'],$units*38000,"Tarifa $n");
}
eq($rules->calcular(events(21,420),'2026-09',true)['valor_centavos'],42000,'Tarifa contratual');
eq($rules->calcular(events(40),'2026-09',false)['valor_centavos'],0,'FIXO sem bônus');
$mixed=events(10); foreach(events(20) as $e) {$e['imagem_id']+=100; $e['funcao_id']=3; $mixed[]=$e;}
$r=$rules->calcular($mixed,'2026-09',true); eq($r['quantidade_finalizacao_r0'],10,'Bruna só Finalização'); eq($r['quantidade_bonus'],0,'Bruna sem bônus');
$mixed=events(21); $extra=$mixed[0]; $extra['data']='2026-09-20'; $mixed[]=$extra;
$extra['imagem_id']=200; $extra['ciclo']=3; $mixed[]=$extra;
$extra['imagem_id']=201; $extra['ciclo']=2; $extra['status_novo']='Ajuste'; $mixed[]=$extra;
$extra['imagem_id']=202; $extra['status_novo']='Em aprovação'; $mixed[]=$extra;
$extra['imagem_id']=203; $extra['status_novo']='Finalizado'; $extra['data']='2026-10-01'; $mixed[]=$extra;
eq($rules->calcular($mixed,'2026-09',true)['quantidade_finalizacao_r0'],21,'Deduplicação/ciclo/evento/competência');
$different=events(21); $different[0]['valor']='450'; eq(count($rules->calcular($different,'2026-09',true)['pendencias']),1,'Tarifa mista exige ação');
$missing=events(1); $missing[0]['ciclo']=null; eq(count($rules->calcular($missing,'2026-09',true)['pendencias']),1,'Histórico de ciclo ausente exige ação');
$servicos=['colaborador_id'=>2,'competencia'=>'2026-09','snapshot_em'=>'2026-10-06T10:00:00-03:00','timezone'=>'America/Sao_Paulo','rule_version'=>FechamentoFinanceiroRules::VERSION,
 'subtotal_servicos_centavos'=>100000,'subtotal_servicos_completo'=>true,'bloqueado'=>false,'pendencias'=>[],'servicos_devidos'=>[],'itens_analisados'=>[]];
$context=['colaborador_id'=>2,'competencia'=>'2026-09','fluxo'=>'fechamento_mensal_v1','fixo'=>['configurado'=>'1500.00'],'extras'=>['estado'=>'PENDENTE','itens'=>[]],'eventos_r0'=>events(21)];
$c=new FechamentoComposicaoRules();
foreach(['FIXO'=>150000,'VARIAVEL'=>138000,'FIXO_VARIAVEL'=>288000] as $type=>$total) {
 $r=$c->compor($servicos,$context+['tipo_remuneracao'=>$type]); eq($r['total_final_centavos'],$total,$type); eq($r['situacao'],'PRONTO',"$type pronto sem liquidação/decisão bônus");
}
eq($c->compor($servicos,$context)['situacao'],'ATENCAO','Tipo ausente');
$ctx=$context; $ctx['fixo']['configurado']=null;
eq($c->compor($servicos,$ctx+['tipo_remuneracao'=>'FIXO'])['situacao'],'ATENCAO','Fixo null');
$ctx['fixo']['configurado']='0.00'; eq($c->compor($servicos,$ctx+['tipo_remuneracao'=>'FIXO'])['total_final_centavos'],0,'Fixo zero válido');
$bad=$servicos; $bad['bloqueado']=true; $bad['subtotal_servicos_completo']=false;
eq($c->compor($bad,$context+['tipo_remuneracao'=>'VARIAVEL'])['situacao'],'ATENCAO','Divergência variável');
eq($c->compor($bad,$context+['tipo_remuneracao'=>'FIXO'])['situacao'],'PRONTO','Produção consultiva não bloqueia FIXO');
$registro=['colaborador_id'=>2,'competencia'=>'2026-09','classe'=>'BONUS_EXTRAS','autor_id'=>1,'registrado_em'=>'2026-10-06T09:00:00-03:00','referencia'=>'manual-fixture','motivo'=>'Qualidade'];
$manual=$context;
$manual['extras']=['estado'=>'DEFINIDO','decisao'=>$registro+['estado'=>'DEFINIDO'],'itens'=>[$registro+['categoria'=>'Extra qualidade','valor_centavos'=>50000]]];
foreach(['FIXO'=>200000,'VARIAVEL'=>188000,'FIXO_VARIAVEL'=>338000] as $type=>$total) {
 $r=$c->compor($servicos,$manual+['tipo_remuneracao'=>$type]); eq($r['total_final_centavos'],$total,"$type com extra");
 eq($r['componentes']['BONUS_EXTRAS'],50000,"$type preserva extra manual");
 eq($r['componentes']['BONUS_PRODUTIVIDADE'],$type==='FIXO'?0:38000,"$type bônus automático");
}
$manual['extras']['itens'][0]['valor_centavos']=-1;
eq($c->compor($servicos,$manual+['tipo_remuneracao'=>'FIXO'])['situacao'],'ATENCAO','Extra inválido não é descartado no FIXO');
$normalize=fn($input,$atual=[],$novo=false)=>FechamentoCadastroRules::normalizar($input,$atual,$novo);
eq($normalize(['participa_fechamento_mensal'=>'0'],[],true),['participa_fechamento_mensal'=>0,'tipo_remuneracao'=>null,'valor_fixo'=>null],'Não permite cadastro sem remuneração/fixo');
eq($normalize([])['participa_fechamento_mensal'],null,'Existente não ganha classificação');
eq($normalize(['participa_fechamento_mensal'=>'1','tipo_remuneracao'=>'VARIAVEL'])['valor_fixo'],null,'Variável participante não exige fixo');
foreach(['FIXO','FIXO_VARIAVEL'] as $type) eq($normalize(['participa_fechamento_mensal'=>'1','tipo_remuneracao'=>$type,'valor_fixo'=>'0'])['valor_fixo'],'0',"$type participante aceita zero");
eq($normalize(['valor_fixo'=>'1500,25'],['participa_fechamento_mensal'=>1,'tipo_remuneracao'=>'FIXO'])['valor_fixo'],'1500.25','Update parcial conserva participação/tipo');
foreach([
 ['participa_fechamento_mensal'=>'1'],
 ['participa_fechamento_mensal'=>'1','tipo_remuneracao'=>'FIXO'],
 ['participa_fechamento_mensal'=>'1','tipo_remuneracao'=>'FIXO_VARIAVEL'],
 ['participa_fechamento_mensal'=>'2'],
 ['participa_fechamento_mensal'=>'0','valor_fixo'=>'-1'],
 ['participa_fechamento_mensal'=>'1','tipo_remuneracao'=>'FIXO','valor_fixo'=>'1.500,00'],
] as $i=>$input) {
 $invalid=false;try{$normalize($input);}catch(InvalidArgumentException $e){$invalid=true;}
 eq($invalid,true,'Validação de cadastro '.$i);
}
$invalid=false;try{$normalize([],[],true);}catch(InvalidArgumentException $e){$invalid=true;}eq($invalid,true,'Novo exige escolha explícita de participação');
echo "$checks verificações de regras passaram.\n";
