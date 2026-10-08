<?php
/** Pré-flight somente leitura. Não aplica migrations nem habilita rollout. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/pagamento_fechamento.php';
require_once __DIR__.'/../Pagamento/services/FechamentoRevisaoRepository.php';
require_once __DIR__.'/../Pagamento/services/FechamentoDocumentoRepository.php';
$c=null;
try {
    $c=pagamento_fechamento_connection();
    $result=['mysql_version'=>$c->query('SELECT VERSION() v')->fetch_assoc()['v'],'flag_enabled'=>pagamento_fechamento_enabled()];
    $privileges=[];
    foreach($c->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as $row) {
        if(preg_match('/^GRANT (.+?) ON /',$row[0],$m)) foreach(explode(', ',$m[1]) as $privilege) $privileges[$privilege]=true;
    }
    // Não publica contas, hashes, senha ou destinos presentes em SHOW GRANTS.
    $result['privileges_seen']=array_keys($privileges);
    if(in_array('--readiness',$argv,true)) {
        // Somente metadados. Não consulta snapshots/dados jurídicos nem executa DDL/DML/locks.
        $c->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $read = static function(string $sql) use ($c): array {
            if(!preg_match('/^(SELECT|SHOW)\b/i',$sql) || preg_match('/;|\b(INTO|FOR\s+UPDATE|LOCK\s+IN|SLEEP|GET_LOCK)\b/i',$sql))
                throw new LogicException('Diagnóstico permite somente SELECT/SHOW.');
            return $c->query($sql)->fetch_all(MYSQLI_ASSOC);
        };
        $result['identity']=$read('SELECT DATABASE() db, CURRENT_USER() account, CURRENT_ROLE() active_roles, @@hostname server_hostname, @@port server_port, @@version_comment version_comment, @@default_storage_engine default_engine, @@session.transaction_isolation session_isolation, @@global.log_bin log_bin, @@global.log_bin_trust_function_creators trust_function_creators')[0];
        $result['connection_transport']=$c->host_info;
        $result['grant_scopes']=[];
        foreach($c->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as $row) {
            if(preg_match('/^GRANT (.+?) ON (.+?) TO /',$row[0],$m))
                $result['grant_scopes'][]=['privileges'=>explode(', ',$m[1]),'scope'=>$m[2]];
        }
        $tables=[]; $triggers=[]; $constraints=[]; $result['migrations']=[];
        foreach(['revisao','documento'] as $kind) {
            $file=__DIR__.'/../sql/2026-10-02_pagamento_fechamento_'.$kind.'.sql';
            $sql=file_get_contents($file);
            preg_match_all('/CREATE TABLE\s+(\w+)/i',$sql,$t);
            preg_match_all('/CREATE TRIGGER\s+(\w+)/i',$sql,$tr);
            preg_match_all('/CONSTRAINT\s+(\w+)/i',$sql,$co);
            $tables=array_merge($tables,$t[1]); $triggers=array_merge($triggers,$tr[1]); $constraints=array_merge($constraints,$co[1]);
            $result['migrations'][$kind]=['sha256'=>hash_file('sha256',$file),'tables'=>$t[1],'triggers'=>$tr[1]];
        }
        $names=static fn(array $list): string => "'".implode("','",$list)."'"; // Identificadores do SQL versionado, nunca input externo.
        $result['existing_new_tables']=$read('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$names($tables).')');
        $result['existing_new_triggers']=$read('SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('.$names($triggers).')');
        $result['constraint_collisions']=$read('SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('.$names($constraints).')');
        $origins=['colaborador','usuario','funcao_imagem','imagens_cliente_obra','funcao','log_alteracoes','funcao_animacao','animacao','acompanhamento','pagamento_itens','pagamentos','adendos','informacoes_usuario','endereco','endereco_cnpj'];
        $result['origin_engines']=$read('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$names($origins).') ORDER BY TABLE_NAME');
        $result['database_engines']=$read("SELECT ENGINE, COUNT(*) tables_count FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' GROUP BY ENGINE");
        $result['parent_columns']=$read("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='colaborador' AND COLUMN_NAME='idcolaborador') OR (TABLE_NAME='usuario' AND COLUMN_NAME='idusuario'))");
        $result['installation_inventory']=count($result['existing_new_tables'])===0 && count($result['existing_new_triggers'])===0 && count($result['constraint_collisions'])===0 ? 'ABSENT' : 'PRESENT_REQUIRES_AUDIT';
        $result['flag_source']=getenv('PAGAMENTO_FECHAMENTO_V2_ENABLED')===false ? 'ABSENT' : (pagamento_fechamento_enabled()?'ON':'OFF');
        $result['storage_configured']=getenv('PAGAMENTO_FECHAMENTO_STORAGE_ROOT')!==false && getenv('PAGAMENTO_FECHAMENTO_STORAGE_ROOT')!=='';
        $c->rollback();
    }
    foreach(['financial'=>(new FechamentoRevisaoRepository($c)),'documental'=>(new FechamentoDocumentoRepository($c))] as $name=>$repo) {
        try { $name==='financial'?$repo->conferirEstrutura():$repo->conferir(); $result[$name]='OK'; }
        catch(Throwable $e){$result[$name]='NOT_READY';}
    }
    try { pagamento_fechamento_storage(); $result['private_storage']='OK'; }
    catch(Throwable $e){$result['private_storage']='NOT_READY';}
    if(in_array('--readiness',$argv,true)) {
        $originNames=array_column($result['origin_engines'],'TABLE_NAME');
        $engines=array_unique(array_map('strtoupper',array_column($result['origin_engines'],'ENGINE')));
        $globalPrivileges=[];
        foreach($result['grant_scopes'] as $grant) if($grant['scope']==='*.*') $globalPrivileges=array_merge($globalPrivileges,$grant['privileges']);
        $needsSuper=(bool)$result['identity']['log_bin'] && !(bool)$result['identity']['trust_function_creators'];
        $result['pre_migration_checks']=[
            'MYSQL'=>str_starts_with($result['mysql_version'],'8.')?'OK':'NOT_READY',
            'INNODB'=>!array_diff($origins,$originNames) && $engines===['INNODB']?'OK':'NOT_READY',
            'STORAGE'=>$result['private_storage'],
            'FLAG'=>$result['flag_enabled']?'ON':'OFF',
            'SCHEMA'=>$result['installation_inventory'],
            'TRIGGERS'=>count($result['existing_new_triggers'])===0?'ABSENT':'PRESENT_REQUIRES_AUDIT',
            'DDL_ACCOUNT'=>$needsSuper && !in_array('SUPER',$globalPrivileges,true) && !in_array('ALL PRIVILEGES',$globalPrivileges,true)?'DBA_REQUIRED':'REQUIRES_DBA_CONFIRMATION',
            'BACKUP_RESTORE_ACL'=>'EXTERNAL_EVIDENCE_REQUIRED'
        ];
        $result['readiness_stage']='PRE_MIGRATION';
        $result['migrations_applied']=$result['financial']==='OK' && $result['documental']==='OK';
    }
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    exit(in_array('NOT_READY',$result,true)?2:0);
} catch(Throwable $e){fwrite(STDERR,'Pré-flight indisponível: '.get_class($e)."\n");exit(2);}
finally{if($c instanceof mysqli)$c->close();}
