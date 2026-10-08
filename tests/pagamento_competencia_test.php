<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/fixtures/fechamento_mensal_v1.php';
require_once __DIR__.'/../Pagamento/services/FechamentoInterfaceService.php';
require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaAutomacao.php';
require_once __DIR__.'/../Pagamento/resumo_geral.php';
require_once __DIR__.'/../Pagamento/api/FechamentoHttp.php';
putenv('FLOW_CONNECT_PAGAMENTO_MODE=shadow');
$f = mensal_test_seed();
$c = documental_test_connection($f['db']);
$checks = 0;
function ok(bool $v, string $s): void
{
    global $checks;
    if (!$v) {
        throw new RuntimeException($s);
    } $checks++;
}
function blocked(callable $f, string $s): void
{
    $b = false;
    try {
        $f();
    } catch (DomainException|InvalidArgumentException|mysqli_sql_exception $e) {
        $b = true;
    } ok($b, $s);
}
function race(string $db, string $action): array
{
    $workers = [];
    for ($i = 0;$i < 2;$i++) {
        $p = proc_open([PHP_BINARY,__DIR__.'/pagamento_competencia_worker.php',$db,$action], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
        if (!is_resource($p)) {
            throw new RuntimeException('Worker sintético não iniciou.');
        }
        fclose($pipes[0]);
        $workers[] = [$p,$pipes];
    }
    $out = [];
    foreach ($workers as [$p,$pipes]) {
        $raw = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($err || $code) {
            throw new RuntimeException('Concorrência sintética falhou: '.$raw.$err);
        }
        $out[] = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
    return $out;
}
// Fixture documental antiga permite dois cabeçalhos; adaptar somente os dados sintéticos ao schema real.
$c->query('UPDATE pagamento_itens SET pagamento_id=1 WHERE pagamento_id=3');
$c->query('DELETE FROM pagamentos WHERE idpagamento=3');
$c->query('ALTER TABLE pagamentos MODIFY idpagamento INT NOT NULL AUTO_INCREMENT, ADD criado_por INT NULL, ADD data_pagamento DATE NULL, ADD UNIQUE KEY uq_colab_mes(colaborador_id,mes_ref)');
$c->query('ALTER TABLE pagamento_itens MODIFY idpagamento_item INT NOT NULL AUTO_INCREMENT, ADD criado_em DATETIME DEFAULT CURRENT_TIMESTAMP');
$c->query('CREATE TABLE pagamento_eventos (idpagamento_evento INT AUTO_INCREMENT PRIMARY KEY,pagamento_id INT,tipo VARCHAR(50),descricao TEXT,usuario_id INT,criado_em DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$c->query("CREATE TABLE checklist_operacional (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,module_key VARCHAR(40),entity_type VARCHAR(40),entity_id INT,requirements_version VARCHAR(40),sla_start_at DATETIME,due_at DATETIME,status VARCHAR(20) DEFAULT 'aberto',UNIQUE KEY uq(module_key,entity_type,entity_id)) ENGINE=InnoDB");
$c->query("CREATE TABLE checklist_operacional_item (id INT AUTO_INCREMENT PRIMARY KEY,checklist_id INT,item_key VARCHAR(60),label VARCHAR(120),required INT,update_mode VARCHAR(20),done INT,done_at DATETIME,UNIQUE KEY uq(checklist_id,item_key)) ENGINE=InnoDB");
$c->query("INSERT INTO colaborador (idcolaborador,nome_colaborador,valor_fixo,ativo,participa_fechamento_mensal,tipo_remuneracao) VALUES (21,'Pedro responsável',0,1,0,NULL),(9,'André L. responsável',0,1,0,NULL),(43,'Giovana responsável',0,1,0,NULL),(17,'Só FIXO sem tarefas',3000,1,1,'FIXO')");
$c->query("INSERT INTO usuario VALUES (5,2,1,5,'Pessoa 5 sintética'),(6,2,1,6,'Pessoa 6 sintética'),(17,2,1,17,'Só FIXO sintético')");
$c->query("INSERT INTO informacoes_usuario SELECT idusuario,'11.111.111/0001-11','Empresa sintética','111.111.111-11','solteiro' FROM usuario WHERE idusuario IN (5,6,17)");
$c->query("INSERT INTO endereco SELECT idusuario,'Rua Sintética','1','','Centro','Blumenau','SC','89000-000' FROM usuario WHERE idusuario IN (5,6,17)");
$c->query('UPDATE colaborador SET participa_fechamento_mensal=0 WHERE idcolaborador=4');
$c->query('UPDATE funcao_imagem SET valor=0,pagamento=0 WHERE idfuncao_imagem=2');
documental_test_migration($c, __DIR__.'/../sql/2026-10-07_pagamento_competencia.sql');
documental_test_migration($c, __DIR__.'/../sql/2026-10-08_pagamento_retiradas.sql');
documental_test_migration($c, __DIR__.'/../FlowConnect/migrations/001_flow_connect_core.sql');
$s = new FechamentoCompetenciaService($c, 1);
$ui = new FechamentoInterfaceService($c, $f['root'], 1, true);
$a = new FechamentoCompetenciaAutomacao($c, 1);
ok(pagamento_previsao('2026-09') === '2026-10-06', '5º dia útil outubro');
ok(pagamento_previsao('2026-12') === '2027-01-07', 'Ano seguinte e feriado do calendário existente');
$c->query("INSERT INTO pagamento_competencia (competencia,criado_em,criado_por,previsto_em) VALUES ('2026-09',UTC_TIMESTAMP(6),1,'2026-10-07')");
$first = $a->executar(new DateTimeImmutable('2026-10-01 08:00:00', new DateTimeZone('America/Sao_Paulo')), true, true);
blocked(fn () => $c->query("UPDATE pagamento_competencia SET previsto_em='2026-10-06'"), 'Previsão não muda sem auditoria');
ok($s->atualizarPrevisao('2026-09')['previsto_em'] === '2026-10-06', 'Correção explícita de data preserva competência aberta');
ok((int)$c->query("SELECT COUNT(*) n FROM pagamento_competencia_evento WHERE tipo='PREVISAO_ALTERADA'")->fetch_assoc()['n'] === 1, 'Mudança de data auditada');
$s->atualizarPrevisao('2026-09');
ok((int)$c->query("SELECT COUNT(*) n FROM pagamento_competencia_evento WHERE tipo='PREVISAO_ALTERADA'")->fetch_assoc()['n'] === 1, 'Atualização de previsão idempotente');
ok((int)$c->query("SELECT COUNT(*) n FROM checklist_operacional WHERE due_at='2026-10-06 23:59:59'")->fetch_assoc()['n'] === 2, 'Prazos das pendências acompanham previsão auditada');
ok($first['quantidade'] === 7, 'Snapshot inclui FIXO sem tarefas, FIXO_VARIAVEL e zero');
ok($first['quantidade'] === 7 && $s->resumo('2026-09')['colaboradores'][6]['preparado'], 'Job consolida novos indivíduos automaticamente');
ok($a->executar(new DateTimeImmutable('2026-10-01 09:00:00', new DateTimeZone('America/Sao_Paulo')))['ciclo_id'] === $first['ciclo_id'], 'Repetição não duplica ciclo');
ok((int)$c->query('SELECT COUNT(*) n FROM checklist_operacional')->fetch_assoc()['n'] === 2, 'Duas pendências únicas');
ok((int)$c->query('SELECT COUNT(*) n FROM pagamento_competencia_responsavel')->fetch_assoc()['n'] === 3, 'Três responsáveis por IDs');
ok((int)$c->query('SELECT COUNT(*) n FROM flow_connect_events')->fetch_assoc()['n'] === 1, 'Alerta inicial único');
blocked(fn () => $s->pagar('2026-09', 17, 'early-pay', '2026-10-01'), 'Pagamento bloqueado antes do fechamento');
$m = $ui->mensal('2026-09', true, 'iniciar-fixture');
ok($ui->mensal('2026-09', true, 'iniciar-fixture')['quantidade'] === 7, 'Preparação idempotente');
ok($ui->obter(17, '2026-09')['revisao']['total_final_centavos'] === '300000', 'FIXO sem adendos');
$c->query('UPDATE colaborador SET ativo=0,participa_fechamento_mensal=0,valor_fixo=9999 WHERE idcolaborador=17');
ok($ui->mensal('2026-09')['quantidade'] === 7, 'Cadastro posterior não altera participantes');
$r = $ui->preparar(17, '2026-09', 1, 'fixed-captured');
ok($r['revisao']['total_final_centavos'] === '300000', 'Fixo capturado preservado após mudança de cadastro');
$r = $ui->decidir(17, '2026-09', 2, 'desconto-fixture', 'DESCONTO', ['estado' => 'DEFINIDO','valor' => '50,00','motivo' => 'Desconto sintético autorizado']);
ok($r['revisao']['total_final_centavos'] === '295000', 'Desconto auditado compõe total');
blocked(fn () => $ui->decidir(17, '2026-09', 3, 'discount-bad', 'DESCONTO', ['estado' => 'DEFINIDO','valor' => '1,00','motivo' => '']), 'Desconto exige motivo');
blocked(fn () => $s->concluir('2026-09', 'early-close'), 'Fechamento exige 100% revisados');
$due = $a->executar(new DateTimeImmutable('2026-10-06 08:00:00', new DateTimeZone('America/Sao_Paulo')), true, true);
$msg = $c->query("SELECT payload_json FROM flow_connect_events WHERE event_type='pagamento.competencia.pagamento'")->fetch_assoc()['payload_json'];
ok(str_contains($msg, 'bloqueado'), '5º dia útil alerta bloqueio');
$a->executar(new DateTimeImmutable('2026-10-06 09:00:00', new DateTimeZone('America/Sao_Paulo')));
ok((int)$c->query('SELECT COUNT(*) n FROM flow_connect_events')->fetch_assoc()['n'] === 2, 'Repetir automações não duplica alertas');
ok((int)$c->query('SELECT COUNT(*) n FROM flow_connect_notifications')->fetch_assoc()['n'] === 2, 'Planejamento usa duas notificações únicas');
ok((new \FlowConnect\Infrastructure\DeliveryRepository($c))->claimEligible(20, 'fixture-worker', true) === [], 'Alertas shadow não são enviados externamente');
$before = pagamento_resumo_geral($c, 9, 2026);
ok(!$before['resumo']['grafico_financeiro_disponivel'] && $before['fechamento']['total_fechado_centavos'] === null, 'Parcial não é valor oficial nem gráfico financeiro');
foreach ($ui->mensal('2026-09')['colaboradores'] as $p) {
    $r = $ui->obter($p['colaborador_id'], '2026-09')['revisao'];
    $doc = $ui->gerar($p['colaborador_id'], '2026-09', $r['fechamento_id'], $r['id'], 'preview-'.$p['colaborador_id']);
    $ui->visualizar($p['colaborador_id'], '2026-09', $doc['document_id'], 'view-'.$p['colaborador_id']);
    $ui->confirmar($p['colaborador_id'], '2026-09', $doc['document_id'], $r['id'], $doc['pdf_hash'], 'confirm-'.$p['colaborador_id']);
    $model = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.$doc['document_id'])->fetch_assoc()['modelo_json'], true);
    ok((int)$model['total_centavos'] === $p['total_centavos'], 'PDF revisado conserva o valor reconhecido, inclusive pagamentos históricos');
    $paidOtherCompetence = [];
    foreach ($r['financeiro_servicos']['itens_analisados'] as $item) {
        if ($item['situacao'] === 'QUITADO'
            && !array_filter($item['pagamentos'], fn ($payment) => ($payment['competencia_pagamento'] ?? null) === '2026-09' && (int)$payment['valor_centavos'] > 0)) {
            $paidOtherCompetence[] = $item['identidade'];
        }
    }
    ok(!array_filter($model['servicos'], fn ($row) => in_array($row['identidade'], $paidOtherCompetence, true)), 'PDF mantém fora os quitados sem pagamento nesta competência');
    $snapshot = (new FechamentoRevisaoRepository($c))->revisao($r['id'])['snapshot']['composicao'];
    ok($model['total_documental_centavos'] === max(0, $snapshot['total_final_centavos'] - ($snapshot['pagamentos_competencia_incluidos_centavos'] ?? 0)), 'Total do adendo desconta pagamentos históricos da competência');
    if ($p['colaborador_id'] === 17) {
        $model = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.$doc['document_id'])->fetch_assoc()['modelo_json'], true);
        ok((bool)array_filter($model['rubricas'], fn ($r) => !empty($r['desconto'])), 'Desconto consta no PDF');
    }
}
// Quitados pagos com a própria competência ficam visíveis no adendo sem alterar o total documental.
$c->query("UPDATE pagamentos SET mes_ref='2026-09' WHERE idpagamento=900");
$sameMonth = $ui->preparar(8, '2026-09', 2, 'paid-same-competence')['revisao'];
$sameMonthDoc = $ui->gerar(8, '2026-09', $sameMonth['fechamento_id'], $sameMonth['id'], 'paid-same-competence-preview');
$sameMonthModel = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.$sameMonthDoc['document_id'])->fetch_assoc()['modelo_json'], true);
$paidRow = array_values(array_filter($sameMonthModel['servicos'], fn ($row) => (int)$row['identidade']['origem_id'] === 903))[0] ?? null;
ok($paidRow !== null && $paidRow['situacao'] === 'PAGO_NA_COMPETENCIA' && $paidRow['valor_centavos'] === 19000 && str_contains($sameMonthModel['placeholders']['tabela_servicos'], 'Pago nesta competência'), 'PDF mostra o serviço quitado, o valor pago e identifica o pagamento da competência');
$syntheticReview = $sameMonth;
$syntheticReview['snapshot']['composicao']['producao_consulta']['itens_analisados'][] = ['identidade' => ['beneficiario_id' => 8,'origem' => 'funcao_animacao','origem_id' => 8801,'classe' => 'FUNCAO_ANIMACAO'],
    'elegibilidade' => ['elegivel' => true],'situacao' => 'DEVIDO','saldo_centavos' => 500,'descricao' => ['obra_id' => 4,'obra_nome' => 'Projeto B','imagem' => 'Logo Principal','funcao' => 'Animação','tipo_animacao' => 'logo ia']];
$syntheticReview['snapshot']['composicao']['producao_consulta']['itens_analisados'][] = ['identidade' => ['beneficiario_id' => 8,'origem' => 'funcao_animacao','origem_id' => 8802,'classe' => 'FUNCAO_ANIMACAO'],
    'elegibilidade' => ['elegivel' => true],'situacao' => 'DEVIDO','saldo_centavos' => 500,'descricao' => ['obra_id' => 3,'obra_nome' => 'Projeto A','imagem' => 'Zeta','funcao' => 'Animação','tipo_animacao' => 'logo ia']];
$syntheticReview['snapshot']['composicao']['producao_consulta']['itens_analisados'][] = ['identidade' => ['beneficiario_id' => 8,'origem' => 'funcao_animacao','origem_id' => 8803,'classe' => 'FUNCAO_ANIMACAO'],
    'elegibilidade' => ['elegivel' => true],'situacao' => 'DEVIDO','saldo_centavos' => 500,'descricao' => ['obra_id' => 3,'obra_nome' => 'Projeto A','imagem' => 'Alpha','funcao' => 'Animação','tipo_animacao' => 'Horizontal','animacao_id' => 200]];
$syntheticReview['snapshot']['composicao']['producao_consulta']['itens_analisados'][] = ['identidade' => ['beneficiario_id' => 8,'origem' => 'funcao_animacao','origem_id' => 8804,'classe' => 'FUNCAO_ANIMACAO'],
    'elegibilidade' => ['elegivel' => true],'situacao' => 'DEVIDO','saldo_centavos' => 500,'descricao' => ['obra_id' => 3,'obra_nome' => 'Projeto A','imagem' => 'Alpha','funcao' => 'Pós-produção','tipo_animacao' => 'Horizontal','animacao_id' => 200]];
$syntheticReview['snapshot']['composicao']['producao_consulta']['itens_analisados'][] = ['identidade' => ['beneficiario_id' => 8,'origem' => 'funcao_animacao','origem_id' => 8805,'classe' => 'FUNCAO_ANIMACAO'],
    'elegibilidade' => ['elegivel' => true],'situacao' => 'DEVIDO','saldo_centavos' => 500,'descricao' => ['obra_id' => 3,'obra_nome' => 'Projeto A','imagem' => 'Alpha','funcao' => 'Animação','tipo_animacao' => 'Detalhe','animacao_id' => 201]];
$syntheticReview['snapshot']['composicao']['producao_consulta']['itens_analisados'][] = ['identidade' => ['beneficiario_id' => 8,'origem' => 'funcao_animacao','origem_id' => 8806,'classe' => 'FUNCAO_ANIMACAO'],
    'elegibilidade' => ['elegivel' => true],'situacao' => 'DEVIDO','saldo_centavos' => 500,'descricao' => ['obra_id' => 3,'obra_nome' => 'Projeto A','imagem' => 'Alpha','funcao' => 'Pós-produção','tipo_animacao' => 'Detalhe','animacao_id' => 201]];
$syntheticReview['snapshot_hash'] = FechamentoSnapshot::hash($syntheticReview['snapshot']);
$animationIdentity = (new FechamentoDocumentoRepository($c))->identidade(8);
$animationModel = (new FechamentoDocumentoProjection())->projetar($syntheticReview, $animationIdentity, '2026-10-07');
$animationRow = array_values(array_filter($animationModel['servicos'], fn ($row) => $row['identidade']['origem_id'] === 8801))[0] ?? null;
ok(($animationRow['imagem'] ?? null) === 'Logo Principal - Logo IA', 'Adendo identifica a imagem e capitaliza IA no tipo de animação');
$sortedAnimationIds = array_column(array_values(array_filter($animationModel['servicos'], fn ($row) => in_array((int)$row['identidade']['origem_id'], [8801,8802,8803,8804,8805,8806], true))), 'identidade');
$sortedAnimationIds = array_map(fn ($identity) => (int)$identity['origem_id'], $sortedAnimationIds);
ok($sortedAnimationIds === [8803,8804,8805,8806,8802,8801], 'Adendo ordena por projeto, imagem, animação e tarefas da animação');
$ui->visualizar(8, '2026-09', $sameMonthDoc['document_id'], 'paid-same-competence-view');
$ui->confirmar(8, '2026-09', $sameMonthDoc['document_id'], $sameMonth['id'], $sameMonthDoc['pdf_hash'], 'paid-same-competence-confirm');
ok($s->resumo('2026-09')['contagens']['CONFIRMADO'] === 7, 'PDF confirmado equivale a revisado');
// Tarefa individual, função em lote e exceção individual, com journals/revisões reais.
function decideServices($ui, $b, $key, $input)
{
    $r = $ui->obter($b, '2026-09');
    return $ui->decidir($b, '2026-09', $r['latest_version'], $key, 'SERVICOS', $input)['revisao'];
}
$b = 2;
$initial = $ui->obter($b, '2026-09')['revisao'];
$item = array_values(array_filter($initial['financeiro_servicos']['itens_analisados'], fn ($i) => $i['situacao'] === 'DEVIDO' && (int)$i['descricao']['funcao_id'] === 4))[0];
$input = ['estado' => 'RETIRAR','alvo' => 'ITEM','identidade' => $item['identidade'],'motivo' => 'Somente render: não remunerar nesta competência'];
blocked(fn () => decideServices($ui, $b, 'no-reason', array_replace($input, ['motivo' => ''])), 'Retirada exige motivo');
$payload = ['colaborador_id' => $b,'competencia' => '2026-09','expected_version' => $initial['version'],'idempotency_key' => 'http-withdraw','tipo' => 'SERVICOS','input' => $input];
ok(FechamentoHttp::validar('decidir', $payload)['tipo'] === 'SERVICOS', 'API aceita retirada com identidade canônica');
$bad = $payload;
$bad['input']['identidade']['origem_id'] = '1';
blocked(fn () => FechamentoHttp::validar('decidir', $bad), 'API rejeita identidade mal tipada');
$bad = $payload;
$bad['input']['funcao_id'] = 4;
blocked(fn () => FechamentoHttp::validar('decidir', $bad), 'API rejeita mistura de item com função');
$foreign = $input;
$foreign['identidade']['beneficiario_id'] = 999;
blocked(fn () => decideServices($ui, $b, 'foreign-task', $foreign), 'Retirada rejeita tarefa de outro colaborador');
$r = decideServices($ui, $b, 'withdraw-one', $input);
ok(count(array_filter($r['financeiro_servicos']['itens_analisados'], fn ($i) => $i['situacao'] === 'RETIRADO')) === 1, 'Retirada individual persistida');
ok($r['bonus_produtividade']['quantidade_finalizacao_r0'] === 20 && $r['bonus_produtividade']['valor_centavos'] === '0', 'Retirada remove Finalização da faixa de bônus');
ok((int)$r['total_final_centavos'] === (int)$initial['total_final_centavos'] - 76000, 'Total desconta serviço e bônus associado');
ok($s->resumo('2026-09')['contagens']['CONFIRMADO'] === 6, 'Retirada invalida revisão documental');
$d = $ui->gerar($b, '2026-09', $r['fechamento_id'], $r['id'], 'withdraw-preview');
$model = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.$d['document_id'])->fetch_assoc()['modelo_json'], true);
ok(!array_filter($model['servicos'], fn ($row) => $row['identidade'] === $item['identidade']), 'Tarefa retirada não consta no PDF');
$retry = $ui->decidir($b, '2026-09', $initial['version'], 'withdraw-one', 'SERVICOS', $input);
ok($retry['revisao']['id'] === $r['id'], 'Retirada idempotente');
$r = decideServices($ui, $b, 'restore-one', array_replace($input, ['estado' => 'RESTAURAR','motivo' => 'Revisão financeira autorizou pagar']));
ok($r['total_final_centavos'] === $initial['total_final_centavos'], 'Restaurar recompõe serviço e bônus');
$zeroItem = array_values(array_filter($r['financeiro_servicos']['itens_analisados'], fn ($i) => $i['situacao'] === 'DEVIDO' && (int)$i['descricao']['funcao_id'] === 4))[0];
$c->query("INSERT INTO pagamento_itens (pagamento_id,origem,origem_id,valor,observacao) VALUES (901,'funcao_imagem',".(int)$zeroItem['identidade']['origem_id'].",0,'Pagamento zerado sintético')");
$r = decideServices($ui, $b, 'withdraw-function', ['estado' => 'RETIRAR','alvo' => 'FUNCAO','funcao_id' => 4,'motivo' => 'Retirar função deste fechamento']);
ok(!array_filter($r['financeiro_servicos']['servicos_devidos'], fn ($i) => (int)$i['descricao']['funcao_id'] === 4), 'Retirada por função alcança todos os itens sem pagamento');
ok((array_values(array_filter($r['financeiro_servicos']['itens_analisados'], fn ($i) => (int)$i['identidade']['origem_id'] === (int)$zeroItem['identidade']['origem_id']))[0]['situacao'] ?? null) === 'RETIRADO', 'Registro de pagamento zerado não bloqueia retirada da função');
$r = decideServices($ui, $b, 'restore-exception', array_replace($input, ['estado' => 'RESTAURAR','motivo' => 'Exceção individual aprovada']));
ok(count(array_filter($r['financeiro_servicos']['servicos_devidos'], fn ($i) => (int)$i['descricao']['funcao_id'] === 4)) === 1, 'Restaurar item cria exceção à retirada da função');
$r = decideServices($ui, $b, 'restore-function', ['estado' => 'RESTAURAR','alvo' => 'FUNCAO','funcao_id' => 4,'motivo' => 'Função novamente autorizada']);
ok($r['total_final_centavos'] === $initial['total_final_centavos'], 'Restaurar função limpa exceções e recompõe total');
ok((int)$c->query("SELECT COUNT(*) n FROM pagamento_fechamento_decisao WHERE tipo='SERVICOS'")->fetch_assoc()['n'] === 5, 'Journals auditam cinco decisões sem duplicar retry');
$d = $ui->gerar($b, '2026-09', $r['fechamento_id'], $r['id'], 'restored-preview');
$ui->visualizar($b, '2026-09', $d['document_id'], 'restored-view');
$ui->confirmar($b, '2026-09', $d['document_id'], $r['id'], $d['pdf_hash'], 'restored-confirm');
// Função distinta do trabalho habitual não impede inclusão ou retirada (Composição).
$other = $ui->obter(3, '2026-09')['revisao'];
$cross = array_values(array_filter($other['financeiro_servicos']['itens_analisados'], fn ($i) => (int)$i['identidade']['origem_id'] === 900))[0];
ok($cross['situacao'] === 'DEVIDO', 'Outra função atribuída aparece normalmente');
$r = decideServices($ui, 3, 'withdraw-cross', ['estado' => 'RETIRAR','alvo' => 'ITEM','identidade' => $cross['identidade'],'motivo' => 'Não remunerar este serviço']);
ok((int)$r['total_final_centavos'] === (int)$other['total_final_centavos'] - 7500, 'Retirada ampla funciona em outra função');
$r = decideServices($ui, 3, 'restore-cross', ['estado' => 'RESTAURAR','alvo' => 'ITEM','identidade' => $cross['identidade'],'motivo' => 'Restaurar para demais cenários']);
$d = $ui->gerar(3, '2026-09', $r['fechamento_id'], $r['id'], 'cross-preview');
$ui->visualizar(3, '2026-09', $d['document_id'], 'cross-view');
$ui->confirmar(3, '2026-09', $d['document_id'], $r['id'], $d['pdf_hash'], 'cross-confirm');
$bruna = $ui->obter(8, '2026-09')['revisao'];
$settled = array_values(array_filter($bruna['financeiro_servicos']['itens_analisados'], fn ($i) => (int)$i['identidade']['origem_id'] === 903))[0];
blocked(fn () => decideServices($ui, 8, 'withdraw-paid', ['estado' => 'RETIRAR','alvo' => 'ITEM','identidade' => $settled['identidade'],'motivo' => 'Tentativa inválida']), 'Retirada não apaga tarefas já pagas');
$oldProjection = new FechamentoDocumentoProjection();
$repo = new FechamentoRevisaoRepository($c);
$rev = $repo->revisao($bruna['id']);
$identity = (new FechamentoDocumentoRepository($c))->identidade(8);
$mod = $oldProjection->projetar($rev, $identity, '2026-10-07');
ok((array_values(array_filter($mod['servicos'], fn ($i) => (int)$i['identidade']['origem_id'] === 903))[0]['situacao'] ?? null) === 'PAGO_NA_COMPETENCIA', 'PDF oficial mostra quitado pago com a competência selecionada');
ok(str_contains($mod['placeholders']['data_pagamento'], '6 de outubro'), 'Adendo usa sábado na previsão');

// Reprodução de pagamento posterior ao snapshot, mantendo a revisão reconhecida.
$c->query("INSERT INTO pagamento_itens (pagamento_id,origem,origem_id,valor,observacao) VALUES (1,'funcao_imagem',1,100,'')");
$c->query('UPDATE funcao_imagem SET pagamento=1 WHERE idfuncao_imagem=1');
if (in_array('--browser-ready', $argv, true)) {
    file_put_contents(__DIR__.'/../output/competencia-fixture.json', json_encode($f));
    echo 'Fixture isolada pronta para concluir e pagar pelo navegador: '.$f['db'].PHP_EOL;
    exit;
}
$closed = $s->concluir('2026-09', 'close-all');
ok($closed['estado'] === 'CONCLUIDO', 'Conclusão com 100% revisado');
blocked(fn () => $c->query("UPDATE pagamento_competencia SET previsto_em='2026-10-08'"), 'Data de competência concluída preservada');
ok($closed['pago_centavos'] >= 10000, 'Ledger legado reconciliado sem novo pagamento');
ok((int)$c->query("SELECT COUNT(*) n FROM checklist_operacional WHERE entity_type='fechamento' AND status='concluido'")->fetch_assoc()['n'] === 1, 'Pendência de fechamento encerrada');
blocked(fn () => $ui->preparar(17, '2026-09', 3, 'change-after-close'), 'Recalcular após conclusão bloqueado');
blocked(fn () => decideServices($ui, 2, 'withdraw-closed', $input), 'Retirada bloqueada após conclusão geral');
$total = $closed['total_fechado_centavos'];
$c->query('UPDATE funcao_imagem SET valor=999 WHERE idfuncao_imagem=900');
ok($s->resumo('2026-09')['total_fechado_centavos'] === $total, 'Produção posterior não altera snapshot');
$part = $s->pagar('2026-09', 17, 'paid-17', '2026-10-07', 'Teste isolado');
ok($part['pago_centavos'] + $part['pendente_centavos'] === $total, 'Pago mais Pendente é Total fechado');
ok($part['quantidade_pagos'] < 7 && $part['situacao'] === 'PARCIALMENTE_PAGO', 'Pagamento parcial da competência');
ok($s->pagar('2026-09', 17, 'paid-17', '2026-10-07', 'Teste isolado')['pago_centavos'] === $part['pago_centavos'], 'Retry pagamento não duplica ledger');
blocked(fn () => $s->pagar('2026-09', 17, 'paid-17', '2026-10-07', 'Outro conteúdo'), 'Chave não pode mudar de conteúdo');
blocked(fn () => $s->concluirPagamento('2026-09', 'quit-early'), 'Quitação exige todos pagos');
$race = race($f['db'], 'pagar');
ok($race[0]['ok'] && $race[1]['ok'] && $race[0]['pago'] === $race[1]['pago'], 'Dois pagamentos concorrentes retornam a mesma liquidação');
ok((int)$c->query("SELECT COUNT(*) n FROM pagamento_competencia_evento WHERE chave='concurrent-paid-3'")->fetch_assoc()['n'] === 1, 'Pagamento concorrente registra um evento e um valor');

ok(str_contains(FechamentoCompetenciaAutomacao::mensagem($part, 'pagamento', 'https://improov/ImproovWeb/'), 'Total fechado:'), 'Alerta normal usa fechamento concluído');
$zero = array_values(array_filter($part['colaboradores'], fn ($p) => $p['total_centavos'] === 0))[0];
$zeroPid = (new PagamentoService($c, 1))->garantirPagamento($zero['colaborador_id'], 9, 2026);
$c->query("UPDATE pagamentos SET data_pagamento='2026-10-05',pago_em='2026-10-05' WHERE idpagamento=".$zeroPid);
foreach ($part['colaboradores'] as $p) {
    if ($p['pagamento_status'] !== 'PAGO') {
        $s->pagar('2026-09', $p['colaborador_id'], 'paid-'.$p['colaborador_id'], '2026-10-07', 'Teste isolado');
    }
}
$zeroHeader = $c->query('SELECT data_pagamento,pago_em FROM pagamentos WHERE idpagamento='.$zeroPid)->fetch_assoc();
ok($zeroHeader['data_pagamento'] === '2026-10-05' && substr($zeroHeader['pago_em'], 0, 10) === '2026-10-05', 'Quitação sem novo valor preserva datas históricas do pagamento');
$done = $s->resumo('2026-09');
ok($done['quantidade_pagos'] === 7 && $done['pendente_centavos'] === 0 && $done['situacao'] === 'QUITADO', '100% pagos e competência quitada');
ok((int)$c->query("SELECT COUNT(*) n FROM checklist_operacional WHERE status='concluido'")->fetch_assoc()['n'] === 2, 'Ambas as pendências encerradas automaticamente');
ok($s->concluirPagamento('2026-09', 'quit-final')['situacao'] === 'QUITADO', 'Conclusão pagamento validada');
$payload = pagamento_resumo_geral($c, 9, 2026);
ok($payload['resumo']['total'] === $total && $payload['resumo']['pago'] === $total && $payload['resumo']['pendente'] === 0, 'KPI e gráfico usam mesma fonte');
ok(array_sum(array_column($payload['funcoes'],'total')) === $total,'Componentes do gráfico reconciliam total');
blocked(fn () => $c->query("UPDATE pagamento_competencia SET estado='EM_ANDAMENTO',concluido_em=NULL,total_fechado_centavos=NULL WHERE id=".$done['ciclo_id']),'Trigger impede reabertura');
blocked(fn () => $c->query('UPDATE pagamento_competencia_colaborador SET total_centavos=0 WHERE competencia_id='.$done['ciclo_id']),'Trigger protege snapshot');
blocked(fn () => $c->query('UPDATE pagamento_itens SET valor=0 WHERE idpagamento_item=(SELECT pagamento_item_id FROM pagamento_competencia_lancamento LIMIT 1)'),'Ledger vinculado imutável');
blocked(fn () => financeiro_pagar($c,['colaborador_id' => 17,'mes' => 9,'ano' => 2026],1),'Writer legado bloqueado');
ok((int)$c->query("SELECT COUNT(*) n FROM pagamento_competencia_evento WHERE tipo='QUITADO'")->fetch_assoc()['n'] === 1,'Auditoria da quitação é única');
blocked(fn () => $c->query('UPDATE pagamento_fechamento SET numero_revisao=numero_revisao+1,lock_version=lock_version+1 WHERE id='.$done['colaboradores'][0]['fechamento_id']),'Trigger congela versão oficial');
$race = race($f['db'],'criar');
ok($race[0]['ok'] && $race[1]['ok'] && $race[0]['ciclo'] === $race[1]['ciclo'],'Criação concorrente mantém uma competência oficial');
ok((int)$c->query("SELECT COUNT(*) n FROM pagamento_competencia WHERE competencia='2026-10'")->fetch_assoc()['n'] === 1,'Banco garante competência única');
echo "$checks verificações do ciclo financeiro passaram; banco isolado ".$f['db'].".\n";
file_put_contents(__DIR__.'/../output/competencia-fixture.json',json_encode($f));
