<?php
// Inicie a sessão
session_start();

// Verifique se o usuário está autenticado
if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Inclua a conexão com o banco de dados
include '../conexao.php';

// Verifique se a solicitação é POST e contém os dados necessários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leia os dados enviados via JSON
    $data = json_decode(file_get_contents('php://input'), true);
    $idfuncao_imagem = $data['idfuncao_imagem'] ?? null;

    // Verifique se o ID da função foi fornecido
    if ($idfuncao_imagem === null) {
        echo json_encode(['success' => false, 'message' => 'ID da tarefa não fornecido.']);
        exit;
    }

    // Prepare a consulta para atualizar a tarefa
    $stmt = $conn->prepare("UPDATE funcao_imagem SET check_funcao = 1 WHERE idfuncao_imagem = ?");
    if ($stmt) {
        $stmt->bind_param("i", $idfuncao_imagem);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a tarefa.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar a consulta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método de solicitação inválido.']);
}

// Feche a conexão com o banco de dados
$conn->close();
