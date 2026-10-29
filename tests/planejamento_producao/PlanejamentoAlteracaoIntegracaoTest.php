<?php
/** Only session-local TEMPORARY tables. Never writes persistent business data. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('FLOW_R01_TEMP_DB_TEST')!=='1') {echo "SKIP: defina FLOW_R01_TEMP_DB_TEST=1 para usar tabelas temporárias.\n";exit;}
require_once __DIR__.'/PlanejamentoAlteracaoTest.php';
require_once dirname(__DIR__,2).'/conexaoMain.php';
$conn=conectarBanco();
$_SESSION=['logado'=>true,'nivel_acesso'=>1,'idcolaborador'=>1];
$tables=['entregas','entregas_itens','funcao_imagem','historico_aprovacoes_imagens','colaborador','funcao_colaborador','funcao_colaborador_tipo_finalizacao','planejamento_capacidade_etapa','planejamento_capacidade_etapa_periodo','entrega_planejamento_capacidade_validacao','entrega_planejamento_funcao','pre_alt_itens','pre_alt_liberacao_itens','obra','status_imagem','entrega_planejamento_producao','entrega_planejamento_versao','entrega_planejamento_evento','janela_operacional_ciclo','janela_operacional_ciclo_item'];
// Copy columns/indexes into connection-local tables, without triggers or FKs.
function pa_shadow(mysqli $conn,string $t): void {
    $ddl=$conn->query('SHOW CREATE TABLE '.$t)->fetch_row()[1];
    $ddl=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl);
    $ddl=implode("\n",array_filter(explode("\n",$ddl),fn($line)=>!preg_match('/^\s*CONSTRAINT /',$line)));
    $ddl=preg_replace('/,\n\)/',"\n)",$ddl);
    $conn->query($ddl);
}
foreach($tables as $t) pa_shadow($conn,$t);
// The suite supports running both before and after the real migration.
if (!$conn->query("SHOW COLUMNS FROM entrega_planejamento_producao LIKE 'modelo'")->num_rows) {
    $conn->query("ALTER TABLE entrega_planejamento_producao ADD modelo VARCHAR(40) NOT NULL DEFAULT 'FUNCOES_R00'");
}
if (!$conn->query("SHOW COLUMNS FROM entrega_planejamento_producao LIKE 'rascunho_json'")->num_rows) {
    $conn->query('ALTER TABLE entrega_planejamento_producao ADD rascunho_json JSON NULL');
}
if (!$conn->query("SHOW COLUMNS FROM janela_operacional_ciclo LIKE 'entrega_item_id'")->num_rows) {
    $conn->query('ALTER TABLE janela_operacional_ciclo ADD entrega_item_id INT NULL');
}
$migration=file_get_contents(dirname(__DIR__,2).'/sql/2026-10-28_planejamento_alteracao_manual.sql');
preg_match('/CREATE TABLE entrega_planejamento_item \((.*?)\) ENGINE=/s',$migration,$m);
$body=preg_replace('/,\s*CONSTRAINT .*?(?=,\s*CONSTRAINT |$)/s','',$m[1]);
$conn->query('CREATE TEMPORARY TABLE entrega_planejamento_item ('.$body.') ENGINE=InnoDB');
// Minimum required columns use the actual schema discovered by LIKE.
$conn->query("INSERT INTO colaborador(idcolaborador,nome_colaborador,ativo) VALUES(1,'Teste A',1),(2,'Teste B',1)");
$conn->query("INSERT INTO funcao_colaborador(colaborador_id,funcao_id,tipo_atuacao) VALUES(1,6,'PRINCIPAL'),(2,6,'SECUNDARIA')");
$conn->query("INSERT INTO planejamento_capacidade_etapa(codigo_etapa,capacidade_padrao,parametros_json) VALUES('ALTERACAO_PONTOS',25,'{\"niveis\":{\"1\":5,\"2\":10,\"3\":15,\"4\":20,\"5\":25},\"colaboradores\":{}}')");
function pa_ack(array $plan,array $payload): array {
    $payload['capacidade_fingerprint']=$plan['capacidade']['fingerprint'];
    $byId=array_column($plan['itens'],null,'entrega_item_id');
    $merged=array_map(fn($i)=>array_merge($byId[$i['entrega_item_id']],$i),$payload['itens']);
    foreach (flow_pac_sobrecargas($merged,$plan['capacidade']['agenda']) as $s) foreach ($payload['itens'] as &$i) {
        if ((int)($i['responsavel_planejado_id']??0)===$s['colaborador_id']&&($i['fim_planejado']??'')===$s['dia']) $i['excecao_capacidade']=$s+['fingerprint'=>$plan['capacidade']['fingerprint']];
    }
    unset($i);return $payload;
}
$conn->query("INSERT INTO obra(idobra,nomenclatura) VALUES(1,'TESTE')");
$conn->query("INSERT INTO status_imagem(idstatus,nome_status) VALUES(3,'R01'),(4,'R02')");
$conn->query("INSERT INTO entregas(id,obra_id,status_id,data_recebimento,data_prevista,status) VALUES(1,1,3,'2026-10-28','2026-11-06','Pendente')");
// Shadow image table as well before any insert.
pa_shadow($conn,'imagens_cliente_obra');
for($n=1;$n<=10;$n++) {
    $conn->query("INSERT INTO imagens_cliente_obra(idimagens_cliente_obra,imagem_nome,status_id,cliente_id,obra_id,antecipada,dias_trabalhados,animacao,clima) VALUES($n,'Teste $n',3,1,1,0,0,0,'Dia')");
    $conn->query("INSERT INTO entregas_itens(id,entrega_id,imagem_id) VALUES($n,1,$n)");
    $conn->query("INSERT INTO funcao_imagem(idfuncao_imagem,imagem_id,funcao_id,status) VALUES($n,$n,6,'Não iniciado')");
    $conn->query("INSERT INTO pre_alt_itens(id,pre_alt_lote_id,review_batch_item_id,entrega_id,imagem_id,resultado,nivel_complexidade) VALUES($n,1,$n,999,$n,'ALTERACAO',3)");
    $conn->query("INSERT INTO pre_alt_liberacao_itens(liberacao_id,pre_alt_item_id,entrega_destino_id,status_destino_id,prazo) VALUES(1,$n,1,3,'2026-11-06')");
}
$conn->begin_transaction();flow_planejamento_alteracao_criar($conn,1,1);$conn->commit();
$p=flow_planejamento_alteracao_carregar($conn,1);
pa_assert(count($p['itens'])===10 && !$p['planejamento']['versao_atual_id'],'Entrega com 10 pendentes');
$payload=['lock_version'=>$p['planejamento']['lock_version'],'fingerprint'=>$p['fingerprint'],'itens'=>[['entrega_item_id'=>1,'responsavel_planejado_id'=>1,'fim_planejado'=>'2026-11-05']]];
$payload['capacidade_fingerprint']=$p['capacidade']['fingerprint'];
$partial=flow_planejamento_alteracao_salvar($conn,1,$payload,false,1);
pa_assert($partial['itens'][0]['responsavel_planejado_id']===1,'Rascunho persistido');
pa_assert(flow_pa_rows($conn,'SELECT colaborador_id FROM funcao_imagem WHERE idfuncao_imagem=1')[0]['colaborador_id']===null,'Rascunho não aplica responsável');
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$payload,false,1),'outro gestor');
$payload['lock_version']=$partial['planejamento']['lock_version'];
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$payload,true,1),'Todas as imagens');
$payload['itens']=array_map(fn($i)=>['entrega_item_id'=>$i['entrega_item_id'],'responsavel_planejado_id'=>1,'fim_planejado'=>'2026-11-05'],$p['itens']);
$noDate=$payload;$noDate['itens'][0]['fim_planejado']=null;
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$noDate,true,1),'fim planejado');
$stale=$payload;$stale['fingerprint']=str_repeat('a',64);
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$stale,true,1),'escopo');
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$payload,true,1),'Capacidade excedida');
$payload=pa_ack($partial,$payload);
$confirmed=flow_planejamento_alteracao_salvar($conn,1,$payload,true,1);
$baseline=$confirmed['planejamento']['baseline_versao_id'];
pa_assert($baseline && $baseline===$confirmed['planejamento']['versao_atual_id'],'Confirmação cria baseline');
pa_assert((int)flow_pa_rows($conn,'SELECT colaborador_id FROM funcao_imagem WHERE idfuncao_imagem=1')[0]['colaborador_id']===1,'Confirmação atribui tarefa NULL');
pa_assert(count(flow_pa_rows($conn,'SELECT * FROM entrega_planejamento_item'))===10,'10 itens imutáveis');
pa_assert(count(flow_pa_rows($conn,'SELECT * FROM entrega_planejamento_capacidade_validacao'))===1,'Exceção é auditada por colaborador/dia na infraestrutura existente');
$snapshot=flow_planejamento_carregar_versao($conn,(int)$baseline);
pa_assert(json_decode($snapshot['snapshot_json'],true)['itens'][0]['pontos_snapshot']==15,'Confirmação preserva pontos no snapshot');
$conn->query("UPDATE planejamento_capacidade_etapa SET parametros_json='{\"niveis\":{\"1\":5,\"2\":10,\"3\":18,\"4\":20,\"5\":25},\"colaboradores\":{}}' WHERE codigo_etapa='ALTERACAO_PONTOS'");
$afterConfig=flow_planejamento_alteracao_carregar($conn,1);
pa_assert($afterConfig['itens'][0]['pontos_snapshot']==15,'Configuração posterior não recalcula plano confirmado');
$staleConfig=$payload;$staleConfig['lock_version']=$confirmed['planejamento']['lock_version'];$staleConfig['replanejar']=true;
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$staleConfig,false,1),'capacidade ou configuração');
$confirmed=$afterConfig;
$payload['lock_version']=$confirmed['planejamento']['lock_version'];$payload['replanejar']=true;$payload['motivo_codigo']='REDISTRIBUICAO_EQUIPE';$payload['itens'][0]['responsavel_planejado_id']=2;
$payload=pa_ack($confirmed,$payload);
$replan=flow_planejamento_alteracao_salvar($conn,1,$payload,true,1);
// A mesma pessoa não aparece duas vezes e vínculos fora de Alteração não entram.
$conn->query("INSERT INTO colaborador(idcolaborador,nome_colaborador,ativo) VALUES(3,'Sem Alteração',1),(4,'Inativo',0)");
$conn->query("INSERT INTO funcao_colaborador(colaborador_id,funcao_id,tipo_atuacao) VALUES(3,1,'PRINCIPAL'),(4,6,'PRINCIPAL')");
$eligible=flow_pa_colaboradores($conn);
pa_assert(count($eligible)===2 && $eligible[0]['tipo_atuacao']==='PRINCIPAL' && $eligible[1]['tipo_atuacao']==='SECUNDARIA','Elegibilidade por função, estado e papel deduplicado');
$blocked=$payload;$blocked['lock_version']=$replan['planejamento']['lock_version'];$blocked['itens'][0]['responsavel_planejado_id']=3;
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$blocked,false,1),'sem vínculo');
pa_assert($replan['planejamento']['baseline_versao_id']===$baseline && $replan['planejamento']['versao_atual_id']!==$baseline,'Replanejar preserva baseline');
pa_assert((int)flow_pa_rows($conn,'SELECT responsavel_planejado_id FROM entrega_planejamento_item WHERE versao_id=? AND entrega_item_id=1','i',[(int)$baseline])[0]['responsavel_planejado_id']===1,'Baseline original permanece imutável');
$conn->query("UPDATE entregas SET data_prevista='2026-11-09' WHERE id=1");
$payload['lock_version']=$replan['planejamento']['lock_version'];
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,1,$payload,true,1),'escopo');
pa_assert(flow_planejamento_alteracao_carregar($conn,1)['desatualizado'],'Mudança estrutural sinalizada na leitura');
pa_assert(flow_planejamento_modelo($conn,999)==='FUNCOES_R00','Entrega antiga mantém fluxo legado');
// A release plans its destination delivery, even while its lot has other images.
// Six images are released to R02; the other four remain in R01/triage.
$conn->query("INSERT INTO entregas(id,obra_id,status_id,data_recebimento,data_prevista,status) VALUES(2,1,4,'2026-11-02','2026-11-11','Pendente')");
for ($n=1;$n<=6;$n++) {
    $newId=10+$n;
    $conn->query("INSERT INTO entregas_itens(id,entrega_id,imagem_id) VALUES($newId,2,$n)");
    $conn->query("INSERT INTO pre_alt_itens(id,pre_alt_lote_id,review_batch_item_id,entrega_id,imagem_id,resultado,nivel_complexidade) VALUES($newId,1,$newId,1,$n,'ALTERACAO',4)");
    $conn->query("INSERT INTO pre_alt_liberacao_itens(liberacao_id,pre_alt_item_id,entrega_destino_id,status_destino_id,prazo) VALUES(2,$newId,2,4,'2026-11-11')");
}
$conn->begin_transaction();flow_planejamento_alteracao_criar($conn,2,1);$conn->commit();
$round2=flow_planejamento_alteracao_carregar($conn,2);
pa_assert(count($round2['itens'])===6,'Liberação parcial planeja apenas as seis imagens da entrega de destino');
pa_assert(!array_filter($round2['itens'],fn($i)=>$i['responsavel_planejado_id']!==null),'R02 não herda decisão R01, mesmo com tarefa atribuída');
pa_assert($round2['itens'][0]['nivel_complexidade_snapshot']===4,'R02 lê seu nível pela liberação de destino');
pa_assert($round2['itens'][0]['entrega_item_id']!==$p['itens'][0]['entrega_item_id'] && $round2['itens'][0]['funcao_imagem_id']===$p['itens'][0]['funcao_imagem_id'],'Mesma tarefa e imagem preservam identidades de rodada diferentes');
// Force a late error after creating a version and one item: transaction must
// roll back the version, assignments and baseline as a single unit.
$conn->query('ALTER TABLE entrega_planejamento_item ADD test_fault_token INT GENERATED ALWAYS AS (CASE WHEN entrega_item_id>10 THEN 1 ELSE NULL END) STORED, ADD UNIQUE INDEX test_fail_second_item(versao_id,test_fault_token)');
$conn->query('UPDATE imagens_cliente_obra SET status_id=4 WHERE idimagens_cliente_obra<=6');
$round2=flow_planejamento_alteracao_carregar($conn,2);
$draft2=['lock_version'=>$round2['planejamento']['lock_version'],'fingerprint'=>$round2['fingerprint'],'itens'=>array_map(fn($i)=>['entrega_item_id'=>$i['entrega_item_id'],'responsavel_planejado_id'=>1,'fim_planejado'=>'2026-11-10'],$round2['itens'])];
$draft2=pa_ack($round2,$draft2);
pa_fails(fn()=>flow_planejamento_alteracao_salvar($conn,2,$draft2,true,1),'Duplicate');
$root2=flow_planejamento_carregar_raiz($conn,2);
pa_assert($root2['baseline_versao_id']===null && $root2['versao_atual_id']===null,'Erro tardio não deixa baseline ou versão parcial');
pa_assert((int)flow_pa_rows($conn,'SELECT colaborador_id FROM funcao_imagem WHERE idfuncao_imagem=1')[0]['colaborador_id']===2,'Erro tardio desfaz atribuição operacional');
$conn->query('ALTER TABLE entrega_planejamento_producao DROP COLUMN modelo');
$conn->begin_transaction();
$conn->query("INSERT INTO entregas(id,obra_id,status_id,data_recebimento,data_prevista,status) VALUES(3,1,4,'2026-11-02','2026-11-11','Pendente')");
pa_fails(fn()=>flow_planejamento_alteracao_criar($conn,3,1),'Aplique a migration');
$conn->rollback();
pa_assert(!flow_pa_rows($conn,'SELECT id FROM entregas WHERE id=3'),'Falha ao preparar planejamento permite desfazer toda a criação da entrega');
$conn->close();
echo "OK: $checks verificações incluindo persistência transacional com tabelas temporárias.\n";
