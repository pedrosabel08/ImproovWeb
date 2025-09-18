<?php
require 'conexao.php';

$idFuncaoImagem = $_POST['idfuncao_imagem'];
$observacao = $_POST['observacao'] ?? '';

$sql = "UPDATE funcao_imagem 
        SET status = 'HOLD', observacao = ? 
        WHERE idfuncao_imagem = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $observacao, $idFuncaoImagem);
$stmt->execute();

echo json_encode(["success" => true]);
