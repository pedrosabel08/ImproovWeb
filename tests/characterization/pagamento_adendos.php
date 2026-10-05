<?php
/**
 * FASE 0: diagnóstico CLI isolado. Nunca chama endpoints, gerarAdendo(), PDF ou writers.
 * --capture: SELECT/SHOW e saída JSON para stdout; não grava o snapshot automaticamente.
 * --verify: replay offline do saldo e transformações documentais contra o snapshot congelado.
 * --summary: resumo do snapshot; não conecta ao banco.
 * Projeção de células JS é evidência estática, não validação de navegador/regra aprovada.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const FASE0_ROOT = __DIR__ . '/../..';
const FASE0_SNAPSHOT = __DIR__ . '/pagamento_adendos_golden.php';

function fase0_read_snapshot(): array
{
    $text=file_get_contents(FASE0_SNAPSHOT);$marker='__halt_compiler();';
    $offset=strpos($text,$marker);
    if($offset===false)throw new RuntimeException('Envelope do snapshot inválido.');
    return json_decode(substr($text,$offset+strlen($marker)),true,512,JSON_THROW_ON_ERROR);
}

final class Fase0ReadOnlyConnection extends mysqli
{
    public array $audit = [];
    private function checkSql(string $sql): void
    {
        // Falha fechada: só as consultas auditadas SELECT/SHOW, sem locks/output/múltiplas statements.
        if (!preg_match('/^\s*(SELECT|SHOW)\b/i', $sql)
            || preg_match('/;|\b(INTO|OUTFILE|DUMPFILE|FOR\s+UPDATE|LOCK\s+IN\s+SHARE|GET_LOCK|RELEASE_LOCK|SLEEP|BENCHMARK)\b/i', $sql)) {
            throw new RuntimeException('FASE0: SQL não permitido.');
        }
        $this->audit[] = $sql;
    }
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool
    { $this->checkSql($query); return parent::query($query, $result_mode); }
    public function prepare(string $query): mysqli_stmt|false
    { $this->checkSql($query); return parent::prepare($query); }
    public function multi_query(string $query): bool
    { throw new RuntimeException('FASE0: multi_query proibido.'); }
    public function real_query(string $query): bool
    { $this->checkSql($query); return parent::real_query($query); }
}

function fase0_query(mysqli $conn, string $sql, string $types = '', array $values = []): array
{ return custos_query($conn, $sql, $types, $values); }

function fase0_log(string $message): bool
{ $GLOBALS['_fase0_runtime_logs'][]=$message; return true; }

// Substitui apenas a saída de erro do controller na execução isolada, sem carregar autenticação.
function pagamento_json(array $payload, int $status = 200): void
{ throw new RuntimeException('Controller read-only HTTP ' . $status . ': ' . json_encode($payload)); }

function fase0_service(?mysqli $conn = null): AdendoLocalService
{
    $class = new ReflectionClass(AdendoLocalService::class);
    $service = $class->newInstanceWithoutConstructor();
    if ($conn) $class->getProperty('conn')->setValue($service, $conn);
    return $service;
}

function fase0_pure(AdendoLocalService $service, string $method, array $args): mixed
{
    $allow = ['normalizeItensInput','buildRows','sumRows','normalizeExtras','sumExtras','buildTabelaHtml','buildExtrasTabelaHtml'];
    if (!in_array($method, $allow, true)) throw new RuntimeException('Método não auditado.');
    return (new ReflectionMethod($service, $method))->invokeArgs($service, $args);
}

function fase0_screen(mysqli $conn, int $colab, string $ref): array
{
    $file = FASE0_ROOT . '/Pagamento/getColaborador.php';
    $source = file_get_contents($file);
    $start = strpos($source, '$colaboradorId = intval');
    $end = strpos($source, 'echo json_encode($response);');
    if ($start === false || $end === false || $end <= $start) throw new RuntimeException('Delimitadores do controller mudaram.');
    $body = substr($source, $start, $end - $start);
    // Sem bootstrap, conexão, sessão, HTTP ou logs. SQL/cálculos permanecem no texto original.
    $body = str_replace("require_once __DIR__ . '/financeiro_v2.php';", '', $body);
    $body = preg_replace('/\berror_log\s*\(/', 'fase0_log(', $body);
    if (preg_match('/\b(require|include|session_start|file_put_contents|fopen|fwrite|mkdir|rename|unlink|curl_exec|exec|financeiro_pagar|financeiro_lancar|financeiro_total)\s*(\(|_once\b)/', $body)) {
        throw new RuntimeException('Corpo contém operação não auditada.');
    }
    [$year, $month] = array_map('intval', explode('-', $ref));
    $previousGet = $_GET;
    $_GET = ['colaborador_id'=>$colab, 'mes_id'=>$month, 'ano'=>$year];
    try {
        $result = (static function (mysqli $conn, string $body): array {
            eval($body);
            // Não conservar CPF, endereço, email ou outros dados cadastrais pessoais na golden.
            $stmt->close();
            return ['funcoes'=>$response['funcoes'], 'custo_total'=>$response['custo_total']];
        })($conn, $body);
    } finally { $_GET = $previousGet; }
    return $result;
}

function fase0_role(array $item): string
{
    $role = (string)($item['nome_funcao'] ?? '');
    if ($item['origem'] === 'acompanhamento') $role = 'Acompanhamento';
    if ($item['origem'] === 'funcao_animacao' && $role === '') $role = 'Animação';
    if ($item['origem'] === 'funcao_imagem') {
        if ((int)($item['pago_completa_count'] ?? 0)>0) $role .= ' Pago Completa';
        elseif ((int)($item['pago_parcial_count'] ?? 0)>0) $role .= ' Pago Parcial';
    }
    return $role;
}

function fase0_tab(array $item): string
{
    // Equivalente ao NFD + remoção de diacríticos usado pelo JS; iconv no Windows
    // produz "a~" para ã e não representa essa normalização.
    $role = strtr(mb_strtolower((string)($item['nome_funcao'] ?? '')), [
        'ã'=>'a','á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ç'=>'c','é'=>'e','ê'=>'e',
        'í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u',
    ]);
    $isComplete = str_contains($role, 'finalizacao') && str_contains($role, 'completa');
    $paid = (int)$item['pagamento']===1 && !($isComplete && (int)($item['pago_parcial_count']??0)>0 && (int)($item['pago_completa_count']??0)===0);
    return $paid ? 'pagos' : 'a_pagar';
}

function fase0_payload(array $item, string $tab): array
{
    // Projeção estática dos índices APÓS transformUnpaid/transformPaid; não executa UI.
    $amount = (float)($item['valor_exibido'] ?? $item['valor'] ?? 0);
    $money = 'R$ ' . number_format($amount, 2, ',', '.');
    $role = fase0_role($item);
    if ($tab === 'pagos') return [
        'imagem_nome'=>trim($role), 'nome_funcao'=>$money, 'valor'=>0,
        'data_pagamento'=>'—', 'pago_parcial_count'=>0, 'pago_completa_count'=>0,
    ];
    $role = trim(preg_replace(['/Pago\s*Parcial/i','/Pago\s*Completa/i','/Pago/i'], '', $role));
    return [
        'imagem_nome'=>trim((string)($item['imagem_nome']??'')), 'nome_funcao'=>$role,
        'valor'=>$amount, 'data_pagamento'=>null,
        'pago_parcial_count'=>(int)($item['pago_parcial_count']??0),
        'pago_completa_count'=>(int)($item['pago_completa_count']??0),
    ];
}

function fase0_document(AdendoLocalService $service, array $payload, int $colab, ?float $fixed, array $extras = []): array
{
    $normalized = fase0_pure($service, 'normalizeItensInput', [$payload]);
    $rows = fase0_pure($service, 'buildRows', [$normalized,true]);
    $normalizedExtras = fase0_pure($service, 'normalizeExtras', [$extras]);
    // Mesma rubrica hardcoded, identificada separadamente; não recebe intenção de negócio.
    if ($colab===1) $normalizedExtras = [['categoria'=>'Acompanhamento','valor'=>4000.0]];
    $services = fase0_pure($service, 'sumRows', [$rows]);
    $extraTotal = fase0_pure($service, 'sumExtras', [$normalizedExtras]);
    return ['payload'=>$payload,'normalized'=>$normalized,'rows'=>$rows,'services_total'=>$services,
        'extras'=>$normalizedExtras,'extras_total'=>$extraTotal,'fixed_input'=>$fixed,
        'total_conditional'=>$fixed===null?null:$services+$extraTotal+$fixed,
        'empty_payload_triggers_fallback'=>count($payload)===0,
        'extras_input_source'=>'Lista manual não observada; replay condicionado a nenhum extra manual. ID1 aplica 4000 do código.',
        'limitation'=>'Projeção estática do DOM + execução real de métodos puros; sem PDF. Fixo null = modal não observado. Total não implica que regeneração/branch vazio permitam gerar.'];
}

function fase0_resumo(mysqli $conn, string $ref): array
{
    $source=file_get_contents(FASE0_ROOT.'/Pagamento/getResumo.php');
    $start=strpos($source,'$ano = isset');
    $end=strpos($source,"echo json_encode(['items' => \$items, 'mes_ref' => \$mes_ref]);");
    if($start===false||$end===false)throw new RuntimeException('Delimitadores do resumo mudaram.');
    $body=substr($source,$start,$end-$start);
    [$year,$month]=array_map('intval',explode('-',$ref));$previousGet=$_GET;
    $_GET=['ano'=>$year,'mes'=>$month];
    try{return(static function(mysqli $conn,string $body):array{eval($body);return $items;})($conn,$body);}
    finally{$_GET=$previousGet;}
}

function fase0_fallback(mysqli $conn, int $colab, string $ref): array
{
    $service = fase0_service($conn);
    [$year,$month]=array_map('intval',explode('-',$ref));
    try {
        $items=(new ReflectionMethod($service,'getAdendoItens'))->invoke($service,$colab,$month,$year);
        $rows=fase0_pure($service,'buildRows',[$items,true]);
        return ['status'=>'executed_read_only','items'=>$items,'rows'=>$rows,'services_total'=>fase0_pure($service,'sumRows',[$rows])];
    } catch(Throwable $e) {
        return ['status'=>'original_error','error_class'=>get_class($e),'error'=>$e->getMessage()];
    }
}

function fase0_pair(mysqli $conn, int $colab, string $ref): array
{
    $screen=fase0_screen($conn,$colab,$ref);
    [$year,$month]=array_map('intval',explode('-',$ref));
    $eligible=financeiro_elegiveis($conn,$colab,$month,$year);
    $ledger=fase0_query($conn,'SELECT pi.*,p.colaborador_id,p.mes_ref FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE p.colaborador_id=? ORDER BY pi.idpagamento_item','i',[$colab]);
    $col=fase0_query($conn,'SELECT idcolaborador,nome_colaborador,ativo,valor_fixo FROM colaborador WHERE idcolaborador=?','i',[$colab])[0];
    $old=fase0_query($conn,'SELECT id,competencia,status,payload_enviado,arquivo_nome FROM adendos WHERE colaborador_id=? AND competencia=?','is',[$colab,$ref]);
    $fixed=count($old)&&json_decode($old[0]['payload_enviado']??'',true)?(json_decode($old[0]['payload_enviado'],true)['VALOR_FIXO']??null):null;
    $service=fase0_service();
    $annotated=[];$payloads=['a_pagar'=>[],'pagos'=>[]];
    foreach($screen['funcoes'] as $item){
        $origin=$item['origem'];$id=(int)$item['identificador'];$commission=!empty($item['comissao_gestor']);
        $payments=array_values(array_filter($ledger,fn($p)=>$p['origem']===$origin&&(int)$p['origem_id']===$id&&((custos_tipo($p)==='COMISSAO')===$commission)));
        $totalPaid=array_sum(array_map(fn($p)=>custos_centavos($p['valor']),$payments))/100;
        $tab=fase0_tab($item);$payload=fase0_payload($item,$tab);$payloads[$tab][]=$payload;
        $single=fase0_document($service,[$payload],$colab,null);
        $annotated[]=['identity'=>['origem'=>$origin,'id'=>$id,'beneficiary'=>$colab,'commission'=>$commission],
            'endpoint_item'=>$item,'ledger'=>$payments,'paid_total'=>$totalPaid,'tab_projected'=>$tab,
            'value_endpoint_balance'=>(float)$item['valor_exibido'],
            'value_cell_before_redesign'=>(float)($origin==='funcao_imagem'?$item['valor_exibido']:($item['valor']??0)),
            'value_cell_after_redesign'=>(float)$item['valor_exibido'],
            'js_projected'=>$payload,'backend_normalized'=>$single['normalized'][0]??null,
            'included_by_buildRows'=>count($single['rows'])>0,'document_row'=>$single['rows'][0]??null];
    }
    $fallback=fase0_fallback($conn,$colab,$ref);
    $comparison=['a_screen_count'=>count($screen['funcoes']),'b_status'=>$fallback['status']];
    if($fallback['status']==='executed_read_only'){
        $aMap=[];$bMap=[];
        foreach($screen['funcoes'] as $r)$aMap[$r['origem'].':'.$r['identificador']]=['name'=>$r['imagem_nome']??null,'role'=>$r['nome_funcao']??null,'value'=>$r['valor_exibido']];
        foreach($fallback['items'] as $r)$bMap[$r['origem'].':'.$r['identificador']]=['name'=>$r['imagem_nome']??null,'role'=>$r['nome_funcao']??null,'value'=>$r['valor']];
        $aIds=array_keys($aMap);$bIds=array_keys($bMap);sort($aIds);sort($bIds);
        $diff=[];$semantic=[];
        foreach(array_intersect($aIds,$bIds) as $id){
            if($aMap[$id]!==$bMap[$id])$diff[$id]=['A'=>$aMap[$id],'B'=>$bMap[$id]];
            $a=$aMap[$id];$b=$bMap[$id];
            $a['value']=$a['value']===null?null:(float)$a['value'];
            $b['value']=$b['value']===null?null:(float)$b['value'];
            if($a!==$b)$semantic[$id]=['A'=>$aMap[$id],'B'=>$bMap[$id]];
        }
        $comparison+=['b_count'=>count($fallback['items']),'A_ids'=>$aIds,'B_ids'=>$bIds,'only_A'=>array_values(array_diff($aIds,$bIds)),'only_B'=>array_values(array_diff($bIds,$aIds)),
            'field_differences'=>$diff,'semantic_differences'=>$semantic,'comparison_note'=>'field_differences preserva tipos PHP; semantic_differences compara números após float, preservando null.'];
    }
    return ['colaborador'=>$col,'competencia'=>$ref,'screen'=>$screen,'eligible_v2'=>$eligible,'items'=>$annotated,
        'documents'=>['a_pagar'=>fase0_document($service,$payloads['a_pagar'],$colab,$fixed),
            'pagos'=>fase0_document($service,$payloads['pagos'],$colab,$fixed)],
        'fixed_input_source'=>$fixed===null?'not_observed':'VALOR_FIXO of existing adendo; conditional replay, not historical full reconstruction',
        'existing_adendo'=>$old,
        'generation_gate'=>$old&&in_array($old[0]['status'],['assinado','recusado','expirado'],true)?'blocked_by_existing_status':'allowed_by_existing_status',
        'fallback'=>$fallback,'path_comparison'=>$comparison];
}

function fase0_capture(): array
{
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    // Arquivo auditado: configurações + conexão, sem SQL de escrita. Nunca imprimir credenciais.
    require FASE0_ROOT . '/conexao.php';
    $conn->close();
    $conn=new Fase0ReadOnlyConnection($servername,$username,$password,$dbname);
    $conn->set_charset('utf8mb4');
    $pairs=[[27,'2026-09'],[20,'2025-11'],[6,'2026-09'],[8,'2026-09'],[8,'2026-08'],[13,'2026-09'],[13,'2026-08'],[1,'2025-01'],[1,'2026-08'],[7,'2026-08'],[4,'2026-08'],[33,'2026-08'],[33,'2026-09'],[40,'2026-04']];
    $result=['format_version'=>1,'captured_at'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM),
        'scope'=>'FASE0: banco somente leitura; snapshots atuais, não reconstrução histórica da data original; UI não executada',
        'source_hashes'=>[], 'pairs'=>[], 'reference_cases'=>[], 'queries'=>[]];
    foreach(['Pagamento/getColaborador.php','Pagamento/financeiro_v2.php','Pagamento/script.js','Contratos/services/AdendoLocalService.php','helpers/custos_helper.php','helpers/custo_tarefa.php'] as $file) $result['source_hashes'][$file]=hash_file('sha256',FASE0_ROOT.'/'.$file);
    foreach($pairs as [$colab,$ref]){fwrite(STDERR,"Capturando leitura $colab/$ref\n");$result['pairs']["$colab/$ref"]=fase0_pair($conn,$colab,$ref);}
    $definitions=[
        ['CASE_NORMAL_001',27,'2026-09','funcao_imagem',120437],
        ['CASE_PARCIAL_001',20,'2025-11','funcao_imagem',102804],
        ['CASE_PAGO_001',6,'2026-09','funcao_imagem',110526],
        ['CASE_COMISSAO_001',8,'2026-09','funcao_imagem',120172],
        ['CASE_COMISSAO_PAGA_001',8,'2026-08','funcao_imagem',118830],
        ['CASE_ANIMACAO_001',13,'2026-09','funcao_animacao',534],
        ['CASE_ACOMPANHAMENTO_001',1,'2025-01','acompanhamento',617],
        ['CASE_DIVERGENCIA_001',33,'2026-08','funcao_imagem',110107],
        ['CASE_LOG_001',33,'2026-09','funcao_imagem',120385],
        ['CASE_ENTRE_MESES_001',40,'2026-04','funcao_imagem',111524],
    ];
    foreach($definitions as [$name,$colab,$ref,$origin,$id]){
        $pair=$result['pairs']["$colab/$ref"];
        $match=array_values(array_filter($pair['items'],fn($r)=>$r['identity']['origem']===$origin&&$r['identity']['id']===$id));
        $v2=array_values(array_filter($pair['eligible_v2'],fn($r)=>$r['origem']===$origin&&(int)$r['origem_id']===$id));
        $raw=$origin==='funcao_imagem'?fase0_query($conn,'SELECT fi.*,f.nome_funcao,i.imagem_nome,i.tipo_imagem FROM funcao_imagem fi JOIN funcao f ON f.idfuncao=fi.funcao_id JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id WHERE fi.idfuncao_imagem=?','i',[$id]):
            ($origin==='acompanhamento'?fase0_query($conn,'SELECT a.*,i.imagem_nome FROM acompanhamento a LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=a.imagem_id WHERE a.idacompanhamento=?','i',[$id]):
            fase0_query($conn,'SELECT fa.*,a.data_anima,f.nome_funcao,i.imagem_nome FROM funcao_animacao fa JOIN animacao a ON a.idanimacao=fa.animacao_id JOIN funcao f ON f.idfuncao=fa.funcao_id LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=a.imagem_id WHERE fa.id=?','i',[$id]));
        $ledger=fase0_query($conn,'SELECT pi.*,p.colaborador_id,p.mes_ref FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE pi.origem=? AND pi.origem_id=? ORDER BY pi.idpagamento_item','si',[$origin,$id]);
        $logs=$origin==='funcao_imagem'?fase0_query($conn,'SELECT idlog,data,status_anterior,status_novo,colaborador_id FROM log_alteracoes WHERE funcao_imagem_id=? ORDER BY data,idlog','i',[$id]):[];
        $result['reference_cases'][$name]=['pair'=>"$colab/$ref",'origin'=>$origin,'origin_id'=>$id,'raw_origin'=>$raw[0]??null,'screen_match'=>$match[0]??null,'eligible_v2_match'=>$v2[0]??null,'all_origin_ledger'=>$ledger,'logs'=>$logs];
    }
    $result['resumos']=['2026-08'=>fase0_resumo($conn,'2026-08'),'2025-01'=>fase0_resumo($conn,'2025-01')];
    $result['coverage_queries']=[
        'fixed_positive'=>fase0_query($conn,'SELECT idcolaborador,nome_colaborador,ativo,valor_fixo FROM colaborador WHERE valor_fixo>0 ORDER BY idcolaborador'),
        'fixed_null_count'=>fase0_query($conn,'SELECT COUNT(*) n FROM colaborador WHERE valor_fixo IS NULL'),
        'animation_175_count'=>fase0_query($conn,'SELECT COUNT(*) n FROM funcao_animacao WHERE valor=175'),
        'adendo_statuses'=>fase0_query($conn,'SELECT status,COUNT(*) n FROM adendos GROUP BY status'),
        'ledger_origins'=>fase0_query($conn,"SELECT origem,observacao,COUNT(*) n FROM pagamento_itens GROUP BY origem,observacao"),
        'adendos_fixed'=>fase0_query($conn,"SELECT id,colaborador_id,competencia,status,payload_enviado FROM adendos WHERE colaborador_id IN (1,7,34,4,8) AND payload_enviado IS NOT NULL ORDER BY competencia DESC,id DESC"),
        'schema'=>fase0_query($conn,"SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('funcao_imagem','funcao_animacao','animacao','acompanhamento','pagamento_itens','pagamentos','colaborador') ORDER BY TABLE_NAME,ORDINAL_POSITION"),
    ];
    $caseEvidence=[
        'CASE_NORMAL_001'=>['Tarefa positiva sem ledger ou divergência','COMPORTAMENTO ATUAL CONFIRMADO'],
        'CASE_PARCIAL_001'=>['Parcial real; saldo matemático 125, mas elegibilidade marca parcial e tela remove','COMPORTAMENTO ATUAL CONFIRMADO'],
        'CASE_PAGO_001'=>['Ledger cobre valor; routing especial conserva item na aba A pagar com saldo zero','POSSÍVEL BUG'],
        'CASE_COMISSAO_001'=>['Beneficiário 8, tarefa 40, valor registrado 300 e comissão 80','REGRA DE NEGÓCIO APARENTE'],
        'CASE_COMISSAO_PAGA_001'=>['Comissão e remuneração da tarefa coexistem em beneficiários diferentes','COMPORTAMENTO ATUAL CONFIRMADO'],
        'CASE_ANIMACAO_001'=>['Data animação Setembro, prazo Agosto, ledger Agosto; saldo zero e nomes degradados no V2','POSSÍVEL BUG'],
        'CASE_ACOMPANHAMENTO_001'=>['Origem paga sem ledger; projeção A inclui valor e perde data; B exclui','POSSÍVEL BUG'],
        'CASE_DIVERGENCIA_001'=>['Snapshot 150, pago 275, flag V2 true e legada false','POSSÍVEL BUG'],
        'CASE_LOG_001'=>['Prazo Outubro; Setembro entra pelo log elegível','COMPORTAMENTO ATUAL CONFIRMADO'],
        'CASE_ENTRE_MESES_001'=>['Parcial Março+complemento Abril deixam saldo25, excluído por contador completa','POSSÍVEL BUG'],
    ];
    foreach($result['reference_cases'] as $name=>&$case){
        [$description,$classification]=$caseEvidence[$name];$case['description']=$description;$case['classification']=$classification;
        $case['evidence']=['Pagamento/getColaborador.php:737-772','Pagamento/financeiro_v2.php:7-29','Pagamento/script.js:1792-1843,2786-2869','Contratos/services/AdendoLocalService.php:216-270'];
        $case['observation_level']='SELECT real + execução isolada do controller/métodos; payload DOM projetado estaticamente';
        $pair=$result['pairs'][$case['pair']];$case['fixed_input']=$pair['documents']['a_pagar']['fixed_input'];
        $case['extras']=$pair['documents']['a_pagar']['extras'];$case['whole_competence_services']=$pair['documents']['a_pagar']['services_total'];
        $case['whole_competence_total_conditional']=$pair['documents']['a_pagar']['total_conditional'];
        $case['generation_gate']=$pair['generation_gate'];
        $raw=$case['raw_origin'];[$y,$m]=array_map('intval',explode('-',explode('/',$case['pair'])[1]));
        $begin=sprintf('%04d-%02d-01',$y,$m);$finish=(new DateTimeImmutable($begin))->modify('first day of next month')->format('Y-m-d');
        $eligibleStatus=['finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado'];
        $case['eligibility_reasons']=$case['origin']==='funcao_imagem'?[
            'current_status_and_deadline'=>in_array(mb_strtolower(trim($raw['status'])), $eligibleStatus,true)&&$raw['prazo']>=$begin&&$raw['prazo']<$finish,
            'eligible_log_ids'=>array_column(array_values(array_filter($case['logs'],fn($l)=>$l['data']>=$begin&&$l['data']<$finish&&in_array(mb_strtolower(trim($l['status_novo'])),$eligibleStatus,true))),'idlog'),
        ]:($case['origin']==='funcao_animacao'?['date_source'=>'animacao.data_anima','date'=>$raw['data_anima'],'status'=>$raw['status']]:['date_source'=>'acompanhamento.data','date'=>$raw['data']]);
    }unset($case);
    $staticCases=[
        'CASE_FIXO_001'=>[7,'2026-08','REGRA NÃO DETERMINADA','Cadastro4600; adendo133 VALOR_FIXO4600/total4600; modal sem prefill'],
        'CASE_FIXO_ZERO_001'=>[4,'2026-08','COMPORTAMENTO ATUAL CONFIRMADO','Cadastro0; adendo141 fixo0; NULL inexistente no banco capturado'],
        'CASE_BONUS_001'=>[1,'2026-08','REGRA DE NEGÓCIO APARENTE','Extras manuais não recuperáveis; regra existente Acompanhamento4000 substitui array; adendo145 total8000'],
        'CASE_FILTROS_001'=>[27,'2026-09','DÍVIDA TÉCNICA','Busca/função/divergência/aba mudam linhas visíveis; obra indisponível; checkbox marcado não participa'],
        'CASE_ABA_A_PAGAR_001'=>[1,'2025-01','POSSÍVEL BUG','Índices checkbox,tarefa,função,valor,situação,ações; índice5 usado como data'],
        'CASE_ABA_PAGOS_001'=>[13,'2026-09','POSSÍVEL BUG','Índices tarefa,função,valor,tipo,data,detalhes; coletor lê1/2/3/5 e recebe valor0'],
        'CASE_LISTA_VAZIA_001'=>[27,'2026-09','POSSÍVEL BUG','itens=[] aciona fallback; execução somente leitura confirmou erro bind7 tipos/6 args'],
        'CASE_REGENERACAO_001'=>[8,'2026-08','REGRA NÃO DETERMINADA','Registro140 gerado permite; visualizado permite; assinado/recusado/expirado bloqueiam; sem executar regeneração'],
    ];
    foreach($staticCases as $name=>[$colab,$ref,$classification,$description])$result['reference_cases'][$name]=[
        'pair'=>"$colab/$ref",'classification'=>$classification,'description'=>$description,
        'observation_level'=>'Registros reais vinculados + análise estática; sem interação, geração ou confirmação',
        'evidence'=>['Pagamento/script.js:1695-1856,2786-2869','Contratos/services/AdendoLocalService.php:25-139,188-203'],
    ];
    $result['query_audit']=['count'=>count($conn->audit),'sql'=>array_values(array_unique($conn->audit)),'allowed'=>'SELECT/SHOW only, guard before query/prepare'];
    $conn->close();return $result;
}

function fase0_verify(array $snapshot): void
{
    $service=fase0_service();$checks=0;
    $source=file_get_contents(FASE0_ROOT.'/Pagamento/getColaborador.php');
    $begin=strpos($source,'$paid = [];');
    $end=$begin===false?false:strpos($source,'unset($f);',$begin);
    if($begin===false||$end===false)throw new RuntimeException('Trecho do saldo mudou; adaptar diagnóstico explicitamente.');
    $balanceBody=substr($source,$begin,$end+strlen('unset($f);')-$begin);
    // O controller também pode agregar ledger antes de selecionar os itens.
    // Reproduzir só agregação + saldo sobre os MESMOS inputs congelados;
    // seleção/elegibilidade não pertencem ao replay histórico offline.
    if(str_contains($balanceBody,'foreach ($eligible as $r)')){
        $aggregationEnd=strpos($source,'    $funcoes = [];',$begin);
        $balanceBegin=$aggregationEnd===false?false:strpos($source,'    foreach ($funcoes as &$f) {',$aggregationEnd);
        if($aggregationEnd===false||$balanceBegin===false||$aggregationEnd>=$balanceBegin||$balanceBegin>=$end){
            throw new RuntimeException('Agregação/seleção/saldo mudaram; auditar diagnóstico explicitamente.');
        }
        $balanceBody=substr($source,$begin,$aggregationEnd-$begin)
            .substr($source,$balanceBegin,$end+strlen('unset($f);')-$balanceBegin);
    }
    if(preg_match('/\b(SELECT|UPDATE|INSERT|DELETE|require|include|prepare|query)\b/i',$balanceBody))throw new RuntimeException('Replay do saldo contém operação não pura.');
    foreach($snapshot['pairs'] as $key=>$pair){
        $expectedItems=$pair['screen']['funcoes'];$ledger=[];
        // Congela o mesmo conjunto de entradas consultadas na captura, sem DB.
        $ledgerById=[];foreach($pair['items'] as $item)foreach($item['ledger'] as $payment)$ledgerById[$payment['idpagamento_item']]=$payment;
        $ledger=array_values($ledgerById);
        $inputs=$expectedItems;foreach($inputs as &$input)$input['valor_exibido']=$input['custo'];unset($input);
        $replayed=(static function(array $funcoes,array $ledger,string $body):array{eval($body);return $funcoes;})($inputs,$ledger,$balanceBody);
        foreach($replayed as $idx=>$item)foreach(['valor_exibido','pagamento','divergencia_financeira'] as $field){
            $checks++;
            if($item[$field]!==$expectedItems[$idx][$field])throw new RuntimeException("Golden saldo mudou: $key/$idx/$field");
        }
        foreach($pair['documents'] as $tab=>$expected){
            $actual=fase0_document($service,$expected['payload'],(int)$pair['colaborador']['idcolaborador'],$expected['fixed_input']);
            foreach(['normalized','rows','services_total','extras','extras_total','total_conditional'] as $field){
                $checks++;
                if(json_encode($actual[$field],JSON_PRESERVE_ZERO_FRACTION)!==json_encode($expected[$field],JSON_PRESERVE_ZERO_FRACTION)) throw new RuntimeException("Golden master mudou: $key/$tab/$field");
            }
        }
        if($pair['fallback']['status']==='executed_read_only'){
            $rows=fase0_pure($service,'buildRows',[$pair['fallback']['items'],true]);$checks++;
            if(json_encode($rows)!==json_encode($pair['fallback']['rows']))throw new RuntimeException("Golden fallback mudou: $key");
        }
    }
    echo "OK: $checks comparações offline, sem conexão DB/PDF/DOM vivo.\n";
}

require_once FASE0_ROOT . '/helpers/custo_tarefa.php';
require_once FASE0_ROOT . '/Pagamento/financeiro_v2.php';
require_once FASE0_ROOT . '/Contratos/services/AdendoLocalService.php';
try {
    $mode=$argv[1]??'--verify';
    if($mode==='--capture') echo json_encode(fase0_capture(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR).PHP_EOL;
    elseif($mode==='--verify') fase0_verify(fase0_read_snapshot());
    elseif($mode==='--summary'){
        $s=fase0_read_snapshot();
        foreach($s['pairs'] as $key=>$p)echo json_encode(['pair'=>$key,'screen_n'=>count($p['items']),'v2_n'=>count($p['eligible_v2']),'a_pagar_rows'=>count($p['documents']['a_pagar']['rows']),'a_pagar_services'=>$p['documents']['a_pagar']['services_total'],'fixed'=>$p['documents']['a_pagar']['fixed_input'],'extras'=>$p['documents']['a_pagar']['extras_total'],'total_conditional'=>$p['documents']['a_pagar']['total_conditional'],'fallback_status'=>$p['fallback']['status'],'fallback_n'=>count($p['fallback']['items']??[]),'fallback_services'=>$p['fallback']['services_total']??null],JSON_UNESCAPED_UNICODE).PHP_EOL;
        foreach($s['reference_cases'] as $name=>$c){
            $r=$c['raw_origin']??[];$m=$c['screen_match']??null;
            echo json_encode(['case'=>$name,'pair'=>$c['pair'],'id'=>$c['origin_id']??null,'description'=>$c['description'],'raw_value'=>$r['valor']??null,
                'raw_status'=>$r['status']??null,'screen_name'=>$m['endpoint_item']['imagem_nome']??null,'screen_role'=>$m['endpoint_item']['nome_funcao']??null,
                'paid'=>$m['paid_total']??null,'balance'=>$m['value_endpoint_balance']??null,'tab'=>$m['tab_projected']??null,
                'legacy_divergence'=>$m['endpoint_item']['tem_divergencia']??null,'v2_divergence'=>$m['endpoint_item']['divergencia_financeira']??null,
                'js'=>$m['js_projected']??null,'included'=>$m['included_by_buildRows']??null,'document_row'=>$m['document_row']??null,'ledger'=>$c['all_origin_ledger']??null,
                'eligibility'=>$c['eligibility_reasons']??null],JSON_UNESCAPED_UNICODE).PHP_EOL;
        }
    } elseif($mode==='--snapshot-json')echo json_encode(fase0_read_snapshot(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR).PHP_EOL;
    else throw new RuntimeException('Use --capture, --verify, --summary ou --snapshot-json.');
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
