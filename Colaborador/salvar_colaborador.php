<?php

include '../conexao.php';

$idusuario = $_POST['idusuario'];
$cargos = $_POST['cargos'];  // Isso é um array

// Insere os novos cargos
$sql = "INSERT INTO usuario_cargo (usuario_id, cargo_id) VALUES (?, ?)";
$stmt = $conn->prepare($sql);

foreach ($cargos as $idcargo) {
    $stmt->bind_param("ii", $idusuario, $idcargo);
    $stmt->execute();
}

$conn->close();
echo "Colaborador atualizado com sucesso!";
