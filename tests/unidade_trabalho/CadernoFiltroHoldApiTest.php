<?php

// Exercita o endpoint real com commit desabilitado e sem publicar notificações.
// Uso: php tests/unidade_trabalho/CadernoFiltroHoldApiTest.php create|continue_task
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexao.php';

class CfHoldRollbackConnection extends mysqli
{
    public function begin_transaction(int $flags = 0, ?string $name = null): bool { return true; }
    public function commit(int $flags = 0, ?string $name = null): bool { return true; }
    public function startTest(): void { parent::begin_transaction(); }
}

// As migrações do endpoint fazem DDL com commit implícito. Não fazem parte deste teste.
function flow_block_operacional_ensure_schema(mysqli $conn): void {}
function flow_block_notify(...$args): void {}
function flow_block_publish(...$args): void {}
function flow_block_publish_operational_lifecycle(...$args): void {}

function flow_block_json_response(array $payload, int $status = 200): void
{
    global $conn, $caderno, $filtro, $action;
    $expected = $action === 'create' ? 'HOLD' : 'Em andamento';
    $rows = $conn->query("SELECT status FROM funcao_imagem WHERE idfuncao_imagem IN ($caderno,$filtro)")->fetch_all(MYSQLI_ASSOC);
    $ok = !empty($payload['ok']) && count($rows) === 2;
    foreach ($rows as $row) {
        $ok = $ok && $row['status'] === $expected;
    }
    $conn->rollback();
    if (!$ok) {
        fwrite(STDERR, json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }
    echo "CadernoFiltroHoldApiTest: OK ($action, ambos $expected; rollback)\n";
    exit;
}

$conn->close();
$conn = new CfHoldRollbackConnection($servername, $username, $password, $dbname);
$conn->set_charset('utf8mb4');
$conn->startTest();
register_shutdown_function(static function () use ($conn): void { $conn->rollback(); });
$pair = $conn->query("SELECT c.idfuncao_imagem caderno,f.idfuncao_imagem filtro,c.colaborador_id
    FROM funcao_imagem c JOIN funcao_imagem f
      ON f.imagem_id=c.imagem_id AND f.colaborador_id=c.colaborador_id AND f.funcao_id=8
    WHERE c.funcao_id=1 AND c.colaborador_id > 0
      AND NOT EXISTS (SELECT 1 FROM funcao_par_separado s WHERE s.imagem_id=c.imagem_id AND s.par_tipo='caderno_filtro')
      AND NOT EXISTS (SELECT 1 FROM flow_issue i WHERE i.funcao_imagem_id IN (c.idfuncao_imagem,f.idfuncao_imagem))
    LIMIT 1")->fetch_assoc();
if (!$pair) {
    throw new RuntimeException('Sem par disponível para teste transacional.');
}
$caderno = (int) $pair['caderno'];
$filtro = (int) $pair['filtro'];
$actor = (int) $pair['colaborador_id'];
$_SESSION = ['logado' => true, 'idcolaborador' => $actor, 'nivel_acesso' => 1];
$action = $argv[1] ?? 'create';
if (!in_array($action, ['create', 'continue_task'], true)) {
    throw new InvalidArgumentException('Ação inválida.');
}
$type = (int) $conn->query('SELECT id FROM flow_issue_tipo WHERE ativo=1 LIMIT 1')->fetch_assoc()['id'];
$_GET['action'] = $action;
$_POST = ['funcao_imagem_id' => $caderno, 'tipo_id' => $type, 'descricao' => 'Teste transacional HOLD conjunto'];
if ($action === 'create') {
    $conn->query("UPDATE funcao_imagem SET status='Em andamento' WHERE idfuncao_imagem IN ($caderno,$filtro)");
} else {
    $conn->query("UPDATE funcao_imagem SET status='HOLD' WHERE idfuncao_imagem IN ($caderno,$filtro)");
    // A liberação está vinculada ao outro membro para testar o escopo compartilhado.
    $conn->query("INSERT INTO flow_issue (funcao_imagem_id,tipo_id,descricao,status,criado_por_colaborador_id,sla_atendimento_em)
        VALUES ($filtro,$type,'Teste transacional de retomada','CANCELADA',$actor,NOW())");
    $_POST['prazo'] = date('Y-m-d', strtotime('+2 days'));
}
require dirname(__DIR__, 2) . '/FlowBlock/api.php';
