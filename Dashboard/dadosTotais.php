<?php
// Incluir o arquivo de conexão
include '../conexao.php';

// Criação da conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Checando a conexão
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query SQL para buscar os dados
$sql = "SELECT o.nome_obra, o.idobra, o.nomenclatura, SUM(fi.valor) AS total_custo_obra 
        FROM funcao_imagem fi 
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra 
        JOIN obra o ON o.idobra = ico.obra_id
        GROUP BY ico.obra_id";

// Executar a query
$result = $conn->query($sql);

$data = array();

// Verificando se há resultados
if ($result->num_rows > 0) {
    // Loop para buscar os dados e armazenar em um array
    while ($row = $result->fetch_assoc()) {
        $data[] = array('idobra' => $row['idobra'],'nomenclatura' => $row['nomenclatura'],'nome_obra' => $row['nome_obra'], 'total_custo_obra' => $row['total_custo_obra']);
    }
} else {
    // Se não houver resultados, retorna uma mensagem
    echo json_encode(array('message' => 'Nenhum dado encontrado'));
    exit;  // Finaliza o script para não retornar nada após o JSON
}

// Fechar a conexão
$conn->close();

// Retornando os dados em formato JSON
echo json_encode($data);
