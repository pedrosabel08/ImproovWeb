<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

$isJson = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

if ($isJson) {
    $data  = json_decode(file_get_contents('php://input'));
    $id    = isset($data->id)    ? intval($data->id)    : 0;
    $texto = isset($data->texto) ? $data->texto          : '';
    $mencionados = isset($data->mencionados) ? (is_array($data->mencionados) ? $data->mencionados : (json_decode($data->mencionados, true) ?: [])) : [];
} else {
    $id    = isset($_POST['id'])    ? intval($_POST['id'])    : 0;
    $texto = isset($_POST['texto']) ? $_POST['texto']          : '';
    $mencionados = json_decode($_POST['mencionados'] ?? '[]', true) ?: [];
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

$responsavel = $_SESSION['idcolaborador'];

$imagem_path = null;
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    require_once __DIR__ . '/upload_comentario_vps.php';
    try {
        $imagem_path = uploadComentarioVps($_FILES['imagem']);
    } catch (RuntimeException $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => 'Falha ao enviar imagem: ' . $e->getMessage()]);
        exit;
    }
}

if ($imagem_path !== null) {
    $stmt = $conn->prepare('UPDATE respostas_comentario SET texto = ?, imagem = ? WHERE id = ? AND responsavel = ?');
    $stmt->bind_param('ssii', $texto, $imagem_path, $id, $responsavel);
} else {
    $stmt = $conn->prepare('UPDATE respostas_comentario SET texto = ? WHERE id = ? AND responsavel = ?');
    $stmt->bind_param('sii', $texto, $id, $responsavel);
}

$stmt->execute();

if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
    // Sincroniza menções da resposta: apaga antigas e re-insere novas
    $stmtDelMencoes = $conn->prepare("DELETE FROM mencoes WHERE resposta_id = ?");
    $stmtDelMencoes->bind_param('i', $id);
    $stmtDelMencoes->execute();

    if (!empty($mencionados)) {
        $stmtInsMencoes = $conn->prepare("INSERT INTO mencoes (resposta_id, mencionado_id) VALUES (?, ?)");
        foreach ($mencionados as $mid) {
            $mid = intval($mid);
            if ($mid > 0) {
                $stmtInsMencoes->bind_param('ii', $id, $mid);
                $stmtInsMencoes->execute();
            }
        }
    }

    echo json_encode(['sucesso' => true, 'imagem' => $imagem_path]);
} else {
    echo json_encode(['sucesso' => false]);
}
