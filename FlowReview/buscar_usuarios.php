<?php
include '../conexao.php';

$query = "SELECT idcolaborador, nome_colaborador FROM colaborador WHERE ativo = 1 ORDER BY nome_colaborador";
$result = $conn->query($query);

$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios);
