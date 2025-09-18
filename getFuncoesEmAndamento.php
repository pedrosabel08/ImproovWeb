<?php
require 'conexao.php';

$colaboradorId = $_POST['idcolaborador']; // ou pegue de token/login

$sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, f.nome_funcao, ico.imagem_nome, o.nomenclatura
FROM funcao_imagem fi
JOIN funcao f ON fi.funcao_id = f.idfuncao
JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
JOIN obra o ON ico.obra_id = o.idobra
WHERE fi.colaborador_id = ? AND fi.status = 'Em andamento'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $colaboradorId);
$stmt->execute();
$result = $stmt->get_result();

$funcoes = [];
while ($row = $result->fetch_assoc()) {
    $funcoes[] = $row;
}

echo json_encode($funcoes);
