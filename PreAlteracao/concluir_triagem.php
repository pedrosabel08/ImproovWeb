<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/conclusao_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload invalido.']);
    exit;
}

$loteId = isset($input['lote_id']) ? (int) $input['lote_id'] : 0;
$dataTriagem = isset($input['data_triagem']) ? trim((string) $input['data_triagem']) : '';
$prazoEf = isset($input['prazo_ef']) ? trim((string) $input['prazo_ef']) : '';
$prazoAlteracao = isset($input['prazo_alteracao']) ? trim((string) $input['prazo_alteracao']) : '';
$observacao = isset($input['observacao']) ? trim((string) $input['observacao']) : '';

if ($loteId <= 0 || !entregas_valid_date($dataTriagem)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe lote e data de triagem validos.']);
    exit;
}

try {
    pre_alt_ensure_schema($conn);
    $conn->begin_transaction();

    $stmtLock = $conn->prepare('SELECT status FROM pre_alt_lote WHERE id = ? FOR UPDATE');
    if (!$stmtLock) {
        throw new RuntimeException('Nao foi possivel bloquear o lote para conclusao.');
    }
    $stmtLock->bind_param('i', $loteId);
    $stmtLock->execute();
    $locked = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();

    if (!$locked) {
        throw new RuntimeException('Lote de triagem nao encontrado.');
    }
    if (($locked['status'] ?? '') === 'PLANEJADO') {
        throw new RuntimeException('Este lote ja foi planejado e nao pode ser concluido novamente.');
    }

    $summary = pre_alt_fetch_conclusao_summary($conn, $loteId, $dataTriagem);
    if (!$summary['eligible']) {
        throw new RuntimeException('Conclua as pendencias antes de liberar o lote: ' . implode(' | ', $summary['pendencias']));
    }

    $totalEf = (int) $summary['grupos']['ef']['total'];
    $totalAlteracao = (int) $summary['grupos']['alteracao']['total'];

    if ($totalEf > 0 && !entregas_valid_date($prazoEf)) {
        throw new RuntimeException('Informe um prazo EF valido.');
    }
    if ($totalAlteracao > 0 && !entregas_valid_date($prazoAlteracao)) {
        throw new RuntimeException('Informe um prazo de alteracao valido.');
    }

    $obraId = (int) $summary['lote']['obra_id'];
    $statusEf = (int) $summary['grupos']['ef']['status_id'];
    $statusAlteracao = (int) $summary['grupos']['alteracao']['status_id'];
    $observacaoEntrega = $observacao !== '' ? $observacao : 'Entrega gerada pela conclusao da triagem de Pre-Alteracao.';

    $itensEf = $summary['grupos']['ef']['itens'];
    $itensAlteracao = $summary['grupos']['alteracao']['itens'];
    $idsEf = array_map(static fn(array $item): int => (int) $item['imagem_id'], $itensEf);
    $idsAlteracao = array_map(static fn(array $item): int => (int) $item['imagem_id'], $itensAlteracao);

    $entregaEfId = $totalEf > 0
        ? pre_alt_criar_entrega_conclusao($conn, $obraId, $statusEf, $dataTriagem, $prazoEf, $idsEf, $observacaoEntrega)
        : null;
    $entregaAlteracaoId = $totalAlteracao > 0
        ? pre_alt_criar_entrega_conclusao($conn, $obraId, $statusAlteracao, $dataTriagem, $prazoAlteracao, $idsAlteracao, $observacaoEntrega)
        : null;

    $stmtUpdateImagem = $conn->prepare('UPDATE imagens_cliente_obra SET status_id = ?, prazo = ? WHERE idimagens_cliente_obra = ?');
    $stmtEvento = $conn->prepare('INSERT INTO eventos_obra (descricao, data_evento, tipo_evento, obra_id, responsavel_id) VALUES (?, ?, ?, ?, ?)');
    if (!$stmtUpdateImagem) {
        throw new RuntimeException('Nao foi possivel preparar a atualizacao das imagens.');
    }

    $responsavelId = (int) $_SESSION['idcolaborador'];
    $tipoEvento = 'Entrega';

    foreach ($itensEf as $item) {
        $imagemId = (int) $item['imagem_id'];
        $funcaoId = pre_alt_upsert_funcao_alteracao($conn, $imagemId, $prazoEf);
        $stmtUpdateImagem->bind_param('isi', $statusEf, $prazoEf, $imagemId);
        $stmtUpdateImagem->execute();

        alteracoes_upsert_registro($conn, $funcaoId, $statusEf, $dataTriagem);

        if ($stmtEvento) {
            $descricao = trim((string) $item['imagem_nome']) . ' - Entrega EF';
            $stmtEvento->bind_param('sssii', $descricao, $prazoEf, $tipoEvento, $obraId, $responsavelId);
            $stmtEvento->execute();
        }
    }

    foreach ($itensAlteracao as $item) {
        $imagemId = (int) $item['imagem_id'];
        $nivel = (int) $item['nivel_complexidade'];
        $funcaoId = pre_alt_upsert_funcao_alteracao($conn, $imagemId, $prazoAlteracao);
        $stmtUpdateImagem->bind_param('isi', $statusAlteracao, $prazoAlteracao, $imagemId);
        $stmtUpdateImagem->execute();
        alteracoes_upsert_registro($conn, $funcaoId, $statusAlteracao, $dataTriagem, $nivel);

        if ($stmtEvento) {
            $descricao = trim((string) $item['imagem_nome']) . ' - Alteracao N' . $nivel;
            $stmtEvento->bind_param('sssii', $descricao, $prazoAlteracao, $tipoEvento, $obraId, $responsavelId);
            $stmtEvento->execute();
        }
    }

    $stmtUpdateImagem->close();
    if ($stmtEvento) {
        $stmtEvento->close();
    }

    $statusLoteFinal = $totalAlteracao > 0 ? $statusAlteracao : $statusEf;
    $stmtUpdateLote = $conn->prepare("UPDATE pre_alt_lote SET status_id = ?, status = 'PLANEJADO', prazo = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmtUpdateLote) {
        throw new RuntimeException('Nao foi possivel atualizar o lote.');
    }
    $prazoLote = $totalAlteracao > 0 ? $prazoAlteracao : $prazoEf;
    $stmtUpdateLote->bind_param('isi', $statusLoteFinal, $prazoLote, $loteId);
    $stmtUpdateLote->execute();
    $stmtUpdateLote->close();

    pre_alt_registrar_historico(
        $conn,
        $loteId,
        'CONCLUSAO_TRIAGEM',
        'status',
        $locked['status'] ?? null,
        'PLANEJADO',
        $observacao !== '' ? $observacao : null,
        null,
        pre_alt_batch_id(),
        [
            'data_triagem' => $dataTriagem,
            'entrega_ef_id' => $entregaEfId,
            'entrega_alteracao_id' => $entregaAlteracaoId,
            'status_id_anterior' => (int) $summary['lote']['status_id'],
            'status_id_final' => $statusLoteFinal,
            'totais' => $summary['totais'],
        ]
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Triagem concluida e lote liberado.',
        'entregas' => [
            'ef' => $entregaEfId,
            'alteracao' => $entregaAlteracaoId,
        ],
        'totais' => $summary['totais'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
