<?php

include '../conexao.php';

$sql = "SELECT 
	u.*,
    c.*,
	GROUP_CONCAT(car.nome SEPARATOR ', ') AS nome_cargo
FROM 
    usuario u
LEFT JOIN colaborador c ON u.idcolaborador = c.idcolaborador
LEFT JOIN usuario_cargo uc ON u.idusuario = uc.usuario_id
LEFT JOIN cargo car ON car.id = uc.cargo_id
GROUP BY u.idusuario";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $usuarios = array();
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
    echo json_encode($usuarios);
} else {
    echo json_encode([]);
}
$conn->close();
