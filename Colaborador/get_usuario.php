<?php
header('Content-Type: application/json');

include '../conexao.php'; // Certifique-se de incluir a conexão com o banco

$idusuario = $_GET['idusuario'] ?? 0;

// Consulta para pegar as informações do usuário
$sql_usuario = "SELECT 
                    u.*,
                    c.nome_colaborador,
                    CONCAT(UPPER(LEFT(SUBSTRING_INDEX(u.nome_usuario, ' ', 1), 1)), LOWER(SUBSTRING(SUBSTRING_INDEX(u.nome_usuario, ' ', 1), 2))) AS primeiro_nome_formatado
                FROM 
                    usuario u
                LEFT JOIN 
                    colaborador c ON u.idcolaborador = c.idcolaborador
                WHERE 
                    u.idusuario = ? 
                GROUP BY
                    u.idusuario";

$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("i", $idusuario);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();

// Consulta para pegar os cargos do usuário
$sql_cargos = "SELECT c.id AS cargo_id, c.nome AS cargo_nome 
               FROM cargo c
               JOIN usuario_cargo uc ON c.id = uc.cargo_id
               WHERE uc.usuario_id = ?";

$stmt_cargos = $conn->prepare($sql_cargos);
$stmt_cargos->bind_param("i", $idusuario);
$stmt_cargos->execute();
$result_cargos = $stmt_cargos->get_result();

// Cria um array para armazenar os cargos
$cargos = [];
while ($row = $result_cargos->fetch_assoc()) {
    $cargos[] = $row['cargo_id']; // Armazena o ID do cargo
}

$response = [
    'usuario' => $usuario,
    'cargos' => $cargos
];

echo json_encode($response);
