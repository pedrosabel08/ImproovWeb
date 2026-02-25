<?php
/**
 * buscar_imagens.php
 * Lista as imagens (imagens_cliente_obra) de uma obra para uso no modal de vínculo.
 *
 * GET params:
 *   obra_id    (int, obrigatório)
 *   planta_id  (int, opcional) — se informado, indica quais imagens já estão vinculadas
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$obraId   = isset($_GET['obra_id'])   ? (int)$_GET['obra_id']   : 0;
$plantaId = isset($_GET['planta_id']) ? (int)$_GET['planta_id'] : 0;

if ($obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'obra_id inválido.']);
    exit();
}

$conn = conectarBanco();

// --- Imagens da obra ---
$stmt = $conn->prepare("
    SELECT
        ico.idimagens_cliente_obra AS id,
        ico.imagem_nome,
        si.nome_status,
        ico.status_id
    FROM imagens_cliente_obra ico
    LEFT JOIN status_imagem si ON si.idstatus = ico.status_id
    WHERE ico.obra_id = ?
    ORDER BY ico.imagem_nome ASC
");
$stmt->bind_param('i', $obraId);
$stmt->execute();
$imagens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- IDs já vinculados (para informação ao frontend) ---
$vinculados = [];
if ($plantaId > 0) {
    $stmtVin = $conn->prepare("
        SELECT imagem_id
        FROM planta_marcacoes
        WHERE planta_id = ? AND imagem_id IS NOT NULL
    ");
    $stmtVin->bind_param('i', $plantaId);
    $stmtVin->execute();
    $rows = $stmtVin->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtVin->close();
    $vinculados = array_column($rows, 'imagem_id');
}

$conn->close();

// Normalizar tipos
$imagens = array_map(function ($img) use ($vinculados) {
    return [
        'id'          => (int)$img['id'],
        'imagem_nome' => $img['imagem_nome'],
        'nome_status' => $img['nome_status'] ?? '',
        'status_id'   => (int)$img['status_id'],
        'vinculada'   => in_array((int)$img['id'], array_map('intval', $vinculados)),
    ];
}, $imagens);

echo json_encode(['sucesso' => true, 'imagens' => $imagens]);
