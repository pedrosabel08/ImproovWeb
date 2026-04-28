<?php
// PreAlteracao/get_imagens_rvw_done.php
// Retorna imagens da obra com substatus RVW_DONE (10), PRE_ALT (11) ou READY_FOR_PLANNING (12)
// Inclui dados da pre_alt_analise se já tiver sido preenchida
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once '../config/session_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

$obra_id = isset($_GET['obra_id']) && is_numeric($_GET['obra_id'])
    ? intval($_GET['obra_id'])
    : 0;

if ($obra_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'obra_id inválido.']);
    exit;
}

// entrega_id: -1 = não informado (todas), 0 = sem entrega, >0 = entrega específica
$entrega_id = isset($_GET['entrega_id']) ? intval($_GET['entrega_id']) : -1;

// Busca imagens com RVW_DONE (10), PRE_ALT (11) ou READY_FOR_PLANNING (12), já com análise se existir
$base_sql = "
    SELECT
        i.idimagens_cliente_obra AS imagem_id,
        i.imagem_nome            AS nome,
        i.substatus_id,
        ss.nome_substatus,
        e.id                     AS entrega_id,
        pa.id                    AS analise_id,
        pa.complexidade,
        pa.acao,
        pa.necessita_retorno,
        pa.responsavel_id,
        pa.updated_at            AS analise_updated_at,
        c.nome_colaborador       AS responsavel_nome
    FROM imagens_cliente_obra i
    INNER JOIN substatus_imagem ss     ON ss.id = i.substatus_id
    LEFT JOIN entregas_itens ei        ON ei.imagem_id = i.idimagens_cliente_obra
    LEFT JOIN entregas e               ON e.id = ei.entrega_id
    LEFT JOIN pre_alt_analise pa       ON pa.imagem_id = i.idimagens_cliente_obra
                                      AND (e.id IS NULL OR pa.entrega_id = e.id)
    LEFT JOIN colaborador c            ON c.idcolaborador = pa.responsavel_id
    WHERE i.obra_id = ?
      AND i.substatus_id IN (10, 11, 12)
";

$order_sql = "
    ORDER BY
        i.substatus_id ASC,
        CAST(SUBSTRING_INDEX(i.imagem_nome, '.', 1) AS UNSIGNED) ASC,
        i.imagem_nome ASC
";

if ($entrega_id > 0) {
    $stmt = $conn->prepare($base_sql . " AND ei.entrega_id = ? " . $order_sql);
    $stmt->bind_param('ii', $obra_id, $entrega_id);
} elseif ($entrega_id === 0) {
    $stmt = $conn->prepare($base_sql . " AND ei.imagem_id IS NULL " . $order_sql);
    $stmt->bind_param('i', $obra_id);
} else {
    $stmt = $conn->prepare($base_sql . $order_sql);
    $stmt->bind_param('i', $obra_id);
}

$stmt->execute();
$res  = $stmt->get_result();

$imagens = [];
while ($row = $res->fetch_assoc()) {
    $imagens[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'imagens' => $imagens]);
