<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/pre_alt_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autenticado.']);
    exit;
}

function pre_alt_cliente_data_hora(string $value): string
{
    $value = trim($value);
    $formats = ['Y-m-d\\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    throw new RuntimeException('Informe uma data e hora validas.');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload invalido.']);
    exit;
}

$loteId = isset($data['lote_id']) && is_numeric($data['lote_id']) ? (int) $data['lote_id'] : 0;
$itemIds = array_values(array_unique(array_filter(array_map('intval', $data['item_ids'] ?? []))));
$tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
$observacao = trim((string) ($data['observacao'] ?? ''));
$resultadoRetorno = strtoupper(trim((string) ($data['resultado_retorno'] ?? '')));

if ($loteId <= 0 || !$itemIds || !in_array($tipo, ['SOLICITACAO', 'RETORNO'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Informe lote, imagens e tipo de interacao validos.']);
    exit;
}

if ($tipo === 'RETORNO' && !in_array($resultadoRetorno, ['APROVADA', 'ALTERACAO'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Selecione o resultado do retorno do cliente.']);
    exit;
}
if ($tipo === 'SOLICITACAO') {
    $resultadoRetorno = '';
}

try {
    $ocorridoEm = pre_alt_cliente_data_hora((string) ($data['ocorrido_em'] ?? ''));
    pre_alt_ensure_schema($conn);
    $conn->begin_transaction();

    $stmtLote = $conn->prepare('SELECT id, status FROM pre_alt_lote WHERE id = ? FOR UPDATE');
    if (!$stmtLote) {
        throw new RuntimeException('Nao foi possivel validar o lote.');
    }
    $stmtLote->bind_param('i', $loteId);
    $stmtLote->execute();
    $lote = $stmtLote->get_result()->fetch_assoc();
    $stmtLote->close();

    if (!$lote) {
        throw new RuntimeException('Lote nao encontrado.');
    }
    if (in_array((string) $lote['status'], ['PLANEJADO', 'CANCELADO'], true)) {
        throw new RuntimeException('Este lote nao pode receber novas interacoes com o cliente.');
    }

    $itemIdList = implode(',', $itemIds);
    $resItens = $conn->query(
        "SELECT pai.id, pai.imagem_id, pai.resultado, pai.nivel_complexidade, pai.tipo_alteracao, pai.acao,
                pai.necessita_retorno, pai.quantidade_comentarios, pai.responsavel_id, pai.reanalise_pos_retorno,
                (SELECT pli.id FROM pre_alt_liberacao_itens pli WHERE pli.pre_alt_item_id = pai.id LIMIT 1) AS liberacao_item_id
         FROM pre_alt_itens pai
         WHERE pai.pre_alt_lote_id = $loteId
           AND pai.id IN ($itemIdList)
         FOR UPDATE"
    );
    if (!$resItens) {
        throw new RuntimeException('Nao foi possivel validar as imagens selecionadas.');
    }

    $itens = [];
    while ($item = $resItens->fetch_assoc()) {
        $itens[(int) $item['id']] = $item;
    }
    if (count($itens) !== count($itemIds)) {
        throw new RuntimeException('Uma ou mais imagens nao pertencem ao lote selecionado.');
    }

    foreach ($itens as $item) {
        if (!empty($item['liberacao_item_id'])) {
            throw new RuntimeException('Imagens ja liberadas nao podem receber novas interacoes com o cliente.');
        }
    }

    if ($tipo === 'RETORNO') {
        foreach ($itens as $item) {
            if (($item['resultado'] ?? '') !== 'AGUARDANDO_CLIENTE' && (int) ($item['necessita_retorno'] ?? 0) !== 1) {
                throw new RuntimeException('O retorno so pode ser registrado para imagens aguardando cliente.');
            }
        }
    }

    $usuarioId = isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;
    $colaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
    $stmtInteracao = $conn->prepare(
        "INSERT INTO pre_alt_cliente_interacoes (
            pre_alt_lote_id, tipo, ocorrido_em, resultado_retorno, observacao, usuario_id, colaborador_id
         ) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?)"
    );
    if (!$stmtInteracao) {
        throw new RuntimeException('Nao foi possivel registrar a interacao.');
    }
    $stmtInteracao->bind_param('issssii', $loteId, $tipo, $ocorridoEm, $resultadoRetorno, $observacao, $usuarioId, $colaboradorId);
    if (!$stmtInteracao->execute()) {
        throw new RuntimeException('Nao foi possivel registrar a interacao: ' . $stmtInteracao->error);
    }
    $interacaoId = (int) $stmtInteracao->insert_id;
    $stmtInteracao->close();

    $stmtVinculo = $conn->prepare(
        "INSERT INTO pre_alt_cliente_interacao_itens (interacao_id, pre_alt_item_id, estado_anterior_json)
         VALUES (?, ?, NULLIF(?, ''))"
    );
    $stmtSolicitacao = $conn->prepare(
        "UPDATE pre_alt_itens
         SET resultado = 'AGUARDANDO_CLIENTE', nivel_complexidade = NULL, tipo_alteracao = NULL,
             necessita_retorno = 1, reanalise_pos_retorno = 0, updated_at = NOW()
         WHERE id = ?"
    );
    $stmtAprovada = $conn->prepare(
        "UPDATE pre_alt_itens
         SET resultado = 'SEM_ALTERACAO', nivel_complexidade = NULL, tipo_alteracao = NULL,
             necessita_retorno = 0, reanalise_pos_retorno = 0, updated_at = NOW()
         WHERE id = ?"
    );
    $stmtAlteracao = $conn->prepare(
        "UPDATE pre_alt_itens
         SET resultado = 'ALTERACAO', nivel_complexidade = NULL, tipo_alteracao = NULL, acao = NULL,
             necessita_retorno = 0, reanalise_pos_retorno = 1, updated_at = NOW()
         WHERE id = ?"
    );
    if (!$stmtVinculo || !$stmtSolicitacao || !$stmtAprovada || !$stmtAlteracao) {
        throw new RuntimeException('Nao foi possivel preparar a atualizacao das imagens.');
    }

    $batchId = pre_alt_batch_id();
    foreach ($itemIds as $itemId) {
        $item = $itens[$itemId];
        $snapshot = '';
        if ($tipo === 'RETORNO' && $resultadoRetorno === 'ALTERACAO') {
            $snapshot = json_encode([
                'resultado' => $item['resultado'],
                'nivel_complexidade' => isset($item['nivel_complexidade']) ? (int) $item['nivel_complexidade'] : null,
                'tipo_alteracao' => $item['tipo_alteracao'],
                'acao' => $item['acao'],
                'necessita_retorno' => (int) $item['necessita_retorno'],
                'quantidade_comentarios' => isset($item['quantidade_comentarios']) ? (int) $item['quantidade_comentarios'] : null,
            ], JSON_UNESCAPED_UNICODE) ?: '';
        }
        $stmtVinculo->bind_param('iis', $interacaoId, $itemId, $snapshot);
        if (!$stmtVinculo->execute()) {
            throw new RuntimeException('Nao foi possivel vincular a imagem a interacao.');
        }

        if ($tipo === 'SOLICITACAO') {
            $stmtSolicitacao->bind_param('i', $itemId);
            $stmtSolicitacao->execute();
            pre_alt_registrar_historico(
                $conn, $loteId, 'SOLICITACAO_CLIENTE', 'resultado', $item['resultado'], 'AGUARDANDO_CLIENTE',
                $observacao !== '' ? $observacao : 'Solicitacao ao cliente registrada.', $itemId, $batchId,
                ['interacao_id' => $interacaoId, 'ocorrido_em' => $ocorridoEm]
            );
        } elseif ($resultadoRetorno === 'APROVADA') {
            $stmtAprovada->bind_param('i', $itemId);
            $stmtAprovada->execute();
            pre_alt_registrar_historico(
                $conn, $loteId, 'RETORNO_CLIENTE', 'resultado', $item['resultado'], 'SEM_ALTERACAO',
                $observacao !== '' ? $observacao : 'Cliente aprovou a imagem.', $itemId, $batchId,
                ['interacao_id' => $interacaoId, 'ocorrido_em' => $ocorridoEm, 'resultado_retorno' => 'APROVADA']
            );
        } else {
            $stmtAlteracao->bind_param('i', $itemId);
            $stmtAlteracao->execute();
            pre_alt_registrar_historico(
                $conn, $loteId, 'RETORNO_CLIENTE', 'resultado', $item['resultado'], 'ALTERACAO',
                $observacao !== '' ? $observacao : 'Cliente retornou solicitando alteracao; reanalise necessaria.', $itemId, $batchId,
                ['interacao_id' => $interacaoId, 'ocorrido_em' => $ocorridoEm, 'resultado_retorno' => 'ALTERACAO']
            );
        }
    }
    $stmtVinculo->close();
    $stmtSolicitacao->close();
    $stmtAprovada->close();
    $stmtAlteracao->close();

    $statusLote = pre_alt_recalcular_status_lote(
        $conn,
        $loteId,
        $batchId,
        $tipo === 'SOLICITACAO' ? 'Status recalculado apos solicitacao ao cliente.' : 'Status recalculado apos retorno do cliente.'
    );
    $conn->commit();

    echo json_encode([
        'success' => true,
        'interacao_id' => $interacaoId,
        'lote_id' => $loteId,
        'lote_status' => $statusLote,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    @$conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
