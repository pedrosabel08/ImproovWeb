<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/p00_delivery_helpers.php';

$obra_id = $_POST['obra_id'] ?? null;
$status_id = $_POST['status_id'] ?? null;
$imagem_ids = $_POST['imagem_ids'] ?? [];
$prazo = $_POST['prazo'] ?? null;
$observacoes = $_POST['observacoes'] ?? null;

if (!$obra_id || !$status_id || !$prazo) {
    echo json_encode(['success' => false, 'msg' => 'Preencha todos os campos e selecione pelo menos uma imagem.']);
    exit;
}

$obra_id = (int) $obra_id;
$status_id = (int) $status_id;
$imagem_ids = is_array($imagem_ids) ? array_values(array_filter(array_map('intval', $imagem_ids))) : [];

$status_code = improov_p00_fetch_status_name($conn, $status_id) ?? '';
$is_p00_delivery = mb_strtoupper(trim($status_code), 'UTF-8') === 'P00';

if (!$is_p00_delivery && empty($imagem_ids)) {
    echo json_encode(['success' => false, 'msg' => 'Selecione pelo menos uma imagem para criar a entrega.']);
    exit;
}

$conn->begin_transaction();

try {
    improov_p00_ensure_schema($conn);

    // Inserir entrega
    $tipo_entrega = $is_p00_delivery ? 'P00' : 'PADRAO';
    $stmt = $conn->prepare("INSERT INTO entregas (obra_id, status_id, tipo_entrega, data_prevista, observacoes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $obra_id, $status_id, $tipo_entrega, $prazo, $observacoes);
    $stmt->execute();
    $entrega_id = $stmt->insert_id;
    $stmt->close();

    if ($is_p00_delivery) {
        $representative_image_id = $imagem_ids[0] ?? null;
        improov_p00_create_initial_version($conn, $entrega_id, $representative_image_id, $prazo);
        improov_p00_resolve_handoff($conn, $obra_id, $entrega_id);
    } else {
        // Inserir itens
        $stmtItem = $conn->prepare("INSERT INTO entregas_itens (entrega_id, imagem_id, data_prevista) VALUES (?, ?, ?)");
        foreach ($imagem_ids as $imagem_id) {
            $stmtItem->bind_param("iis", $entrega_id, $imagem_id, $prazo);
            $stmtItem->execute();
        }
        $stmtItem->close();
    }

    // Criar acompanhamento fixo para registrar que a entrega foi criada
    // Calcula a próxima ordem para esta obra
    $next_ordem = 1;
    $stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?");
    if ($stmtOrdem) {
        $stmtOrdem->bind_param('i', $obra_id);
        $stmtOrdem->execute();
        $rOrd = $stmtOrdem->get_result()->fetch_assoc();
        if ($rOrd && isset($rOrd['next_ordem'])) $next_ordem = intval($rOrd['next_ordem']);
        $stmtOrdem->close();
    }

    $num_images = is_array($imagem_ids) ? count($imagem_ids) : 0;
    if ($is_p00_delivery) {
        $assunto = "Nova entrega P00 registrada para a modelagem/toons da fachada.";
    } else {
        $assunto = "Nova entrega registrada para o status " . ($status_code ?: $status_id) . ", com " . $num_images . " imagens.";
    }
    $data_today = date('Y-m-d');
    // Inserir acompanhamento não-pendente (campo status vazio) porque este acompanhamento não será atualizado
    $insertAcomp = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, entrega_id, tipo, status) VALUES (?, NULL, ?, ?, ?, ?, 'entrega', '')");
    if ($insertAcomp) {
        $insertAcomp->bind_param('issii', $obra_id, $assunto, $data_today, $next_ordem, $entrega_id);
        $insertAcomp->execute();
        $insertAcomp->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'entrega_id' => $entrega_id]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}
