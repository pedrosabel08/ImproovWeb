<?php
require_once __DIR__ . '/PagamentoService.php';
require_once __DIR__ . '/../helpers/custos_helper.php';

/** Shared eligibility for screen and all payment writers. Historical status is the
 * status at competence end, matching the individual screen's existing contract. */
function financeiro_elegiveis(mysqli $conn, int $colab, int $mes, int $ano): array
{
    $ref = PagamentoService::competencia($mes, $ano);
    $inicio = $ref . '-01';
    $fim = (new DateTimeImmutable($inicio))->modify('first day of next month')->format('Y-m-d');
    $status = "('finalizado','em aprovação','ajuste','aprovado com ajustes','aprovado')";
    $fi = custos_query(
        $conn,
        "SELECT fi.*, 'funcao_imagem' origem, fi.idfuncao_imagem origem_id, i.tipo_imagem, i.imagem_nome,
        CASE WHEN fi.funcao_id=4 AND (EXISTS(SELECT 1 FROM funcao_imagem fp JOIN funcao f ON f.idfuncao=fp.funcao_id WHERE fp.imagem_id=fi.imagem_id AND f.nome_funcao='Pré-Finalização') OR
        (SELECT h.status_id FROM historico_imagens h WHERE h.imagem_id=fi.imagem_id AND h.data_movimento < ? ORDER BY h.data_movimento DESC,h.status_id DESC LIMIT 1)=1) THEN 1 ELSE 0 END parcial
        FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
        WHERE (fi.colaborador_id=? OR (?=8 AND fi.colaborador_id IN (23,40) AND fi.funcao_id=4))
        AND ((LOWER(TRIM(fi.status)) IN $status AND fi.prazo>=? AND fi.prazo<?)
          OR EXISTS(SELECT 1 FROM log_alteracoes l WHERE l.funcao_imagem_id=fi.idfuncao_imagem AND l.data>=? AND l.data<? AND LOWER(TRIM(l.status_novo)) IN $status))",
        'siissss',
        [$fim, $colab, $colab, $inicio, $fim, $inicio, $fim]
    );
    $ac = custos_query($conn, "SELECT a.*, 'acompanhamento' origem, a.idacompanhamento origem_id, 0 parcial FROM acompanhamento a WHERE a.colaborador_id=? AND a.data>=? AND a.data<?", 'iss', [$colab, $inicio, $fim]);
    $an = custos_query($conn, "SELECT fa.*, 'funcao_animacao' origem, fa.id origem_id, 0 parcial FROM funcao_animacao fa JOIN animacao a ON a.idanimacao=fa.animacao_id WHERE fa.colaborador_id=? AND a.data_anima>=? AND a.data_anima<? AND LOWER(TRIM(fa.status)) IN $status", 'iss', [$colab, $inicio, $fim]);
    foreach ($fi as &$r) $r['comissao_gestor'] = (int)$r['colaborador_id'] !== $colab;
    unset($r);
    return array_merge($fi, $ac, $an);
}

function financeiro_total(mysqli $conn, int $id): void
{
    $s = $conn->prepare('UPDATE pagamentos SET valor_total=(SELECT COALESCE(SUM(valor),0) FROM pagamento_itens WHERE pagamento_id=?) WHERE idpagamento=?');
    $s->bind_param('ii', $id, $id);
    $s->execute();
    $s->close();
}

function financeiro_tem_semantica(mysqli $conn): bool
{
    return $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'tipo_lancamento'")->num_rows > 0;
}

/** Caller owns transaction. Lock origin BEFORE checking the ledger, across months.
 * No client amount is accepted. Multiple entries are legitimate only as installments. */
function financeiro_lancar(mysqli $conn, array $row, int $colab, int $mes, int $ano, ?int $user, string $mode = 'normal', ?string $date = null): array
{
    $origem = $row['origem'];
    $id = (int)$row['origem_id'];
    $tables = ['funcao_imagem' => 'idfuncao_imagem', 'acompanhamento' => 'idacompanhamento', 'funcao_animacao' => 'id'];
    if (!isset($tables[$origem])) throw new InvalidArgumentException('Origem inválida.');
    $locked = custos_query($conn, "SELECT * FROM $origem WHERE {$tables[$origem]}=? FOR UPDATE", 'i', [$id])[0] ?? null;
    if (!$locked) throw new InvalidArgumentException('Origem não encontrada.');
    $commission = !empty($row['comissao_gestor']);
    if ($commission) {
        if ($colab !== 8 || !in_array((int)$locked['colaborador_id'], [23, 40], true) || (int)$locked['funcao_id'] !== 4 || !empty($row['parcial'])) throw new InvalidArgumentException('Comissão não elegível.');
    } elseif ((int)$locked['colaborador_id'] !== $colab) throw new InvalidArgumentException('Colaborador incompatível com a origem.');
    $items = custos_query($conn, 'SELECT * FROM pagamento_itens WHERE origem=? AND origem_id=? ORDER BY idpagamento_item FOR UPDATE', 'si', [$origem, $id]);
    $pago = 0;
    $applicable = [];
    foreach ($items as $i) {
        if ((custos_tipo($i) === 'COMISSAO') !== $commission) continue;
        $pago += custos_centavos($i['valor']);
        $applicable[] = $i;
    }
    $previsto = custos_centavos($locked['valor']);
    if ($commission) $previsto = ($row['tipo_imagem'] === 'Fachada' && mb_stripos($row['imagem_nome'], 'embasamento') === false) ? 10000 : 8000;
    if ($previsto < 0 || $pago < 0 || $pago > $previsto) throw new DomainException('Divergência financeira na origem ' . $origem . ' #' . $id . '. Reconcilie antes de pagar.');
    if (count($applicable) > 1) {
        $types = array_map('custos_tipo', $applicable);
        sort($types);
        if ($types !== ['FINALIZACAO_COMPLEMENTO', 'FINALIZACAO_PARCIAL']) throw new DomainException('Lançamentos repetidos exigem reconciliação.');
    }
    if ($mode === 'parcial') {
        if ($origem !== 'funcao_imagem' || (int)$locked['funcao_id'] !== 4 || $commission) throw new InvalidArgumentException('Parcela exige tarefa de Finalização.');
        if ($applicable) return ['id' => $id, 'skipped' => true];
        $valor = (int)round($previsto / 2);
        $tipo = 'FINALIZACAO_PARCIAL';
        $obs = 'Finalização Parcial';
    } else {
        if (!empty($row['parcial'])) return ['id' => $id, 'skipped' => true];
        $valor = $previsto - $pago;
        $tipo = $commission ? 'COMISSAO' : ($applicable && $origem === 'funcao_imagem' && (int)$locked['funcao_id'] === 4 ? 'FINALIZACAO_COMPLEMENTO' : ['funcao_imagem' => 'TAREFA', 'funcao_animacao' => 'ANIMACAO', 'acompanhamento' => 'ACOMPANHAMENTO'][$origem]);
        $obs = $commission ? 'Comissão Gestor' : ($tipo === 'FINALIZACAO_COMPLEMENTO' ? 'Pago Completa' : null);
        if ($valor === 0 || ($applicable && $tipo !== 'FINALIZACAO_COMPLEMENTO')) return ['id' => $id, 'skipped' => true];
        if ($applicable && custos_tipo($applicable[0]) !== 'FINALIZACAO_PARCIAL') throw new DomainException('Complemento sem parcela identificada.');
    }
    if ($valor === 0) return ['id' => $id, 'skipped' => true];
    if (!$items && (int)($locked['pagamento'] ?? 0) === 1 && $mode === 'normal' && !$commission) throw new DomainException('Origem marcada paga sem livro financeiro. Use reconciliação manual.');
    $service = new PagamentoService($conn, $user);
    $pid = $service->garantirPagamento($colab, $mes, $ano);
    $amount = number_format($valor / 100, 2, '.', '');
    $date = $date ?? date('Y-m-d');
    $key = $origem . ':' . $id . ':' . $tipo;
    if (financeiro_tem_semantica($conn)) {
        $s = $conn->prepare('INSERT INTO pagamento_itens (pagamento_id,origem,origem_id,valor,observacao,tipo_lancamento,chave_lancamento) VALUES (?,?,?,?,?,?,?)');
        $s->bind_param('isissss', $pid, $origem, $id, $amount, $obs, $tipo, $key);
    } else {
        $s = $conn->prepare('INSERT INTO pagamento_itens (pagamento_id,origem,origem_id,valor,observacao) VALUES (?,?,?,?,?)');
        $s->bind_param('isiss', $pid, $origem, $id, $amount, $obs);
    }
    $s->execute();
    $s->close();
    if (!$commission) {
        $s = $conn->prepare("UPDATE $origem SET pagamento=1,data_pagamento=? WHERE {$tables[$origem]}=?");
        $s->bind_param('si', $date, $id);
        $s->execute();
        $s->close();
    }
    financeiro_total($conn, $pid);
    $s = $conn->prepare("UPDATE pagamentos SET status='pago', data_pagamento=?,pago_em=NOW() WHERE idpagamento=?");
    $s->bind_param('si', $date, $pid);
    $s->execute();
    $s->close();
    $service->registrarEvento($pid, 'pago', $tipo . ' · ' . $origem . ' #' . $id . ' · R$ ' . $amount . ' · data efetiva ' . $date);
    return ['id' => $id, 'pagamento_id' => $pid, 'valor' => $amount];
}

function financeiro_pagar(mysqli $conn, array $input, ?int $user): array
{
    $colab = (int)($input['colaborador_id'] ?? 0);
    $mes = (int)($input['mes'] ?? 0);
    $ano = (int)($input['ano'] ?? 0);
    if ($colab <= 0) throw new InvalidArgumentException('Colaborador obrigatório.');
    $conn->begin_transaction();
    try {
        $eligible = financeiro_elegiveis($conn, $colab, $mes, $ano);
        $map = [];
        foreach ($eligible as $r) $map[$r['origem'] . ':' . $r['origem_id']] = $r;
        $selected = $input['ids'] ?? array_map(fn($r) => ['origem' => $r['origem'], 'id' => $r['origem_id']], $eligible);
        if (!is_array($selected)) throw new InvalidArgumentException('Seleção inválida.');
        usort($selected, fn($a, $b) => strcmp($a['origem'], $b['origem']) ?: (int)$a['id'] - (int)$b['id']);
        $out = [];
        foreach ($selected as $s) {
            $key = ($s['origem'] ?? '') . ':' . (int)($s['id'] ?? 0);
            if (!isset($map[$key])) throw new InvalidArgumentException('Item não elegível nesta competência: ' . $key);
            $r = $map[$key];
            if (!empty($r['parcial'])) {
                if (isset($input['ids'])) throw new DomainException('Finalização parcial exige o registro específico.');
                continue;
            }
            $out[] = financeiro_lancar($conn, $r, $colab, $mes, $ano, $user);
        }
        $conn->commit();
        return $out;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
