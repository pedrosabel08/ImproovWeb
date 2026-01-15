<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'errors' => ['Acesso negado.']]);
    exit;
}

include '../conexao.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'errors' => ['ID inválido.']]);
    exit;
}

$stmt = $conn->prepare("SELECT id, path, stored_name FROM flow_ref_upload WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => ['Erro interno (prepare).']]);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'errors' => ['Arquivo não encontrado.']]);
    $conn->close();
    exit;
}

$remotePath = $row['path'];

$host = "imp-nas.ddns.net";
$port = 2222;
$username = "flow";
$password = "flow@2025";

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'errors' => ['Erro ao conectar no servidor SFTP.']]);
    $conn->close();
    exit;
}

$errors = [];
if ($remotePath) {
    if ($sftp->file_exists($remotePath)) {
        if (!$sftp->delete($remotePath)) {
            $errors[] = 'Falha ao excluir o arquivo no NAS.';
        }
    }
}

$del = $conn->prepare("DELETE FROM flow_ref_upload WHERE id = ? LIMIT 1");
if (!$del) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => ['Erro interno (prepare delete).']]);
    $conn->close();
    exit;
}
$del->bind_param('i', $id);
if (!$del->execute()) {
    $errors[] = 'Falha ao excluir o registro no banco.';
}
$del->close();
$conn->close();

if (!empty($errors)) {
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

echo json_encode(['ok' => true]);
