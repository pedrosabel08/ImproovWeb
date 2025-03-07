<?php
header('Content-Type: application/json');

include '../conexao.php'; // Certifique-se de incluir a conexão com o banco

$idusuario = $_GET['idusuario'] ?? 0;

$sql = "SELECT 
        u.*,
        iu.*,
        e.*,
        ec.*,
        CONCAT(UPPER(LEFT(SUBSTRING_INDEX(u.nome_usuario, ' ', 1), 1)), LOWER(SUBSTRING(SUBSTRING_INDEX(u.nome_usuario, ' ', 1), 2))) AS primeiro_nome_formatado
        FROM 
        usuario u
    LEFT JOIN 
        informacoes_usuario iu ON u.idusuario = iu.usuario_id
    LEFT JOIN 
        endereco e ON u.idusuario = e.usuario_id
    LEFT JOIN 
        endereco_cnpj ec ON u.idusuario = ec.usuario_id
    WHERE 
        u.idusuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idusuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

echo json_encode($usuario);
