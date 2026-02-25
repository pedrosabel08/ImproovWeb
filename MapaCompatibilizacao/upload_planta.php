<?php
/**
 * upload_planta.php
 * Faz upload de uma nova versão de planta para uma obra.
 * Inativa a versão anterior (ativa=0) e cria um novo registro ativo.
 *
 * POST params:
 *   obra_id  (int, obrigatório)
 *   planta   (file, PNG/JPG/JPEG, obrigatório)
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$nivelAcesso = (int) ($_SESSION['nivel_acesso'] ?? 0);
if (!in_array($nivelAcesso, [1, 2])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sem permissão para enviar plantas.']);
    exit();
}

// --- Validações ---
$obraId = isset($_POST['obra_id']) ? (int) $_POST['obra_id'] : 0;
$criadoPor = (int) ($_SESSION['idcolaborador'] ?? 0);

if ($obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'obra_id inválido.']);
    exit();
}

if (!isset($_FILES['planta']) || $_FILES['planta']['error'] !== UPLOAD_ERR_OK) {
    $uploadErro = $_FILES['planta']['error'] ?? 'ausente';
    echo json_encode(['sucesso' => false, 'erro' => "Erro no upload do arquivo (código: {$uploadErro})."]);
    exit();
}

$allowedMimes = ['image/png', 'image/jpeg', 'image/jpg'];
$fileMime = mime_content_type($_FILES['planta']['tmp_name']);
if (!in_array($fileMime, $allowedMimes)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Formato inválido. Use PNG ou JPG.']);
    exit();
}

// --- Conexão ---
$conn = conectarBanco();

// --- Calcular próxima versão ---
$stmtVer = $conn->prepare(
    "SELECT COALESCE(MAX(versao), 0) + 1 AS proxima_versao
     FROM planta_compatibilizacao
     WHERE obra_id = ?"
);
$stmtVer->bind_param('i', $obraId);
$stmtVer->execute();
$versao = (int) $stmtVer->get_result()->fetch_assoc()['proxima_versao'];
$stmtVer->close();

// --- Inativar versões anteriores ---
$stmtInativa = $conn->prepare(
    "UPDATE planta_compatibilizacao SET ativa = 0 WHERE obra_id = ? AND ativa = 1"
);
$stmtInativa->bind_param('i', $obraId);
$stmtInativa->execute();
$stmtInativa->close();

// --- Salvar arquivo ---
$uploadDir = __DIR__ . "/../uploads/plantas/{$obraId}/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = strtolower(pathinfo($_FILES['planta']['name'], PATHINFO_EXTENSION));
$filename = "planta_v{$versao}_" . time() . ".{$ext}";
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['planta']['tmp_name'], $destPath)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha ao mover o arquivo para o servidor.']);
    exit();
}

$relativePath = "uploads/plantas/{$obraId}/{$filename}";

// --- Inserir novo registro ---
$stmtInsert = $conn->prepare(
    "INSERT INTO planta_compatibilizacao (obra_id, versao, imagem_path, ativa, criado_por)
     VALUES (?, ?, ?, 1, ?)"
);
$stmtInsert->bind_param('iisi', $obraId, $versao, $relativePath, $criadoPor);

if (!$stmtInsert->execute()) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar no banco: ' . $stmtInsert->error]);
    $stmtInsert->close();
    $conn->close();
    exit();
}

$plantaId = (int) $conn->insert_id;
$stmtInsert->close();
$conn->close();

echo json_encode([
    'sucesso' => true,
    'planta_id' => $plantaId,
    'versao' => $versao,
    'imagem_url' => $relativePath,
]);
