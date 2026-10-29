<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
session_save_path(sys_get_temp_dir());
require_once __DIR__.'/../Pagamento/services/FechamentoRetiradaRules.php';
require_once __DIR__.'/../Pagamento/api/FechamentoHttp.php';
$checks=0;
function phCheck(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks++;}
function phReject(callable $f,string $label):void{try{$f();}catch(InvalidArgumentException|DomainException $e){phCheck(true,$label);return;}throw new RuntimeException($label);}
function phSource(string $name,string $value='250',array $payments=[],array $extra=[]):array {
    $o=array_replace(['origem'=>'funcao_imagem','origem_id'=>100,'colaborador_id'=>12,'funcao_id'=>4,'imagem_id'=>10,'imagem_nome'=>$name,'tipo_imagem'=>'Planta Humanizada','nome_funcao'=>'Finalização','valor'=>$value,'pagamento'=>0,'status'=>'Finalizado','prazo'=>'2026-10-10','finalizacao_parcial'=>0],$extra);
    return (new FechamentoFinanceiroRules())->calcular(['origens'=>[$o],'ledger'=>$payments],(int)$o['colaborador_id'],'2026-10',new DateTimeImmutable('2026-10-29T10:00:00-03:00'),true);
}
$examples=[['Apto individual','APTO_INDIVIDUAL',13000],['Apto variação','APTO_VARIACAO',8000],['Apartamento repetição','APTO_VARIACAO',8000],['Pavimento aptos','PAVIMENTO',15000],['Pavimento garagem','PAVIMENTO',15000],['Garagem repetição','GARAGEM_REPETICAO',8000],['Pavimento de garagem variação','GARAGEM_REPETICAO',8000],['Pavimento lazer','LAZER_IMPLANTACAO',20000],['Implantação grande','LAZER_IMPLANTACAO',20000]];
foreach($examples as [$name,$category,$amount]){
    $r=FechamentoRetiradaRules::aplicar(phSource($name,(string)($amount/100)),[]);
    $i=$r['itens_analisados'][0];
    phCheck($i['planta_humanizada']['categoria']===$category&&$i['planta_humanizada']['esperado_centavos']===$amount,'Categoria/tarifa '.$name);
    phCheck(!$r['bloqueado'],'Tarifa correta aceita '.$name);
}
$raw=phSource('Apto individual');$id=$raw['itens_analisados'][0]['identidade'];
$r=FechamentoRetiradaRules::aplicar($raw,[]);
phCheck($r['bloqueado']&&$r['itens_analisados'][0]['saldo_centavos']===null,'250 não passa silenciosamente');
phCheck(FechamentoRetiradaRules::aplicar(phSource('Apto individual','150'),[])['bloqueado'],'Tarifa de outra categoria também diverge');
phCheck(FechamentoRetiradaRules::aplicar(phSource('Planta 01'),[])['pendencias'][0]['codigo']==='PLANTA_TIPO_PENDENTE','Nome ambíguo exige classificação');
$input=['estado'=>'APLICAR_TARIFA','alvo'=>'ITEM','identidade'=>$id,'categoria_planta'=>'APTO_INDIVIDUAL'];
$payload=['colaborador_id'=>12,'competencia'=>'2026-10','expected_version'=>1,'idempotency_key'=>'ph-standard','tipo'=>'SERVICOS','input'=>$input];
$normalized=FechamentoHttp::validar('decidir',$payload)['input'];
phCheck(str_contains($normalized['motivo'],'Apartamento individual'),'Motivo padrão gerado no servidor');
$d=FechamentoRetiradaRules::decidir([],$normalized,$raw,1,'2026-10-29T10:00:00-03:00');
$r=FechamentoRetiradaRules::aplicar($raw,$d);
phCheck(!$r['bloqueado']&&$r['subtotal_servicos_centavos']===13000,'Aplicação calcula tarifa do catálogo');
phCheck($d['plantas'][FechamentoFinanceiroRules::chave($id)]['modo']==='PADRAO','Classificação auditada');
$manual=['estado'=>'ALTERAR_VALOR','alvo'=>'ITEM','identidade'=>$id,'categoria_planta'=>'APTO_INDIVIDUAL','valor'=>'250','motivo'=>'Complexidade excepcional aprovada para esta planta'];
$exception=FechamentoRetiradaRules::decidir($d,$manual,$raw,1,'2026-10-29T10:00:00-03:00');
$r=FechamentoRetiradaRules::aplicar($raw,$exception);
phCheck(!$r['bloqueado']&&$r['subtotal_servicos_centavos']===25000&&$r['itens_analisados'][0]['planta_humanizada']['excecao'],'Exceção aceita somente com categoria e motivo');
phReject(fn()=>FechamentoRetiradaRules::decidir([] ,array_replace($manual,['motivo'=>'']),$raw,1,'2026-10-29T10:00:00-03:00'),'Exceção sem motivo rejeitada');
phReject(fn()=>FechamentoRetiradaRules::decidir([] ,array_diff_key($manual,['categoria_planta'=>true]),$raw,1,'2026-10-29T10:00:00-03:00'),'Exceção sem categoria rejeitada');
phReject(fn()=>FechamentoHttp::validar('decidir',array_replace($payload,['input'=>$input+['valor'=>'1']])),'API não aceita tarifa arbitrária');
phReject(fn()=>FechamentoHttp::validar('decidir',array_replace($payload,['input'=>array_replace($input,['categoria_planta'=>'INVALIDA'])])),'API rejeita categoria desconhecida');
phReject(fn()=>FechamentoHttp::validar('decidir',array_replace($payload,['input'=>$input+['motivo'=>'Justificativa inventada']])),'Motivo do padrão não vem do cliente');
$payment=['origem'=>'funcao_imagem','origem_id'=>100,'beneficiario_id'=>12,'idpagamento_item'=>1,'pagamento_id'=>1,'valor'=>'80','observacao'=>'Finalização parcial','mes_ref'=>'2026-09'];
$partial=phSource('Apto individual','250',[$payment]);
$partialDecision=FechamentoRetiradaRules::decidir([],$normalized,$partial,1,'2026-10-29T10:00:00-03:00');
phCheck(FechamentoRetiradaRules::aplicar($partial,$partialDecision)['subtotal_servicos_centavos']===5000,'Padrão desconta parcela já paga');
$payment['valor']='140';
phReject(fn()=>FechamentoRetiradaRules::decidir([],$normalized,phSource('Apto individual','250',[$payment]),1,'2026-10-29T10:00:00-03:00'),'Padrão não reduz total abaixo do já pago');
$payment['valor']='250';$payment['observacao']='Pago Completa';
$paid=FechamentoRetiradaRules::aplicar(phSource('Apto individual','250',[$payment]),[]);
phCheck(!$paid['bloqueado']&&$paid['itens_analisados'][0]['pago_centavos']===25000,'Histórico pago não é reescrito nem bloqueado');
phReject(fn()=>FechamentoRetiradaRules::decidir([],$normalized,phSource('Apto individual','250',[$payment]),1,'2026-10-29T10:00:00-03:00'),'Não aplica tarifa em tarefa quitada');
$other=phSource('Apto individual','250',[],['colaborador_id'=>33]);
phCheck(FechamentoRetiradaRules::aplicar($other,[])['bloqueado'],'Tipo PH vale para outros colaboradores');
$other=phSource('Imagem','250',[],['colaborador_id'=>33,'tipo_imagem'=>'Interna']);
phCheck(!FechamentoRetiradaRules::aplicar($other,[])['bloqueado'],'Não altera tarifa de outras imagens');
foreach([12,24] as $worker) phCheck(FechamentoRetiradaRules::aplicar(phSource('Apto individual','250',[],['colaborador_id'=>$worker,'tipo_imagem'=>'Interna']),[])['bloqueado'],'Preserva identificação legada de PH '.$worker);
$commission=(new FechamentoFinanceiroRules())->calcular(['origens'=>[['origem'=>'funcao_imagem','origem_id'=>200,'colaborador_id'=>23,'funcao_id'=>4,'imagem_nome'=>'Apto individual','tipo_imagem'=>'Planta Humanizada','valor'=>'250','status'=>'Finalizado','prazo'=>'2026-10-10','pagamento'=>0]]],8,'2026-10',new DateTimeImmutable('2026-10-29T10:00:00-03:00'),true);
phCheck($commission['itens_analisados'][0]['base_centavos']===8000&&$commission['itens_analisados'][0]['planta_humanizada']===null,'Não troca a tarifa da comissão pela tarifa PH');
echo "$checks verificações de tarifas PH passaram.\n";
