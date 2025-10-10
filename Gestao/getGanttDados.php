<?php
include '../conexao.php';
header('Content-Type: application/json; charset=utf-8');

$funcao = $_GET['funcao'] ?? '';

$sql = "SELECT 
    c.nome_colaborador,
    fi.idfuncao_imagem,
    ico.imagem_nome,
    MIN(la.data) AS data_inicio,
    MAX(fi.prazo) AS data_fim
FROM funcao_imagem fi
JOIN funcao f ON f.idfuncao = fi.funcao_id
JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
LEFT JOIN log_alteracoes la ON la.funcao_imagem_id = fi.idfuncao_imagem
WHERE f.nome_funcao = ?
  AND fi.status IN ('Em andamento')
GROUP BY c.nome_colaborador, fi.idfuncao_imagem, ico.imagem_nome
ORDER BY c.nome_colaborador, data_inicio
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $funcao);
$stmt->execute();
$result = $stmt->get_result();

$gantt = [];
while ($row = $result->fetch_assoc()) {
    $colab = $row['nome_colaborador'];
    if (!isset($gantt[$colab])) $gantt[$colab] = [];
    $gantt[$colab][] = [
        'imagem' => $row['imagem_nome'],
        'start' => $row['data_inicio'] ? date('Y-m-d', strtotime($row['data_inicio'])) : null,
        'end' => $row['data_fim'] ? date('Y-m-d', strtotime($row['data_fim'])) : null
    ];
}

echo json_encode($gantt, JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
