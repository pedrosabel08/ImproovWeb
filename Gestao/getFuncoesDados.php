<?php
include '../conexao.php';
header('Content-Type: application/json; charset=utf-8');

$response = [];

// 1️⃣ Métricas por função
$sqlMetricas = "WITH metricas AS (
    SELECT 
        f.nome_funcao AS funcao_nome,
        SUM(CASE WHEN fi.status IN ('Em andamento', 'Em aprovação') THEN 1 ELSE 0 END) AS em_andamento,
        SUM(CASE WHEN fi.status = 'Não iniciado' THEN 1 ELSE 0 END) AS nao_iniciado
    FROM funcao_imagem fi
    JOIN funcao f ON fi.funcao_id = f.idfuncao
    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = fi.imagem_id
    JOIN obra o ON o.idobra = i.obra_id 
    WHERE fi.colaborador_id <> 15 AND o.status_obra = 0
    GROUP BY f.nome_funcao
)
SELECT *, (em_andamento + nao_iniciado) AS total FROM metricas
";

$stmt = $conn->prepare($sqlMetricas);
$stmt->execute();
$result = $stmt->get_result();

$metricasFuncoes = [];
while ($row = $result->fetch_assoc()) {
    $funcao = $row['funcao_nome'];
    $metricasFuncoes[$funcao] = [
        'em_andamento' => (int)$row['em_andamento'],
        'nao_iniciado' => (int)$row['nao_iniciado'],
        'total' => (int)$row['total']
    ];
}

$response['metricas_funcoes'] = $metricasFuncoes;
$stmt->close();

// 2️⃣ Colaboradores por função
$sqlColabs = "
SELECT f.nome_funcao, c.nome_colaborador
FROM funcao_colaborador fc
JOIN funcao f ON f.idfuncao = fc.funcao_id
JOIN colaborador c ON c.idcolaborador = fc.colaborador_id
GROUP BY c.idcolaborador, f.idfuncao
ORDER BY f.nome_funcao
";

$stmt = $conn->prepare($sqlColabs);
$stmt->execute();
$result = $stmt->get_result();

$colaboradores = [];
while ($row = $result->fetch_assoc()) {
    $funcao = $row['nome_funcao'];
    $colaborador = $row['nome_colaborador'];

    if (!isset($colaboradores[$funcao])) {
        $colaboradores[$funcao] = [];
    }
    $colaboradores[$funcao][] = $colaborador;
}

$response['colaboradores_funcoes'] = $colaboradores;

$stmt->close();
$conn->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
