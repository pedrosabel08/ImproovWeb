<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

include __DIR__ . '/../conexao.php';
include __DIR__ . '/../conexaoMain.php';

$colaboradorId = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
$competencia = isset($_GET['competencia']) ? trim((string)$_GET['competencia']) : null;

if (!$colaboradorId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'colaborador_id obrigatório.']);
    exit;
}

$conn = conectarBanco();

if ($competencia) {
    $sql = "SELECT * FROM contratos WHERE colaborador_id = ? AND competencia = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $colaboradorId, $competencia);
} else {
    $sql = "SELECT * FROM contratos WHERE colaborador_id = ? ORDER BY competencia DESC, id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $colaboradorId);
}

$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode([
        'success' => true,
        'competencia' => null,
        'status' => 'nao_gerado',
        'zapsign_doc_token' => null,
        'arquivo_nome' => null,
        'download_url' => null
    ]);
    exit;
}

$arquivoNome = $row['arquivo_nome'] ?? null;
$downloadUrl = $arquivoNome ? ('./download.php?arquivo=' . rawurlencode($arquivoNome)) : null;

echo json_encode([
    'success' => true,
    'competencia' => $row['competencia'],
    'status' => $row['status'],
    'zapsign_doc_token' => $row['zapsign_doc_token'],
    'arquivo_nome' => $arquivoNome,
    'download_url' => $downloadUrl
]);
