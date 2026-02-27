<?php
/**
 * upload_planta_pdf.php
 * Recebe o PNG renderizado de uma página de PDF pelo browser (PDF.js)
 * e salva como nova versão de planta para uma imagem específica da obra.
 *
 * POST params:
 *   obra_id    (int, obrigatório)
 *   imagem_id  (int, obrigatório) — idimagens_cliente_obra da Planta Humanizada
 *   pagina_pdf (int, obrigatório) — número da página no PDF original
 *   planta     (file, PNG/JPG, obrigatório) — canvas renderizado pelo PDF.js
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
    echo json_encode(['sucesso' => false, 'erro' => 'Sem permissão.']);
    exit();
}

// --- Parâmetros ---
$obraId     = isset($_POST['obra_id'])    ? (int) $_POST['obra_id']    : 0;
$imagemId   = isset($_POST['imagem_id'])  ? (int) $_POST['imagem_id']  : 0;
$paginaPdf  = isset($_POST['pagina_pdf']) ? (int) $_POST['pagina_pdf'] : 0;
$criadoPor  = (int) ($_SESSION['idcolaborador'] ?? 0);

if ($obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'obra_id inválido.']);
    exit();
}
if ($imagemId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'imagem_id inválido.']);
    exit();
}
if ($paginaPdf <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'pagina_pdf inválida.']);
    exit();
}

// --- Validar arquivo ---
if (!isset($_FILES['planta']) || $_FILES['planta']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['planta']['error'] ?? 'ausente';
    echo json_encode(['sucesso' => false, 'erro' => "Erro no upload (código: {$code})."]);
    exit();
}

$allowedMimes = ['image/png', 'image/jpeg', 'image/jpg'];
$fileMime = mime_content_type($_FILES['planta']['tmp_name']);
if (!in_array($fileMime, $allowedMimes)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Formato inválido. Use PNG ou JPG.']);
    exit();
}

$conn = conectarBanco();

// --- Verificar que imagem pertence à obra ---
$chk = $conn->prepare(
    "SELECT idimagens_cliente_obra FROM imagens_cliente_obra
     WHERE idimagens_cliente_obra = ? AND obra_id = ? LIMIT 1"
);
$chk->bind_param('ii', $imagemId, $obraId);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    $chk->close(); $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Imagem não pertence à obra.']);
    exit();
}
$chk->close();

// --- Calcular próxima versão para este (obra_id, imagem_id) ---
$stmtVer = $conn->prepare(
    "SELECT COALESCE(MAX(versao), 0) + 1 AS proxima
     FROM planta_compatibilizacao
     WHERE obra_id = ? AND imagem_id = ?"
);
$stmtVer->bind_param('ii', $obraId, $imagemId);
$stmtVer->execute();
$versao = (int) $stmtVer->get_result()->fetch_assoc()['proxima'];
$stmtVer->close();

// --- Inativar versão anterior desta imagem ---
$upd = $conn->prepare(
    "UPDATE planta_compatibilizacao SET ativa = 0
     WHERE obra_id = ? AND imagem_id = ? AND ativa = 1"
);
$upd->bind_param('ii', $obraId, $imagemId);
$upd->execute();
$upd->close();

// --- Salvar arquivo ---
$uploadDir = __DIR__ . "/../uploads/plantas/{$obraId}/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext      = $fileMime === 'image/png' ? 'png' : 'jpg';
$filename = "planta_img{$imagemId}_v{$versao}_" . time() . ".{$ext}";
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['planta']['tmp_name'], $destPath)) {
    $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Falha ao salvar arquivo no servidor.']);
    exit();
}

$relativePath = "uploads/plantas/{$obraId}/{$filename}";

// --- Inserir registro ---
$ins = $conn->prepare(
    "INSERT INTO planta_compatibilizacao
        (obra_id, imagem_id, pagina_pdf, versao, imagem_path, ativa, criado_por)
     VALUES (?, ?, ?, ?, ?, 1, ?)"
);
$ins->bind_param('iiissi', $obraId, $imagemId, $paginaPdf, $versao, $relativePath, $criadoPor);

if (!$ins->execute()) {
    $ins->close(); $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar no banco: ' . $conn->error]);
    exit();
}

$plantaId = (int) $conn->insert_id;
$ins->close();
$conn->close();

echo json_encode([
    'sucesso'    => true,
    'planta_id'  => $plantaId,
    'versao'     => $versao,
    'imagem_url' => $relativePath,
    'imagem_id'  => $imagemId,
    'pagina_pdf' => $paginaPdf,
]);
