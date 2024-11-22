<?php
// Inclui o arquivo de conexão
require_once '../conexao.php';

// Verifica se a solicitação é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém os dados enviados no corpo da requisição
    $data = json_decode(file_get_contents('php://input'), true);

    // Verifica se todos os dados necessários foram recebidos
    if (!isset($data['idObra'], $data['valor'], $data['data'], $data['tipo'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Dados incompletos.']);
        exit;
    }

    $idObra = (int)$data['idObra'];
    $valor = (float)$data['valor'];
    $dataOrcamento = $data['data'];
    $tipo = $data['tipo'];

    // Prepara a query para inserir os dados
    $sql = "INSERT INTO orcamentos_obra (obra_id, tipo, valor, data) VALUES (?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        // Vincula os parâmetros
        $stmt->bind_param('isds', $idObra, $tipo, $valor, $dataOrcamento);

        // Executa a query
        if ($stmt->execute()) {
            http_response_code(200); // OK
            echo json_encode(['message' => 'Orçamento salvo com sucesso.']);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Erro ao salvar orçamento: ' . $stmt->error]);
        }

        // Fecha o statement
        $stmt->close();
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Erro ao preparar a query: ' . $conn->error]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Método não permitido.']);
}
