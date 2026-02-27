<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth_cookie.php';
if (empty($flow_user_id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$historico_imagem_id = isset($_GET['historico_imagem_id']) ? intval($_GET['historico_imagem_id']) : 0;
$entrega_item_id = isset($_GET['entrega_item_id']) ? intval($_GET['entrega_item_id']) : 0;

if ($historico_imagem_id <= 0 && $entrega_item_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit();
}

try {
    $where = [];
    $types = '';
    $params = [];

    if ($historico_imagem_id > 0) {
        $where[] = 'd.historico_imagem_id = ?';
        $types .= 'i';
        $params[] = $historico_imagem_id;
    }
    if ($entrega_item_id > 0) {
        $where[] = 'd.entrega_item_id = ?';
        $types .= 'i';
        $params[] = $entrega_item_id;
    }

    $sql = "SELECT d.id,
                   d.entrega_item_id,
                   d.historico_imagem_id,
                   d.decisao,
                   d.created_at,
                   u.idusuario,
                   COALESCE(c.nome_colaborador, u.nome_slack, CONCAT('Usuário #', u.idusuario)) AS usuario_nome
            FROM imagem_decisoes d
            LEFT JOIN usuario u ON u.idusuario = d.usuario_id
            LEFT JOIN colaborador c ON c.idcolaborador = u.idcolaborador
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$r['id'],
            'entrega_item_id' => (int)$r['entrega_item_id'],
            'historico_imagem_id' => (int)$r['historico_imagem_id'],
            'decisao' => $r['decisao'],
            'created_at' => $r['created_at'],
            'usuario_id' => (int)$r['idusuario'],
            'usuario_nome' => $r['usuario_nome']
        ];
    }

    echo json_encode(['success' => true, 'decisoes' => $rows]);
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
