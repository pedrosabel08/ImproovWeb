<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--db-readonly',$argv,true)) { fwrite(STDERR,"Use --db-readonly para testes SELECT/transação somente leitura.\n"); exit(2); }
require_once __DIR__ . '/../Pagamento/services/FechamentoFinanceiroService.php';
require_once __DIR__ . '/../Pagamento/services/FechamentoFinanceiroReadOnlyConnection.php';

$checks=0;
function readonly_check(bool $ok,string $message):void
{ global $checks;$checks++;if(!$ok)throw new RuntimeException($message); }
require __DIR__ . '/../conexao.php';
$conn->close();$conn=new FechamentoFinanceiroReadOnlyConnection($servername,$username,$password,$dbname);
try {
    $conn->set_charset('utf8mb4');$repo=new FechamentoFinanceiroRepository($conn);
    $read=$repo->carregar(27,'2026-09',static function(mysqli $db,array $dados,DateTimeImmutable $time):array{
        // Falha de SET TRANSACTION confirma transação ativa sem tentativa de escrita.
        $rejected=false;
        try{$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');}
        catch(mysqli_sql_exception $e){$rejected=$e->getCode()===1568;}
        readonly_check($rejected,'Características não deveriam ser alteráveis durante snapshot.');
        $a=$db->query('SELECT COUNT(*) n FROM pagamento_itens')->fetch_assoc();
        $b=$db->query('SELECT COUNT(*) n FROM pagamento_itens')->fetch_assoc();
        readonly_check($a===$b,'Leituras repetidas na mesma visão.');
        readonly_check(count($dados['origens'])>0,'Repositório carregou candidatos reais.');
        return ['snapshot_em'=>$time->format(DATE_ATOM)];
    });
    readonly_check(in_array('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY',$conn->audit,true),'Flags consistentes/read-only executadas.');
    readonly_check(end($conn->audit)==='ROLLBACK','Transação encerrada após sucesso.');
    readonly_check($read['snapshot'] instanceof DateTimeImmutable,'Instante identificado.');
    $service=new FechamentoFinanceiroService($repo);$r=$service->calcular(8,'2026-09');
    readonly_check($r['rule_version']===FechamentoFinanceiroRules::VERSION,'Orquestração motor paralelo.');
    readonly_check($r['subtotal_servicos_centavos']===912000,'Gestor setembro real: serviços9120.');
    readonly_check(!array_key_exists('total_final_adendo',$r),'Não inclui fixo/extras/total final.');
    // Caminho de erro deve encerrar SOMENTE a própria transação.
    $failed=false;try{$repo->carregar(2147483647,'2026-09');}catch(InvalidArgumentException $e){$failed=true;}
    readonly_check($failed,'Colaborador inexistente rejeitado.');readonly_check(end($conn->audit)==='ROLLBACK','Rollback após exceção.');
    // Não assumir ou confirmar a transação do chamador.
    $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY|MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try{
        $start=count($conn->audit);$failed=false;
        try{$repo->carregar(27,'2026-09');}catch(mysqli_sql_exception $e){$failed=$e->getCode()===1568;}
        readonly_check($failed,'Transação preexistente rejeitada.');
        readonly_check(!in_array('ROLLBACK',array_slice($conn->audit,$start),true),'Repositório não encerra transação do chamador.');
        readonly_check((int)$conn->query('SELECT 1 n')->fetch_assoc()['n']===1,'Transação do chamador continua utilizável.');
    }finally{$conn->rollback();}
    echo "OK: $checks verificações de integração read-only; nenhum DML/DDL/PDF/adendo.\n";
}finally{$conn->close();}
