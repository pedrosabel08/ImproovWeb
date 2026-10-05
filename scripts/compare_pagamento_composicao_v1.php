<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../Pagamento/services/FechamentoComposicaoService.php';
require_once __DIR__ . '/diagnostico/ComparacaoComposicaoFinanceira.php';

try {
    $opts=getopt('',['colaborador:','mes:','ano:','golden','resumo']);$reports=[];
    if(isset($opts['golden'])) {
        require_once __DIR__ . '/../tests/fixtures/pagamento_adendos_composicao.php';$g=financeiro_test_golden();$time=new DateTimeImmutable($g['captured_at']);
        foreach(['CASE_FIXO_001'=>'7/2026-08','CASE_FIXO_ZERO_001'=>'4/2026-08','CASE_BONUS_001'=>'1/2026-08'] as $id=>$pair) {
            $c=composicao_fixture_golden_contexto($g,$pair);$b=$c['colaborador_id'];$ref=$c['competencia'];
            $r=['fixo'=>(new FechamentoFixoRules())->calcular($c['fixo'],$b,$ref,$time),
                'extras'=>(new FechamentoExtrasRules())->calcular($c['extras'],$b,$ref,$time),
                'acompanhamento_especial'=>(new FechamentoRubricasEspeciais())->obter($b,$ref),'snapshot_em'=>$time->format(DATE_ATOM)];
            $d=ComparacaoComposicaoFinanceira::comparar($c,$r,ComparacaoComposicaoFinanceira::regraEspecialLegada($b));
            $d['case_id']=$id;$d['escopo']=$c['observacao'];$d['complementos']=$r;$reports[]=$d;
        }
    } else {
        $b=filter_var($opts['colaborador']??null,FILTER_VALIDATE_INT);$mes=filter_var($opts['mes']??null,FILTER_VALIDATE_INT);$ano=filter_var($opts['ano']??null,FILTER_VALIDATE_INT);
        if(!$b||!$mes||!$ano)throw new InvalidArgumentException('Use --golden ou --colaborador=7 --mes=8 --ano=2026.');
        $ref=PagamentoService::competencia($mes,$ano);$regra=ComparacaoComposicaoFinanceira::regraEspecialLegada($b);
        require __DIR__ . '/../conexao.php';$conn->close();$conn=new FechamentoFinanceiroReadOnlyConnection($servername,$username,$password,$dbname);
        try {
            $conn->set_charset('utf8mb4');$s=new FechamentoFinanceiroService(new FechamentoFinanceiroRepository($conn));
            $service=new FechamentoComposicaoService($s,new FechamentoComposicaoRepository());$read=$service->calcularComContexto($b,$ref);
            $d=ComparacaoComposicaoFinanceira::comparar($read['contexto'],$read['composicao'],$regra);
            $d['composicao']=$read['composicao'];$d['operacoes_sql_auditadas']=count($conn->audit);$reports[]=$d;
        }finally{$conn->close();}
    }
    $unexpected=0;
    foreach($reports as $d){$unexpected+=$d['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0;
        if(isset($opts['resumo'])&&isset($d['composicao']))unset($d['composicao']['financeiro_servicos']['itens_analisados'],$d['composicao']['financeiro_servicos']['servicos_devidos']);
        echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
    if($unexpected)exit(2);
}catch(Throwable $e){fwrite(STDERR,'Shadow composição: '.$e->getMessage().PHP_EOL);exit(1);}
