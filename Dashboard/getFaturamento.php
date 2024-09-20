<?php
header('Content-Type: application/json');

// Conexão com o banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Capturando os parâmetros da requisição
$anoId = intval($_GET['ano']);
$clienteId = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
$obraId = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

// Construção da query com condições dinâmicas
$sql = "SELECT ico.imagem_nome, 
               f.status_pagamento, 
               f.valor 
        FROM faturamento f 
        INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = f.imagem_id
        INNER JOIN ano a ON a.idano = f.ano
        WHERE f.ano = ?";

// Adicionando condições adicionais dinamicamente
if ($clienteId) {
    $sql .= " AND f.cliente_id = ?";
}
if ($obraId) {
    $sql .= " AND f.obra_id = ?";
}

// Preparando a query
$stmt = $conn->prepare($sql);

// Vinculando os parâmetros de forma dinâmica
if ($clienteId && $obraId) {
    $stmt->bind_param('iii', $anoId, $clienteId, $obraId);
} elseif ($clienteId) {
    $stmt->bind_param('ii', $anoId, $clienteId);
} elseif ($obraId) {
    $stmt->bind_param('ii', $anoId, $obraId);
} else {
    $stmt->bind_param('i', $anoId);
}

// Executando a query
$stmt->execute();
$result = $stmt->get_result();

// Processando os resultados
$funcoes = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcoes[] = $row;
    }
}

// Retornando o resultado em JSON
echo json_encode($funcoes);

// Fechando a conexão
$stmt->close();
$conn->close();
