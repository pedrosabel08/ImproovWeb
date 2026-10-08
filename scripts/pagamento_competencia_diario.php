<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/secure_env.php';
improov_load_env_once(getenv('PAGAMENTO_JOB_ENV')?:null);
require_once __DIR__.'/../config/pagamento_fechamento.php';
require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaAutomacao.php';
try {
    $conn=pagamento_fechamento_connection();
    $actor=(int)(getenv('PAGAMENTO_AUTOMACAO_USUARIO_ID')?:1);
    $a=new FechamentoCompetenciaAutomacao($conn,$actor);
    $entregar=in_array('--entregar',$argv,true); $alertar=!in_array('--sem-alertas',$argv,true);
    if($entregar && !$alertar) throw new InvalidArgumentException('Flags incompatíveis.');
    $result=$a->executar(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')),$alertar,$entregar);
    if($entregar) {
        require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaNotificacoes.php';
        $result['entregas']=FechamentoCompetenciaNotificacoes::entregar($conn);
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;
    $conn->close();
} catch(Throwable $e) { fwrite(STDERR,'Falha no ciclo financeiro: '.get_class($e).PHP_EOL); exit(1); }
