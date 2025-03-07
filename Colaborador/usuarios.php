<?php

include '../conexao.php';

$sql = "SELECT u.*, c.* FROM usuario u LEFT JOIN colaborador c ON u.idcolaborador = c.idcolaborador";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $usuarios = array();
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
    echo json_encode($usuarios);
} else {
    echo "0 results";
}
$conn->close();
