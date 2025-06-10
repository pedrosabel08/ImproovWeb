<?php
include '../conexao.php';
$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id']);
$arquivo = basename($data['nomeArquivo']); // proteção contra path traversal

// Deleta o registro
$stmt = $conn->prepare("DELETE FROM review_uploads WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Remove o arquivo físico (se existir)
$caminho = "../uploads/imagens/" . $arquivo;
if (file_exists($caminho)) {
    unlink($caminho);
}

echo json_encode(["success" => true]);
