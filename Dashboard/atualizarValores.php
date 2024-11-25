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
$sql = "SELECT 
    ROUND(SUM(fi.valor)) AS total_producao, 
    (SELECT COUNT(DISTINCT idobra) FROM obra WHERE status_obra = 0) AS obras_ativas,
    ROUND((SELECT SUM(cc.valor) FROM controle_comercial cc)) AS total_orcamento
FROM 
    funcao_imagem fi
JOIN 
    imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN 
    obra o ON o.idobra = ico.obra_id";

// Executar a query
$result = $conn->query($sql);

$data = array();

// Verificando se há resultados
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Não formate os valores no PHP
        $data[] = array(
            'total_producao' => (float) $row['total_producao'],  // Convertendo para número
            'obras_ativas' => $row['obras_ativas'],
            'total_orcamento' => (float) $row['total_orcamento'] // Convertendo para número
        );
    }
} else {
    echo json_encode(array('message' => 'Nenhum dado encontrado'));
    exit;
}

// Fechar a conexão
$conn->close();

// Retornando os dados em formato JSON
echo json_encode($data);
