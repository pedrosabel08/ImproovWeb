<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../Pagamento/services/FechamentoDocumentoService.php';
require_once __DIR__.'/../tests/fixtures/pagamento_adendos_documental.php';
$o=getopt('', ['acao:','isolado','banco:','usuario:','fechamento:','revisao:','documento:','operacao:','hash:','chave:','data:','ajuda']);
if (isset($o['ajuda']) || !isset($o['acao'])) {
    echo "FASE 1C-B; somente MySQL 8 isolado, loopback 127.0.0.1:3320. Sem envio/assinatura.\n";
    echo "--acao=fixture --isolado cria banco sintético com revisões A–F.\n";
    echo "--acao=gerar|listar|obter|validar-hash|visualizar|confirmar|comparar|pendentes|recuperar --isolado --banco=pagamento_1cb_test_<10 hex> --usuario=<id>\n";
    echo "gerar: --fechamento=<id> --revisao=<id> --chave=<idempotencia> [--data=YYYY-MM-DD]\n";
    echo "listar: --fechamento=<id>; obter/validar-hash/comparar: --documento=<id>\n";
    echo "visualizar: --documento=<id> --chave=<idempotencia>; exporta cópia privada dos bytes validados.\n";
    echo "confirmar: --documento=<id> --revisao=<id esperado> --hash=<SHA256 esperado> --chave=<idempotencia>\n";
    echo "recuperar: --operacao=<id>; pendentes: operações reservadas do ator.\n";
    exit;
}
function documento_cli_id(array $o,string $name): int {
    $v=$o[$name]??null;
    if (!is_string($v) || !preg_match('/^[1-9][0-9]{0,9}$/D',$v)) throw new InvalidArgumentException('Parâmetro inválido: '.$name);
    return (int)$v;
}
try {
    if (!isset($o['isolado'])) throw new InvalidArgumentException('Ambiente --isolado explícito obrigatório.');
    $acao=(string)$o['acao'];
    if (!in_array($acao,['fixture','gerar','listar','obter','validar-hash','visualizar','confirmar','comparar','pendentes','recuperar'],true)) throw new InvalidArgumentException('Ação documental inválida.');
    if ($acao==='fixture') {
        $c=documental_test_connection(); $db='pagamento_1cb_test_'.bin2hex(random_bytes(5));
        $c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $c->select_db($db);
        try {
            documental_test_schema($c); $f=new FechamentoRevisaoService($c);
            $a=documental_test_limpa($f,2,'A');
            $b=$f->decidir(2,'2026-09',1,$a['version'],'B-bonus','BONUS',['estado'=>'DEFINIDO','motivo'=>'Fixture B','itens'=>[['referencia'=>'B1','categoria'=>'Qualidade Fixture','valor_centavos'=>10050],['referencia'=>'B2','categoria'=>'Entrega Fixture','valor_centavos'=>20000]]]);
            $revisions=['A'=>$a,'B'=>$b,'C'=>documental_test_limpa($f,1,'C',[['referencia'=>'C1','categoria'=>'Bonus Nicolle Fixture','valor_centavos'=>12500]]),'D'=>documental_test_limpa($f,8,'D'),'E'=>documental_test_limpa($f,3,'E'),'F'=>$f->prepararRevisao(4,'2026-09',1,0,'F-pendente')];
            $out=['banco'=>$db,'porta'=>3320,'dados'=>'somente fixtures sintéticas','revisoes'=>array_map(fn($r)=>['fechamento_id'=>$r['fechamento_id'],'revision_id'=>$r['id'],'estado'=>$r['estado']],$revisions)];
        } catch(Throwable $e) { $c->query("DROP DATABASE `$db`"); throw $e; }
    } else {
        $db=(string)($o['banco']??''); if ($db==='') throw new InvalidArgumentException('Banco isolado explícito obrigatório.');
        $c=documental_test_connection($db); $root=documental_test_root($db); $s=new FechamentoDocumentoService($c,$root); $u=documento_cli_id($o,'usuario');
        if ($acao==='gerar') $out=$s->gerarPreview(documento_cli_id($o,'fechamento'),documento_cli_id($o,'revisao'),$u,(string)($o['chave']??''),$o['data']??null);
        elseif ($acao==='listar') $out=$s->listar(documento_cli_id($o,'fechamento'),$u);
        elseif ($acao==='confirmar') $out=$s->confirmar(documento_cli_id($o,'documento'),documento_cli_id($o,'revisao'),(string)($o['hash']??''),$u,(string)($o['chave']??''));
        elseif ($acao==='comparar') $out=$s->diagnosticar(documento_cli_id($o,'documento'),$u);
        elseif ($acao==='pendentes') $out=$s->pendentes($u);
        elseif ($acao==='recuperar') $out=$s->recuperar(documento_cli_id($o,'operacao'),$u);
        elseif ($acao==='visualizar') {
            $view=$s->visualizar(documento_cli_id($o,'documento'),$u,(string)($o['chave']??''));
            // Cópia de leitura; nunca usada como fonte para confirmação. Nenhum path livre de input.
            $dir=$root.DIRECTORY_SEPARATOR.'exports'; if (!is_dir($dir) && !mkdir($dir,0700)) throw new RuntimeException('Exportação indisponível.');
            if (is_link($dir) || strcasecmp((string)realpath($dir),$dir)!==0) throw new RuntimeException('Exportação fora da raiz privada.');
            $path=$dir.DIRECTORY_SEPARATOR.'view_d'.$view['metadata']['document_id'].'_'.bin2hex(random_bytes(8)).'.pdf';
            $file=fopen($path,'x+b'); if (!$file) throw new RuntimeException('Exportação indisponível.');
            try { $offset=0; $len=strlen($view['bytes']); while($offset<$len) { $n=fwrite($file,substr($view['bytes'],$offset)); if(!$n)throw new RuntimeException('Exportação parcial.'); $offset+=$n; } if(!fflush($file))throw new RuntimeException('Exportação parcial.'); }
            catch(Throwable $e) { fclose($file); unlink($path); throw $e; } fclose($file);
            $out=['metadata'=>$view['metadata'],'copia_para_visualizar'=>$path,'hash_valido'=>hash_equals($view['metadata']['pdf_hash'],hash_file('sha256',$path))];
        } else {
            $out=$s->obter(documento_cli_id($o,'documento'),$u);
            if ($acao==='validar-hash') { if($out['estado']===null)throw new DomainException('Preview ainda não publicado.'); $out=['document_id'=>$out['document_id'],'pdf_hash'=>$out['pdf_hash'],'tamanho_bytes'=>$out['tamanho_bytes'],'hash_valido'=>true]; }
        }
    }
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    $c->close();
} catch(Throwable $e) {
    $msg=$e instanceof mysqli_sql_exception?'Falha SQL; confira instância isolada/migrations/permissões.':$e->getMessage();
    fwrite(STDERR,json_encode(['erro'=>$msg],JSON_UNESCAPED_UNICODE)."\n"); exit(1);
}
