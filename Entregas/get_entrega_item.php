<?php
// get_entrega_item.php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da entrega inválido.']);
    exit;
}

$entrega_id = intval($_GET['id']);

try {
    // buscar informações da entrega
    $sql = "SELECT e.id, e.obra_id, e.status_id, e.data_prevista, e.status, e.data_conclusao, e.observacoes, s.nome_status as nome_etapa, o.nomenclatura
            FROM entregas e 
            JOIN status_imagem s ON e.status_id = s.idstatus
            JOIN obra o ON e.obra_id = o.idobra
            WHERE e.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['error' => 'Entrega não encontrada']);
        exit;
    }
    $entrega = $res->fetch_assoc();

    // buscar itens da entrega
    // Prioriza explicitamente substatus RVW, depois DRV, depois pendentes
    $sql2 = "SELECT ei.id, ei.imagem_id, i.imagem_nome AS nome, ei.status, ss.nome_substatus
             FROM entregas_itens ei
             INNER JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
             INNER JOIN substatus_imagem ss ON ss.id = i.substatus_id
             WHERE ei.entrega_id = ?
             ORDER BY
             CASE
                 WHEN LOWER(TRIM(ei.status)) = 'entrega pendente' OR UPPER(TRIM(ss.nome_substatus)) = 'RVW' THEN 1
                 WHEN UPPER(TRIM(ss.nome_substatus)) = 'DRV' THEN 2
                 WHEN LOWER(TRIM(ei.status)) LIKE '%pendente%' THEN 3
                 ELSE 4
             END ASC,
             CASE
                 WHEN LOWER(TRIM(ei.status)) = 'entrega pendente' OR UPPER(TRIM(ss.nome_substatus)) = 'RVW'
                     THEN CAST(SUBSTRING_INDEX(i.imagem_nome, '.', 1) AS UNSIGNED)
                 ELSE NULL
             END ASC,
             CASE
                 WHEN LOWER(TRIM(ei.status)) = 'entrega pendente' OR UPPER(TRIM(ss.nome_substatus)) = 'RVW'
                     THEN i.imagem_nome
                 ELSE NULL
             END ASC,
             CASE
                 WHEN LOWER(TRIM(ei.status)) = 'entrega pendente' THEN 1
                 WHEN LOWER(TRIM(ei.status)) = 'pendente' THEN 2
                 WHEN LOWER(TRIM(ei.status)) = 'parcial' THEN 3
                 WHEN LOWER(TRIM(ei.status)) = 'entregue com atraso' THEN 4
                 WHEN LOWER(TRIM(ei.status)) = 'entregue no prazo' THEN 5
                 WHEN LOWER(TRIM(ei.status)) = 'entrega antecipada' THEN 6
                 ELSE 99
             END ASC,
             ei.id ASC";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $entrega_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $itens = [];
    while ($row = $res2->fetch_assoc()) {
        $itens[] = $row;
    }

    $entrega['itens'] = $itens;

    echo json_encode($entrega);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
