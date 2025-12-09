<?php
require_once '../conexao.php';

$obra_id = $_POST['obra_id'] ?? null;
$status_id = $_POST['status_id'] ?? null;
$imagem_ids = $_POST['imagem_ids'] ?? [];
$prazo = $_POST['prazo'] ?? null;
$observacoes = $_POST['observacoes'] ?? null;

if (!$obra_id || !$status_id || empty($imagem_ids) || !$prazo) {
    echo json_encode(['success' => false, 'msg' => 'Preencha todos os campos e selecione pelo menos uma imagem.']);
    exit;
}

$conn->begin_transaction();

try {
    // Inserir entrega
    $stmt = $conn->prepare("INSERT INTO entregas (obra_id, status_id, data_prevista, observacoes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $obra_id, $status_id, $prazo, $observacoes);
    $stmt->execute();
    $entrega_id = $stmt->insert_id;
    $stmt->close();

    // Inserir itens
    $stmtItem = $conn->prepare("INSERT INTO entregas_itens (entrega_id, imagem_id, data_prevista) VALUES (?, ?, ?)");
    foreach ($imagem_ids as $imagem_id) {
        $stmtItem->bind_param("iis", $entrega_id, $imagem_id, $prazo);
        $stmtItem->execute();
    }
    $stmtItem->close();

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

    // Buscar código/nome do status (ex: P00) para compor a mensagem
    $status_code = '';
    $stmtStatus = $conn->prepare("SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1");
    if ($stmtStatus) {
        $stmtStatus->bind_param('i', $status_id);
        $stmtStatus->execute();
        $rStatus = $stmtStatus->get_result()->fetch_assoc();
        if ($rStatus && isset($rStatus['nome_status'])) $status_code = $rStatus['nome_status'];
        $stmtStatus->close();
    }

    $num_images = is_array($imagem_ids) ? count($imagem_ids) : 0;
    $assunto = "Nova entrega registrada para o status " . ($status_code ?: $status_id) . ", com " . $num_images . " imagens.";
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
