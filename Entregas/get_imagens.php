<?php
require_once __DIR__ . '/../conexao.php';

$obra_id = $_GET['obra_id'] ?? null;
$status_id = $_GET['status_id'] ?? null;

if (!$obra_id || !$status_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT
    ico.idimagens_cliente_obra AS id,
    ico.imagem_nome AS nome,
    ico.antecipada
FROM imagens_cliente_obra ico
WHERE ico.obra_id = ?
    AND ico.status_id IN (
        ?,
        CASE
            WHEN ? = 2 THEN 1
            ELSE ?
        END
    )
    AND (ico.substatus_id IS NULL OR ico.substatus_id <> 7)
    AND NOT EXISTS (
        SELECT 1
        FROM entregas_itens ei
        JOIN entregas e ON e.id = ei.entrega_id
        WHERE ei.imagem_id = ico.idimagens_cliente_obra
            AND e.status_id = ico.status_id
    );
");
$stmt->bind_param(
    'iiii',
    $obra_id,
    $status_id,
    $status_id,
    $status_id
);
$stmt->execute();
$result = $stmt->get_result();

$imagens = [];
while ($row = $result->fetch_assoc()) {
    $imagens[] = $row;
}

echo json_encode($imagens);
