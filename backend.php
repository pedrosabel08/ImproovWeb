<?php
header('Content-Type: application/json');
// Permite acesso de qualquer origem (para testes)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Permite requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
include 'conexao.php';

// Recebe dados do WhatsApp
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['nome'])) {
    $nome = $conn->real_escape_string($data['nome']);

    // Consulta obra associada ao contato
    $sql = "SELECT obra_id FROM contatos WHERE nome = '$nome'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id_obra = $row['obra_id'];

        // Insere tarefa/evento
        $descricao = "Nova tarefa gerada via WhatsApp";
        $sqlInsert = "INSERT INTO tarefas (obra_id, descricao) VALUES ('$id_obra', '$descricao')";
        if ($conn->query($sqlInsert)) {
            echo json_encode(['status' => 'ok', 'mensagem' => "Evento registrado para $nome"]);
        } else {
            echo json_encode(['status' => 'erro', 'mensagem' => $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'erro', 'mensagem' => "Contato não encontrado"]);
    }
} else {
    echo json_encode(['status' => 'erro', 'mensagem' => "Nome não fornecido"]);
}

$conn->close();
