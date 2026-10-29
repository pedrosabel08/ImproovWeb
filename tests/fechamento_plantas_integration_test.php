<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/fixtures/fechamento_mensal_v1.php';
require_once __DIR__.'/../Pagamento/services/FechamentoInterfaceService.php';
require_once __DIR__.'/../Pagamento/api/FechamentoHttp.php';
$f=mensal_test_seed();$c=documental_test_connection($f['db']);
$c->query("INSERT INTO usuario VALUES (6,2,1,6,'Colaborador PH sintético')");
$c->query("INSERT INTO informacoes_usuario VALUES (6,'11.111.111/0001-11','Empresa PH sintética','111.111.111-11','solteiro')");
$c->query("INSERT INTO endereco VALUES (6,'Rua Teste','1','Fixture','Centro','Blumenau','SC','89000-000')");
$c->query("INSERT INTO endereco_cnpj VALUES (6,'Rua Teste Empresa','2','Fixture','Centro','Blumenau','SC','89000-000')");
$c->query("ALTER TABLE pagamento_fechamento_decisao MODIFY tipo ENUM('FIXO','BONUS','LIQUIDACAO','DESCONTO','SERVICOS') NOT NULL");
$c->query("ALTER TABLE pagamento_fechamento_operacao MODIFY tipo ENUM('PREPARAR','FIXO','BONUS','LIQUIDACAO','DESCONTO','SERVICOS') NOT NULL");
$examples=[5001=>['Apartamento individual PH','APTO_INDIVIDUAL',13000],5002=>['Apto variação PH','APTO_VARIACAO',8000],5003=>['Pavimento aptos PH','PAVIMENTO',15000],5004=>['Garagem repetição PH','GARAGEM_REPETICAO',8000],5005=>['Lazer implantação PH','LAZER_IMPLANTACAO',20000],5006=>['Planta sem classificação','APTO_INDIVIDUAL',13000]];
foreach($examples as $id=>[$name,$category,$value]){
    $q=$c->prepare('INSERT INTO imagens_cliente_obra VALUES (?, ?, ?, 2)');$type='Planta Humanizada';$q->bind_param('iss',$id,$name,$type);$q->execute();
    $c->query("INSERT INTO funcao_imagem VALUES ($id,$id,6,4,250,'Finalizado','2026-10-10',0,0)");
}
$s=new FechamentoInterfaceService($c,$f['root'],1,true);$checks=0;
function plantOk(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function plantNo(callable $f,string $label):void{try{$f();}catch(DomainException|InvalidArgumentException $e){plantOk(true,$label);return;}throw new RuntimeException($label);}
$r=$s->preparar(6,'2026-10',0,'ph-prepare');
plantOk(!$r['revisao']['total_final_determinado']&&count($r['revisao']['pendencias'])===6,'Divergências bloqueiam PDF até resolução');
plantNo(fn()=>$s->gerar(6,'2026-10',$r['revisao']['fechamento_id'],$r['revisao']['id'],'ph-blocked-preview'),'Não gera PDF com tarifa pendente');
$first=$r;
foreach($examples as $id=>[$name,$category,$value]){
    $item=array_values(array_filter($r['revisao']['financeiro_servicos']['itens_analisados'],fn($i)=>(int)$i['identidade']['origem_id']===$id))[0];
    $input=['estado'=>'APLICAR_TARIFA','alvo'=>'ITEM','identidade'=>$item['identidade'],'categoria_planta'=>$category];
    $payload=['colaborador_id'=>6,'competencia'=>'2026-10','expected_version'=>$r['latest_version'],'idempotency_key'=>'ph-standard-'.$id,'tipo'=>'SERVICOS','input'=>$input];
    $validated=FechamentoHttp::validar('decidir',$payload);
    // Chamada direta sem motivo também usa o normalizador server-side.
    $r=$s->decidir(6,'2026-10',$r['latest_version'],$payload['idempotency_key'],'SERVICOS',$input);
    $adjusted=array_values(array_filter($r['revisao']['financeiro_servicos']['itens_analisados'],fn($i)=>(int)$i['identidade']['origem_id']===$id))[0];
    plantOk($adjusted['base_centavos']===(string)$value&&!$adjusted['planta_humanizada']['divergente'],'Tarifa aplicada '.$category);
    plantOk($s->decidir(6,'2026-10',$payload['expected_version'],$payload['idempotency_key'],'SERVICOS',$input)['revisao']['id']===$r['revisao']['id'],'Retry idempotente '.$id);
}
plantOk($r['revisao']['total_final_centavos']==='77000','Total de seis categorias correto');
$doc=$s->gerar(6,'2026-10',$r['revisao']['fechamento_id'],$r['revisao']['id'],'ph-preview');
$model=json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$doc['document_id'])->fetch_assoc()['modelo_json'],true);
plantOk(count($model['servicos'])===6&&array_sum(array_column($model['servicos'],'valor_centavos'))===77000,'PDF corresponde à tabela');
$firstRaw=(new FechamentoRevisaoRepository($c))->revisao($first['revisao']['id']);
plantOk($firstRaw['snapshot']['composicao']['total_final_centavos']===null,'Snapshot anterior preservado');
plantOk((int)$c->query('SELECT COUNT(*) n FROM funcao_imagem WHERE idfuncao_imagem>=5001 AND valor=250')->fetch_assoc()['n']===6,'Não regrava as tarifas da origem');
$item=array_values(array_filter($r['revisao']['financeiro_servicos']['itens_analisados'],fn($i)=>(int)$i['identidade']['origem_id']===5001))[0];
$manual=['estado'=>'ALTERAR_VALOR','alvo'=>'ITEM','identidade'=>$item['identidade'],'categoria_planta'=>'APTO_INDIVIDUAL','valor'=>'250,00','motivo'=>'Complexidade excepcional sintética aprovada'];
plantNo(fn()=>$s->decidir(6,'2026-10',$r['latest_version'],'ph-no-reason','SERVICOS',array_replace($manual,['motivo'=>''])),'Exceção exige motivo');
$exception=$s->decidir(6,'2026-10',$r['latest_version'],'ph-exception','SERVICOS',$manual);
plantOk($exception['revisao']['total_final_centavos']==='89000','Exceção justificada altera apenas uma tarefa');
$exceptionDoc=$s->gerar(6,'2026-10',$exception['revisao']['fechamento_id'],$exception['revisao']['id'],'ph-exception-preview');
$model=json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$exceptionDoc['document_id'])->fetch_assoc()['modelo_json'],true);
plantOk(array_values(array_filter($model['servicos'],fn($i)=>(int)$i['identidade']['origem_id']===5001))[0]['valor_centavos']===25000,'PDF usa exceção aprovada');
$restore=$s->decidir(6,'2026-10',$exception['latest_version'],'ph-restore','SERVICOS',['estado'=>'RESTAURAR_VALOR','alvo'=>'ITEM','identidade'=>$item['identidade'],'motivo'=>'Teste sintético de restauração']);
plantOk(!$restore['revisao']['total_final_determinado'],'Restaurar original divergente volta a exigir conferência');
$c->query("UPDATE funcao_imagem SET valor=251 WHERE idfuncao_imagem=5002");
$stale=$s->preparar(6,'2026-10',$restore['latest_version'],'ph-stale');
plantOk(in_array('AJUSTE_TAREFA_DESATUALIZADO',array_column($stale['revisao']['pendencias'],'codigo'),true),'Fonte alterada invalida tarifa aplicada antiga');
$c->query("UPDATE funcao_imagem SET valor=250 WHERE idfuncao_imagem=5002");
$s->preparar(6,'2026-10',$stale['latest_version'],'ph-browser-reset');
// Deixa o apartamento individual divergente para exercitar a UI sem dados produtivos.
file_put_contents(__DIR__.'/../output/mensal-fixture.json',json_encode($f,JSON_UNESCAPED_SLASHES));
echo "$checks verificações de integração PH passaram; fixture sintética pronta para navegador.\n";
