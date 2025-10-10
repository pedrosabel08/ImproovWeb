<?php
include '../conexao.php';
header('Content-Type: application/json');

$response = [];

// 🧩 1️⃣ - Funções (status das imagens)
$sqlFuncoes = "SELECT 
        fi.status, 
        COUNT(*) AS quantidade
    FROM funcao_imagem fi
    JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
    JOIN obra o ON ico.obra_id = o.idobra
    WHERE o.status_obra = 0
    GROUP BY fi.status
";

$stmtFuncoes = $conn->prepare($sqlFuncoes);
if (!$stmtFuncoes) {
    die(json_encode(['error' => 'Erro na preparação do primeiro SELECT: ' . $conn->error]));
}
$stmtFuncoes->execute();
$resultFuncoes = $stmtFuncoes->get_result();

$metricasFuncoes = [];
while ($row = $resultFuncoes->fetch_assoc()) {
    $status = $row['status'];
    $quantidade = (int)$row['quantidade'];

    $metricasFuncoes[$status] = $quantidade;
}

$response['metricas_funcoes'] = $metricasFuncoes;


// 🧩 2️⃣ - Obras ativas
$sqlObras = "SELECT COUNT(*) AS total_obras_ativas FROM obra WHERE status_obra = 0";
$stmtObras = $conn->prepare($sqlObras);
if (!$stmtObras) {
    die(json_encode(['error' => 'Erro na preparação do segundo SELECT: ' . $conn->error]));
}
$stmtObras->execute();
$resultObras = $stmtObras->get_result();
$response['obras_ativas'] = (int)$resultObras->fetch_assoc()['total_obras_ativas'];

// 🧩 3️⃣ - Imagens ativas (ligadas a obras ativas)
$sqlImagens = "SELECT COUNT(*) AS total_imagens_ativas
    FROM imagens_cliente_obra i
    JOIN obra o ON o.idobra = i.obra_id
    WHERE o.status_obra = 0 AND i.substatus_id <> 7 
";
$stmtImagens = $conn->prepare($sqlImagens);
if (!$stmtImagens) {
    die(json_encode(['error' => 'Erro na preparação do terceiro SELECT: ' . $conn->error]));
}
$stmtImagens->execute();
$resultImagens = $stmtImagens->get_result();
$response['imagens_ativas'] = (int)$resultImagens->fetch_assoc()['total_imagens_ativas'];

// Fecha as conexões
$stmtFuncoes->close();
$stmtObras->close();
$stmtImagens->close();
$conn->close();

// 🧩 Retorna tudo como JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
