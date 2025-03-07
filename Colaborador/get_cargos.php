<?php
header('Content-Type: application/json');
include '../conexao.php'; // Conexão com o banco

$sql = "SELECT id, nome FROM cargo";
$result = $conn->query($sql);

$cargos = [];
while ($row = $result->fetch_assoc()) {
    $cargos[] = $row;
}

echo json_encode($cargos);
