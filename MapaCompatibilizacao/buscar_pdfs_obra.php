<?php
/**
 * buscar_pdfs_obra.php
 * Lista PDFs do tipo "Planta Humanizada" (tipo_imagem_id=6), categoria
 * Arquitetônica (categoria_id=1), excluindo esquadrias, disponíveis
 * para uma obra na tabela `arquivos`.
 *
 * GET params:
 *   obra_id (int, obrigatório)
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
if ($obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'obra_id inválido.']);
    exit();
}

$conn = conectarBanco();

$sql = "SELECT a.idarquivo,
               a.nome_original,
               a.nome_interno
        FROM arquivos a
        WHERE a.obra_id       = ?
          AND a.status       != 'antigo'
          AND LOWER(a.tipo)   = 'pdf'
          AND LOWER(a.nome_original) LIKE '%.pdf'
          AND a.tipo_imagem_id = 6
          AND a.categoria_id   = 1
          AND LOWER(a.nome_interno) NOT LIKE '%esquadria%'
        ORDER BY a.nome_original ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $obraId);
$stmt->execute();
$res = $stmt->get_result();

$pdfs = [];
while ($row = $res->fetch_assoc()) {
    $pdfs[] = [
        'id'   => (int) $row['idarquivo'],
        'nome' => $row['nome_original'],
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['sucesso' => true, 'pdfs' => $pdfs]);
