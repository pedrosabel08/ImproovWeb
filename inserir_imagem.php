<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Falha na conexão com o banco de dados']);
    exit();
}

// Decodificar os dados JSON recebidos
$data = json_decode(file_get_contents('php://input'), true);

// Obter os dados enviados
$opcaoCliente = isset($data['opcaoCliente']) ? $data['opcaoCliente'] : null;
$opcaoObra = isset($data['opcaoObra']) ? $data['opcaoObra'] : null;
$arquivo = $data['arquivo'];
$data_inicio = $data['data_inicio'];
$prazo = $data['prazo'];
$imagem = $data['imagem'];

// Verificar se ambos cliente e obra foram fornecidos
if ($opcaoCliente && $opcaoObra) {
    $sql = "INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo) 
            VALUES ('$opcaoCliente', '$opcaoObra', '$imagem', '$arquivo', '$data_inicio', '$prazo')";
} else {
    echo json_encode(['status' => 'error', 'message' => 'Cliente e obra são necessários']);
    exit();
}

// Executar a query
if ($conn->query($sql) === TRUE) {
    echo json_encode(['status' => 'success', 'message' => 'Imagem cadastrada com sucesso!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar imagem: ' . $conn->error]);
}

// Fechar a conexão
$conn->close();