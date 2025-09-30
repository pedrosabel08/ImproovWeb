<?php
include '../conexao.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$obra_id = intval($data['obra_id'] ?? 0);
$tiposImagem = $data['tipo_imagem'] ?? [];

if (!$obra_id || empty($tiposImagem)) {
    echo json_encode([]);
    exit;
}

// Escapa os nomes para usar no SQL
$tiposEscapados = array_map(function ($tipo) use ($conn) {
    return "'" . $conn->real_escape_string($tipo) . "'";
}, $tiposImagem);

$tiposStr = implode(',', $tiposEscapados);

$sql = "SELECT idimagens_cliente_obra, imagem_nome 
        FROM imagens_cliente_obra 
        WHERE obra_id=$obra_id AND tipo_imagem IN ($tiposStr)";

$res = $conn->query($sql);

$imagens = [];
while ($row = $res->fetch_assoc()) {
    $imagens[] = [
        'id' => $row['idimagens_cliente_obra'],
        'imagem_nome' => $row['imagem_nome']
    ];
}

echo json_encode($imagens);
