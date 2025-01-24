<?php
header('Content-Type: application/json');

include '../conexao.php';

// Decodificar os dados JSON recebidos
$data = json_decode(file_get_contents('php://input'), true);

// Obter os dados enviados
$opcaoCliente = isset($data['opcaoCliente']) ? $data['opcaoCliente'] : null;
$opcaoObra = isset($data['opcaoObra']) ? $data['opcaoObra'] : null;
$arquivo = $data['arquivo'] ?? null;
$data_inicio = $data['data_inicio'] ?? null;
$prazo = $data['prazo'] ?? null;
$imagem = $data['imagem'] ?? null;
$tipo_imagem = $data['tipo'] ?? null;


// Verificar se cliente e obra foram fornecidos
if (!$opcaoCliente || !$opcaoObra) {
    echo json_encode(['status' => 'error', 'message' => 'Cliente e obra são necessários.']);
    exit(); // Para a execução do script
}

// Construir a query
$sql = "INSERT INTO imagens_cliente_obra (cliente_id, obra_id, imagem_nome, recebimento_arquivos, data_inicio, prazo, tipo_imagem) 
        VALUES ('$opcaoCliente', '$opcaoObra', '$imagem', '$arquivo', '$data_inicio', '$prazo', '$tipo_imagem')";

// Executar a query
if ($conn->query($sql) === TRUE) {
    echo json_encode(['status' => 'success', 'message' => 'Imagem cadastrada com sucesso!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar imagem: ' . $conn->error]);
    exit(); // Certifique-se de encerrar aqui também
}

// Fechar a conexão
$conn->close();
