<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

if (!isset($_GET['obra_id']) || !is_numeric($_GET['obra_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'obra_id inválido']);
    exit;
}
$obra_id = intval($_GET['obra_id']);

try {
    // info
    $sql = "SELECT * FROM fotografico_info WHERE obra_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $obra_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $info = $res->fetch_assoc() ?: null;

    // alturas
    $sqlAlt = "SELECT fa.id, fa.altura, fa.observacoes, fa.created_at
               FROM fotografico_alturas fa
               WHERE fa.obra_id = ?
               ORDER BY fa.created_at DESC, fa.id DESC";
    $stmtAlt = $conn->prepare($sqlAlt);
    $stmtAlt->bind_param('i', $obra_id);
    $stmtAlt->execute();
    $resAlt = $stmtAlt->get_result();
    $alturas = [];
    while ($r = $resAlt->fetch_assoc()) $alturas[] = $r;

    // registros
    $sql2 = "SELECT fr.id, fr.registro_data, fr.observacoes, fr.criado_por, fr.created_at
             FROM fotografico_registro fr
             WHERE fr.obra_id = ?
             ORDER BY fr.registro_data DESC, fr.id DESC";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('i', $obra_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $registros = [];
    while ($r = $res2->fetch_assoc()) $registros[] = $r;

    echo json_encode(['success' => true, 'info' => $info, 'alturas' => $alturas, 'registros' => $registros]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
