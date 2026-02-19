<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/secure_env.php';
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(403);
    echo json_encode(['errors' => ['Acesso negado.'], 'success' => []]);
    exit;
}

include '../conexao.php';

$success = [];
$errors = [];

$axis_id = intval($_POST['axis_id'] ?? 0);
$category_id = intval($_POST['category_id'] ?? 0);
$subcategory_id = intval($_POST['subcategory_id'] ?? 0);
$descricao = $_POST['descricao'] ?? null;
$colaborador_id = (isset($_SESSION['idcolaborador']) && intval($_SESSION['idcolaborador']) > 0) ? intval($_SESSION['idcolaborador']) : null;

if (!$axis_id || !$category_id || !$subcategory_id) {
    echo json_encode(['errors' => ['Parâmetros inválidos.'], 'success' => []]);
    exit;
}

// fetch taxonomy row (and validate consistency)
$stmt = $conn->prepare(
    "SELECT sub.id, sub.nome, sub.slug, sub.allowed_exts_json, sub.tipo_label,
            cat.id AS category_id, cat.nome AS category_nome, cat.slug AS category_slug,
            ax.id AS axis_id, ax.nome AS axis_nome, ax.slug AS axis_slug
     FROM flow_ref_subcategory sub
     JOIN flow_ref_category cat ON cat.id = sub.category_id
     JOIN flow_ref_axis ax ON ax.id = cat.axis_id
     WHERE sub.id = ? AND cat.id = ? AND ax.id = ? AND sub.ativo=1 AND cat.ativo=1 AND ax.ativo=1
     LIMIT 1"
);
if (!$stmt) {
    echo json_encode(['errors' => ['Erro interno (prepare).'], 'success' => []]);
    exit;
}
$stmt->bind_param('iii', $subcategory_id, $category_id, $axis_id);
$stmt->execute();
$res = $stmt->get_result();
$tax = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$tax) {
    echo json_encode(['errors' => ['Taxonomia inválida (eixo/categoria/subcategoria).'], 'success' => []]);
    exit;
}

$allowed = [];
$decoded = json_decode($tax['allowed_exts_json'] ?? '[]', true);
if (is_array($decoded)) {
    foreach ($decoded as $ext) {
        $ext = strtolower(trim((string)$ext));
        if ($ext !== '') $allowed[] = $ext;
    }
}

if (empty($allowed)) {
    echo json_encode(['errors' => ['Subcategoria sem tipos permitidos configurados.'], 'success' => []]);
    exit;
}

// Helpers
function slug_safe($s)
{
    $s = (string)$s;
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'item';
}

function prefix3($s)
{
    $s = (string)$s;
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9]/', '', $s);
    $s = substr($s, 0, 3);
    if ($s === '') $s = 'XXX';
    if (strlen($s) < 3) $s = str_pad($s, 3, 'X');
    return $s;
}

function ensureDirSftp($sftp, $path)
{
    if ($sftp->is_dir($path)) return true;
    return $sftp->mkdir($path, 0777, true);
}

// Files
$files = $_FILES['arquivos'] ?? null;
if (!$files || empty($files['name'])) {
    echo json_encode(['errors' => ['Nenhum arquivo enviado.'], 'success' => []]);
    exit;
}

$names = $files['name'];
$tmpNames = $files['tmp_name'];
$errorsArr = $files['error'];
$sizes = $files['size'];
$types = $files['type'];

// Normalize to arrays
if (!is_array($names)) {
    $names = [$names];
    $tmpNames = [$tmpNames];
    $errorsArr = [$errorsArr];
    $sizes = [$sizes];
    $types = [$types];
}

// ---- SFTP config (NAS) ----
try {
    $sftpCfg = improov_sftp_config();
} catch (RuntimeException $e) {
    echo json_encode(['errors' => ['Configuração SFTP ausente no ambiente.'], 'success' => []]);
    exit;
}

$host = $sftpCfg['host'];
$port = $sftpCfg['port'];
$username = $sftpCfg['user'];
$password = $sftpCfg['pass'];

$SFTP_BASE_DIR = '/mnt/exchange/_SIRE';

$axisSlug = slug_safe($tax['axis_slug'] ?? $tax['axis_id']);
$categorySlug = slug_safe($tax['category_slug'] ?? $tax['category_id']);
$subSlug = slug_safe($tax['slug'] ?? $tax['id']);

$axisPrefix = prefix3($tax['axis_nome'] ?? $tax['axis_slug'] ?? $tax['axis_id']);
$catPrefix = prefix3($tax['category_nome'] ?? $tax['category_slug'] ?? $tax['category_id']);
$subPrefix = prefix3($tax['nome'] ?? $tax['slug'] ?? $tax['id']);

$remoteDir = rtrim($SFTP_BASE_DIR, '/') . '/flow_referencias/' . $axisSlug . '/' . $categorySlug . '/' . $subSlug;

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    echo json_encode(['errors' => ['Erro ao conectar no servidor SFTP.'], 'success' => []]);
    exit;
}

if (!ensureDirSftp($sftp, $remoteDir)) {
    echo json_encode(['errors' => ['Não foi possível criar a pasta de destino no SFTP.'], 'success' => []]);
    exit;
}

$insert = $conn->prepare(
    "INSERT INTO flow_ref_upload (axis_id, category_id, subcategory_id, original_name, stored_name, path, ext, mime, size_bytes, descricao, colaborador_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$insert) {
    echo json_encode(['errors' => ['Erro interno (prepare insert).'], 'success' => []]);
    exit;
}

for ($i = 0; $i < count($names); $i++) {
    $origName = $names[$i];
    $tmp = $tmpNames[$i];
    $err = $errorsArr[$i];
    $size = $sizes[$i] ?? null;
    $mime = $types[$i] ?? null;

    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = "Falha ao enviar '$origName' (código $err).";
        continue;
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $errors[] = "Arquivo '$origName' sem extensão.";
        continue;
    }

    if (!in_array($ext, $allowed, true)) {
        $errors[] = "Tipo inválido para '$origName'. Permitidos: " . implode(', ', $allowed);
        continue;
    }

    $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
    if ($safeBase === '' || $safeBase === '.' || $safeBase === '..') $safeBase = 'arquivo';

    $prefix = $axisPrefix . '_' . $catPrefix . '_' . $subPrefix;
    $storedBase = $prefix . '_' . $safeBase;
    $stored = $storedBase . '.' . $ext;
    $remotePath = rtrim($remoteDir, '/') . '/' . $stored;

    if ($sftp->file_exists($remotePath)) {
        $suffix = 1;
        do {
            $stored = $storedBase . '_' . $suffix . '.' . $ext;
            $remotePath = rtrim($remoteDir, '/') . '/' . $stored;
            $suffix++;
        } while ($sftp->file_exists($remotePath) && $suffix < 1000);
    }

    // Upload without loading into memory
    if (!$sftp->put($remotePath, $tmp, SFTP::SOURCE_LOCAL_FILE)) {
        $errors[] = "Não foi possível salvar '$origName' no NAS (SFTP).";
        continue;
    }

    $sizeBytes = is_numeric($size) ? (int)$size : null;
    $mimeStr = $mime ? (string)$mime : null;
    $desc = ($descricao !== null && trim($descricao) !== '') ? $descricao : null;

    $insert->bind_param(
        'iiissssssis',
        $axis_id,
        $category_id,
        $subcategory_id,
        $origName,
        $stored,
        $remotePath,
        $ext,
        $mimeStr,
        $sizeBytes,
        $desc,
        $colaborador_id
    );

    if (!$insert->execute()) {
        // rollback file if DB fails
        try {
            $sftp->delete($remotePath);
        } catch (Exception $e) {
        }
        $errors[] = "Falha ao registrar '$origName' no banco: " . $insert->error;
        continue;
    }

    $success[] = "Upload OK: $origName";
}

$insert->close();
$conn->close();

echo json_encode(['success' => $success, 'errors' => $errors]);
