<?php

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../Pagamento/services/FechamentoRevisaoService.php';
$o=getopt('', ['acao:','banco:','isolado','readonly-configurado','colaborador:','competencia:','usuario:','expected-version:','chave:','revisao:','tipo:','input:','snapshot','ajuda']);
if (isset($o['ajuda']) || !isset($o['acao'])) {
    echo "FASE 1C-A, sem PDF/documentos.\n";
    echo "--acao=fixture --isolado cria banco sintético (migration apenas nele).\n";
    echo "--acao=preparar|decidir|listar|obter|comparar --isolado --banco=pagamento_1ca_test_<10 hex> --usuario=<id>\n";
    echo "preparar/decidir: --colaborador=<id> --competencia=YYYY-MM --expected-version=<n> --chave=<idempotencia>\n";
    echo "decidir: --tipo=FIXO|BONUS|LIQUIDACAO --input=<arquivo JSON>\n";
    echo "listar: colaborador/competencia; obter/comparar: --revisao=<id>; obter --snapshot inclui snapshot financeiro.\n";
    echo "Somente leituras: --readonly-configurado usa conexão protegida configurada. Não aplica migration.\n";
    exit;
}
function cli_int(array $o,string $name,bool $zero=false): int {
    $v=$o[$name]??null;
    if (!is_string($v) || !preg_match('/^(0|[1-9][0-9]*)$/D',$v) || strlen($v)>10 || (int)$v<($zero?0:1)) throw new InvalidArgumentException('Parâmetro inválido: '.$name);
    return (int)$v;
}
function cli_resumo(array $r): array {
    $c=$r['snapshot']['composicao'];
    return array_diff_key($r,['snapshot'=>true])+['snapshot_em'=>$r['snapshot']['snapshot_em'],'componentes'=>$c['componentes'],
        'componentes_conhecidos_centavos'=>$c['componentes_conhecidos_centavos'],'total_final_centavos'=>$c['total_final_centavos'],
        'total_final_determinado'=>$c['total_final_determinado'],'bloqueado'=>$c['bloqueado'],
        'pendencias'=>array_map(fn($p)=>['codigo'=>$p['codigo'],'componente'=>$p['componente']??null],$c['pendencias'])];
}
try {
    $acao=$o['acao'];
    if (!in_array($acao,['fixture','preparar','decidir','listar','obter','comparar'],true)) throw new InvalidArgumentException('Ação inválida.');
    $isolado=isset($o['isolado']); $readonly=isset($o['readonly-configurado']);
    if ($isolado===$readonly) throw new InvalidArgumentException('Escolha exatamente um ambiente explícito.');
    if (!$isolado && !in_array($acao,['listar','obter','comparar'],true)) throw new RuntimeException('Gravação permitida apenas no banco isolado de fixture.');
    if ($isolado) {
        require_once __DIR__.'/../tests/fixtures/pagamento_adendos_persistencia.php';
        if ($acao==='fixture') {
            $conn=fechamento_test_connection(); $db='pagamento_1ca_test_'.bin2hex(random_bytes(5));
            $conn->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $conn->select_db($db);
            try { fechamento_test_schema($conn); } catch(Throwable $e) { $conn->query("DROP DATABASE `$db`"); throw $e; }
            echo json_encode(['banco'=>$db,'host'=>'127.0.0.1','porta'=>3319,'dados'=>'somente fixtures sintéticas'],JSON_THROW_ON_ERROR)."\n"; exit;
        }
        $conn=fechamento_test_connection((string)($o['banco']??''));
        if (($o['banco']??'')==='') throw new InvalidArgumentException('Banco isolado explícito obrigatório.');
    } else {
        // Bootstrap legado abre conexão, mas não escreve; substituição por guarda SQL antes do serviço.
        require __DIR__.'/../conexao.php';
        $conn->close();
        $conn=new FechamentoFinanceiroReadOnlyConnection($servername,$username,$password,$dbname);
        $conn->set_charset('utf8mb4');
    }
    $s=new FechamentoRevisaoService($conn); $u=cli_int($o,'usuario');
    if (in_array($acao,['preparar','decidir','listar'],true)) { $b=cli_int($o,'colaborador'); $ref=(string)($o['competencia']??''); }
    if ($acao==='preparar') $out=cli_resumo($s->prepararRevisao($b,$ref,$u,cli_int($o,'expected-version',true),(string)($o['chave']??'')));
    elseif ($acao==='decidir') {
        $path=(string)($o['input']??'');
        if (!is_file($path)) throw new InvalidArgumentException('Arquivo JSON de decisão obrigatório.');
        $input=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Decisão JSON inválida.');
        $out=cli_resumo($s->decidir($b,$ref,$u,cli_int($o,'expected-version',true),(string)($o['chave']??''),(string)($o['tipo']??''),$input));
    } elseif ($acao==='listar') $out=$s->listarRevisoes($b,$ref,$u);
    elseif ($acao==='obter') { $r=$s->obterRevisao(cli_int($o,'revisao'),$u); $out=isset($o['snapshot'])?$r:cli_resumo($r); }
    else {
        $r=$s->compararRevisao(cli_int($o,'revisao'),$u);
        unset($r['composicao_atual']);
        foreach (['pendencias_persistidas','pendencias_atuais'] as $p) $r[$p]=array_map(fn($v)=>['codigo'=>$v['codigo'],'componente'=>$v['componente']??null],$r[$p]);
        $out=$r;
    }
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e) {
    // Não imprimir DSN, credenciais, inputs ou trace; mensagens de conexão não vazam hosts.
    $msg=$e instanceof mysqli_sql_exception ? 'Falha SQL; confira ambiente isolado/migration/permissões.' : $e->getMessage();
    fwrite(STDERR,json_encode(['erro'=>$msg],JSON_UNESCAPED_UNICODE)."\n"); exit(1);
}
