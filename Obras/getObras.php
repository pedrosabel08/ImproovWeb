<?php
// Configurações do banco de dados
include '../conexao.php';

// Consulta para obter os dados da tabela obra
$sql = "SELECT idobra, nomenclatura as nome_obra, status_obra FROM obra ORDER BY idobra DESC";
$result = $conn->query($sql);

$obras = [];

if ($result->num_rows > 0) {
    // Armazena cada linha de resultado como um array associativo
    while ($row = $result->fetch_assoc()) {
        $obras[] = $row;
    }
}

// Converte o array em JSON e exibe
echo json_encode($obras);

// Fecha a conexão
$conn->close();
