<?php

/** Custos V2: monetary arithmetic in cents; no current collaborator tariffs. */
function custos_centavos($value): int
{
    if ($value === null || $value === '') return 0;
    if (!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('Valor financeiro inválido.');
    return (int) round((float)$value * 100);
}

function custos_tipo(array $item): string
{
    if (!empty($item['tipo_lancamento'])) return $item['tipo_lancamento'];
    // Compatibility adapter only. New writers persist a structured type when migrated.
    $legacy = ['finalização parcial' => 'FINALIZACAO_PARCIAL', 'pago completa' => 'FINALIZACAO_COMPLEMENTO', 'comissão gestor' => 'COMISSAO'];
    return $legacy[mb_strtolower(trim($item['observacao'] ?? ''))] ?? ['funcao_imagem' => 'TAREFA', 'acompanhamento' => 'ACOMPANHAMENTO', 'funcao_animacao' => 'ANIMACAO', 'animacao' => 'ANIMACAO'][$item['origem'] ?? ''] ?? 'NAO_CLASSIFICADO';
}

function custos_saude(int $liquido, int $margem, bool $incompleto, ?float $meta = null): array
{
    if ($margem < 0) return ['codigo' => 'critico', 'texto' => 'Crítico', 'motivo' => 'Produção acima da receita líquida.'];
    if ($incompleto || $liquido <= 0) return ['codigo' => 'atencao', 'texto' => 'Atenção', 'motivo' => 'Receita incompleta ou divergência financeira.'];
    if ($meta === null) return ['codigo' => 'neutro', 'texto' => 'Margem positiva', 'motivo' => 'Meta de margem não definida.'];
    return $margem / $liquido * 100 >= $meta
        ? ['codigo' => 'saudavel', 'texto' => 'Saudável', 'motivo' => 'Margem dentro da meta configurada.']
        : ['codigo' => 'atencao', 'texto' => 'Atenção', 'motivo' => 'Margem abaixo da meta configurada.'];
}

/** Input collections are loaded once per project, never by image. Also usable offline. */
function custos_calcular(array $dados, ?float $meta = null): array
{
    $empty = fn() => ['vendido' => 0, 'impostos' => 0, 'comissao' => 0, 'liquido' => 0, 'previsto' => 0, 'realizado' => 0, 'a_pagar' => 0, 'projetado' => 0, 'margem' => 0];
    $imagens = [];
    $gerais = ['id' => null, 'nome' => 'Custos gerais da obra', 'totais' => $empty(), 'producao' => [], 'comercial' => [], 'alertas' => []];
    foreach ($dados['imagens'] as $i) $imagens[(int)$i['id']] = $i + ['totais' => $empty(), 'producao' => [], 'comercial' => [], 'alertas' => []];
    foreach ($dados['comercial'] as $c) {
        $id = (int)($c['imagem_id'] ?? 0);
        if ($id && !isset($imagens[$id])) throw new RuntimeException('Receita vinculada a imagem de outra obra.');
        $dest = &$gerais;
        if ($id) $dest = &$imagens[$id];
        foreach (['vendido' => 'valor', 'impostos' => 'valor_imposto', 'comissao' => 'valor_comissao_comercial'] as $k => $field) $dest['totais'][$k] += custos_centavos($c[$field] ?? 0);
        $dest['comercial'][] = $c;
        unset($dest);
    }
    $tarefas = [];
    foreach ($dados['tarefas'] as $t) {
        $key = $t['origem'] . ':' . $t['origem_id'];
        $tarefas[$key] = $t + ['pago' => 0, 'lancamentos' => [], 'alertas' => []];
        $tarefas[$key]['previsto'] = custos_centavos($t['valor']);
        if (!empty($t['legado_sobreposto'])) $tarefas[$key]['alertas'][] = 'Animação com lançamento legado e funções: revisar possível sobreposição.';
        if ($tarefas[$key]['previsto'] < 0) $tarefas[$key]['alertas'][] = 'Previsão negativa: revisar origem ' . $key;
    }
    $recentes = [];
    $alertas = [];
    foreach ($dados['itens'] as $item) {
        $key = $item['origem'] . ':' . $item['origem_id'];
        $tipo = custos_tipo($item);
        if (!isset($tarefas[$key])) {
            $alertas[] = 'Lançamento sem origem válida: #' . $item['idpagamento_item'];
            continue;
        }
        if ($tipo === 'COMISSAO') {
            $key .= ':comissao';
            if (!isset($tarefas[$key])) {
                $base = $tarefas[$item['origem'] . ':' . $item['origem_id']];
                $tarefas[$key] = array_merge($base, ['nome_funcao' => 'Comissão gestor', 'grupo' => 'Finalização', 'previsto' => 0, 'pago' => 0, 'lancamentos' => [], 'alertas' => [], 'adicional' => true]);
            }
        }
        $v = custos_centavos($item['valor']);
        $tarefas[$key]['pago'] += $v;
        $item['tipo'] = $tipo;
        $tarefas[$key]['lancamentos'][] = $item;
        if ($v < 0) $tarefas[$key]['alertas'][] = 'Lançamento negativo: revisar #' . $item['idpagamento_item'];
        $recentes[] = $item + ['imagem_id' => $tarefas[$key]['imagem_id'], 'descricao' => $tarefas[$key]['nome_funcao'], 'imagem_nome' => $imagens[(int)$tarefas[$key]['imagem_id']]['nome'] ?? 'Custo geral da obra'];
    }
    $distribuicao = [];
    foreach ($tarefas as $t) {
        if (!empty($t['pagamento']) && !$t['lancamentos']) $t['alertas'][] = 'Origem marcada paga no legado, sem lançamento no livro financeiro. Conciliar antes de interpretar o saldo a pagar.';
        if (!empty($t['adicional'])) $t['previsto'] = $t['pago'];
        if ($t['pago'] > $t['previsto']) $t['alertas'][] = 'Pago acima do previsto: ' . $t['origem'] . ' #' . $t['origem_id'] . ' (excedente R$ ' . number_format(($t['pago'] - $t['previsto']) / 100, 2, ',', '.') . ').';
        if (count($t['lancamentos']) > 1) {
            $tipos = array_column($t['lancamentos'], 'tipo');
            sort($tipos);
            if ($tipos !== ['FINALIZACAO_COMPLEMENTO', 'FINALIZACAO_PARCIAL']) $t['alertas'][] = 'Múltiplos lançamentos na mesma origem: conferir parcelas/duplicidade.';
        }
        foreach ($t['lancamentos'] as $l) if ($l['tipo'] === 'FINALIZACAO_PARCIAL' && custos_centavos($l['valor']) > (int)round($t['previsto'] / 2)) $t['alertas'][] = 'Parcial histórico superior a 50%: confirmar valor efetivamente pago.';
        $t['a_pagar'] = max(0, $t['previsto'] - $t['pago']);
        $t['projetado'] = $t['pago'] + $t['a_pagar'];
        $id = (int)($t['imagem_id'] ?? 0);
        $dest = &$gerais;
        if ($id && isset($imagens[$id])) $dest = &$imagens[$id];
        elseif ($id) $t['alertas'][] = 'Vínculo de imagem incompatível; custo mantido na obra de origem.';
        foreach (['previsto' => 'previsto', 'realizado' => 'pago', 'a_pagar' => 'a_pagar', 'projetado' => 'projetado'] as $k => $v) $dest['totais'][$k] += $t[$v];
        $dest['producao'][] = $t;
        $dest['alertas'] = array_merge($dest['alertas'], $t['alertas']);
        $grupo = $t['grupo'] ?? ((int)($t['funcao_id'] ?? 0) === 6 ? 'Alteração' : $t['nome_funcao']);
        if (!isset($distribuicao[$grupo])) $distribuicao[$grupo] = ['nome' => $grupo, 'realizado' => 0, 'projetado' => 0];
        $distribuicao[$grupo]['realizado'] += $t['pago'];
        $distribuicao[$grupo]['projetado'] += $t['projetado'];
        unset($dest);
    }
    $resumo = $empty();
    $incompleto = false;
    foreach ($imagens as &$i) {
        if (!$i['comercial']) $i['alertas'][] = 'Imagem sem valor comercial cadastrado.';
        $i['totais']['liquido'] = $i['totais']['vendido'] - $i['totais']['impostos'] - $i['totais']['comissao'];
        $i['totais']['margem'] = $i['totais']['liquido'] - $i['totais']['projetado'];
        $i['totais']['margem_percentual'] = $i['totais']['liquido'] > 0 ? round($i['totais']['margem'] / $i['totais']['liquido'] * 100, 2) : null;
        $i['saude'] = custos_saude($i['totais']['liquido'], $i['totais']['margem'], !!$i['alertas'], $meta);
        if ($i['alertas']) $incompleto = true;
        foreach ($resumo as $k => $_) $resumo[$k] += $i['totais'][$k];
    }
    unset($i);
    $gerais['totais']['liquido'] = $gerais['totais']['vendido'] - $gerais['totais']['impostos'] - $gerais['totais']['comissao'];
    $gerais['totais']['margem'] = $gerais['totais']['liquido'] - $gerais['totais']['projetado'];
    foreach ($resumo as $k => $_) $resumo[$k] += $gerais['totais'][$k];
    $resumo['margem_percentual'] = $resumo['liquido'] > 0 ? round($resumo['margem'] / $resumo['liquido'] * 100, 2) : null;
    $resumo['saude'] = custos_saude($resumo['liquido'], $resumo['margem'], $incompleto || !!$alertas || !!$gerais['alertas'], $meta);
    usort($recentes, fn($a, $b) => strcmp($b['criado_em'], $a['criado_em']) ?: (int)$b['idpagamento_item'] - (int)$a['idpagamento_item']);
    return ['unidade_monetaria' => 'centavos', 'resumo' => $resumo, 'distribuicao' => array_values($distribuicao), 'imagens' => array_values($imagens), 'custos_gerais' => $gerais, 'pagamentos_recentes' => array_slice($recentes, 0, 30), 'eventos' => $dados['eventos'] ?? [], 'alertas' => $alertas, 'meta_margem' => $meta];
}

function custos_query(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $s = $conn->prepare($sql);
    if ($types) $s->bind_param($types, ...$args);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}

function custos_carregar(mysqli $conn, int $obra): array
{
    $imagens = custos_query($conn, 'SELECT idimagens_cliente_obra id, imagem_nome nome, tipo_imagem tipo, subtipo_imagem subtipo FROM imagens_cliente_obra WHERE obra_id=? ORDER BY idimagens_cliente_obra', 'i', [$obra]);
    $thumbs = custos_query($conn, "SELECT fi.imagem_id, MAX(h.id) thumb_id FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id JOIN historico_aprovacoes_imagens h ON h.funcao_imagem_id=fi.idfuncao_imagem WHERE i.obra_id=? AND h.media_tipo='imagem' AND h.imagem IS NOT NULL GROUP BY fi.imagem_id", 'i', [$obra]);
    $thumbMap = array_column($thumbs, 'thumb_id', 'imagem_id');
    foreach ($imagens as &$i) $i['thumbnail'] = isset($thumbMap[$i['id']]) ? 'thumbnail.php?obra_id=' . $obra . '&imagem_id=' . $i['id'] . '&id=' . $thumbMap[$i['id']] : null;
    unset($i);
    $comercial = custos_query($conn, "SELECT ic.*, 'imagem' categoria FROM imagem_comercial ic WHERE ic.obra_id=?", 'i', [$obra]);
    $fotos = custos_query($conn, "SELECT id, valor, NULL imagem_id, 'foto' categoria FROM servico_foto WHERE obra_id=?", 'i', [$obra]);
    $tarefas = custos_query($conn, "SELECT 'funcao_imagem' origem, fi.idfuncao_imagem origem_id, fi.imagem_id, fi.funcao_id, f.nome_funcao, fi.valor, fi.pagamento FROM funcao_imagem fi JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id JOIN funcao f ON f.idfuncao=fi.funcao_id WHERE i.obra_id=?
        UNION ALL SELECT 'acompanhamento', a.idacompanhamento, a.imagem_id, NULL, 'Acompanhamento', a.valor,a.pagamento FROM acompanhamento a WHERE a.obra_id=?
        UNION ALL SELECT 'funcao_animacao', fa.id, a.imagem_id, fa.funcao_id, 'Animação', fa.valor,fa.pagamento FROM funcao_animacao fa JOIN animacao a ON a.idanimacao=fa.animacao_id WHERE a.obra_id=?", 'iii', [$obra, $obra, $obra]);
    $itens = custos_query($conn, "SELECT pi.*, p.mes_ref, p.colaborador_id, p.status pagamento_status FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id
        JOIN funcao_imagem fi ON pi.origem='funcao_imagem' AND fi.idfuncao_imagem=pi.origem_id JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id WHERE i.obra_id=?
        UNION ALL SELECT pi.*, p.mes_ref, p.colaborador_id, p.status FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id JOIN acompanhamento a ON pi.origem='acompanhamento' AND a.idacompanhamento=pi.origem_id WHERE a.obra_id=?
        UNION ALL SELECT pi.*, p.mes_ref, p.colaborador_id, p.status FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id JOIN funcao_animacao fa ON pi.origem='funcao_animacao' AND fa.id=pi.origem_id JOIN animacao a ON a.idanimacao=fa.animacao_id WHERE a.obra_id=?", 'iii', [$obra, $obra, $obra]);
    $legado = custos_query($conn, "SELECT 'animacao' origem,a.idanimacao origem_id,a.imagem_id,NULL funcao_id,'Animação' nome_funcao,a.valor,EXISTS(SELECT 1 FROM funcao_animacao fa WHERE fa.animacao_id=a.idanimacao) legado_sobreposto FROM animacao a WHERE a.obra_id=? AND (NOT EXISTS(SELECT 1 FROM funcao_animacao fa WHERE fa.animacao_id=a.idanimacao) OR EXISTS(SELECT 1 FROM pagamento_itens pi WHERE pi.origem='animacao' AND pi.origem_id=a.idanimacao))", 'i', [$obra]);
    $itensLegado = custos_query($conn, "SELECT pi.*,p.mes_ref,p.colaborador_id,p.status pagamento_status FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id JOIN animacao a ON pi.origem='animacao' AND pi.origem_id=a.idanimacao WHERE a.obra_id=?", 'i', [$obra]);
    $itens = array_merge($itens, $itensLegado);
    $pids = array_values(array_unique(array_column($itens, 'pagamento_id')));
    $eventos = [];
    if ($pids) $eventos = custos_query($conn, 'SELECT idpagamento_evento,pagamento_id,tipo,descricao,criado_em FROM pagamento_eventos WHERE pagamento_id IN (' . implode(',', array_fill(0, count($pids), '?')) . ') ORDER BY criado_em DESC,idpagamento_evento DESC LIMIT 100', str_repeat('i', count($pids)), $pids);
    return ['imagens' => $imagens, 'comercial' => array_merge($comercial, $fotos), 'tarefas' => array_merge($tarefas, $legado), 'itens' => $itens, 'eventos' => $eventos];
}
