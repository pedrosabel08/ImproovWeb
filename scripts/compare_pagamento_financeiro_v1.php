<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../Pagamento/services/FechamentoFinanceiroService.php';
require_once __DIR__ . '/../Pagamento/services/FechamentoFinanceiroReadOnlyConnection.php';
require_once __DIR__ . '/diagnostico/ComparacaoFechamentoFinanceiro.php';

try {
    $options = getopt('', ['colaborador:', 'mes:', 'ano:', 'golden', 'resumo']);
    $rules = new FechamentoFinanceiroRules();
    $reports = [];
    if (isset($options['golden'])) {
        require_once __DIR__ . '/../tests/fixtures/pagamento_adendos_financeiro.php';
        $golden = financeiro_test_golden();
        foreach ($golden['reference_cases'] as $caseId=>$case) {
            if (!isset($case['raw_origin'])) continue;
            $c=financeiro_test_caso($golden,$caseId);
            $target=$rules->calcular($c['dados'],$c['beneficiario'],$c['competencia'],$c['snapshot']);
            $report=ComparacaoFechamentoFinanceiro::comparar($c['legacy_screen'],$c['legacy_eligible'],$c['dados'],$target);
            $report['case_id']=$caseId; $report['origem_dados']=$c['observacao']; $reports[]=$report;
        }
    } else {
        $colab=filter_var($options['colaborador']??null,FILTER_VALIDATE_INT);
        $mes=filter_var($options['mes']??null,FILTER_VALIDATE_INT);
        $ano=filter_var($options['ano']??null,FILTER_VALIDATE_INT);
        if (!$colab || !$mes || !$ano) throw new InvalidArgumentException('Use --golden ou --colaborador=8 --mes=9 --ano=2026; --resumo é opcional.');
        $ref=PagamentoService::competencia($mes,$ano);
        require __DIR__ . '/../conexao.php'; // Configuração local auditada; sem SQL de escrita.
        $conn->close();
        $conn=new FechamentoFinanceiroReadOnlyConnection($servername,$username,$password,$dbname);
        try {
            $conn->set_charset('utf8mb4');
            $repo=new FechamentoFinanceiroRepository($conn);
            $read=$repo->carregar($colab,$ref,fn($db)=>ComparacaoFechamentoFinanceiro::lerLegadoA($db,$colab,$ref));
            $target=$rules->calcular($read['dados'],$colab,$ref,$read['snapshot']);
            $report=ComparacaoFechamentoFinanceiro::comparar($read['adicional']['screen'],$read['adicional']['eligible'],$read['dados'],$target);
            $report['consistencia']=$read['consistencia']; $report['operacoes_sql_auditadas']=count($conn->audit);
            $report['legacy_source_sha256']=$read['adicional']['source_sha256']; $reports[]=$report;
        } finally { $conn->close(); }
    }
    $unexpected=0;
    foreach ($reports as $r) {
        $unexpected += $r['resumo_classificacoes']['UNEXPECTED_DIFFERENCE']??0;
        if (isset($options['resumo'])) {
            unset($r['direitos']); $r['pendencias']=array_map(fn($p)=>['codigo'=>$p['codigo'],'identidade'=>$p['identidade']],$r['pendencias']);
        }
        echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    }
    if ($unexpected) exit(2); // Diferença inesperada não pode passar silenciosamente.
} catch (Throwable $e) { fwrite(STDERR,'Shadow financeiro: '.$e->getMessage().PHP_EOL); exit(1); }
