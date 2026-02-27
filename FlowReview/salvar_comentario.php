<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

function tableHasColumn(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0);
}

function ensurePdfCommentColumns(mysqli $conn): void {
    // comentários em PDF: amarrados a arquivo_log
    if (!tableHasColumn($conn, 'comentarios_imagem', 'arquivo_log_id')) {
        @ $conn->query("ALTER TABLE comentarios_imagem ADD COLUMN arquivo_log_id INT NULL");
    }
    if (!tableHasColumn($conn, 'comentarios_imagem', 'pagina')) {
        @ $conn->query("ALTER TABLE comentarios_imagem ADD COLUMN pagina INT NULL");
    }
}

function ensureShapeColumns(mysqli $conn): void {
    // coordenadas finais para formas geométricas (rect/circle)
    if (!tableHasColumn($conn, 'comentarios_imagem', 'x2')) {
        @ $conn->query("ALTER TABLE comentarios_imagem ADD COLUMN x2 DOUBLE NULL");
    }
    if (!tableHasColumn($conn, 'comentarios_imagem', 'y2')) {
        @ $conn->query("ALTER TABLE comentarios_imagem ADD COLUMN y2 DOUBLE NULL");
    }
    // caminho SVG para freehand
    if (!tableHasColumn($conn, 'comentarios_imagem', 'path_data')) {
        @ $conn->query("ALTER TABLE comentarios_imagem ADD COLUMN path_data LONGTEXT NULL");
    }
    // cor do desenho
    if (!tableHasColumn($conn, 'comentarios_imagem', 'cor')) {
        @ $conn->query("ALTER TABLE comentarios_imagem ADD COLUMN cor VARCHAR(20) NULL");
    }
}

// Agora os dados vêm de $_POST e $_FILES
$ap_imagem_id = (isset($_POST['ap_imagem_id']) && $_POST['ap_imagem_id'] !== '') ? intval($_POST['ap_imagem_id']) : null;
$arquivo_log_id = (isset($_POST['arquivo_log_id']) && $_POST['arquivo_log_id'] !== '') ? intval($_POST['arquivo_log_id']) : null;
$pagina = (isset($_POST['pagina']) && $_POST['pagina'] !== '') ? intval($_POST['pagina']) : 1;

$x = isset($_POST['x']) ? floatval($_POST['x']) : 0.0;
$y = isset($_POST['y']) ? floatval($_POST['y']) : 0.0;
$x2 = (isset($_POST['x2']) && $_POST['x2'] !== '') ? floatval($_POST['x2']) : null;
$y2 = (isset($_POST['y2']) && $_POST['y2'] !== '') ? floatval($_POST['y2']) : null;
$texto = $_POST['texto'] ?? '';
$responsavel = $_SESSION['idcolaborador'];
// tipo do comentário: 'ponto', 'rect', 'circle' ou 'freehand' (default 'ponto')
$tipo = isset($_POST['tipo']) && in_array($_POST['tipo'], ['ponto','rect','circle','freehand']) ? $_POST['tipo'] : 'ponto';
$path_data = (isset($_POST['path_data']) && $_POST['path_data'] !== '') ? $_POST['path_data'] : null;
$cor = (isset($_POST['cor']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cor'])) ? $_POST['cor'] : '#f59e0b';

// Garante colunas de forma geométrica
ensureShapeColumns($conn);

if (!$ap_imagem_id && !$arquivo_log_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'erro' => 'Informe ap_imagem_id (JPG) ou arquivo_log_id (PDF).']);
    exit;
}

if ($arquivo_log_id) {
    ensurePdfCommentColumns($conn);

    if (!tableHasColumn($conn, 'comentarios_imagem', 'arquivo_log_id') || !tableHasColumn($conn, 'comentarios_imagem', 'pagina')) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Banco sem colunas para comentário em PDF (arquivo_log_id/pagina). Rode um ALTER TABLE em comentarios_imagem.'
        ]);
        exit;
    }
}

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
if ($arquivo_log_id) {
    // numeração por documento PDF
    $stmt = $conn->prepare('SELECT IFNULL(MAX(numero_comentario), 0) + 1 AS proximo_numero FROM comentarios_imagem WHERE arquivo_log_id = ?');
    $stmt->bind_param('i', $arquivo_log_id);
} else {
    $stmt = $conn->prepare('SELECT IFNULL(MAX(numero_comentario), 0) + 1 AS proximo_numero FROM comentarios_imagem WHERE ap_imagem_id = ?');
    $stmt->bind_param('i', $ap_imagem_id);
}
$stmt->execute();
$result = $stmt->get_result();
$numero_comentario = $result->fetch_assoc()['proximo_numero'];

// 2. Inserir comentário
if ($arquivo_log_id) {
    $stmt = $conn->prepare(
        "INSERT INTO comentarios_imagem (arquivo_log_id, pagina, numero_comentario, x, y, x2, y2, texto, imagem, tipo, responsavel_id, path_data, cor, data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('iiiddddsssiss', $arquivo_log_id, $pagina, $numero_comentario, $x, $y, $x2, $y2, $texto, $imagem_path, $tipo, $responsavel, $path_data, $cor);
    $stmt->execute();
} else {
    $stmt = $conn->prepare(
        "INSERT INTO comentarios_imagem (ap_imagem_id, numero_comentario, x, y, x2, y2, texto, imagem, tipo, responsavel_id, path_data, cor, data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('iiddddsssiss', $ap_imagem_id, $numero_comentario, $x, $y, $x2, $y2, $texto, $imagem_path, $tipo, $responsavel, $path_data, $cor);
    $stmt->execute();
}

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
