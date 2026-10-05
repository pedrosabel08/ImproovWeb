<?php

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/fixtures/pagamento_adendos_persistencia.php';
require_once __DIR__.'/../Pagamento/services/FechamentoRevisaoService.php';

// Workers separados: o processo pai segura o fechamento até ambos estarem prontos.
if (($argv[1]??'')==='--worker') {
    $c=fechamento_test_connection($argv[2]);
    echo 'READY '.$c->thread_id."\n"; flush();
    try {
        $s=new FechamentoRevisaoService($c);
        $r=$s->decidir((int)($argv[5]??6),'2026-09',(int)$argv[3],1,$argv[4],'BONUS',['estado'=>'SEM_BONUS','motivo'=>'Teste concorrente']);
        echo json_encode(['ok'=>true,'id'=>$r['id'],'numero'=>$r['numero']])."\n";
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'erro'=>$e->getMessage()])."\n"; }
    exit;
}

$checks=0;
function igual($actual,$expected,string $label): void { global $checks; $checks++; if ($actual!==$expected) throw new RuntimeException($label.': '.json_encode($actual).' != '.json_encode($expected)); }
function rejeita(callable $f,string $parte,string $label): void { global $checks; $checks++; try { $f(); } catch(Throwable $e) { if (str_contains($e->getMessage(),$parte)) return; throw new RuntimeException($label.': exceção inesperada '.$e->getMessage()); } throw new RuntimeException($label.': aceitou ação inválida'); }
function total(array $r) { return $r['snapshot']['composicao']['total_final_centavos']; }

final class FechamentoSnapshotTestConnection extends mysqli
{
    public $aposVisao = null;
    public function prepare(string $query): mysqli_stmt|false
    {
        if ($this->aposVisao && str_contains($query,'FROM funcao_imagem fi JOIN')) {
            $hook=$this->aposVisao; $this->aposVisao=null; $hook();
        }
        return parent::prepare($query);
    }
}

$golden=hash_file('sha256',__DIR__.'/characterization/pagamento_adendos_golden.php');
$c=fechamento_test_connection();
$db='pagamento_1ca_test_'.bin2hex(random_bytes(5));
$c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $c->select_db($db);
try {
    fechamento_test_schema($c); $s=new FechamentoRevisaoService($c);
    $v1=$s->prepararRevisao(2,'2026-09',1,0,'inicial');
    igual($v1['numero'],1,'V1'); igual($v1['estado'],'PENDENTE','Pendente inicial'); igual(total($v1),null,'Final desconhecido');
    igual($v1['snapshot']['composicao']['fixo']['estado_liquidacao'],'LIQUIDACAO_INDETERMINADA','Sem inferência de legado pago/assinado');
    igual($v1['snapshot']['composicao']['extras']['estado'],'PENDENTE','Ausência não equivale a SEM_BONUS');
    igual($v1['snapshot']['composicao']['financeiro_servicos']['subtotal_servicos_centavos'],10000,'1A consumida');
    igual($s->prepararRevisao(2,'2026-09',1,0,'inicial'),$v1,'Retry retorna exatamente V1');
    rejeita(fn()=>$s->prepararRevisao(2,'2026-09',1,1,'inicial'),'idempotência','Mesma chave outro conteúdo');
    rejeita(fn()=>$s->prepararRevisao(2,'2026-09',2,0,'inicial'),'idempotência','Chave outro autor');
    $v2=$s->decidir(2,'2026-09',2,1,'sem-bonus','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Decisão mensal explícita']);
    igual($v2['numero'],2,'V2'); igual($v2['snapshot']['composicao']['extras']['estado'],'SEM_BONUS','PENDENTE→SEM'); igual(total($v2),null,'Fixo ainda desconhecido');
    igual($s->obterRevisao($v1['id'],1),$v1,'V1 preservada após decisão');
    rejeita(fn()=>$s->decidir(2,'2026-09',1,1,'stale','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Antiga']),'STALE_VERSION','Duas abas');
    $v3=$s->decidir(2,'2026-09',1,2,'reconciliar','LIQUIDACAO',['motivo'=>'Apuração administrativa do direito mensal','evidencias'=>[['tipo'=>'APURACAO_FIXO_SEM_PAGAMENTO','valor_centavos'=>0,'referencia'=>'apuracao-2026-09','origem_verificavel'=>'Registro administrativo fixture F001']]]);
    igual($v3['estado'],'PRONTO','Reconciliado'); igual(total($v3),160000,'Saldo fixo+serviço');
    $v4=$s->decidir(2,'2026-09',1,3,'parcial','LIQUIDACAO',['motivo'=>'Recibo discriminado','evidencias'=>[['tipo'=>'PAGAMENTO_FIXO','valor'=>'500,00','referencia'=>'recibo-1','origem_verificavel'=>'Recibo fixture R001']]]);
    igual($v4['snapshot']['composicao']['fixo']['pago_centavos'],50000,'Liquidação parcial'); igual(total($v4),110000,'Saldo parcial');
    $v5=$s->decidir(2,'2026-09',1,4,'integral','LIQUIDACAO',['motivo'=>'Recibos completos','evidencias'=>[['tipo'=>'PAGAMENTO_FIXO','valor_centavos'=>50000,'referencia'=>'recibo-1','origem_verificavel'=>'R001'],['tipo'=>'PAGAMENTO_FIXO','valor_centavos'=>100000,'referencia'=>'recibo-2','origem_verificavel'=>'R002']]]);
    igual(total($v5),10000,'Liquidação integral');
    $v6=$s->decidir(2,'2026-09',1,5,'override','FIXO',['estado'=>'OVERRIDE','valor'=>'1.800,00','motivo'=>'Ajuste mensal autorizado']);
    igual($v6['snapshot']['composicao']['fixo']['override']['original_centavos'],150000,'Original observado'); igual(total($v6),40000,'Override preserva saldo');
    igual($c->query('SELECT valor_fixo FROM colaborador WHERE idcolaborador=2')->fetch_assoc()['valor_fixo'],'1500.00','Cadastro intocado');
    $c->query('UPDATE colaborador SET valor_fixo=1900 WHERE idcolaborador=2');
    $v7=$s->prepararRevisao(2,'2026-09',1,6,'cadastro-novo');
    igual(total($v7),null,'Override original divergente exige ato novo'); igual($s->obterRevisao($v6['id'],1),$v6,'Snapshot antigo usa cadastro observado');
    $v8=$s->decidir(2,'2026-09',1,7,'override-novo','FIXO',['estado'=>'OVERRIDE','valor_centavos'=>200000,'motivo'=>'Revalidar diante do cadastro novo']);
    igual(total($v8),60000,'Novo override');
    $p=$s->prepararRevisao(3,'2026-09',1,0,'zero');
    $extras=['estado'=>'DEFINIDO','motivo'=>'Rubricas aprovadas','itens'=>[['referencia'=>'bonus-1','categoria'=>'Qualidade','valor'=>'100,50'],['referencia'=>'bonus-2','categoria'=>'Entrega','valor_centavos'=>20000]]];
    $ex=$s->decidir(3,'2026-09',1,1,'extras','BONUS',$extras);
    igual(total($ex),30050,'Extras múltiplos'); igual($ex['snapshot']['composicao']['extras']['estado'],'DEFINIDO','PENDENTE→DEFINIDO');
    igual((int)$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_extra')->fetch_assoc()['n'],2,'Rubricas individualizadas');
    igual($s->decidir(3,'2026-09',1,1,'extras','BONUS',$extras),$ex,'Retry não duplica extras');
    foreach ([0,-1,'1.234','abc'] as $bad) {
        rejeita(fn()=>$s->decidir(3,'2026-09',1,2,'bad-'.md5((string)$bad),'BONUS',['estado'=>'DEFINIDO','motivo'=>'Teste','itens'=>[['referencia'=>'inv','categoria'=>'X','valor'=>$bad]]]), $bad===0||$bad===-1?'inválidos':'', 'Valor inválido');
    }
    rejeita(fn()=>$s->decidir(3,'2026-09',1,2,'duplicado','BONUS',['estado'=>'DEFINIDO','motivo'=>'Teste','itens'=>[$extras['itens'][0],$extras['itens'][0]]]),'inválidos','Referência duplicada');
    rejeita(fn()=>$s->decidir(3,'2026-09',1,2,'categoria','BONUS',['estado'=>'DEFINIDO','motivo'=>'Teste','itens'=>[['referencia'=>'x','categoria'=>'','valor_centavos'=>1]]]),'Categoria','Categoria vazia');
    rejeita(fn()=>$s->decidir(3,'2026-09',1,2,'sem-motivo','BONUS',['estado'=>'SEM_BONUS']),'Motivo','Sem motivo');
    rejeita(fn()=>$s->decidir(3,'2026-09',1,2,'def-vazio','BONUS',['estado'=>'DEFINIDO','motivo'=>'Teste']),'inválidos','DEFINIDO sem extra');
    $null=$s->prepararRevisao(4,'2026-09',1,0,'null'); igual($null['snapshot']['composicao']['fixo']['estado'],'NAO_DEFINIDO','Cadastro NULL');
    $sem=$s->decidir(4,'2026-09',1,1,'sem-fixo','FIXO',['estado'=>'SEM_VALOR_FIXO','motivo'=>'Competência sem fixo']);
    igual($sem['snapshot']['composicao']['fixo']['utilizado_centavos'],0,'SEM_VALOR_FIXO');
    $semPronto=$s->decidir(4,'2026-09',1,2,'sem-extra','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Sem rubricas']); igual(total($semPronto),0,'Zero final determinado');
    $ni=$s->prepararRevisao(1,'2026-09',1,0,'nicolle'); igual($ni['snapshot']['composicao']['acompanhamento_especial']['valor_centavos'],400000,'Nicolle especial separado');
    $ni2=$s->decidir(1,'2026-09',1,1,'nicolle-sem-fixo','FIXO',['estado'=>'SEM_VALOR_FIXO','motivo'=>'Fixture']);
    $ni3=$s->decidir(1,'2026-09',1,2,'nicolle-sem-bonus','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Fixture']); igual(total($ni3),400000,'Especial não substitui bônus');
    $pend=$s->prepararRevisao(5,'2026-09',1,0,'pend1a');
    $pend2=$s->decidir(5,'2026-09',1,1,'sem-pend','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Fixture']);
    igual(total($pend2),null,'Pendência 1A bloqueia final'); igual($pend2['snapshot']['composicao']['financeiro_servicos']['pendencias'][0]['codigo'],'PAGAMENTO_SEM_LEDGER','Pendência 1A intacta');
    foreach ([3,4,999] as $u) {
        rejeita(fn()=>$s->prepararRevisao(3,'2026-10',$u,0,'nao-autorizado'),'autorização','Autor inválido/inativo/nível 2');
        rejeita(fn()=>$s->obterRevisao($v1['id'],$u),'autorização','Leitura restrita');
        rejeita(fn()=>$s->decidir(3,'2026-09',$u,2,'nao-autorizado','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Fixture']),'autorização','Decisão restrita');
    }
    $audit=$c->query("SELECT * FROM pagamento_fechamento_decisao WHERE tipo='FIXO' ORDER BY id LIMIT 1")->fetch_assoc();
    igual((int)$audit['autor_id'],1,'Autoria no servidor'); igual($audit['motivo'],'Ajuste mensal autorizado','Motivo persistido');
    igual(json_decode($audit['antes_json'],true)['configurado_observado'],'1500.00','Antes auditado');
    igual(json_decode($audit['depois_json'],true)['override']['substituto_centavos'],180000,'Depois auditado');
    igual($s->obterRevisao($v1['id'],1)['snapshot_hash'],FechamentoSnapshot::hash($v1['snapshot']),'Hash armazenado íntegro');
    $replay=(new FechamentoFinanceiroRules())->calcular($ex['snapshot']['dados_servicos'],3,'2026-09',FechamentoComposicaoSupport::instante($ex['snapshot']['snapshot_em']));
    $replay['consistencia']=$ex['snapshot']['composicao']['financeiro_servicos']['consistencia'];
    igual((new FechamentoComposicaoRules())->compor($replay,$ex['snapshot']['contexto_composicao']),$ex['snapshot']['composicao'],'Reproduzir integralmente sem consultar cadastro atual');
    $shuffled=$ex['snapshot']; $shuffled['contexto_composicao']['extras']['itens']=array_reverse($shuffled['contexto_composicao']['extras']['itens']); $shuffled=array_reverse($shuffled,true);
    igual(FechamentoSnapshot::hash($shuffled),FechamentoSnapshot::hash($ex['snapshot']),'Hash independente de SELECT/ordem de chaves');
    $changed=$ex['snapshot']; $changed['composicao']['componentes']['BONUS_EXTRAS']++; igual(FechamentoSnapshot::hash($changed)===$ex['snapshot_hash'],false,'Hash detecta conteúdo');
    foreach (['revisao','decisao','extra','evidencia','operacao'] as $t) {
        rejeita(fn()=>$c->query("UPDATE pagamento_fechamento_$t SET id=id WHERE id=(SELECT id FROM (SELECT MIN(id) id FROM pagamento_fechamento_$t) x)"),$t==='revisao'?'imutavel':'append-only', 'UPDATE append-only '.$t);
        rejeita(fn()=>$c->query("DELETE FROM pagamento_fechamento_$t LIMIT 1"),$t==='revisao'?'imutavel':'append-only', 'DELETE append-only '.$t);
    }
    // Falha real no último INSERT, depois de decisão, extras e revisão: tudo rollback.
    $before=$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'];
    $beforeActs=$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_decisao')->fetch_assoc()['n'];
    $c->query("CREATE TRIGGER fail_operacao BEFORE INSERT ON pagamento_fechamento_operacao FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha fixture'");
    rejeita(fn()=>$s->decidir(3,'2026-09',1,2,'falha','BONUS',$extras),'falha fixture','Rollback falha final');
    igual($c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'],$before,'Nenhuma revisão parcial');
    igual($c->query('SELECT COUNT(*) n FROM pagamento_fechamento_decisao')->fetch_assoc()['n'],$beforeActs,'Nenhum ato parcial');
    igual((int)$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_extra')->fetch_assoc()['n'],2,'Nenhum extra parcial');
    $c->query('DROP TRIGGER fail_operacao');
    igual($s->decidir(3,'2026-09',1,2,'falha','BONUS',$extras)['numero'],3,'Retry depois de rollback válido');
    $count=$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'];
    $cmp=$s->compararRevisao($v1['id'],1); igual(isset($cmp['diferencas']['componentes']),true,'Comparação fresca encontra mudanças');
    igual($c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'],$count,'Comparação não cria revisão');
    igual($s->obterRevisao($v1['id'],1),$v1,'Comparação não muda V1');
    igual(count($s->listarRevisoes(2,'2026-09',1)),8,'Lista revisões ordenadas');
    $new=$s->prepararRevisao(2,'2026-09',1,8,'recalcular-explicito'); igual($new['numero'],9,'Recalcular explícito cria próxima');
    igual($s->prepararRevisao(2,'2026-09',1,0,'inicial'),$v1,'Retry antigo permanece V1 após nove revisões');
    $writer=fechamento_test_connection($db);
    $view=new FechamentoSnapshotTestConnection('127.0.0.1','root','',$db,3319); $view->set_charset('utf8mb4');
    $view->aposVisao=function() use($writer) {
        $writer->begin_transaction();
        $writer->query('UPDATE funcao_imagem SET valor=200 WHERE idfuncao_imagem=1');
        $writer->query("INSERT INTO pagamentos VALUES (2,2,'2026-09','pago',50,'2026-09-20')");
        $writer->query("INSERT INTO pagamento_itens VALUES (1,2,'funcao_imagem',1,50,'')");
        $writer->commit();
    };
    $viewRevision=(new FechamentoRevisaoService($view))->prepararRevisao(2,'2026-09',1,9,'writer-durante-snapshot');
    igual($viewRevision['snapshot']['composicao']['financeiro_servicos']['subtotal_servicos_centavos'],10000,'Writer concorrente não mistura origem nova com ledger novo');
    igual(count($viewRevision['snapshot']['dados_servicos']['ledger']),0,'Ledger posterior não entra na view anterior');
    $view->close(); $writer->close();
    $afterWriter=$s->prepararRevisao(2,'2026-09',1,10,'apos-writer');
    igual($afterWriter['snapshot']['composicao']['financeiro_servicos']['subtotal_servicos_centavos'],15000,'Próxima revisão enxerga origem e pagamento novos');
    igual($s->obterRevisao($viewRevision['id'],1),$viewRevision,'Writer não muda snapshot persistido');
    $zeroAto=$s->decidir(3,'2026-10',2,0,'primeiro-ato','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Fixture','autor_id'=>999,'nivel_acesso'=>1]);
    igual($zeroAto['autor_id'],2,'Autor é o usuário validado, não payload');
    igual($zeroAto['snapshot']['composicao']['extras']['decisao']['autor_id'],2,'Autoria da decisão não aceita campo enviado');
    igual($zeroAto['estado'],'PRONTO','Primeiro ato também cria fechamento/revisão');
    rejeita(fn()=>$s->decidir(3,'2026-09',1,3,'evid-sem-origem','LIQUIDACAO',['motivo'=>'Fixture','evidencias'=>[['tipo'=>'PAGAMENTO_FIXO','referencia'=>'falso','valor_centavos'=>1]]]),'Origem verificável','Evidência exige origem');
    rejeita(fn()=>$s->decidir(3,'2026-09',1,3,'evid-conflito','LIQUIDACAO',['motivo'=>'Fixture','evidencias'=>[['tipo'=>'PAGAMENTO_FIXO','referencia'=>'r1','valor_centavos'=>1,'origem_verificavel'=>'R1'],['tipo'=>'APURACAO_FIXO_SEM_PAGAMENTO','referencia'=>'a1','valor_centavos'=>0,'origem_verificavel'=>'A1']]]),'conflitantes','Apuração zero não coexiste com pagamento');
    $estadoPend=$s->decidir(3,'2026-10',2,1,'voltar-pendente','BONUS',['estado'=>'PENDENTE','motivo'=>'Reabrir decisão']);
    igual($estadoPend['snapshot']['composicao']['extras']['estado'],'PENDENTE','PENDENTE explícito persistido'); igual(total($estadoPend),null,'Reabertura não imputa zero final');
    $floatEntrada=$s->decidir(3,'2026-10',2,2,'normalizar-entrada','BONUS',['estado'=>'DEFINIDO','motivo'=>'Entrada normalizada no backend','itens'=>[['categoria'=>'Fixture','referencia'=>'float-entrada','valor'=>10.25]]]);
    igual(total($floatEntrada),1025,'Float de entrada normalizado pela 1B, centavos persistidos inteiros');
    igual(is_int($floatEntrada['snapshot']['composicao']['extras']['itens'][0]['valor_centavos']),true,'Não persiste float como valor principal');
    igual(FechamentoSnapshot::hash(['informativo'=>-0.0]),FechamentoSnapshot::hash(['informativo'=>0]),'Hash normaliza zero JSON');
    rejeita(fn()=>$c->query('DELETE FROM usuario WHERE idusuario=1'),'foreign key','FK preserva autoria');
    rejeita(fn()=>$c->query('DELETE FROM colaborador WHERE idcolaborador=2'),'foreign key','FK preserva beneficiário');
    $retornoCadastro=$s->decidir(2,'2026-09',1,11,'retornar-cadastro','FIXO',['estado'=>'CADASTRO','motivo'=>'Retirar override mensal']);
    igual($retornoCadastro['snapshot']['composicao']['fixo']['utilizado_centavos'],190000,'CADASTRO retira override com ato auditado');
    igual(total($retornoCadastro),55000,'Retorno ao cadastro usa saldo e 1A atuais');
    $c->begin_transaction(); $c->query('UPDATE colaborador SET valor_fixo=777 WHERE idcolaborador=3');
    rejeita(fn()=>$s->prepararRevisao(3,'2026-09',1,3,'nested'),'Transaction','Não assumir transação do chamador');
    igual($c->query('SELECT valor_fixo FROM colaborador WHERE idcolaborador=3')->fetch_assoc()['valor_fixo'],'777.00','Transação do chamador intacta'); $c->rollback();

    // Concorrência real: dois processos bloqueados na mesma linha.
    $cc=$s->prepararRevisao(6,'2026-09',1,0,'conc-inicial');
    function concorrentes(mysqli $c,string $db,int $f,array $keys): array {
        $c->begin_transaction(); $c->query('SELECT id FROM pagamento_fechamento WHERE id='.$f.' FOR UPDATE');
        $workers=[];
        try {
            foreach ($keys as $idx=>$key) {
                $user=is_array($key)?$key['user']:($idx+1);
                $b=is_array($key)?$key['b']:6;
                $key=is_array($key)?$key['key']:$key;
                $pipes=[];
                $p=proc_open([PHP_BINARY,__FILE__,'--worker',$db,(string)$user,$key,(string)$b],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                if (!is_resource($p)) throw new RuntimeException('Worker indisponível');
                fclose($pipes[0]); stream_set_timeout($pipes[1],15);
                $ready=trim(fgets($pipes[1])?:'');
                if (!preg_match('/^READY ([0-9]+)$/D',$ready,$m)) throw new RuntimeException('Worker não iniciou');
                $workers[]=[$p,$pipes,(int)$m[1]];
            }
            $threads=implode(',',array_column($workers,2)); $limit=microtime(true)+10; $waiting=0;
            do {
                $waiting=(int)$c->query('SELECT COUNT(DISTINCT w.requesting_trx_id) n FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id IN ('.$threads.')')->fetch_assoc()['n'];
                if ($waiting>=2) break;
                usleep(10000);
            } while(microtime(true)<$limit);
            igual($waiting>=2,true,'Dois workers realmente aguardam locks simultaneamente');
            $c->commit(); $out=[];
            foreach ($workers as [$p,$pipes]) { $raw=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); if ($err) throw new RuntimeException($err); $out[]=json_decode(trim($raw),true,512,JSON_THROW_ON_ERROR); }
            return $out;
        } finally {
            $c->rollback();
            foreach ($workers as [$p,$pipes]) {
                if (is_resource($p)) proc_terminate($p);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                if (is_resource($p)) proc_close($p);
            }
        }
    }
    $race=concorrentes($c,$db,$cc['fechamento_id'],['gestor-a','gestor-b']);
    igual(count(array_filter($race,fn($r)=>$r['ok'])),1,'Um gestor vence');
    igual(count(array_filter($race,fn($r)=>!$r['ok'] && str_contains($r['erro'],'STALE_VERSION'))),1,'Outro recebe stale');
    igual(count($s->listarRevisoes(6,'2026-09',1)),2,'Sem sobrescrita/revisão duplicada');
    // Retry concorrente precisa do mesmo autor e mesmo payload.
    $c->query("INSERT INTO colaborador VALUES (7,'Fixture 7',0)");
    $same=$s->prepararRevisao(7,'2026-09',1,0,'dup-inicial');
    $dup=concorrentes($c,$db,$same['fechamento_id'],[['key'=>'retry-concorrente','b'=>7,'user'=>1],['key'=>'retry-concorrente','b'=>7,'user'=>1]]);
    igual(count(array_filter($dup,fn($r)=>$r['ok'])),2,'Dois retries concorrentes têm sucesso');
    igual($dup[0]['id'],$dup[1]['id'],'Retry concorrente retorna mesma revisão');
    igual(count($s->listarRevisoes(7,'2026-09',1)),2,'Retry concorrente cria apenas V2');
    igual(hash_file('sha256',__DIR__.'/characterization/pagamento_adendos_golden.php'),$golden,'Golden intacta');
    // Rollback SQL apenas nesta estrutura descartável; tabelas legadas sobrevivem.
    fechamento_test_migration($c,__DIR__.'/../sql/2026-10-02_pagamento_fechamento_revisao_rollback.sql');
    igual((int)$c->query("SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'pagamento_fechamento%'")->fetch_assoc()['n'],0,'Rollback remove apenas estrutura nova');
    igual((int)$c->query('SELECT COUNT(*) n FROM colaborador')->fetch_assoc()['n'],7,'Legado preservado no rollback');
    echo "$checks verificações de persistência OK; MariaDB isolado 127.0.0.1:3319; $db\n";
} finally {
    $c->rollback();
    // Nome gerado e validado, somente o banco descartável criado por este processo.
    if (!preg_match('/^pagamento_1ca_test_[a-f0-9]{10}$/D',$db)) throw new RuntimeException('Nome inseguro para limpeza');
    $c->query("DROP DATABASE `$db`"); $c->close();
}
