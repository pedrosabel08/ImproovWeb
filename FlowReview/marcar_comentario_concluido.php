<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit;
}

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    return ($res && $res->num_rows > 0);
}

function ensureConcluidoColumns(mysqli $conn): void
{
    if (!tableHasColumn($conn, 'comentarios_imagem', 'concluido')) {
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN concluido TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($conn, 'comentarios_imagem', 'concluido_por')) {
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN concluido_por INT NULL");
    }
    if (!tableHasColumn($conn, 'comentarios_imagem', 'concluido_em')) {
        @$conn->query("ALTER TABLE comentarios_imagem ADD COLUMN concluido_em DATETIME NULL");
    }
}

$data = json_decode(file_get_contents('php://input'), true);
$comentario_id = isset($data['comentario_id']) ? intval($data['comentario_id']) : 0;
$concluido     = isset($data['concluido'])     ? (int)(bool)$data['concluido']   : 0;

if (!$comentario_id) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'comentario_id inválido.']);
    exit;
}

ensureConcluidoColumns($conn);

$idColab = (int)$_SESSION['idcolaborador'];

if ($concluido) {
    $stmt = $conn->prepare(
        "UPDATE comentarios_imagem SET concluido = 1, concluido_por = ?, concluido_em = NOW() WHERE id = ?"
    );
    $stmt->bind_param('ii', $idColab, $comentario_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE comentarios_imagem SET concluido = 0, concluido_por = NULL, concluido_em = NULL WHERE id = ?"
    );
    $stmt->bind_param('i', $comentario_id);
}
$stmt->execute();
$stmt->close();

// Busca ap_imagem_id para retornar progresso atualizado
$stmtImg = $conn->prepare("SELECT ap_imagem_id FROM comentarios_imagem WHERE id = ?");
$stmtImg->bind_param('i', $comentario_id);
$stmtImg->execute();
$rowImg = $stmtImg->get_result()->fetch_assoc();
$stmtImg->close();

$ap_imagem_id = $rowImg ? (int)$rowImg['ap_imagem_id'] : 0;

$total     = 0;
$concluidos = 0;

if ($ap_imagem_id) {
    $stmtProg = $conn->prepare(
        "SELECT COUNT(*) AS total, SUM(concluido) AS concluidos FROM comentarios_imagem WHERE ap_imagem_id = ?"
    );
    $stmtProg->bind_param('i', $ap_imagem_id);
    $stmtProg->execute();
    $prog       = $stmtProg->get_result()->fetch_assoc();
    $stmtProg->close();
    $total      = (int)($prog['total']     ?? 0);
    $concluidos = (int)($prog['concluidos'] ?? 0);
}

echo json_encode([
    'sucesso'    => true,
    'concluido'  => $concluido,
    'total'      => $total,
    'concluidos' => $concluidos,
    'pendentes'  => $total - $concluidos,
]);
