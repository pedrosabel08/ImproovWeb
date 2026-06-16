<?php
// PreAlteracao/get_pre_alt_entregas.php
// Retorna lotes de triagem de alteracao agrupados por obra/etapa/data.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/pre_alt_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autenticado.']);
    exit;
}

pre_alt_ensure_schema($conn);

$sql = "
    SELECT
        l.id AS lote_id,
        l.obra_id,
        l.status_id,
        l.data_finalizacao_cliente,
        l.status AS lote_status,
        o.nomenclatura,
        si.nome_status AS nome_etapa,
        COUNT(DISTINCT lb.review_batch_id) AS batch_count,
        COUNT(i.id) AS total_itens,
        SUM(CASE WHEN i.resultado = 'ALTERACAO' THEN 1 ELSE 0 END) AS count_alteracao,
        SUM(CASE WHEN i.resultado = 'SEM_ALTERACAO' THEN 1 ELSE 0 END) AS count_sem_alteracao,
        SUM(CASE WHEN i.resultado = 'AGUARDANDO_CLIENTE' OR i.necessita_retorno = 1 THEN 1 ELSE 0 END) AS count_aguardando,
        SUM(CASE WHEN i.resultado IS NULL OR (i.resultado = 'ALTERACAO' AND i.nivel_complexidade IS NULL) THEN 1 ELSE 0 END) AS count_incompleto,
        SUM(CASE WHEN i.nivel_complexidade = 1 THEN 1 ELSE 0 END) AS nivel_1,
        SUM(CASE WHEN i.nivel_complexidade = 2 THEN 1 ELSE 0 END) AS nivel_2,
        SUM(CASE WHEN i.nivel_complexidade = 3 THEN 1 ELSE 0 END) AS nivel_3,
        SUM(CASE WHEN i.nivel_complexidade = 4 THEN 1 ELSE 0 END) AS nivel_4,
        SUM(CASE WHEN i.nivel_complexidade = 5 THEN 1 ELSE 0 END) AS nivel_5,
        GROUP_CONCAT(DISTINCT i.entrega_id ORDER BY i.entrega_id SEPARATOR ',') AS entrega_ids
    FROM pre_alt_lote l
    JOIN obra o ON o.idobra = l.obra_id
    JOIN status_imagem si ON si.idstatus = l.status_id
    LEFT JOIN pre_alt_lote_batches lb ON lb.pre_alt_lote_id = l.id
    LEFT JOIN pre_alt_itens i ON i.pre_alt_lote_id = l.id
    WHERE o.status_obra = 0
      AND l.status <> 'CANCELADO'
    GROUP BY
        l.id,
        l.obra_id,
        l.status_id,
        l.data_finalizacao_cliente,
        l.status,
        o.nomenclatura,
        si.nome_status
    ORDER BY
        FIELD(l.status, 'EM_TRIAGEM', 'AGUARDANDO_CLIENTE', 'PRONTO_PLANEJAMENTO', 'PLANEJADO'),
        l.data_finalizacao_cliente DESC,
        o.nomenclatura ASC,
        si.nome_status ASC
";

$res = $conn->query($sql);
if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$lotes = [];
$obrasMap = [];

while ($row = $res->fetch_assoc()) {
    $row['lote_id'] = (int) $row['lote_id'];
    $row['obra_id'] = (int) $row['obra_id'];
    $row['status_id'] = (int) $row['status_id'];
    $row['batch_count'] = (int) ($row['batch_count'] ?? 0);
    $row['total_itens'] = (int) ($row['total_itens'] ?? 0);
    $row['count_alteracao'] = (int) ($row['count_alteracao'] ?? 0);
    $row['count_sem_alteracao'] = (int) ($row['count_sem_alteracao'] ?? 0);
    $row['count_aguardando'] = (int) ($row['count_aguardando'] ?? 0);
    $row['count_incompleto'] = (int) ($row['count_incompleto'] ?? 0);
    for ($nivel = 1; $nivel <= 5; $nivel++) {
        $row['nivel_' . $nivel] = (int) ($row['nivel_' . $nivel] ?? 0);
    }
    $lotes[] = $row;
    $obrasMap[$row['obra_id']] = $row['nomenclatura'];
}

$obras = [];
foreach ($obrasMap as $id => $nome) {
    $obras[] = ['idobra' => (int) $id, 'nomenclatura' => $nome];
}
usort($obras, static fn($a, $b) => strcmp($a['nomenclatura'], $b['nomenclatura']));

echo json_encode(['success' => true, 'lotes' => $lotes, 'obras' => $obras]);
