<?php
require_once __DIR__ . '/../vendor/autoload.php';
use phpseclib3\Net\SFTP;

session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

include '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['idarquivo']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$id = intval($input['idarquivo']);
$action = $input['action']; // 'antigo' or 'atualizado'

// SFTP credentials (same as upload.php)
$host = "imp-nas.ddns.net";
$port = 2222;
$username = "flow";
$password = "flow@2025";

$q = $conn->prepare("SELECT idarquivo, caminho, nome_interno, status FROM arquivos WHERE idarquivo = ? LIMIT 1");
$q->bind_param('i', $id);
$q->execute();
$res = $q->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Arquivo não encontrado']);
    exit;
}
$row = $res->fetch_assoc();
$oldPath = $row['caminho'];
$nomeInterno = $row['nome_interno'];
$currentStatus = $row['status'];

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    echo json_encode(['success' => false, 'error' => 'Falha ao conectar SFTP']);
    exit;
}

// helpers
function ensureDirSftp($sftp, $path, &$log) {
    if ($sftp->is_dir($path)) return true;
    if ($sftp->mkdir($path, 0777, true)) { $log[] = "Criada pasta SFTP: $path"; return true; }
    return false;
}

$log = [];
try {
    $dirname = dirname($oldPath);
    $basename = basename($oldPath);

    if ($action === 'antigo') {
        // move to OLD subfolder
        $oldDir = rtrim($dirname, '/') . '/OLD';
        if (!ensureDirSftp($sftp, $oldDir, $log)) {
            throw new Exception('Falha ao criar pasta OLD no SFTP: ' . $oldDir);
        }
        $newPath = $oldDir . '/' . $basename;
        // try rename
        if (!$sftp->rename($oldPath, $newPath)) {
            // fallback: get content and put
            $data = $sftp->get($oldPath);
            if ($data === false || !$sftp->put($newPath, $data)) {
                throw new Exception('Falha ao mover arquivo para OLD (rename e fallback falharam)');
            }
            // delete original
            $sftp->delete($oldPath);
        }
        // update DB
        $stmt = $conn->prepare("UPDATE arquivos SET status='antigo', caminho = ? WHERE idarquivo = ?");
        $stmt->bind_param('si', $newPath, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'new_path' => $newPath, 'log' => $log]);
        exit;
    } elseif ($action === 'atualizado') {
        // move from OLD to parent
        // If path contains /OLD/ segment, remove it; otherwise, assume it is in OLD sibling
        if (strpos($oldPath, '/OLD/') !== false) {
            $newPath = str_replace('/OLD/', '/', $oldPath);
        } else {
            // if file currently in parent (not OLD), maybe we want to move from OLD sibling back; attempt to find in OLD
            $parent = dirname($oldPath);
            $candidateOld = rtrim($parent, '/') . '/OLD/' . basename($oldPath);
            if ($sftp->file_exists($candidateOld)) {
                $oldPath = $candidateOld;
                $newPath = rtrim($parent, '/') . '/' . basename($oldPath);
            } else {
                // nothing to do
                throw new Exception('Arquivo não está em OLD e não foi encontrado em OLD.');
            }
        }

        // ensure parent dir exists
        $newDir = dirname($newPath);
        if (!ensureDirSftp($sftp, $newDir, $log)) {
            throw new Exception('Falha ao garantir pasta de destino no SFTP: ' . $newDir);
        }

        if (!$sftp->rename($oldPath, $newPath)) {
            $data = $sftp->get($oldPath);
            if ($data === false || !$sftp->put($newPath, $data)) {
                throw new Exception('Falha ao mover arquivo de OLD para principal (rename e fallback falharam)');
            }
            $sftp->delete($oldPath);
        }

        $stmt = $conn->prepare("UPDATE arquivos SET status='atualizado', caminho = ? WHERE idarquivo = ?");
        $stmt->bind_param('si', $newPath, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'new_path' => $newPath, 'log' => $log]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Ação desconhecida']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'log' => $log]);
    exit;
}

?>
