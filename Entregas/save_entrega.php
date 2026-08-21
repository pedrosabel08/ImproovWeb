<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/p00_delivery_helpers.php';
require_once __DIR__ . '/prazo_entrega_helper.php';
require_once __DIR__ . '/pendencias_entrega_helper.php';
require_once __DIR__ . '/../helpers/planejamento_producao_helper.php';

$obra_id = $_POST['obra_id'] ?? null;
$status_id = $_POST['status_id'] ?? null;
$imagem_ids = $_POST['imagem_ids'] ?? [];
$data_recebimento = isset($_POST['data_recebimento']) ? trim((string) $_POST['data_recebimento']) : '';
$prazo = isset($_POST['prazo']) ? trim((string) $_POST['prazo']) : '';
$observacoes = $_POST['observacoes'] ?? null;

if (!$obra_id || !$status_id || !$data_recebimento) {
    echo json_encode(['success' => false, 'msg' => 'Preencha obra, etapa e data de recebimento.']);
    exit;
}

if (!entregas_valid_date($data_recebimento)) {
    echo json_encode(['success' => false, 'msg' => 'Data de recebimento invalida.']);
    exit;
}

$obra_id = (int) $obra_id;
$status_id = (int) $status_id;
$imagem_ids = is_array($imagem_ids)
    ? array_values(array_unique(array_filter(array_map('intval', $imagem_ids))))
    : [];

entregas_ensure_data_recebimento_schema($conn);
$calculoPrazo = entregas_calcular_prazo_previsto($conn, $obra_id, $status_id, $data_recebimento);
if ($prazo === '' && $calculoPrazo && !empty($calculoPrazo['data_prevista'])) {
    $prazo = $calculoPrazo['data_prevista'];
}

if ($prazo === '' || !entregas_valid_date($prazo)) {
    echo json_encode(['success' => false, 'msg' => 'Informe um prazo previsto valido.']);
    exit;
}

$status_code = improov_p00_fetch_status_name($conn, $status_id) ?? '';
$is_p00_delivery = mb_strtoupper(trim($status_code), 'UTF-8') === 'P00';

if (!$is_p00_delivery && empty($imagem_ids)) {
    echo json_encode(['success' => false, 'msg' => 'Selecione pelo menos uma imagem para criar a entrega.']);
    exit;
}

if (!$is_p00_delivery && !empty($imagem_ids)) {
    $validateImage = $conn->prepare(
        "SELECT ico.idimagens_cliente_obra
         FROM imagens_cliente_obra ico
         WHERE ico.idimagens_cliente_obra = ?
           AND ico.obra_id = ?
           AND ico.status_id IN (?, CASE WHEN ? = 2 THEN 1 ELSE ? END)
           AND (ico.substatus_id IS NULL OR ico.substatus_id <> 7)
           AND NOT EXISTS (
               SELECT 1
               FROM entregas_itens ei
               INNER JOIN entregas e ON e.id = ei.entrega_id
               WHERE ei.imagem_id = ico.idimagens_cliente_obra
                 AND e.status_id = ?
           )
         LIMIT 1"
    );

    if (!$validateImage) {
        echo json_encode(['success' => false, 'msg' => 'Nao foi possivel validar as imagens selecionadas.']);
        exit;
    }

    foreach ($imagem_ids as $imagem_id) {
        $validateImage->bind_param('iiiiii', $imagem_id, $obra_id, $status_id, $status_id, $status_id, $status_id);
        $validateImage->execute();
        $valid = $validateImage->get_result()->num_rows > 0;
        if (!$valid) {
            $validateImage->close();
            echo json_encode([
                'success' => false,
                'msg' => "A imagem #{$imagem_id} nao pertence a esta obra/etapa ou ja foi atribuida.",
            ]);
            exit;
        }
    }
    $validateImage->close();
}

entregas_pendencias_ensure_schema($conn);
$conn->begin_transaction();

try {
    improov_p00_ensure_schema($conn);

    // Inserir entrega
    $tipo_entrega = $is_p00_delivery ? 'P00' : 'PADRAO';
    $stmt = $conn->prepare("INSERT INTO entregas (obra_id, status_id, tipo_entrega, data_recebimento, data_prevista, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $obra_id, $status_id, $tipo_entrega, $data_recebimento, $prazo, $observacoes);
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
        $pendenciasResolvidas = 0;
        $resolvidaPor = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
        foreach ($imagem_ids as $imagem_id) {
            $stmtItem->bind_param("iis", $entrega_id, $imagem_id, $prazo);
            $stmtItem->execute();
            $entrega_item_id = (int) $stmtItem->insert_id;
            if ($entrega_item_id > 0) {
                $pendenciasResolvidas += resolver_pendencias_entrega($conn, $entrega_id, $imagem_id, $entrega_item_id, $resolvidaPor);
            }
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
    // O rascunho é criado após a R00 existir. Falha de planejamento nunca
    // invalida a criação operacional da entrega.
    $planejamentoDisponivel = false;
    if ((int) $status_id === 2) {
        try {
            $planejamentoDisponivel = flow_planejamento_garantir_rascunho(
                $conn,
                (int) $entrega_id,
                isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
            ) !== null;
        } catch (Throwable $planejamentoErro) {
            error_log('Rascunho de planejamento não criado para entrega ' . $entrega_id . ': ' . $planejamentoErro->getMessage());
        }
    }
    echo json_encode(['success' => true, 'entrega_id' => $entrega_id, 'pendencias_resolvidas' => $pendenciasResolvidas ?? 0, 'planejamento_rascunho_disponivel' => $planejamentoDisponivel]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}
