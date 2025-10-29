<?php
include '../conexao.php'; // conexão com o banco

header('Content-Type: application/json');

// Filtros aceitos via query string
$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;
$filtro_tipo = isset($_GET['tipo']) ? $conn->real_escape_string($_GET['tipo']) : null;
$filtro_tipo_arquivo = isset($_GET['tipo_arquivo']) ? $conn->real_escape_string($_GET['tipo_arquivo']) : null;
// opcional: filtro por status (campo `status` na tabela arquivos)
$filtro_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : null;

$sql = "SELECT a.*, o.nomenclatura as projeto, ti.nome as tipo_imagem
        FROM arquivos a
        LEFT JOIN obra o ON a.obra_id = o.idobra
        LEFT JOIN tipo_imagem ti ON a.tipo_imagem_id = ti.id_tipo_imagem
        WHERE 1";

if ($obra_id) $sql .= " AND a.obra_id = $obra_id";
if ($filtro_tipo) $sql .= " AND ti.nome = '$filtro_tipo'";
if ($filtro_tipo_arquivo) $sql .= " AND a.tipo = '$filtro_tipo_arquivo'";
if ($filtro_status) $sql .= " AND a.status = '$filtro_status'";

$sql .= " ORDER BY a.recebido_em DESC";

$result = $conn->query($sql);

$arquivos = [];
while ($row = $result->fetch_assoc()) {
    $arquivos[] = $row;
}

echo json_encode($arquivos);
$conn->close();
