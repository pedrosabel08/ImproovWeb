<?php
// Dedicated loopback-only database instance. NEVER loads conexao.php.
// php tests/custos_db_integration.php /tmp/custos-schema.json
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../helpers/custos_helper.php';
require_once __DIR__ . '/../Pagamento/financeiro_v2.php';
require_once __DIR__ . '/../Custos/comercial_helper.php';
$conn = new mysqli('127.0.0.1', 'root', '', '', 3319);
$conn->set_charset('utf8mb4');
$name = 'custos_test_' . bin2hex(random_bytes(5));
$conn->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$conn->select_db($name);
$conn->query('SET FOREIGN_KEY_CHECKS=0');
$conn->query("SET SESSION sql_mode=''");
$fixture = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
foreach ($fixture['schema'] as $sql) $conn->query(str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $sql));
$conn->begin_transaction();
foreach ($fixture['tables'] as $table => $rows) {
    if (!$rows) continue;
    $cols = array_keys($rows[0]);
    $s = $conn->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
    foreach ($rows as $row) {
        $values = array_values($row);
        $s->bind_param(str_repeat('s', count($cols)), ...$values);
        $s->execute();
    }
    $s->close();
}
$conn->commit();
$checks = 0;
function check($actual, $expected, $name)
{
    global $checks;
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($name . ': ' . json_encode($actual) . ' != ' . json_encode($expected));
}
$data = custos_calcular(custos_carregar($conn, 67));
check($data['resumo']['vendido'], 2830000, 'Receita real');
check($data['resumo']['realizado'], 80000, 'Realizado real');
check($data['resumo']['a_pagar'], 688000, 'Restante real');
check($data['resumo']['margem'], 2062000, 'Margem real');
// Independent aggregate SQL, not the engine's collection joins.
$ind = custos_query($conn, 'SELECT SUM(valor) vendido,SUM(valor_imposto) impostos,SUM(valor_comissao_comercial) comissao FROM imagem_comercial WHERE obra_id=?', 'i', [67])[0];
check(custos_centavos($ind['vendido']) + custos_centavos(custos_query($conn, 'SELECT SUM(valor) total FROM servico_foto WHERE obra_id=?', 'i', [67])[0]['total']), $data['resumo']['vendido'], 'Receita por SQL independente');
$dbPaid = custos_query($conn, "SELECT SUM(pi.valor) total FROM pagamento_itens pi WHERE pi.origem='funcao_imagem' AND pi.origem_id IN (SELECT fi.idfuncao_imagem FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id WHERE i.obra_id=67)")[0];
check(custos_centavos($dbPaid['total']), 80000, 'Livro por SQL independente');
// Synthetic origins, outside the imported IDs. All writes stay in this local DB.
$conn->query("INSERT INTO obra (idobra,nomenclatura,status_obra) VALUES (900001,'TEST_V2',0)");
$conn->query("INSERT INTO imagens_cliente_obra (idimagens_cliente_obra,obra_id,cliente_id,imagem_nome,tipo_imagem,antecipada,dias_trabalhados,clima) VALUES (900001,900001,0,'Teste Fachada','Fachada',0,0,'')");
$conn->query("INSERT INTO funcao_imagem (idfuncao_imagem,imagem_id,colaborador_id,funcao_id,valor,status,prazo) VALUES (900001,900001,23,4,300,'Finalizado','2026-09-05'),(900002,900001,23,1,100,'Finalizado','2026-09-05'),(900003,900001,23,2,100,'Não iniciado','2026-09-05')");
$row = ['origem' => 'funcao_imagem', 'origem_id' => 900001, 'comissao_gestor' => false, 'tipo_imagem' => 'Fachada', 'imagem_nome' => 'Teste Fachada'];
$conn->begin_transaction();
$p = financeiro_lancar($conn, $row, 23, 9, 2026, null, 'parcial');
$conn->commit();
check($p['valor'], '150.00', 'Parcial valor efetivo');
$conn->begin_transaction();
$again = financeiro_lancar($conn, $row, 23, 10, 2026, null, 'parcial');
$conn->commit();
check($again['skipped'], true, 'Parcial idempotente entre meses');
$conn->begin_transaction();
$complete = financeiro_lancar($conn, $row, 23, 9, 2026, null, 'completa');
$conn->commit();
check($complete['valor'], '150.00', 'Complemento do saldo');
$pid = $p['pagamento_id'];
$total = custos_query($conn, 'SELECT valor_total FROM pagamentos WHERE idpagamento=?', 'i', [$pid])[0];
check(custos_centavos($total['valor_total']), 30000, 'Agregado dois lotes');
$result = financeiro_pagar($conn, ['colaborador_id' => 23, 'mes' => 9, 'ano' => 2026, 'ids' => [['origem' => 'funcao_imagem', 'id' => 900002, 'valor' => 99999]]], null);
check($result[0]['valor'], '100.00', 'Ignora valor de navegador');
$total = custos_query($conn, 'SELECT valor_total FROM pagamentos WHERE idpagamento=?', 'i', [$pid])[0];
check(custos_centavos($total['valor_total']), 40000, 'Agregado terceiro lote');
try {
    financeiro_pagar($conn, ['colaborador_id' => 23, 'mes' => 9, 'ano' => 2026, 'ids' => [['origem' => 'funcao_imagem', 'id' => 900003]]], null);
    throw new RuntimeException('Pagou não iniciado');
} catch (InvalidArgumentException $e) {
    $checks++;
}
$eligible = financeiro_elegiveis($conn, 23, 9, 2026);
check(in_array(900003, array_map('intval', array_column($eligible, 'origem_id'))), false, 'Lote exclui não concluída');
$commission = $row;
$commission['comissao_gestor'] = true;
$conn->begin_transaction();
$c = financeiro_lancar($conn, $commission, 8, 9, 2026, null);
$conn->commit();
check($c['valor'], '100.00', 'Comissão rastreada');
$r = custos_calcular(custos_carregar($conn, 900001));
check($r['resumo']['realizado'], 50000, 'Comissão não abate tarefa');
check($r['resumo']['a_pagar'], 10000, 'Tarefa não iniciada continua prevista');
// Migration only in the local test database.
$conn->query('ALTER TABLE pagamento_itens ADD COLUMN tipo_lancamento VARCHAR(40) NULL, ADD COLUMN chave_lancamento VARCHAR(160) NULL, ADD UNIQUE KEY uq_pagamento_lancamento(chave_lancamento)');
$conn->begin_transaction();
custos_query($conn, 'SELECT idobra FROM obra WHERE idobra=? FOR UPDATE', 'i', [900001]);
$v = custos_comercial_validar($conn, 900001, ['imagem_id' => 900001, 'valor' => '1000', 'valor_imposto' => '100']);
custos_comercial_salvar($conn, 900001, $v, true);
custos_comercial_salvar($conn, 900001, $v, true);
$conn->commit();
check((int)custos_query($conn, 'SELECT COUNT(*) n FROM imagem_comercial WHERE obra_id=900001')[0]['n'], 1, 'Upsert sem duplicidade');
try {
    custos_comercial_validar($conn, 900001, ['imagem_id' => 1, 'valor' => 1]);
    throw new RuntimeException('Aceitou imagem alheia');
} catch (InvalidArgumentException $e) {
    $checks++;
}
// Rollback: insert a photo then deliberately reject a cross-project image.
$conn->begin_transaction();
try {
    custos_comercial_salvar($conn, 900001, ['categoria' => 'foto', 'id' => 0, 'valor' => '50.00']);
    custos_comercial_validar($conn, 900001, ['imagem_id' => 1, 'valor' => 1]);
} catch (InvalidArgumentException $e) {
    $conn->rollback();
}
check((int)custos_query($conn, 'SELECT COUNT(*) n FROM servico_foto WHERE obra_id=900001')[0]['n'], 0, 'Transação comercial atômica');
echo "$checks verificações de integração OK; MariaDB local; banco isolado $name\n";
echo 'INO_MAR: ' . json_encode($data['resumo'], JSON_UNESCAPED_UNICODE) . "\n";
