<?php
include '../conexao.php';

// Primeiro SELECT: funcao_imagem
$query1 = "SELECT MONTH(prazo) AS mes, SUM(valor) AS total_funcao_imagem FROM funcao_imagem GROUP BY MONTH(prazo)";
$result1 = $conn->query($query1);
$data1 = [];
while ($row = $result1->fetch_assoc()) {
    $data1[] = $row;
}

// Segundo SELECT: controle_comercial
$query2 = "SELECT mes, SUM(valor) AS total_controle_comercial FROM controle_comercial GROUP BY mes";
$result2 = $conn->query($query2);
$data2 = [];
while ($row = $result2->fetch_assoc()) {
    $data2[] = $row;
}

// Combina os resultados em um array
$response = [
    'funcao_imagem' => $data1,
    'controle_comercial' => $data2,
];

// Retorna o JSON combinado
echo json_encode($response);
