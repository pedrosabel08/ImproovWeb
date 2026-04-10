<?php
// PreAlteracao/get_pre_alt_entregas.php
// Retorna entregas agrupadas que possuem imagens em pré-análise (substatus 10/11/12)
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';
require_once '../config/session_bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

// Agrupa imagens por entrega (ou por obra quando não há entrega vinculada)
$sql = "
    SELECT
        e.id              AS entrega_id,
        o.idobra          AS obra_id,
        o.nomenclatura,
        e.data_prevista,
        SUM(CASE WHEN i.substatus_id IN (10, 11) THEN 1 ELSE 0 END) AS count_analise,
        SUM(CASE WHEN i.substatus_id = 12        THEN 1 ELSE 0 END) AS count_planning
    FROM imagens_cliente_obra i
    JOIN obra o ON o.idobra = i.obra_id
    LEFT JOIN entregas_itens ei ON ei.imagem_id = i.idimagens_cliente_obra
    LEFT JOIN entregas e        ON e.id = ei.entrega_id
    WHERE i.substatus_id IN (10, 11, 12)
      AND o.status_obra = 0
    GROUP BY e.id, o.idobra, o.nomenclatura, e.data_prevista
    HAVING (SUM(CASE WHEN i.substatus_id IN (10, 11) THEN 1 ELSE 0 END) > 0
         OR SUM(CASE WHEN i.substatus_id = 12        THEN 1 ELSE 0 END) > 0)
    ORDER BY o.nomenclatura, e.id
";

$res = $conn->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$entregas  = [];
$obras_map = [];

while ($row = $res->fetch_assoc()) {
    $row['entrega_id']     = $row['entrega_id'] !== null ? intval($row['entrega_id']) : 0;
    $row['obra_id']        = intval($row['obra_id']);
    $row['count_analise']  = intval($row['count_analise']);
    $row['count_planning'] = intval($row['count_planning']);
    $entregas[] = $row;

    $oid = $row['obra_id'];
    if (!isset($obras_map[$oid])) {
        $obras_map[$oid] = $row['nomenclatura'];
    }
}

// Lista de obras distintas (para o filtro)
$obras = [];
foreach ($obras_map as $id => $nome) {
    $obras[] = ['idobra' => $id, 'nomenclatura' => $nome];
}
usort($obras, fn($a, $b) => strcmp($a['nomenclatura'], $b['nomenclatura']));

echo json_encode(['success' => true, 'entregas' => $entregas, 'obras' => $obras]);
