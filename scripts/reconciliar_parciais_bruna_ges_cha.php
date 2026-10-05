<?php
/**
 * Corrige os 12 pagamentos parciais de finalização da Bruna (GES_CHA)
 * identificados na competência setembro/2026. Rode sem --apply para revisar.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../Pagamento/financeiro_v2.php';

$origemIds = [
    109687, 109688, 109690, 109691, 109692, 109693,
    110518, 110520, 110524, 110526, 110528, 110532,
];
$esperados = count($origemIds);
$ph = implode(',', array_fill(0, $esperados, '?'));
$types = str_repeat('i', $esperados);
$semTipo = !financeiro_tem_semantica($conn);
$tipoSelect = $semTipo ? "'' AS tipo_lancamento" : 'pi.tipo_lancamento';
$rows = custos_query(
    $conn,
    "SELECT pi.idpagamento_item, pi.pagamento_id, pi.origem, pi.origem_id,
            pi.valor, pi.observacao, $tipoSelect,
            fi.colaborador_id, fi.funcao_id, fi.valor AS valor_origem,
            fi.valor_aprovado, fc.valor AS valor_tarifado, fi.prazo,
            i.imagem_nome
     FROM pagamento_itens pi
     JOIN funcao_imagem fi ON pi.origem='funcao_imagem' AND fi.idfuncao_imagem=pi.origem_id
     JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
     LEFT JOIN funcao_colaborador fc ON fc.colaborador_id=fi.colaborador_id AND fc.funcao_id=fi.funcao_id
     WHERE pi.origem_id IN ($ph)
     ORDER BY pi.origem_id, pi.idpagamento_item",
    $types,
    $origemIds
);

$itemsByOrigin = [];
foreach ($rows as $item) {
    if ((int)$item['colaborador_id'] !== 6 || (int)$item['funcao_id'] !== 4
        || (string)$item['prazo'] < '2026-09-01' || (string)$item['prazo'] >= '2026-10-01'
        || !str_contains($item['imagem_nome'], 'GES_CHA')) {
        throw new RuntimeException('A validação de colaborador, função, competência ou obra falhou.');
    }
    $itemsByOrigin[(int)$item['origem_id']][] = $item;
}

$targets = [];
$ajustes = [];
foreach ($origemIds as $originId) {
    $items = $itemsByOrigin[$originId] ?? [];
    $partials = array_values(array_filter($items, static fn($item) => custos_tipo($item) === 'FINALIZACAO_PARCIAL'));
    if (count($partials) !== 1 || count($items) !== 1) {
        throw new RuntimeException("A origem #$originId não tem exatamente um lançamento parcial isolado.");
    }
    $item = $partials[0];
    $valorParcela = custos_centavos($item['valor']);
    $valorOrigem = custos_centavos($item['valor_origem']);
    if (!in_array($valorParcela, [19000, 38000], true) || !in_array($valorOrigem, [19000, 38000], true)
        || custos_centavos($item['valor_tarifado']) !== 38000 || empty($item['valor_aprovado'])) {
        throw new RuntimeException(sprintf(
            'A origem #%d não está no estado esperado (parcela R$ %s; origem R$ %s; tarifa R$ %s; valor aprovado %s).',
            $originId,
            number_format((float)$item['valor'], 2, ',', '.'),
            number_format((float)$item['valor_origem'], 2, ',', '.'),
            number_format((float)$item['valor_tarifado'], 2, ',', '.'),
            !empty($item['valor_aprovado']) ? 'sim' : 'não'
        ));
    }
    $targets[] = $item;
    if ($valorParcela !== 19000 || $valorOrigem !== 38000) {
        $item['_ajustar_parcela'] = $valorParcela !== 19000;
        $item['_ajustar_origem'] = $valorOrigem !== 38000;
        $ajustes[] = $item;
    }
}

if (count($targets) !== $esperados) {
    throw new RuntimeException('A quantidade de lançamentos não corresponde aos 12 itens esperados.');
}

foreach ($targets as $item) {
    $valorParcela = custos_centavos($item['valor']) / 100;
    $valorOrigem = custos_centavos($item['valor_origem']) / 100;
    $origemInfo = $valorOrigem === 190 ? 'R$ 190,00 -> R$ 380,00' : 'já está em R$ 380,00';
    $parcelaInfo = $valorParcela === 380 ? 'R$ 380,00 -> R$ 190,00' : 'já está em R$ 190,00';
    echo sprintf("%s | origem %s | parcela %s\n", $item['imagem_nome'], $origemInfo, $parcelaInfo);
}

if (!in_array('--apply', $argv, true)) {
    echo "Prévia: nenhum dado foi alterado. Use --apply para aplicar esta reconciliação.\n";
    exit(0);
}

$pagamentoIds = array_values(array_unique(array_map(static fn($item) => (int)$item['pagamento_id'], $targets)));
if (!$ajustes) {
    echo "Todas as tarefas já estão com valor cheio de R$ 380,00 e parcela de R$ 190,00; nenhum dado foi alterado.\n";
    exit(0);
}

$conn->begin_transaction();
try {
    $updateItem = $conn->prepare('UPDATE pagamento_itens SET valor=190.00 WHERE idpagamento_item=? AND valor=380.00');
    $updateOrigin = $conn->prepare('UPDATE funcao_imagem SET valor=380.00 WHERE idfuncao_imagem=? AND valor=190.00 AND COALESCE(valor_aprovado,0)=1');
    if (!$updateItem || !$updateOrigin) throw new RuntimeException('Não foi possível preparar a correção das tarefas e parcelas.');
    foreach ($ajustes as $item) {
        if ($item['_ajustar_parcela']) {
            $itemId = (int)$item['idpagamento_item'];
            $updateItem->bind_param('i', $itemId);
            $updateItem->execute();
            if ($updateItem->affected_rows !== 1) throw new RuntimeException("O lançamento #$itemId mudou durante a reconciliação.");
        }
        if ($item['_ajustar_origem']) {
            $originId = (int)$item['origem_id'];
            $updateOrigin->bind_param('i', $originId);
            $updateOrigin->execute();
            if ($updateOrigin->affected_rows !== 1) throw new RuntimeException("A origem #$originId mudou durante a reconciliação.");
        }
    }
    $updateItem->close();
    $updateOrigin->close();

    $hasParcelChanges = count(array_filter($ajustes, static fn($item) => $item['_ajustar_parcela'])) > 0;
    if ($hasParcelChanges) {
        $total = $conn->prepare('UPDATE pagamentos SET valor_total=(SELECT COALESCE(SUM(valor),0) FROM pagamento_itens WHERE pagamento_id=?) WHERE idpagamento=?');
        if (!$total) throw new RuntimeException('Não foi possível preparar a atualização dos totais.');
        foreach ($pagamentoIds as $paymentId) {
            $total->bind_param('ii', $paymentId, $paymentId);
            $total->execute();
        }
        $total->close();
    }

    $audit = $conn->prepare('INSERT INTO pagamento_eventos (pagamento_id,tipo,descricao,usuario_id) VALUES (?,?,?,NULL)');
    if (!$audit) throw new RuntimeException('Não foi possível preparar o registro de auditoria.');
    $eventType = 'reconciliacao_financeira';
    foreach ($pagamentoIds as $paymentId) {
        $description = sprintf(
            'Reconciliação solicitada: %d tarefas de Finalização Parcial da Bruna em GES_CHA com valor cheio R$ 380,00 e parcela R$ 190,00.',
            count(array_filter($ajustes, static fn($item) => (int)$item['pagamento_id'] === $paymentId))
        );
        $audit->bind_param('iss', $paymentId, $eventType, $description);
        $audit->execute();
    }
    $audit->close();
    $conn->commit();
    echo "Reconciliação aplicada; totais dos pagamentos recalculados e auditados.\n";
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
} finally {
    $conn->close();
}
