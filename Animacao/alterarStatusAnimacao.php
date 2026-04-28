<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$animacao_id  = intval($_POST['animacao_id']  ?? 0);
$substatus_id = intval($_POST['substatus_id'] ?? 0);

if (!$animacao_id || !$substatus_id) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

// Buscar estado atual da animação + status da imagem pai
$stmt = $conn->prepare(
    "SELECT
        a.substatus_id       AS anim_substatus,
        ico.status_id        AS img_status_id,
        si.nome_status       AS img_status_nome,
        ico.substatus_id     AS img_substatus_id,
        sico.nome_substatus  AS img_substatus_nome
     FROM animacao a
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = a.imagem_id
     LEFT JOIN status_imagem    si   ON si.idstatus = ico.status_id
     LEFT JOIN substatus_imagem sico ON sico.id     = ico.substatus_id
     WHERE a.idanimacao = ?"
);
$stmt->bind_param('i', $animacao_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Animação não encontrada']);
    exit;
}

// Regra: para sair do HOLD (ou qualquer avanço), a imagem deve estar
// com status EF (nome_status) e substatus DRV (nome_substatus).
// Verificado pelo nome para desacoplar de IDs fixos.
$is_hold    = ($substatus_id === 7);
$img_ef     = (trim($row['img_status_nome'])    === 'EF');
$img_drv    = (trim($row['img_substatus_nome']) === 'DRV');
$img_ok     = $img_ef && $img_drv;

if (!$is_hold && !$img_ok) {
    echo json_encode([
        'success' => false,
        'error'   => 'A imagem precisa estar com status EF e substatus DRV para avançar a animação.'
    ]);
    exit;
}

$stmtUp = $conn->prepare("UPDATE animacao SET substatus_id = ? WHERE idanimacao = ?");
$stmtUp->bind_param('ii', $substatus_id, $animacao_id);
$stmtUp->execute();
$affected = $stmtUp->affected_rows;
$stmtUp->close();
$conn->close();

echo json_encode(['success' => $affected > 0]);
