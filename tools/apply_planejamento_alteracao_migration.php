<?php
/** CLI-only, explicit application of the authorized additive R01+ migration. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/conexaoMain.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = conectarBanco();
$apply = in_array('--apply', $argv, true);
$lock = false;

function r01_migration_state(mysqli $conn): array
{
    $rows = $conn->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.columns WHERE table_schema=DATABASE() AND (
            (table_name='entrega_planejamento_producao' AND column_name IN ('modelo','rascunho_json')) OR
            (table_name='entrega_planejamento_item') OR
            (table_name='janela_operacional_ciclo' AND column_name='entrega_item_id'))");
    $state = [];
    foreach ($rows as $r) $state[$r['TABLE_NAME'] . '.' . $r['COLUMN_NAME']] = $r;
    return $state;
}

try {
    $result = $conn->query("SELECT GET_LOCK('flow_planejamento_alteracao_migration_20261028', 5) AS acquired")->fetch_assoc();
    $lock = (int)$result['acquired'] === 1;
    if (!$lock) throw new RuntimeException('Outra aplicação da migration está em execução.');
    $before = r01_migration_state($conn);
    $expected = [
        'entrega_planejamento_producao.modelo',
        'entrega_planejamento_producao.rascunho_json',
        'entrega_planejamento_item.id',
        'janela_operacional_ciclo.entrega_item_id',
    ];
    $present = count(array_intersect($expected, array_keys($before)));
    if ($present > 0 && $present < count($expected)) throw new RuntimeException('Schema parcialmente migrado; revise antes de continuar. Nenhuma alteração foi executada.');
    $parents = [
        ['entregas_itens','id','int'], ['funcao_imagem','idfuncao_imagem','int'],
        ['pre_alt_itens','id','int unsigned'], ['colaborador','idcolaborador','int'],
        ['entrega_planejamento_versao','id','bigint unsigned'],
    ];
    foreach ($parents as [$table,$column,$type]) {
        $stmt = $conn->prepare('SELECT c.COLUMN_TYPE,t.ENGINE FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema=c.table_schema AND t.table_name=c.table_name WHERE c.table_schema=DATABASE() AND c.table_name=? AND c.column_name=?');
        $stmt->bind_param('ss',$table,$column); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row || strtolower($row['COLUMN_TYPE']) !== $type || strtolower($row['ENGINE']) !== 'innodb') throw new RuntimeException('Referência incompatível: ' . $table . '.' . $column);
    }
    $report = ['migration'=>'2026-10-28_planejamento_alteracao_manual.sql','mode'=>$apply?'apply':'preflight','already_applied'=>$present===count($expected),'parent_types_verified'=>count($parents),'statements_executed'=>0];
    if ($apply && $present === 0) {
        $conn->query('SET SESSION lock_wait_timeout=15');
        $sql = file_get_contents(dirname(__DIR__) . '/sql/2026-10-28_planejamento_alteracao_manual.sql');
        $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
        if (count($statements) !== 3) throw new RuntimeException('A migration deve conter exatamente as três operações revisadas.');
        foreach ($statements as $statement) { $conn->query($statement); $report['statements_executed']++; }
    }
    if ($apply || $present === count($expected)) {
        $after = r01_migration_state($conn);
        foreach ($expected as $key) if (!isset($after[$key])) throw new RuntimeException('Estrutura ausente após aplicação: ' . $key);
        $constraints = $conn->query("SELECT table_name,constraint_name FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_type='FOREIGN KEY' AND (table_name='entrega_planejamento_item' OR constraint_name='fk_janela_ciclo_entrega_item')")->fetch_all(MYSQLI_ASSOC);
        if (count($constraints) !== 6) throw new RuntimeException('Quantidade inesperada de vínculos: esperados seis FKs.');
        $index = $conn->query("SHOW INDEX FROM entrega_planejamento_item WHERE Key_name='uq_planejamento_item_versao'")->fetch_all(MYSQLI_ASSOC);
        if (array_column($index,'Column_name') !== ['versao_id','entrega_item_id'] || (int)$index[0]['Non_unique'] !== 0) throw new RuntimeException('Índice único por versão/item incompatível.');
        $report['foreign_keys_verified'] = count($constraints);
        $report['unique_item_per_version_verified'] = true;
        $report['models'] = $conn->query('SELECT modelo,COUNT(*) AS total FROM entrega_planejamento_producao GROUP BY modelo')->fetch_all(MYSQLI_ASSOC);
        $report['confirmed_image_items'] = (int)$conn->query('SELECT COUNT(*) AS n FROM entrega_planejamento_item')->fetch_assoc()['n'];
    }
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);
} finally {
    if ($lock) $conn->query("SELECT RELEASE_LOCK('flow_planejamento_alteracao_migration_20261028')");
    $conn->close();
}
