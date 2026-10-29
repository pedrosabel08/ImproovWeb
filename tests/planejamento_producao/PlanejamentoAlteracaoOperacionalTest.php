<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/PreAlteracao/conclusao_helpers.php';
require_once dirname(__DIR__, 2) . '/helpers/planejamento_alteracao_operacional_helper.php';

function pa_op_assert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
function pa_op_reject(callable $call, string $message): void
{
    try { $call(); } catch (DomainException $e) { return; }
    throw new RuntimeException($message);
}

pa_op_assert(pre_alt_data_recebimento_retorno([
    'data_finalizacao_cliente' => '2026-10-19',
    'lote_resolvido_em' => '2026-10-21 13:40:00',
    'data_triagem' => '2026-10-28',
]) === '2026-10-19', 'A data do retorno deve prevalecer sobre resolucao e triagem.');
pa_op_assert(pre_alt_data_recebimento_retorno([
    'data_finalizacao_cliente' => null,
    'lote_resolvido_em' => '2026-10-21 13:40:00',
]) === '2026-10-21', 'Review resolvido e o fallback de recebimento legado.');
pa_op_reject(fn() => pre_alt_data_recebimento_retorno(['data_triagem' => '2026-10-28']), 'Triagem nao pode substituir o recebimento.');

// Legacy deliveries keep their historical self-assignment behavior.
flow_planejamento_alteracao_validar_operacao(null, 22, 22, true);
$contexto = ['versao_atual_id' => 8, 'responsavel_planejado_id' => 11, 'colaborador_id' => 11, 'fim_planejado' => '2026-10-30'];
flow_planejamento_alteracao_validar_operacao($contexto, 11, 11, true);
pa_op_reject(fn() => flow_planejamento_alteracao_validar_operacao($contexto, 22, 22, true), 'Arraste por outro colaborador nao pode destruir a alocacao.');
pa_op_reject(fn() => flow_planejamento_alteracao_validar_operacao($contexto, 22, null, true), 'Omitir atribuir_logado nao deve permitir inicio por outro colaborador.');
pa_op_reject(fn() => flow_planejamento_alteracao_validar_operacao($contexto, 11, 22), 'Upsert legado nao pode transferir o responsavel.');
pa_op_reject(fn() => flow_planejamento_alteracao_validar_operacao(array_merge($contexto, ['colaborador_id' => null]), 11, null, true), 'Atribuicao divergente exige correcao explicita.');
foreach (['versao_atual_id', 'responsavel_planejado_id', 'fim_planejado'] as $campo) {
    pa_op_reject(fn() => flow_planejamento_alteracao_validar_operacao(array_merge($contexto, [$campo => null]), 11, 11, true), 'Plano incompleto deve bloquear a execucao: ' . $campo);
}

if (in_array('--db', $argv ?? [], true)) {
    require_once dirname(__DIR__, 2) . '/config/database.php';
    $db = improov_db_connect();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    // Connection-local tables shadow production; no permanent rows or triggers.
    $db->query('CREATE TEMPORARY TABLE alteracoes (idalt INT AUTO_INCREMENT PRIMARY KEY, funcao_id INT NOT NULL, status_id INT NOT NULL, data_recebimento DATE NULL, nivel_complexidade TINYINT UNSIGNED NULL, UNIQUE KEY rodada(funcao_id,status_id))');
    alteracoes_upsert_registro($db, 101, 3, '2026-10-19', 4);
    alteracoes_upsert_registro($db, 101, 3);
    $row = $db->query('SELECT * FROM alteracoes WHERE funcao_id=101 AND status_id=3')->fetch_assoc();
    pa_op_assert($row['data_recebimento'] === '2026-10-19', 'Upsert operacional nao pode substituir recebimento por hoje.');
    pa_op_assert((int)$row['nivel_complexidade'] === 4, 'Upsert operacional sem nivel deve preservar N4.');
    alteracoes_upsert_registro($db, 101, 4, '2026-10-26', 2);
    $row = $db->query('SELECT * FROM alteracoes WHERE funcao_id=101 AND status_id=4')->fetch_assoc();
    pa_op_assert($row['data_recebimento'] === '2026-10-26' && (int)$row['nivel_complexidade'] === 2, 'Rodada seguinte deve receber seu proprio retorno e nivel.');
    alteracoes_upsert_registro($db, 101, 4, '2026-11-03', 5, true);
    alteracoes_upsert_registro($db, 101, 4, '2026-12-01');
    $row = $db->query('SELECT * FROM alteracoes WHERE funcao_id=101 AND status_id=4')->fetch_assoc();
    pa_op_assert($row['data_recebimento'] === '2026-11-03' && (int)$row['nivel_complexidade'] === 5, 'Somente nova liberacao canonica pode trocar recebimento da copia operacional de etapa repetida; writers genericos preservam-no.');
    $db->query('DROP TEMPORARY TABLE alteracoes');

    $schemas = [
        'funcao_imagem' => 'idfuncao_imagem INT PRIMARY KEY, imagem_id INT, funcao_id INT, colaborador_id INT NULL, status VARCHAR(40), prazo DATE NULL',
        'imagens_cliente_obra' => 'idimagens_cliente_obra INT PRIMARY KEY, status_id INT',
        'pre_alt_itens' => 'id INT PRIMARY KEY, imagem_id INT',
        'pre_alt_liberacao_itens' => 'id INT PRIMARY KEY, pre_alt_item_id INT, entrega_destino_id INT',
        'entregas' => 'id INT PRIMARY KEY, status_id INT',
        'entregas_itens' => 'id INT PRIMARY KEY, entrega_id INT, imagem_id INT',
        'entrega_planejamento_producao' => 'id INT PRIMARY KEY, entrega_id INT, modelo VARCHAR(40), estado VARCHAR(40), versao_atual_id INT NULL',
        'entrega_planejamento_item' => 'versao_id INT, entrega_item_id INT, funcao_imagem_id INT, responsavel_planejado_id INT, inicio_planejado DATE NULL, fim_planejado DATE',
        'janela_operacional_ciclo' => 'id INT PRIMARY KEY, entrega_item_id INT NULL, planejamento_versao_id_snapshot INT NULL, prazo_necessario_snapshot DATE NULL, ativo_token VARCHAR(10) NULL',
        'janela_operacional_ciclo_item' => 'ciclo_id INT, funcao_imagem_id INT',
        'funcao_imagem_prazo_historico' => 'funcao_imagem_id INT, prazo_anterior DATE NULL, prazo_novo DATE NULL, alterado_por_colaborador_id INT NULL, alterado_por_usuario_id INT NULL, origem VARCHAR(80), motivo TEXT NULL, status_anterior VARCHAR(40), status_novo VARCHAR(40)',
    ];
    foreach ($schemas as $table => $columns) $db->query("CREATE TEMPORARY TABLE $table ($columns)");
    $db->query("INSERT INTO funcao_imagem VALUES(101,10,6,11,'Nao iniciado',NULL)");
    $db->query('INSERT INTO imagens_cliente_obra VALUES(10,3)');
    $db->query('INSERT INTO pre_alt_itens VALUES(201,10),(202,10),(203,10)');
    $db->query('INSERT INTO pre_alt_liberacao_itens VALUES(301,201,401)');
    $db->query('INSERT INTO entregas VALUES(401,3),(402,4),(403,4)');
    $db->query('INSERT INTO entregas_itens VALUES(501,401,10),(502,402,10),(503,403,10)');
    $db->query("INSERT INTO entrega_planejamento_producao VALUES(601,401,'IMAGENS_ALTERACAO','CONFIRMADO',701),(602,402,'IMAGENS_ALTERACAO','RASCUNHO',NULL),(603,403,'IMAGENS_ALTERACAO','RASCUNHO',NULL)");
    $db->query("INSERT INTO entrega_planejamento_item VALUES(701,501,101,11,NULL,'2026-10-30')");
    $db->query('INSERT INTO janela_operacional_ciclo(id) VALUES(801),(802)');
    $ctx = flow_planejamento_alteracao_contexto_operacional($db,101,true);
    flow_planejamento_alteracao_validar_operacao($ctx,11,11,true);
    flow_planejamento_alteracao_vincular_ciclo($db,101,801);
    $db->query('INSERT INTO pre_alt_liberacao_itens VALUES(302,202,402)');
    $db->query('UPDATE imagens_cliente_obra SET status_id=4 WHERE idimagens_cliente_obra=10');
    $ctx = flow_planejamento_alteracao_contexto_operacional($db,101,true);
    pa_op_assert((int)$ctx['entrega_item_id']===502, 'R02 deve resolver sua propria identidade de rodada.');
    pa_op_reject(fn() => flow_planejamento_alteracao_validar_operacao($ctx,11,11,true), 'R02 nao pode herdar decisao confirmada da R01 nem o colaborador da tarefa reutilizada.');
    $db->query("INSERT INTO entrega_planejamento_item VALUES(702,502,101,22,NULL,'2026-11-06')");
    $db->query("UPDATE entrega_planejamento_producao SET estado='CONFIRMADO',versao_atual_id=702 WHERE id=602");
    $db->query('UPDATE funcao_imagem SET colaborador_id=22 WHERE idfuncao_imagem=101');
    flow_planejamento_alteracao_vincular_ciclo($db,101,802);
    $cycles = $db->query('SELECT * FROM janela_operacional_ciclo ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    pa_op_assert((int)$cycles[0]['entrega_item_id']===501 && (int)$cycles[0]['planejamento_versao_id_snapshot']===701, 'Ciclo A deve preservar rodada e versao da R01.');
    pa_op_assert((int)$cycles[1]['entrega_item_id']===502 && (int)$cycles[1]['planejamento_versao_id_snapshot']===702, 'Ciclo B deve apontar para rodada e versao da R02.');
    // A repeated registered stage still resolves the newest release, not old R02.
    $db->query('INSERT INTO pre_alt_liberacao_itens VALUES(303,203,403)');
    $ctx = flow_planejamento_alteracao_contexto_operacional($db,101,true);
    pa_op_assert((int)$ctx['entrega_item_id']===503 && $ctx['versao_atual_id']===null, 'Etapa repetida precisa exigir o novo plano, preservando ciclos anteriores.');
    $db->query("UPDATE entrega_planejamento_producao SET modelo='FUNCOES_R00' WHERE id=603");
    pa_op_assert(flow_planejamento_alteracao_contexto_operacional($db,101)===null, 'Rodada sem modelo de alteracao deve preservar contrato legado.');

    // Reset is part of the caller's release transaction and cannot steal a cycle.
    $db->query("UPDATE funcao_imagem SET status='Aprovado',prazo='2026-10-30' WHERE idfuncao_imagem=101");
    $db->query("UPDATE janela_operacional_ciclo SET ativo_token='ATIVO' WHERE id=802");
    $db->query('INSERT INTO janela_operacional_ciclo_item VALUES(802,101)');
    $db->begin_transaction();
    pa_op_reject(fn() => pre_alt_upsert_funcao_alteracao($db,10,'2026-11-13',true), 'Ciclo anterior ativo precisa impedir limpar responsavel da R02.');
    $db->rollback();
    $row = $db->query('SELECT colaborador_id,status FROM funcao_imagem WHERE idfuncao_imagem=101')->fetch_assoc();
    pa_op_assert((int)$row['colaborador_id']===22 && $row['status']==='Aprovado', 'Rejeicao do ciclo ativo deve preservar atribuicao e status anteriores.');
    $db->query('UPDATE janela_operacional_ciclo SET ativo_token=NULL WHERE id=802');
    $db->begin_transaction();
    pre_alt_upsert_funcao_alteracao($db,10,'2026-11-13',true);
    $row = $db->query('SELECT colaborador_id,status,prazo FROM funcao_imagem WHERE idfuncao_imagem=101')->fetch_assoc();
    pa_op_assert($row['colaborador_id']===null && $row['status']==='Não iniciado' && $row['prazo']==='2026-11-13', 'Nova R02 planejavel deve limpar a decisao herdada e preparar tarefa.');
    $db->rollback();
    $row = $db->query('SELECT colaborador_id,status,prazo FROM funcao_imagem WHERE idfuncao_imagem=101')->fetch_assoc();
    pa_op_assert((int)$row['colaborador_id']===22 && $row['status']==='Aprovado' && $row['prazo']==='2026-10-30', 'Falha posterior na liberacao precisa reverter reset, status e prazo.');
    pa_op_assert((int)$db->query('SELECT COUNT(*) n FROM funcao_imagem_prazo_historico')->fetch_assoc()['n']===0, 'Rollback da liberacao tambem deve reverter auditoria do prazo.');
    $db->begin_transaction();
    pre_alt_upsert_funcao_alteracao($db,10,'2026-11-13',false);
    pa_op_assert((int)$db->query('SELECT colaborador_id FROM funcao_imagem WHERE idfuncao_imagem=101')->fetch_assoc()['colaborador_id']===22, 'Preparacao legada nao deve limpar responsavel historico.');
    $db->rollback();
    foreach (array_reverse(array_keys($schemas)) as $table) $db->query("DROP TEMPORARY TABLE $table");
    $db->close();
}
echo "PlanejamentoAlteracaoOperacionalTest: OK\n";
