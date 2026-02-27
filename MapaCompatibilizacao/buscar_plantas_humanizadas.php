<?php
/**
 * buscar_plantas_humanizadas.php
 * Retorna a lista de imagens do tipo "Planta Humanizada" de uma obra,
 * indicando quais já têm uma planta (página de PDF) vinculada.
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

// Busca todas as imagens do tipo Planta Humanizada da obra,
// fazendo LEFT JOIN com a planta ativa para indicar se já foi vinculada.
$stmt = $conn->prepare("
    SELECT
        ico.idimagens_cliente_obra  AS id,
        ico.imagem_nome,
        pc.id                       AS planta_id,
        pc.versao                   AS planta_versao,
        pc.pagina_pdf
    FROM imagens_cliente_obra ico
    LEFT JOIN planta_compatibilizacao pc
           ON pc.imagem_id = ico.idimagens_cliente_obra
          AND pc.obra_id   = ?
          AND pc.ativa     = 1
    WHERE ico.obra_id     = ?
      AND ico.tipo_imagem = 'Planta Humanizada'
    ORDER BY ico.imagem_nome ASC
");
$stmt->bind_param('ii', $obraId, $obraId);
$stmt->execute();
$res  = $stmt->get_result();

$imagens = [];
while ($row = $res->fetch_assoc()) {
    $imagens[] = [
        'id'            => (int) $row['id'],
        'imagem_nome'   => $row['imagem_nome'],
        'planta_id'     => $row['planta_id']     ? (int) $row['planta_id']     : null,
        'planta_versao' => $row['planta_versao'] ? (int) $row['planta_versao'] : null,
        'pagina_pdf'    => $row['pagina_pdf']    ? (int) $row['pagina_pdf']    : null,
        'tem_planta'    => !is_null($row['planta_id']),
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['sucesso' => true, 'imagens' => $imagens]);
