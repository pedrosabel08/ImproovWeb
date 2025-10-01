<?php
include '../conexao.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$obra_id = intval($data['obra_id']);
$tipo_arquivo = $data['tipo_arquivo'];
$tiposImagem = $data['tipo_imagem'];
$imagem_id = isset($data['imagem_id']) ? intval($data['imagem_id']) : null;

$existe = false;

foreach ($tiposImagem as $nomeTipo) {
    $nomeTipo = $conn->real_escape_string($nomeTipo);
    $res = $conn->query("SELECT id_tipo_imagem FROM tipo_imagem WHERE nome='$nomeTipo'");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $tipo_id = $row['id_tipo_imagem'];
        $sql = "SELECT idarquivo FROM arquivos WHERE obra_id=$obra_id AND tipo_imagem_id=$tipo_id AND tipo='$tipo_arquivo' AND status='atualizado'";
        if ($imagem_id !== null) {
            $sql .= " AND imagem_id=$imagem_id";
        }
        $check = $conn->query($sql);
        if ($check->num_rows > 0) {
            $existe = true;
            break;
        }
    }
}
$conn->close();
echo json_encode(['existe' => $existe]);
