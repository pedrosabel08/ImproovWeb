<?php
require_once __DIR__ . '/../conexao.php';

// Suporta tanto multipart/form-data (com imagem) quanto application/json (sem imagem)
$isJson = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

if ($isJson) {
    $data = json_decode(file_get_contents('php://input'));
    $id    = isset($data->id)    ? intval($data->id)    : 0;
    $texto = isset($data->texto) ? $data->texto         : '';
    $mencionados = isset($data->mencionados) ? (is_array($data->mencionados) ? $data->mencionados : (json_decode($data->mencionados, true) ?: [])) : [];
} else {
    $id    = isset($_POST['id'])    ? intval($_POST['id'])    : 0;
    $texto = isset($_POST['texto']) ? $_POST['texto']         : '';
    $mencionados = json_decode($_POST['mencionados'] ?? '[]', true) ?: [];
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

$imagem_path = null;

// Processa upload de imagem se enviado — sempre envia para o VPS via SFTP
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    require_once __DIR__ . '/upload_comentario_vps.php';
    try {
        $imagem_path = uploadComentarioVps($_FILES['imagem']);
    } catch (RuntimeException $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => 'Falha ao enviar imagem para o VPS: ' . $e->getMessage()]);
        exit;
    }
}

if ($imagem_path !== null) {
    $sql  = 'UPDATE comentarios_imagem SET texto = ?, imagem = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $texto, $imagem_path, $id);
} else {
    $sql  = 'UPDATE comentarios_imagem SET texto = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $texto, $id);
}

$stmt->execute();

if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
    // Sincroniza menções: apaga as antigas e re-insere as novas
    $stmtDelMencoes = $conn->prepare("DELETE FROM mencoes WHERE comentario_id = ?");
    $stmtDelMencoes->bind_param('i', $id);
    $stmtDelMencoes->execute();

    if (!empty($mencionados)) {
        $stmtInsMencoes = $conn->prepare("INSERT INTO mencoes (comentario_id, mencionado_id) VALUES (?, ?)");
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

$stmt->close();
$conn->close();
