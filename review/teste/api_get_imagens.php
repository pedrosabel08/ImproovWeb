<?php
header('Content-Type: application/json');
require_once '../../conexao.php'; // ajuste o caminho se necessário

$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : 0;
if (!$obra_id) {
    echo json_encode([]);
    exit;
}

// Busca imagens da obra
$sql = "SELECT ai.id, ai.sugerida, ai.motivo_sugerida, hi.imagem
        FROM angulos_imagens ai 
        JOIN historico_aprovacoes_imagens hi ON ai.historico_id = hi.id
        JOIN funcao_imagem fi ON hi.funcao_imagem_id = fi.idfuncao_imagem
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        WHERE ico.obra_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $obra_id);
$stmt->execute();
$res = $stmt->get_result();
$imagens = [];
while ($row = $res->fetch_assoc()) {
    // Busca comentários para cada imagem
    // $comentarios = [];
    // $sql2 = "SELECT texto, autor FROM comentarios_imagem WHERE imagem_id = ? ORDER BY id ASC";
    // $stmt2 = $conn->prepare($sql2);
    // $stmt2->bind_param('i', $row['id']);
    // $stmt2->execute();
    // $res2 = $stmt2->get_result();
    // while ($c = $res2->fetch_assoc()) {
    //     $comentarios[] = $c;
    // }
    // $row['comentarios'] = $comentarios;
    $imagens[] = $row;
}
$stmt->close();
$conn->close();
echo json_encode($imagens);