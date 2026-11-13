<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/p00_delivery_helpers.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../helpers/planejamento_producao_helper.php';

improov_p00_ensure_schema($conn);

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$entregaId = isset($data['entrega_id']) ? (int)$data['entrega_id'] : 0;
$itemId    = isset($data['item_id']) ? (int)$data['item_id'] : 0; // id da tabela entregas_itens
$imagemId  = isset($data['imagem_id']) ? (int)$data['imagem_id'] : 0; // id da imagem (coluna imagem_id)
$modoRemocao = $itemId > 0 ? 'by_item_id' : 'by_imagem_id';

if ($entregaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'entrega_id inválido']);
    exit;
}

$tipoEntrega = 'PADRAO';
$statusEntrega = 0;
$stmtEntrega = $conn->prepare("SELECT COALESCE(tipo_entrega, 'PADRAO') AS tipo_entrega, status_id FROM entregas WHERE id = ? LIMIT 1");
if ($stmtEntrega) {
    $stmtEntrega->bind_param('i', $entregaId);
    $stmtEntrega->execute();
    $resEntrega = $stmtEntrega->get_result()->fetch_assoc();
    if ($resEntrega && isset($resEntrega['tipo_entrega'])) {
        $tipoEntrega = (string) $resEntrega['tipo_entrega'];
        $statusEntrega = (int) ($resEntrega['status_id'] ?? 0);
    }
    $stmtEntrega->close();
}

try {
    $conn->begin_transaction();

    // Localiza e bloqueia o item antes de remover seus vínculos de planejamento.
    if ($tipoEntrega !== 'P00' && ($itemId > 0 || $imagemId > 0)) {
        $campo = $itemId > 0 ? 'id' : 'imagem_id';
        $identificador = $itemId > 0 ? $itemId : $imagemId;
        $consulta = $conn->prepare("SELECT id FROM entregas_itens WHERE entrega_id = ? AND $campo = ? LIMIT 1 FOR UPDATE");
        $consulta->bind_param('ii', $entregaId, $identificador);
        $consulta->execute();
        $item = $consulta->get_result()->fetch_assoc();
        $consulta->close();

        if ($item) {
            $itemRemovidoId = (int) $item['id'];
            // Instalações anteriores à migração de planejamento podem não ter esses vínculos.
            $colunas = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME = 'entrega_planejamento_item' AND COLUMN_NAME = 'entrega_item_id') OR (TABLE_NAME = 'janela_operacional_ciclo' AND COLUMN_NAME = 'entrega_item_id'))");
            while ($coluna = $colunas->fetch_assoc()) {
                if ($coluna['TABLE_NAME'] === 'entrega_planejamento_item') {
                    $vinculo = $conn->prepare('DELETE FROM entrega_planejamento_item WHERE entrega_item_id = ?');
                } else {
                    $vinculo = $conn->prepare('UPDATE janela_operacional_ciclo SET entrega_item_id = NULL WHERE entrega_item_id = ?');
                }
                $vinculo->bind_param('i', $itemRemovidoId);
                $vinculo->execute();
                $vinculo->close();
            }
            $itemId = $itemRemovidoId;
        }
    }

    // Preferir remoção pelo item_id (chave primária), senão usar entrega_id+imagem_id
    if ($tipoEntrega === 'P00' && $itemId > 0) {
        $stmt = $conn->prepare('DELETE FROM entregas_p00_versoes WHERE id = ? AND entrega_id = ? LIMIT 1');
        $stmt->bind_param('ii', $itemId, $entregaId);
    } elseif ($itemId > 0) {
        $stmt = $conn->prepare('DELETE FROM entregas_itens WHERE id = ? AND entrega_id = ? LIMIT 1');
        $stmt->bind_param('ii', $itemId, $entregaId);
    } elseif ($imagemId > 0) {
        $stmt = $conn->prepare('DELETE FROM entregas_itens WHERE entrega_id = ? AND imagem_id = ? LIMIT 1');
        $stmt->bind_param('ii', $entregaId, $imagemId);
    } else {
        echo json_encode(['success' => false, 'error' => 'Forneça item_id ou imagem_id']);
        exit;
    }

    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $conn->commit();
} catch (Throwable $erro) {
    $conn->rollback();
    error_log('Não foi possível remover a imagem da entrega ' . $entregaId . ': ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível remover a imagem da entrega.']);
    exit;
}

if ($affected > 0 && $statusEntrega === 2) {
    try {
        flow_planejamento_marcar_desatualizado($conn, $entregaId, isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null);
    } catch (Throwable $planejamentoErro) {
        error_log('Não foi possível atualizar o estado do planejamento da entrega ' . $entregaId . ': ' . $planejamentoErro->getMessage());
    }
}

echo json_encode([
    'success' => $affected > 0,
    'removed' => $affected,
    'mode' => $tipoEntrega === 'P00' ? 'by_version_id' : $modoRemocao
]);
