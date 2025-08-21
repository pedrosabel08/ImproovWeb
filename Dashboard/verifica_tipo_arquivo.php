<?php
include '../conexao.php';

$obra_id = 74;
$nome_arquivo = $_POST['nome_arquivo'];

// Determinar tipo do arquivo pelo nome/extensão
$ext = pathinfo($nome_arquivo, PATHINFO_EXTENSION);
$tipoArquivo = strtoupper($ext); // DWG, PDF, 3D...

// Buscar tipos de imagem que ainda possuem esse requisito incompleto
$sql = "SELECT ti.id_tipo_imagem, ti.nome 
        FROM tipo_imagem ti
        JOIN requisito_arquivo ra ON ra.id_tipo_imagem = ti.id_tipo_imagem
        WHERE ti.obra_id = ? 
        AND ra.nome_requisito = ? 
        AND ra.status != 'Completo'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $obra_id, $tipoArquivo);
$stmt->execute();
$result = $stmt->get_result();

$tiposPendentes = [];
while ($row = $result->fetch_assoc()) {
    $tiposPendentes[] = $row;
}

echo json_encode(['tipos_pendentes' => $tiposPendentes]);
