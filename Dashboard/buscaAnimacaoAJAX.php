<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Metodo de requisicao invalido.']);
    exit;
}

$animacaoId = isset($_GET['ajid']) ? (int) $_GET['ajid'] : 0;

if ($animacaoId <= 0) {
    echo json_encode(['error' => 'Parametro ajid invalido.']);
    exit;
}

$stmtAnimacao = $conn->prepare(
    "SELECT
        a.idanimacao,
        a.imagem_id,
        a.obra_id,
        a.cliente_id,
        a.tipo_animacao,
        a.duracao,
        a.valor,
        a.data_anima,
        a.substatus_id,
        su.nome_substatus AS substatus,
        img.imagem_nome,
        img.status_id AS imagem_status_id,
        si.nome_status AS imagem_status_nome
     FROM animacao a
     JOIN imagens_cliente_obra img ON img.idimagens_cliente_obra = a.imagem_id
     LEFT JOIN substatus_imagem su ON su.id = a.substatus_id
     LEFT JOIN status_imagem si ON si.idstatus = img.status_id
     WHERE a.idanimacao = ?
     LIMIT 1"
);
$stmtAnimacao->bind_param('i', $animacaoId);
$stmtAnimacao->execute();
$animacao = $stmtAnimacao->get_result()->fetch_assoc();
$stmtAnimacao->close();

if (!$animacao) {
    echo json_encode(['error' => 'Animacao nao encontrada.']);
    $conn->close();
    exit;
}

$stmtFuncoes = $conn->prepare(
    "SELECT
        f.idfuncao AS funcao_id,
        f.nome_funcao,
        fa.id,
        fa.colaborador_id,
        col.nome_colaborador,
        fa.prazo,
        COALESCE(fa.status, 'Não iniciado') AS status,
        fa.observacao,
        NULL AS nome_pdf,
        NULL AS responsavel_aprovacao,
        NULL AS descricao
     FROM (
        SELECT 10 AS funcao_id, 1 AS ordem
        UNION ALL
        SELECT 5 AS funcao_id, 2 AS ordem
     ) req
     JOIN funcao f ON f.idfuncao = req.funcao_id
     LEFT JOIN funcao_animacao fa
        ON fa.funcao_id = req.funcao_id
       AND fa.animacao_id = ?
     LEFT JOIN colaborador col ON col.idcolaborador = fa.colaborador_id
     ORDER BY req.ordem"
);
$stmtFuncoes->bind_param('i', $animacaoId);
$stmtFuncoes->execute();
$resultFuncoes = $stmtFuncoes->get_result();

$funcoes = [];
$nomeBase = trim((string) ($animacao['imagem_nome'] ?? ''));
$tipo = trim((string) ($animacao['tipo_animacao'] ?? ''));
$nomeAnimacao = $nomeBase;
if ($tipo !== '') {
    $nomeAnimacao .= ' - ' . $tipo;
}

while ($row = $resultFuncoes->fetch_assoc()) {
    $row['imagem_nome'] = $nomeAnimacao;
    $row['clima'] = null;
    $funcoes[] = $row;
}
$stmtFuncoes->close();

echo json_encode([
    'contexto' => 'animacao',
    'animacao' => $animacao,
    'funcoes' => $funcoes,
    'status_id' => $animacao['imagem_status_id'] ?? null,
]);

$conn->close();
