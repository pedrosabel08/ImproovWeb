<?php
include '../conexao.php';
session_start();

// Agora os dados vêm de $_POST e $_FILES
$ap_imagem_id = $_POST['ap_imagem_id'];
$x = $_POST['x'];
$y = $_POST['y'];
$texto = $_POST['texto'];
$responsavel = $_SESSION['idcolaborador'];
// tipo do comentário: 'ponto' ou 'livre' (default 'ponto')
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'ponto';

// Processar imagem (se enviada)
$imagem_path = null;
if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    // filesystem target (relative to this script)
    $uploadDir = __DIR__ . '/../uploads/comentarios/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true); // Cria a pasta se não existir
    }

    $filename = uniqid('coment_', true) . '.' . pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
    $targetPath = $uploadDir . $filename;

    $moved = move_uploaded_file($_FILES['imagem']['tmp_name'], $targetPath);
    if (!$moved) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'erro' => 'Falha ao mover arquivo para destino.', 'target' => $targetPath]);
        exit;
    }

    // Ensure readable permissions (webserver should be able to serve it)
    @chmod($targetPath, 0644);

    // Decide public base URL depending on environment (production vs local dev).
    // If running on the production host, use the real domain; otherwise build from request host.
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'improov.com.br') !== false) {
        $public_base = 'https://improov.com.br/flow/ImproovWeb';
    } else {
        // local dev: construct base from current host. Adjust path if your local vhost differs.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // assume local docroot maps to /ImproovWeb
        $public_base = $scheme . '://' . $host . '/ImproovWeb';
    }

    $imagem_path = $public_base . '/uploads/comentarios/' . $filename;
    $saved_ok = file_exists($targetPath);
}

// 1. Buscar o número do comentário
$stmt = $conn->prepare('SELECT IFNULL(MAX(numero_comentario), 0) + 1 AS proximo_numero FROM comentarios_imagem WHERE ap_imagem_id = ?');
$stmt->bind_param('i', $ap_imagem_id);
$stmt->execute();
$result = $stmt->get_result();
$numero_comentario = $result->fetch_assoc()['proximo_numero'];

// 2. Inserir comentário
$stmt = $conn->prepare(
    "INSERT INTO comentarios_imagem (ap_imagem_id, numero_comentario, x, y, texto, imagem, tipo, responsavel_id, data)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
);
$stmt->bind_param('iiddsssi', $ap_imagem_id, $numero_comentario, $x, $y, $texto, $imagem_path, $tipo, $responsavel);
$stmt->execute();

$comentario_id = $conn->insert_id; // ID do comentário recém-inserido

// 3. Procurar por menções (@Nome)
// Recebe os IDs mencionados do front-end
$mencionados = json_decode($_POST['mencionados'], true);

if (!empty($mencionados)) {
    $stmtInsert = $conn->prepare("INSERT INTO mencoes (comentario_id, mencionado_id) VALUES (?, ?)");

    foreach ($mencionados as $mencionado_id) {
        $stmtInsert->bind_param("ii", $comentario_id, $mencionado_id);
        $stmtInsert->execute();
    }

    $stmtInsert->close();
}

$response = [
    'sucesso' => true,
    'comentario_id' => $comentario_id,
    'mencionados' => $mencionados,
    'imagem' => $imagem_path,
    'fs_path' => isset($targetPath) ? $targetPath : null,
    'saved_ok' => isset($saved_ok) ? $saved_ok : false,
];

header('Content-Type: application/json');
echo json_encode($response);
