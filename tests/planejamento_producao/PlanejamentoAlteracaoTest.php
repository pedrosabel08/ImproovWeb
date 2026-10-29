<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/helpers/planejamento_alteracao_helper.php';
$checks=0;
function pa_assert(bool $ok,string $msg): void {global $checks; $checks++; if (!$ok) throw new RuntimeException($msg);}
function pa_fails(callable $fn,string $contains): void {try {$fn();} catch (Throwable $e) {pa_assert(str_contains($e->getMessage(),$contains),$e->getMessage());return;} throw new RuntimeException('Falha esperada: '.$contains);}
$scope=[];for($i=1;$i<=10;$i++) $scope[]=['entrega_item_id'=>$i,'imagem_id'=>$i,'funcao_imagem_id'=>$i,'pre_alt_item_id'=>$i,'nivel_complexidade_snapshot'=>($i%5)+1,'etapa_atual_id'=>3,'data_entregue'=>null,'tarefa_cancelada'=>false];
$people=[['id'=>1],['id'=>2]];
$draft=flow_pa_validar_itens($scope,[],$people,false);
pa_assert(count($draft)===10 && count(array_filter($draft,fn($i)=>$i['responsavel_planejado_id']===null))===10,'10 imagens nascem pendentes');
$decision=[['entrega_item_id'=>1,'responsavel_planejado_id'=>1,'fim_planejado'=>'2026-11-05']];
$partial=flow_pa_validar_itens($scope,$decision,$people,false);
pa_assert($partial[0]['responsavel_planejado_id']===1 && $partial[1]['responsavel_planejado_id']===null,'Rascunho parcial');
pa_fails(fn()=>flow_pa_validar_itens($scope,$decision,$people,true),'Todas as imagens');
pa_fails(fn()=>flow_pa_validar_itens([$scope[0]],[['entrega_item_id'=>1,'responsavel_planejado_id'=>1]],$people,true),'fim planejado');
pa_fails(fn()=>flow_pa_validar_itens([$scope[0]],[['entrega_item_id'=>1,'fim_planejado'=>'2026-11-05']],$people,true),'responsável');
foreach (['2026-02-30','2026-1-01','inválida'] as $date) pa_fails(fn()=>flow_pa_validar_itens($scope,[['entrega_item_id'=>1,'fim_planejado'=>$date]],$people,false),'Data planejada');
pa_fails(fn()=>flow_pa_validar_itens($scope,[['entrega_item_id'=>1,'responsavel_planejado_id'=>999]],$people,false),'Responsável');
pa_fails(fn()=>flow_pa_validar_itens($scope,[['entrega_item_id'=>999]],$people,false),'escopo');
pa_fails(fn()=>flow_pa_validar_itens($scope,[$decision[0],$decision[0]],$people,false),'duplicada');
$e=['id'=>1,'obra_id'=>1,'status_id'=>3,'data_recebimento'=>'2026-10-28','data_prevista'=>'2026-11-06'];
$fp=flow_pa_fingerprint($e,$scope)['fingerprint'];
$changed=$scope;$changed[0]['pre_alt_item_id']=99;
pa_assert($fp!==flow_pa_fingerprint($e,$changed)['fingerprint'],'Origem da rodada invalida fingerprint');
pa_assert($fp!==flow_pa_fingerprint($e,array_slice($scope,0,6))['fingerprint'],'Liberação parcial tem escopo próprio');
$changed=$scope;$changed[0]['responsavel_planejado_id']=2;
pa_assert($fp===flow_pa_fingerprint($e,$changed)['fingerprint'],'Decisão de rascunho não altera escopo');
echo "OK: $checks verificações de planejamento por imagem.\n";
