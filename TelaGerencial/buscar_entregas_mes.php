<?php
header('Content-Type: application/json; charset=utf-8');
include '../conexao.php';

// Mês vindo da requisição (01–12 ou 1–12)
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

try {
    $sql = "SELECT si.nome_status, COUNT(*) as quantidade,
        SUM(CASE WHEN LOWER(i.tipo_imagem) = 'planta humanizada' THEN 1 ELSE 0 END) AS quantidade_ph
    FROM entregas_itens ei
    JOIN entregas e ON ei.entrega_id = e.id
    JOIN status_imagem si ON si.idstatus = e.status_id
    LEFT JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
    WHERE MONTH(ei.data_entregue) = ? AND YEAR(ei.data_entregue) = ? AND i.obra_id <> 74
    GROUP BY e.status_id, si.nome_status";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('ii', $mes, $ano);
    $stmt->execute();
    $result = $stmt->get_result();

    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'nome_status' => $row['nome_status'],
            'quantidade' => (int)$row['quantidade'],
            'quantidade_ph' => isset($row['quantidade_ph']) ? (int)$row['quantidade_ph'] : 0
        ];
    }

    echo json_encode($out);
    exit;
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}