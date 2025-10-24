<?php
header('Content-Type: application/json; charset=utf-8');
include '../conexao.php';

// Mês vindo da requisição (01–12 ou 1–12)
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$ano = date('Y'); // sempre o ano atual

try {
    $sql = "SELECT si.nome_status, COUNT(*) as quantidade FROM entregas_itens ei 
    JOIN entregas e ON ei.entrega_id = e.id 
    JOIN status_imagem si ON si.idstatus = e.status_id
    WHERE MONTH(ei.data_entregue) = ? AND YEAR(ei.data_entregue) = ? 
    GROUP BY e.status_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $mes, $ano);
    $stmt->execute();
    $result = $stmt->get_result();

    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'nome_status' => $row['nome_status'],
            'quantidade' => (int)$row['quantidade']
        ];
    }

    echo json_encode($out);
    exit;
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
