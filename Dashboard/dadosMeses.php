<?php

header('Content-Type: application/json');

// Conexão com o banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');
$conn->set_charset('utf8mb4');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(['error' => 'Falha na conexão: ' . $conn->connect_error]));
}

// Array para manter a ordem dos meses
$meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Inicializa arrays para os resultados
$valores_totais = array_fill_keys($meses, 0);
$valores_diogo = array_fill_keys($meses, 0);
$valores_carol = array_fill_keys($meses, 0);

// Query para todos os meses
$sql_total = "SELECT mes, SUM(valor) AS total_valor 
              FROM controle_comercial 
              GROUP BY mes 
              ORDER BY FIELD(mes, '" . implode("', '", $meses) . "')";
$result_total = $conn->query($sql_total);
if ($result_total->num_rows > 0) {
    while ($row = $result_total->fetch_assoc()) {
        $valores_totais[$row['mes']] = $row['total_valor'];
    }
}

// Query para Diogo
$sql_diogo = "SELECT mes, SUM(valor) AS total_valor 
              FROM controle_comercial 
              WHERE resp = 'Diogo' 
              GROUP BY mes 
              ORDER BY FIELD(mes, '" . implode("', '", $meses) . "')";
$result_diogo = $conn->query($sql_diogo);
if ($result_diogo->num_rows > 0) {
    while ($row = $result_diogo->fetch_assoc()) {
        $valores_diogo[$row['mes']] = $row['total_valor'];
    }
}

// Query para Carol
$sql_carol = "SELECT mes, SUM(valor) AS total_valor 
              FROM controle_comercial 
              WHERE resp = 'Carol' 
              GROUP BY mes 
              ORDER BY FIELD(mes, '" . implode("', '", $meses) . "')";
$result_carol = $conn->query($sql_carol);
if ($result_carol->num_rows > 0) {
    while ($row = $result_carol->fetch_assoc()) {
        $valores_carol[$row['mes']] = $row['total_valor'];
    }
}

// Estrutura os dados para JSON
$response = [];
foreach ($meses as $mes) {
    $response[] = [
        'mes' => $mes,
        'valor_total' => $valores_totais[$mes],
        'valor_diogo' => $valores_diogo[$mes],
        'valor_carol' => $valores_carol[$mes]
    ];
}

// Retorna o JSON
echo json_encode($response);

// Fechar a conexão
$conn->close();
