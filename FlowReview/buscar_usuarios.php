<?php
include '../conexao.php';

$query = "SELECT idcolaborador, nome_colaborador FROM colaborador";
$result = $conn->query($query);

$usuarios = [];

while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios);
