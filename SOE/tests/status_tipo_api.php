<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../conexao.php';

$start_status_param = isset($_GET['start_status']) && is_numeric($_GET['start_status']) ? (int) $_GET['start_status'] : null;
$filter_tipo = isset($_GET['tipo_imagem']) ? trim($_GET['tipo_imagem']) : '';

function map_end_substatus_for_status($status_id)
{
    if ($status_id === 1 || $status_id === 6)
        return 9;
    return 6;
}

function get_status_name($conn, $status_id)
{
    $id = (int) $status_id;
    $qr = $conn->query("SELECT nome_status FROM status_imagem WHERE idstatus = " . $id . " LIMIT 1");
    if ($qr && $r = $qr->fetch_assoc())
        return $r['nome_status'];
    return null;
}

function get_substatus_name($conn, $substatus_id)
{
    $id = (int) $substatus_id;
    $qr = $conn->query("SELECT nome_substatus FROM substatus_imagem WHERE id = " . $id . " LIMIT 1");
    if ($qr && $r = $qr->fetch_assoc())
        return $r['nome_substatus'];
    return null;
}


function run_query_for_status($conn, $start_status, $end_substatus, $filter_tipo)
{
    $tipo_sql = '';
    if ($filter_tipo !== '') {
        $tipo_esc = $conn->real_escape_string($filter_tipo);
        $tipo_sql = "AND ico.tipo_imagem = '" . $tipo_esc . "'";
    }

    $sql = "WITH inicio AS (
        SELECT hi.imagem_id, MIN(hi.data_movimento) AS data_inicio
        FROM historico_imagens hi
        WHERE hi.status_id = " . (int) $start_status . "
        GROUP BY hi.imagem_id
    ),
    fim AS (
        SELECT hi.imagem_id, MIN(hi.data_movimento) AS data_fim
        FROM historico_imagens hi
        JOIN inicio i ON i.imagem_id = hi.imagem_id AND hi.data_movimento > i.data_inicio
        WHERE hi.substatus_id = " . (int) $end_substatus . "
        GROUP BY hi.imagem_id
    ),
    tempos AS (
        SELECT ico.idimagens_cliente_obra AS imagem_id, ico.tipo_imagem,
            TIMESTAMPDIFF(HOUR, i.data_inicio, f.data_fim) AS tempo_horas
        FROM imagens_cliente_obra ico
        JOIN inicio i ON i.imagem_id = ico.idimagens_cliente_obra
        JOIN fim f ON f.imagem_id = ico.idimagens_cliente_obra
        WHERE TIMESTAMPDIFF(HOUR, i.data_inicio, f.data_fim) > 0
        $tipo_sql
    ),
    ordenado AS (
        SELECT tipo_imagem, tempo_horas,
            ROW_NUMBER() OVER (PARTITION BY tipo_imagem ORDER BY tempo_horas) AS rn,
            COUNT(*) OVER (PARTITION BY tipo_imagem) AS total
        FROM tempos
    )
    SELECT tipo_imagem,
        COUNT(*) AS total_imagens,
        ROUND(AVG(tempo_horas), 1) AS media_horas,
        ROUND(
            AVG(
                CASE
                    WHEN rn IN (
                        FLOOR((total + 1) / 2),
                        CEIL((total + 1) / 2)
                    )
                    THEN tempo_horas
                END
            ), 1
        ) AS mediana_p50,
        MAX(CASE WHEN rn <= total * 0.75 THEN tempo_horas END) AS p75,
        MAX(CASE WHEN rn <= total * 0.90 THEN tempo_horas END) AS p90
    FROM ordenado
    GROUP BY tipo_imagem
    ORDER BY tipo_imagem DESC";

    $res = $conn->query($sql);
    if (!$res)
        return ['error' => $conn->error, 'sql' => $sql];
    $rows = [];
    while ($r = $res->fetch_assoc())
        $rows[] = $r;
    return $rows;
}

$statuses = [];
if ($start_status_param !== null) {
    $statuses[] = $start_status_param;
} else {
    $qr = $conn->query("SELECT DISTINCT status_id FROM historico_imagens WHERE status_id IS NOT NULL ORDER BY status_id");
    while ($r = $qr->fetch_assoc())
        $statuses[] = (int) $r['status_id'];
}

$out = [];
foreach ($statuses as $st) {
    $end_sub = map_end_substatus_for_status($st);
    $status_name = get_status_name($conn, $st) ?: (string) $st;
    $substatus_name = get_substatus_name($conn, $end_sub) ?: (string) $end_sub;
    $rows = run_query_for_status($conn, $st, $end_sub, $filter_tipo);
    $out[] = [
        'status_id' => $st,
        'status_name' => $status_name,
        'end_substatus_id' => $end_sub,
        'end_substatus_name' => $substatus_name,
        'rows' => $rows
    ];
}

echo json_encode($out);
