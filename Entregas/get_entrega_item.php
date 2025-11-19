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
    // Prioriza itens com substatus RVW ou DRV que ainda não foram entregues (assumindo status 'Entregue')
    $sql2 = "SELECT ei.id, ei.imagem_id, i.imagem_nome AS nome, ei.status, ss.nome_substatus
             FROM entregas_itens ei
             INNER JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
             INNER JOIN substatus_imagem ss ON ss.id = i.substatus_id
             WHERE ei.entrega_id = ?
             ORDER BY (CASE WHEN ei.status = 'Entrega pendente' OR ss.nome_substatus IN ('RVW','DRV') THEN 1 ELSE 0 END) DESC,
                      FIELD(ei.status, 'Pendente', 'Parcial') DESC,
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
