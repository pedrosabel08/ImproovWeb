<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__).'/conexaoMain.php';
$conn = conectarBanco();
$sql = file_get_contents(dirname(__DIR__).'/sql/2026-10-28_planejamento_alteracao_pontos.sql');
$statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
if (!$conn->query("SHOW COLUMNS FROM planejamento_capacidade_etapa LIKE 'parametros_json'")->num_rows) {
    $conn->query($statements[0]);
}
$conn->query($statements[1]);
echo "OK: parâmetros R01+ preparados sem alterar planos ou tarefas.\n";
echo json_encode($conn->query("SELECT fc.colaborador_id,fc.tipo_atuacao,c.ativo,c.elegivel_capacidade FROM funcao_colaborador fc JOIN colaborador c ON c.idcolaborador=fc.colaborador_id WHERE fc.funcao_id=6 ORDER BY fc.colaborador_id")->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),"\n";
