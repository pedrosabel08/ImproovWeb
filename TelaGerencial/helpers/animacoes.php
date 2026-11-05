<?php

function tela_gerencial_animacao_categoria(int $funcaoId, ?string $tipo): string
{
    if ($funcaoId === 5) {
        return 'Pós-produção de animação';
    }
    return stripos($tipo ?? '', 'IA') !== false ? 'Animação IA' : 'Animação 3D';
}

function tela_gerencial_animacao_linha(string $categoria): array
{
    return [
        'nome_funcao' => $categoria,
        'quantidade' => 0,
        'pagas' => 0,
        'nao_pagas' => 0,
        'custo' => 0.0,
        'mes_anterior' => 0,
        'recorde_producao' => 0,
        'recorde_data' => null,
        'imagens' => [],
    ];
}

/**
 * As duas tabelas usam as mesmas tarefas, prazo da função e valor salvo.
 * Pagamentos posteriores ao período não quitam retroativamente sua produção.
 * O recorde segue a janela da tela: 36 meses, sem o mês corrente e 2024-10.
 */
function tela_gerencial_animacoes_agrupar(
    array $tarefas,
    string $inicio,
    string $fim,
    bool $historico = true,
    ?DateTimeImmutable $hoje = null
): array {
    $categorias = ['Animação 3D', 'Animação IA', 'Pós-produção de animação'];
    $funcoes = [];
    foreach ($categorias as $categoria) {
        $funcoes[$categoria] = tela_gerencial_animacao_linha($categoria);
    }
    $colaboradores = [];
    $mensais = [];
    $mensaisColaborador = [];
    $inicioAnterior = (new DateTimeImmutable($inicio))->modify('first day of previous month')->format('Y-m-d');
    $fimAnterior = (new DateTimeImmutable($inicio))->modify('last day of previous month')->format('Y-m-d');
    $hoje = $hoje ?? new DateTimeImmutable('today');
    $inicioRecorde = $hoje->modify('-36 months')->format('Y-m-d');
    $fimRecorde = $hoje->modify('first day of this month')->format('Y-m-d');

    foreach ($tarefas as $tarefa) {
        $funcaoId = (int) $tarefa['funcao_id'];
        $colaboradorId = (int) $tarefa['colaborador_id'];
        if (!in_array($funcaoId, [5, 10], true) || $colaboradorId <= 0 || in_array($colaboradorId, [21, 15], true)) {
            continue;
        }
        $status = mb_strtolower(trim($tarefa['status'] ?? ''), 'UTF-8');
        if (!in_array($status, ['finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'], true)) {
            continue;
        }
        $prazo = substr((string) ($tarefa['prazo'] ?? ''), 0, 10);
        if ($prazo === '' || $prazo === '0000-00-00') {
            continue;
        }
        $categoria = tela_gerencial_animacao_categoria($funcaoId, $tarefa['tipo_animacao'] ?? null);
        $chave = $colaboradorId . ':' . $categoria;
        $pagoEm = $tarefa['pago_em'] ?? null;
        $pago = $pagoEm && $pagoEm !== '0000-00-00' && $pagoEm <= $fim;

        if ($prazo >= $inicio && $prazo <= $fim) {
            if (!isset($colaboradores[$chave])) {
                $colaboradores[$chave] = tela_gerencial_animacao_linha($categoria) + [
                    'colaborador_id' => $colaboradorId,
                    'nome_colaborador' => $tarefa['nome_colaborador'],
                    'funcao_id' => $funcaoId,
                    'origem' => 'funcao_animacao',
                ];
            }
            $custo = $pago ? 0 : (float) ($tarefa['valor'] ?? 0);
            foreach ([&$colaboradores[$chave], &$funcoes[$categoria]] as &$linha) {
                $linha['quantidade']++;
                $linha[$pago ? 'pagas' : 'nao_pagas']++;
                $linha['custo'] += $custo;
            }
            unset($linha);
            $tipo = trim($tarefa['tipo_animacao'] ?? '');
            $colaboradores[$chave]['imagens'][] = [
                'imagem_id' => (int) $tarefa['imagem_id'],
                'animacao_id' => (int) $tarefa['animacao_id'],
                'tarefa_id' => (int) $tarefa['id'],
                'imagem_nome' => ($tarefa['imagem_nome'] ?? '') . ($tipo !== '' ? ' - ' . $tipo : ''),
                'pago' => $pago ? 1 : 0,
            ];
        }

        if (!$historico) {
            continue;
        }
        $mes = substr($prazo, 0, 7);
        $fimMes = (new DateTimeImmutable($prazo))->modify('last day of this month')->format('Y-m-d');
        $naoPagoNoMes = !$pagoEm || $pagoEm === '0000-00-00' || $pagoEm > $fimMes;
        if ($naoPagoNoMes && (
            ($prazo >= $inicioRecorde && $prazo < $fimRecorde && $mes !== '2024-10')
            || ($prazo >= $inicioAnterior && $prazo <= $fimAnterior)
        )) {
            $mensais[$categoria][$mes] = ($mensais[$categoria][$mes] ?? 0) + 1;
            $mensaisColaborador[$chave][$mes] = ($mensaisColaborador[$chave][$mes] ?? 0) + 1;
        }
    }

    $completar = static function (array $linha, array $meses) use ($inicioAnterior, $inicioRecorde, $fimRecorde, $historico): array {
        $linha['mes_anterior'] = $historico ? ($meses[substr($inicioAnterior, 0, 7)] ?? 0) : 0;
        ksort($meses);
        foreach ($meses as $mes => $quantidade) {
            if ($mes < substr($inicioRecorde, 0, 7) || $mes >= substr($fimRecorde, 0, 7) || $mes === '2024-10') {
                continue;
            }
            if ($quantidade >= $linha['recorde_producao']) {
                $linha['recorde_producao'] = $quantidade;
                $linha['recorde_data'] = $mes;
            }
        }
        $linha['bate_recorde'] = $linha['nao_pagas'] > $linha['recorde_producao'];
        $linha['custo'] = round($linha['custo'], 2);
        $linha['custo_medio'] = $linha['nao_pagas'] > 0 ? round($linha['custo'] / $linha['nao_pagas'], 2) : 0.0;
        return $linha;
    };
    foreach ($colaboradores as $chave => &$linha) {
        $linha = $completar($linha, $mensaisColaborador[$chave] ?? []);
    }
    unset($linha);
    foreach ($funcoes as $categoria => &$linha) {
        $linha = $completar($linha, $mensais[$categoria] ?? []);
        $linha['custo_total'] = $linha['custo'];
        unset($linha['custo'], $linha['imagens']);
    }
    unset($linha);
    return ['colaboradores' => array_values($colaboradores), 'funcoes' => array_values($funcoes)];
}

function tela_gerencial_animacoes(mysqli $conn, string $inicio, string $fim, bool $historico = true): array
{
    $inicioBusca = $inicio;
    $fimBusca = (new DateTimeImmutable($fim))->modify('+1 day')->format('Y-m-d');
    if ($historico) {
        $inicioBusca = min($inicioBusca, date('Y-m-d', strtotime('-36 months')),
            (new DateTimeImmutable($inicio))->modify('first day of previous month')->format('Y-m-d'));
        $fimBusca = max($fimBusca, date('Y-m-01'));
    }
    $sql = "SELECT fa.id, fa.animacao_id, fa.funcao_id, fa.colaborador_id,
            c.nome_colaborador, fa.status, fa.prazo, fa.valor,
            a.tipo_animacao, a.imagem_id, i.imagem_nome,
            LEAST(
                COALESCE(NULLIF(CAST(fa.data_pagamento AS CHAR), '0000-00-00'), '9999-12-31'),
                COALESCE((SELECT MIN(DATE(pi.criado_em)) FROM pagamento_itens pi
                    WHERE pi.origem = 'funcao_animacao' AND pi.origem_id = fa.id), '9999-12-31')
            ) AS pago_em
        FROM funcao_animacao fa
        JOIN animacao a ON a.idanimacao = fa.animacao_id
        JOIN colaborador c ON c.idcolaborador = fa.colaborador_id
        LEFT JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = a.imagem_id
        WHERE fa.funcao_id IN (5, 10)
            AND fa.colaborador_id NOT IN (21, 15)
            AND fa.prazo >= ? AND fa.prazo < ?
            AND LOWER(TRIM(fa.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
        ORDER BY c.nome_colaborador, fa.funcao_id, fa.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $inicioBusca, $fimBusca);
    $stmt->execute();
    $tarefas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return tela_gerencial_animacoes_agrupar($tarefas, $inicio, $fim, $historico);
}
