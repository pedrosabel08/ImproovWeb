<?php
include '../conexao.php';
session_start();

$idColaborador = $_SESSION['idcolaborador'];

$response = [
    'total_mencoes' => 0,
    'mencoes_por_obra' => []
];

// Buscar menções do colaborador
$stmt = $conn->prepare("SELECT 
    o.nome_obra,
    COUNT(*) AS qtd_mencoes
FROM 
    mencoes m
INNER JOIN comentarios_imagem c ON c.id = m.comentario_id
INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
INNER JOIN funcao_imagem fi ON fi.idfuncao_imagem = hai.funcao_imagem_id
INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
INNER JOIN obra o ON o.idobra = ico.obra_id
WHERE 
    m.mencionado_id = ?
GROUP BY 
    o.nome_obra;");
$stmt->bind_param("i", $idColaborador);
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
while ($row = $result->fetch_assoc()) {
    $obra = $row['nome_obra'];
    $qtd = (int)$row['qtd_mencoes'];
    $total += $qtd;

    if (!isset($response['mencoes_por_obra'][$obra])) {
        $response['mencoes_por_obra'][$obra] = 0;
    }
    $response['mencoes_por_obra'][$obra] += $qtd;
}

$response['total_mencoes'] = $total;

header('Content-Type: application/json');
echo json_encode($response);
