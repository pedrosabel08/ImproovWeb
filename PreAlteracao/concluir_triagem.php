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
$prazoRetornoEf = isset($input['prazo_retorno_ef']) ? trim((string) $input['prazo_retorno_ef']) : '';
$observacao = isset($input['observacao']) ? trim((string) $input['observacao']) : '';

function pre_alt_observacao_retorno_ef(array $itens, string $observacao): string
{
    $linhas = [
        'Retorno da Pré-Alteração para EF — ajustes solicitados pelo cliente. Não reenviar para aprovação.',
    ];

    if ($observacao !== '') {
        $linhas[] = '';
        $linhas[] = 'Observação complementar: ' . $observacao;
    }

    $linhas[] = '';
    $linhas[] = 'Instruções por imagem:';
    foreach ($itens as $item) {
        $nome = trim((string) ($item['imagem_nome'] ?? ('Imagem ' . ($item['imagem_id'] ?? ''))));
        $acao = trim((string) ($item['acao'] ?? ''));
        $linhas[] = '- ' . $nome . ': ' . ($acao !== '' ? $acao : 'Sem instrução adicional registrada na triagem.');
    }

    return implode("\n", $linhas);
}

if ($loteId <= 0 || !entregas_valid_date($dataTriagem)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe lote e data de triagem validos.']);
    exit;
}

try {
    pre_alt_ensure_schema($conn);
    $conn->begin_transaction();

    $stmtLock = $conn->prepare('SELECT status, status_id FROM pre_alt_lote WHERE id = ? FOR UPDATE');
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
    if (in_array(($locked['status'] ?? ''), ['PLANEJADO', 'CANCELADO'], true)) {
        throw new RuntimeException('Este lote nao pode receber novas liberacoes.');
    }

    $stmtLockItens = $conn->prepare('SELECT id FROM pre_alt_itens WHERE pre_alt_lote_id = ? FOR UPDATE');
    if (!$stmtLockItens) {
        throw new RuntimeException('Nao foi possivel bloquear as imagens para liberacao.');
    }
    $stmtLockItens->bind_param('i', $loteId);
    $stmtLockItens->execute();
    $stmtLockItens->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLockItens->close();

    $summary = pre_alt_fetch_conclusao_summary($conn, $loteId, $dataTriagem);
    if (!$summary['eligible']) {
        throw new RuntimeException('Nao ha imagens prontas para liberar: ' . implode(' | ', $summary['pendencias']));
    }

    $totalEf = (int) $summary['grupos']['ef']['total'];
    $totalAlteracao = (int) $summary['grupos']['alteracao']['total'];
    $totalRetornoEf = (int) $summary['grupos']['retorno_ef']['total'];
    $totalSemAlteracaoOrigemEf = (int) $summary['grupos']['sem_alteracao_origem_ef']['total'];

    if ($totalEf > 0 && !entregas_valid_date($prazoEf)) {
        throw new RuntimeException('Informe um prazo EF valido.');
    }
    if ($totalAlteracao > 0 && !entregas_valid_date($prazoAlteracao)) {
        throw new RuntimeException('Informe um prazo de alteracao valido.');
    }
    if ($totalRetornoEf > 0 && !entregas_valid_date($prazoRetornoEf)) {
        throw new RuntimeException('Informe um prazo de retorno para EF válido.');
    }

    $obraId = (int) $summary['lote']['obra_id'];
    $statusEf = (int) $summary['grupos']['ef']['status_id'];
    $statusAlteracao = (int) $summary['grupos']['alteracao']['status_id'];
    $observacaoEntrega = $observacao !== '' ? $observacao : 'Entrega gerada pela conclusao da triagem de Pre-Alteracao.';

    $itensEf = $summary['grupos']['ef']['itens'];
    $itensAlteracao = $summary['grupos']['alteracao']['itens'];
    $itensRetornoEf = $summary['grupos']['retorno_ef']['itens'];
    $itensSemAlteracaoOrigemEf = $summary['grupos']['sem_alteracao_origem_ef']['itens'];
    $idsEf = array_map(static fn(array $item): int => (int) $item['imagem_id'], $itensEf);
    $idsAlteracao = array_map(static fn(array $item): int => (int) $item['imagem_id'], $itensAlteracao);
    $idsRetornoEf = array_map(static fn(array $item): int => (int) $item['imagem_id'], $itensRetornoEf);

    $entregaEfId = $totalEf > 0
        ? pre_alt_criar_entrega_conclusao($conn, $obraId, $statusEf, $dataTriagem, $prazoEf, $idsEf, $observacaoEntrega)
        : null;
    $entregaAlteracaoId = $totalAlteracao > 0
        ? pre_alt_criar_entrega_conclusao($conn, $obraId, $statusAlteracao, $dataTriagem, $prazoAlteracao, $idsAlteracao, $observacaoEntrega)
        : null;
    $observacaoRetornoEf = $totalRetornoEf > 0
        ? pre_alt_observacao_retorno_ef($itensRetornoEf, $observacao)
        : null;
    $entregaRetornoEfId = $totalRetornoEf > 0
        ? pre_alt_criar_entrega_conclusao($conn, $obraId, 6, $dataTriagem, $prazoRetornoEf, $idsRetornoEf, $observacaoRetornoEf)
        : null;

    $responsavelId = (int) $_SESSION['idcolaborador'];
    $stmtLiberacao = $conn->prepare(
        'INSERT INTO pre_alt_liberacoes (
            pre_alt_lote_id, data_triagem, entrega_ef_id, entrega_alteracao_id, observacao, created_by
         ) VALUES (?, ?, ?, ?, NULLIF(?, \'\'), ?)'
    );
    if (!$stmtLiberacao) {
        throw new RuntimeException('Nao foi possivel registrar a liberacao parcial.');
    }
    $entregaEfLiberacaoId = $entregaRetornoEfId ?: $entregaEfId;
    $stmtLiberacao->bind_param('isiisi', $loteId, $dataTriagem, $entregaEfLiberacaoId, $entregaAlteracaoId, $observacao, $responsavelId);
    $stmtLiberacao->execute();
    $liberacaoId = (int) $stmtLiberacao->insert_id;
    $stmtLiberacao->close();

    $stmtLiberacaoItem = $conn->prepare(
        'INSERT INTO pre_alt_liberacao_itens (
            liberacao_id, pre_alt_item_id, entrega_destino_id, status_destino_id, prazo
         ) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmtLiberacaoItem) {
        throw new RuntimeException('Nao foi possivel vincular as imagens a liberacao.');
    }
    foreach ($itensEf as $item) {
        $itemId = (int) $item['item_id'];
        $stmtLiberacaoItem->bind_param('iiiis', $liberacaoId, $itemId, $entregaEfId, $statusEf, $prazoEf);
        $stmtLiberacaoItem->execute();
    }
    foreach ($itensAlteracao as $item) {
        $itemId = (int) $item['item_id'];
        $stmtLiberacaoItem->bind_param('iiiis', $liberacaoId, $itemId, $entregaAlteracaoId, $statusAlteracao, $prazoAlteracao);
        $stmtLiberacaoItem->execute();
    }
    foreach ($itensRetornoEf as $item) {
        $itemId = (int) $item['item_id'];
        $stmtLiberacaoItem->bind_param('iiiis', $liberacaoId, $itemId, $entregaRetornoEfId, $statusEf, $prazoRetornoEf);
        $stmtLiberacaoItem->execute();
    }
    foreach ($itensSemAlteracaoOrigemEf as $item) {
        $itemId = (int) $item['item_id'];
        $entregaOrigemId = (int) ($item['entrega_origem_id'] ?? 0);
        if ($entregaOrigemId <= 0) {
            throw new RuntimeException('A imagem aprovada não possui entrega EF de origem para registrar a liberação.');
        }
        $stmtLiberacaoItem->bind_param('iiiis', $liberacaoId, $itemId, $entregaOrigemId, $statusEf, $dataTriagem);
        $stmtLiberacaoItem->execute();
    }
    $stmtLiberacaoItem->close();

    $stmtUpdateImagem = $conn->prepare('UPDATE imagens_cliente_obra SET status_id = ?, prazo = ? WHERE idimagens_cliente_obra = ?');
    $stmtUpdateImagemRetornoEf = $conn->prepare('UPDATE imagens_cliente_obra SET status_id = ?, substatus_id = 2, prazo = ? WHERE idimagens_cliente_obra = ?');
    $stmtEvento = $conn->prepare('INSERT INTO eventos_obra (descricao, data_evento, tipo_evento, obra_id, responsavel_id) VALUES (?, ?, ?, ?, ?)');
    if (!$stmtUpdateImagem || !$stmtUpdateImagemRetornoEf) {
        throw new RuntimeException('Nao foi possivel preparar a atualizacao das imagens.');
    }

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

    foreach ($itensRetornoEf as $item) {
        $imagemId = (int) $item['imagem_id'];
        $nivel = (int) $item['nivel_complexidade'];
        $funcaoId = pre_alt_upsert_funcao_alteracao($conn, $imagemId, $prazoRetornoEf);
        // TO-DO (2) devolve a imagem para producao sem entrar em RVW (6).
        $stmtUpdateImagemRetornoEf->bind_param('isi', $statusEf, $prazoRetornoEf, $imagemId);
        $stmtUpdateImagemRetornoEf->execute();
        alteracoes_upsert_registro($conn, $funcaoId, $statusEf, $dataTriagem, $nivel);

        if ($stmtEvento) {
            $descricao = trim((string) $item['imagem_nome']) . ' - Retorno à EF - alteração solicitada N' . $nivel;
            $stmtEvento->bind_param('sssii', $descricao, $prazoRetornoEf, $tipoEvento, $obraId, $responsavelId);
            $stmtEvento->execute();
        }
    }

    $stmtUpdateImagem->close();
    $stmtUpdateImagemRetornoEf->close();
    if ($stmtEvento) {
        $stmtEvento->close();
    }

    $stmtTotais = $conn->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN pli.id IS NOT NULL THEN 1 ELSE 0 END) AS liberadas,
            MAX(CASE WHEN pli.status_destino_id <> 6 THEN pli.status_destino_id ELSE NULL END) AS status_alteracao,
            MAX(CASE WHEN pli.status_destino_id <> 6 THEN pli.prazo ELSE NULL END) AS prazo_alteracao,
            MAX(CASE WHEN pli.status_destino_id = 6 THEN pli.prazo ELSE NULL END) AS prazo_ef
         FROM pre_alt_itens pai
         LEFT JOIN pre_alt_liberacao_itens pli ON pli.pre_alt_item_id = pai.id
         WHERE pai.pre_alt_lote_id = ?"
    );
    if (!$stmtTotais) {
        throw new RuntimeException('Nao foi possivel recalcular o saldo do lote.');
    }
    $stmtTotais->bind_param('i', $loteId);
    $stmtTotais->execute();
    $saldo = $stmtTotais->get_result()->fetch_assoc();
    $stmtTotais->close();

    $totalImagens = (int) ($saldo['total'] ?? 0);
    $totalLiberadas = (int) ($saldo['liberadas'] ?? 0);
    $totalRestantes = max(0, $totalImagens - $totalLiberadas);
    $statusLote = pre_alt_recalcular_status_lote(
        $conn,
        $loteId,
        pre_alt_batch_id(),
        'Status recalculado apos liberacao de imagens prontas.'
    );

    $statusLoteFinal = (int) ($locked['status_id'] ?? $summary['lote']['status_id']);
    if ($totalRestantes === 0) {
        $statusAlteracaoHistorico = isset($saldo['status_alteracao']) ? (int) $saldo['status_alteracao'] : 0;
        $statusLoteFinal = $statusAlteracaoHistorico > 0 ? $statusAlteracaoHistorico : $statusEf;
        $prazoLote = $statusAlteracaoHistorico > 0 ? (string) $saldo['prazo_alteracao'] : (string) $saldo['prazo_ef'];
        $stmtUpdateLote = $conn->prepare("UPDATE pre_alt_lote SET status_id = ?, status = 'PLANEJADO', prazo = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmtUpdateLote) {
            throw new RuntimeException('Nao foi possivel finalizar o lote.');
        }
        $stmtUpdateLote->bind_param('isi', $statusLoteFinal, $prazoLote, $loteId);
        $stmtUpdateLote->execute();
        $stmtUpdateLote->close();
        $statusLote = 'PLANEJADO';
    }

    pre_alt_registrar_historico(
        $conn,
        $loteId,
        'LIBERACAO_PARCIAL',
        'itens_liberados',
        null,
        (string) ($totalEf + $totalAlteracao + $totalRetornoEf + $totalSemAlteracaoOrigemEf),
        $observacao !== '' ? $observacao : null,
        null,
        pre_alt_batch_id(),
        [
            'liberacao_id' => $liberacaoId,
            'data_triagem' => $dataTriagem,
            'entrega_ef_id' => $entregaEfLiberacaoId,
            'entrega_alteracao_id' => $entregaAlteracaoId,
            'entrega_retorno_ef_id' => $entregaRetornoEfId,
            'sem_alteracao_na_entrega_origem' => $totalSemAlteracaoOrigemEf,
            'status_id_anterior' => (int) $summary['lote']['status_id'],
            'status_id_final' => $statusLoteFinal,
            'totais' => $summary['totais'],
            'liberadas_acumuladas' => $totalLiberadas,
            'restantes' => $totalRestantes,
        ]
    );

    if ($totalRestantes === 0) {
        pre_alt_registrar_historico(
            $conn,
            $loteId,
            'CONCLUSAO_TRIAGEM',
            'status',
            $locked['status'] ?? null,
            'PLANEJADO',
            $observacao !== '' ? $observacao : 'Todas as imagens do lote foram liberadas.',
            null,
            pre_alt_batch_id(),
            ['liberacao_id' => $liberacaoId, 'status_id_final' => $statusLoteFinal]
        );
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $totalRestantes === 0
            ? 'Imagens liberadas e triagem concluida.'
            : 'Imagens prontas liberadas; o lote permanece aberto para as pendentes.',
        'liberacao_id' => $liberacaoId,
        'entregas' => [
            'ef' => $entregaEfId,
            'alteracao' => $entregaAlteracaoId,
            'retorno_ef' => $entregaRetornoEfId,
        ],
        'liberadas_agora' => $totalEf + $totalAlteracao + $totalRetornoEf + $totalSemAlteracaoOrigemEf,
        'liberadas_acumuladas' => $totalLiberadas,
        'restantes' => $totalRestantes,
        'lote_status' => $statusLote,
        'totais' => $summary['totais'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
