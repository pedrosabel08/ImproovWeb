<?php
require_once '../conexao.php';

$obra_id = $_GET['obra_id'] ?? null;
$status_id = $_GET['status_id'] ?? null;

if (!$obra_id || !$status_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT idimagens_cliente_obra AS id, imagem_nome AS nome, antecipada
        FROM imagens_cliente_obra ico
        WHERE ico.obra_id = ?
            AND ico.status_id = ?
            AND (ico.substatus_id IS NULL OR ico.substatus_id <> 7)
            AND NOT EXISTS (
                    SELECT 1
                    FROM entregas_itens ei
                    JOIN entregas e ON ei.entrega_id = e.id
                    WHERE ei.imagem_id = ico.idimagens_cliente_obra
                        AND e.status_id = ico.status_id
            )
");
$stmt->bind_param("ii", $obra_id, $status_id);
$stmt->execute();
$result = $stmt->get_result();

$imagens = [];
while ($row = $result->fetch_assoc()) {
    $imagens[] = $row;
}

echo json_encode($imagens);
