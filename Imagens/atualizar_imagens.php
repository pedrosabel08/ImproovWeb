<?php
header('Content-Type: application/json');

include '../conexao.php';

// Captura os valores do POST
$id = $_POST['idimagens_cliente_obra'];
$imagem_nome = $_POST['imagem_nome'];
$recebimento_arquivos = $_POST['recebimento_arquivos'];
$data_inicio = $_POST['data_inicio'];
$prazo = $_POST['prazo'];
$tipo_imagem = $_POST['tipo_imagem'];
$antecipada = $_POST['antecipada'];

// Monta a query de update
$sql = "UPDATE imagens_cliente_obra SET 
            imagem_nome = ?, 
            recebimento_arquivos = ?, 
            data_inicio = ?, 
            prazo = ?, 
            tipo_imagem = ?,
            antecipada = ?
        WHERE idimagens_cliente_obra = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssssii', $imagem_nome, $recebimento_arquivos, $data_inicio, $prazo, $tipo_imagem, $antecipada, $id);

// Executa o update e verifica o sucesso
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar os dados.']);
}

// Fecha a conexão
$stmt->close();
$conn->close();
