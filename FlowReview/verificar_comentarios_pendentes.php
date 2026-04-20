<?php
include_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

$funcao_imagem_id = isset($_GET['funcao_imagem_id']) ? intval($_GET['funcao_imagem_id']) : 0;
$ap_imagem_id     = isset($_GET['ap_imagem_id'])     ? intval($_GET['ap_imagem_id'])     : 0;

if (!$funcao_imagem_id && !$ap_imagem_id) {
    http_response_code(400);
    echo json_encode(['erro' => 'Forneça funcao_imagem_id ou ap_imagem_id.']);
    exit;
}

// Verifica se a coluna concluido já existe; se não, não há pendentes
$chk = $conn->prepare(
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios_imagem' AND COLUMN_NAME = 'concluido' LIMIT 1"
);
$chk->execute();
$hasConcluido = ($chk->get_result()->num_rows > 0);
$chk->close();

if (!$hasConcluido) {
    echo json_encode(['tem_pendentes' => false, 'total' => 0, 'concluidos' => 0, 'pendentes' => 0]);
    exit;
}

// Se apenas funcao_imagem_id foi passado, resolve para o ap_imagem_id mais recente
if (!$ap_imagem_id && $funcao_imagem_id) {
    $stmtLast = $conn->prepare(
        "SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmtLast->bind_param('i', $funcao_imagem_id);
    $stmtLast->execute();
    $rowLast   = $stmtLast->get_result()->fetch_assoc();
    $stmtLast->close();
    $ap_imagem_id = $rowLast ? (int)$rowLast['id'] : 0;
}

if (!$ap_imagem_id) {
    echo json_encode(['tem_pendentes' => false, 'total' => 0, 'concluidos' => 0, 'pendentes' => 0]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total, SUM(concluido) AS concluidos FROM comentarios_imagem WHERE ap_imagem_id = ?"
);
$stmt->bind_param('i', $ap_imagem_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total      = (int)($row['total']     ?? 0);
$concluidos = (int)($row['concluidos'] ?? 0);
$pendentes  = $total - $concluidos;

echo json_encode([
    'tem_pendentes' => ($total > 0 && $pendentes > 0),
    'total'         => $total,
    'concluidos'    => $concluidos,
    'pendentes'     => $pendentes,
]);
