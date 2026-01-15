<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_common.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'ID inválido']);
    exit();
}

$sql = "SELECT d.usuario_id, u.nome_usuario, u.ativo, d.visto_em, d.confirmado_em, d.dispensado_em
        FROM notificacoes_destinatarios d
        JOIN usuario u ON u.idusuario = d.usuario_id
    WHERE d.notificacao_id = ? AND u.ativo = 1
        ORDER BY u.nome_usuario ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Falha ao preparar consulta']);
    exit();
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($row = $res->fetch_assoc())) {
    $rows[] = $row;
}
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
exit();
