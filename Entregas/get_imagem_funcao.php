<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

if (!isset($_GET['imagem_id']) || !is_numeric($_GET['imagem_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'imagem_id obrigatório']);
    exit;
}

$imagem_id = intval($_GET['imagem_id']);

try {
    $sql = "SELECT fi.idfuncao_imagem, fi.funcao_id, f.nome_funcao, fi.status, fi.colaborador_id, c.nome_colaborador, fi.prazo
            FROM funcao_imagem fi
            LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao
            LEFT JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
            WHERE fi.imagem_id = ?
            ORDER BY fi.idfuncao_imagem DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $imagem_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        // normalize empty strings to null for dates
        if (isset($row['prazo']) && ($row['prazo'] === '' || $row['prazo'] === '0000-00-00')) $row['prazo'] = null;
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'data' => null]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
