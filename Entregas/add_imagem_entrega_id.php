<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

// Recebe JSON: { entrega_id: int, imagem_ids: [int, ...] }
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input || !isset($input['entrega_id']) || !isset($input['imagem_ids']) || !is_array($input['imagem_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$entrega_id = intval($input['entrega_id']);
$imagem_ids = array_map('intval', $input['imagem_ids']);
$imagem_ids = array_values(array_filter($imagem_ids, function($v){ return $v > 0; }));

if ($entrega_id <= 0 || count($imagem_ids) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'entrega_id ou imagem_ids inválidos']);
    exit;
}

try {
    $conn->begin_transaction();

    // Verifica existência da entrega
    $stmt = $conn->prepare("SELECT id, obra_id, status_id FROM entregas WHERE id = ?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('Entrega não encontrada');
    }
    $ent = $res->fetch_assoc();
    $stmt->close();

    $added = 0;
    $skipped = 0;

    // Prepara statements
    $sel = $conn->prepare("SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? AND obra_id = ?");
    $ins = $conn->prepare("INSERT INTO entregas_itens (entrega_id, imagem_id, status) VALUES (?, ?, 'Pendente')");
    $check = $conn->prepare("SELECT id FROM entregas_itens WHERE entrega_id = ? AND imagem_id = ? LIMIT 1");

    foreach ($imagem_ids as $imgId) {
        // opcional: checar se imagem pertence à mesma obra da entrega
        $sel->bind_param('ii', $imgId, $ent['obra_id']);
        $sel->execute();
        $r = $sel->get_result();
        if ($r->num_rows === 0) {
            // não adiciona imagem que não pertence à obra
            $skipped++;
            continue;
        }

        // checar duplicata
        $check->bind_param('ii', $entrega_id, $imgId);
        $check->execute();
        $r2 = $check->get_result();
        if ($r2->num_rows > 0) {
            $skipped++;
            continue;
        }

        // inserir
        $ins->bind_param('ii', $entrega_id, $imgId);
        if ($ins->execute()) $added++;
    }

    $conn->commit();

    echo json_encode(['success' => true, 'added_count' => $added, 'skipped_count' => $skipped]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>