<?php
include '../conexao.php'; // conexão com o banco

header('Content-Type: application/json');

$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;
$filtro_tipo = $_GET['tipo'] ?? null;

$sql = "SELECT a.*, o.nomenclatura as projeto, ti.nome as tipo_imagem
        FROM arquivos a
        LEFT JOIN obra o ON a.obra_id = o.idobra
        LEFT JOIN tipo_imagem ti ON a.tipo_imagem_id = ti.id_tipo_imagem
        WHERE 1";

if ($obra_id) $sql .= " AND a.obra_id = $obra_id";
if ($filtro_tipo) $sql .= " AND ti.nome = '$filtro_tipo'";

$sql .= " ORDER BY a.recebido_em DESC";

$result = $conn->query($sql);

$arquivos = [];
while ($row = $result->fetch_assoc()) {
    $arquivos[] = $row;
}

echo json_encode($arquivos);
$conn->close();
