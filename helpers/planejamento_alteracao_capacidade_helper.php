<?php

/** Pontos R01+. Decisões e pesos históricos vivem no snapshot da versão. */
require_once __DIR__ . '/planejamento_producao_helper.php';

function flow_pac_config_validar(array $config): array
{
    if (!is_array($config['niveis'] ?? null) || !is_array($config['colaboradores'] ?? [])) {
        throw new InvalidArgumentException('Configuração de níveis ou colaboradores inválida.');
    }
    $numero = static function ($v, bool $zero = false): float {
        if (!is_numeric($v) || !is_finite((float)$v) || (float)$v < ($zero ? 0 : 0.01) || (float)$v > 10000) {
            throw new InvalidArgumentException('Pontos e capacidade devem ser números válidos, até 10000.');
        }
        return round((float)$v, 2);
    };
    $saida = ['capacidade_padrao' => $numero($config['capacidade_padrao'] ?? null),'niveis' => [],'colaboradores' => []];
    foreach (range(1, 5) as $n) {
        $saida['niveis'][$n] = $numero($config['niveis'][$n] ?? null);
    }
    foreach (($config['colaboradores'] ?? []) as $id => $v) {
        if (!ctype_digit((string)$id) || (int)$id < 1) {
            throw new InvalidArgumentException('Colaborador inválido na configuração.');
        }
        if ($v !== null && $v !== '') {
            $saida['colaboradores'][(int)$id] = $numero($v, true);
        }
    }
    ksort($saida['colaboradores']);
    return $saida;
}

function flow_pac_config(mysqli $conn, bool $lock = false): array
{
    try {
        $r = flow_pa_rows($conn, "SELECT capacidade_padrao,parametros_json FROM planejamento_capacidade_etapa WHERE codigo_etapa='ALTERACAO_PONTOS'".($lock ? ' FOR UPDATE' : ''))[0] ?? null;
    } catch (mysqli_sql_exception $e) {
        if (!in_array($e->getCode(), [1054,1146], true)) {
            throw $e;
        }
        throw new RuntimeException('Aplique a migration 2026-10-28_planejamento_alteracao_pontos.sql.');
    }
    if (!$r) {
        throw new RuntimeException('Configure a capacidade de Alterações pela migration de pontos.');
    }
    $p = json_decode($r['parametros_json'] ?? '', true) ?: [];
    return flow_pac_config_validar(['capacidade_padrao' => $r['capacidade_padrao']] + $p);
}

function flow_pac_dia_util(string $dia): bool
{
    if (!entregas_valid_date($dia)) {
        return false;
    }
    return entregas_adicionar_dias_uteis(date('Y-m-d', strtotime($dia.' -1 day')), 1) === $dia;
}

function flow_pac_dias(string $inicio, string $fim): array
{
    if (!entregas_valid_date($inicio) || !entregas_valid_date($fim) || $fim < $inicio || (strtotime($fim) - strtotime($inicio)) / 86400 > 730) {
        throw new InvalidArgumentException('Use um período de até dois anos.');
    }
    $dias = [];
    $d = flow_pac_dia_util($inicio) ? $inicio : entregas_adicionar_dias_uteis($inicio, 1);
    while ($d <= $fim) {
        $dias[] = $d;
        $d = entregas_adicionar_dias_uteis($d, 1);
    }
    return $dias;
}

/** Uma consulta de versões, uma de períodos e uma consulta agregada do motor R00. */
function flow_pac_agenda(mysqli $conn, int $entregaId, array $config, array $pessoas, string $inicio, string $fim): array
{
    $dias = flow_pac_dias($inicio, $fim);
    $agenda = [];
    foreach ($pessoas as $p) {
        foreach ($dias as $d) {
            $cap = (float)($config['colaboradores'][$p['id']] ?? $config['capacidade_padrao']);
            $agenda[$p['id']][$d] = ['nominal' => $cap,'capacidade' => $cap,'comprometidos' => 0,'ocupacao_r00' => 0,'avisos' => []];
        }
    }
    $versoes = flow_pa_rows($conn, "SELECT v.snapshot_json FROM entrega_planejamento_producao p JOIN entrega_planejamento_versao v ON v.id=p.versao_atual_id JOIN entregas e ON e.id=p.entrega_id WHERE p.modelo='IMAGENS_ALTERACAO' AND p.entrega_id<>? AND COALESCE(e.arquivada,0)=0", 'i', [$entregaId]);
    foreach ($versoes as $v) {
        foreach ((json_decode($v['snapshot_json'], true)['itens'] ?? []) as $i) {
            $id = (int)($i['responsavel_planejado_id'] ?? 0);
            $d = $i['fim_planejado'] ?? '';
            if (!isset($agenda[$id][$d])) {
                continue;
            }
            if (!isset($i['pontos_snapshot'])) {
                $agenda[$id][$d]['avisos'][] = 'Alteração histórica sem pontos; capacidade efetiva não estimada';
            } else {
                $agenda[$id][$d]['comprometidos'] += (float)$i['pontos_snapshot'];
            }
        }
    }
    $periodos = flow_pa_rows($conn, "SELECT data_inicio,data_fim,capacidade_disponivel FROM planejamento_capacidade_etapa_periodo WHERE codigo_etapa='ALTERACAO_PONTOS' AND ativo=1 AND data_inicio<=? AND data_fim>=?", 'ss', [$fim,$inicio]);
    foreach ($agenda as &$porDia) {
        foreach ($porDia as $d => &$celula) {
            foreach ($periodos as $p) {
                if ($d >= $p['data_inicio'] && $d <= $p['data_fim']) {
                    $celula['capacidade'] = min($celula['capacidade'], (float)$p['capacidade_disponivel']);
                }
            }
        }
    }
    unset($porDia,$celula);
    require_once __DIR__.'/planejamento_alocacao_helper.php';
    $r00 = flow_alocacao_consultar($conn, $inicio, $fim);
    return flow_pac_adaptar_r00($agenda, $r00, $dias);
}

function flow_pac_adaptar_r00(array $agenda, array $r00, array $dias): array
{
    // Mesma fórmula do motor de alocação, por etapa, para excluir janelas sem evidência.
    foreach ($r00['grupos'] as $g) {
        foreach ($g['projetos'] as $pr) {
            foreach ($pr['pessoas'] as $p) {
                $diasEtapa = flow_alocacao_dias_planejados_etapa($pr['inicio'], $pr['limite'], (int)$pr['duracao_dias_uteis']);
                $confiavel = $pr['codigo_etapa'] !== 'MODELAGEM_FACHADA' && count($diasEtapa) === (int)$pr['duracao_dias_uteis'] && count($diasEtapa) > 0;
                if ($confiavel) {
                    $atribuidas = 0;
                    foreach (array_slice($pr['tarefas_operacionais'], 0, (int)$pr['planejado']) as $u) {
                        if (count($u['responsaveis']) === 1 && (int)$u['responsaveis'][0]['id'] === (int)$p['id']) {
                            $atribuidas++;
                        }
                    }
                    $fracao = (float)$pr['carga_total_planejada_pessoa_dia'] / max(1, (int)$pr['planejado']) * $atribuidas / count($diasEtapa);
                    foreach ($diasEtapa as $d) {
                        if (isset($agenda[$p['id']][$d])) {
                            $agenda[$p['id']][$d]['ocupacao_r00'] += $fracao;
                        }
                    }
                }
                if ((float)($p['carga_compartilhada_sem_peso_pessoa_dia'] ?? 0) > 0 || !$confiavel) {
                    foreach ($dias as $d) {
                        if ($d >= $pr['inicio'] && $d <= $pr['limite'] && isset($agenda[$p['id']][$d])) {
                            $agenda[$p['id']][$d]['avisos'][] = 'Compromisso R00 sem ocupação diária confiável';
                        }
                    }
                }
            }
        }
    }
    foreach ($agenda as &$porDia) {
        foreach ($porDia as &$a) {
            $a['capacidade'] = round($a['capacidade'] * max(0, 1 - $a['ocupacao_r00']), 2);
            $a['avisos'] = array_values(array_unique($a['avisos']));
            $a['disponivel'] = $a['avisos'] ? null : max(0, $a['capacidade'] - $a['comprometidos']);
        }
    }
    unset($porDia,$a);
    return $agenda;
}

function flow_pac_contexto(mysqli $conn, array $e, array $itens, array $pessoas, array $config): array
{
    $inicio = substr($e['data_recebimento'] ?: date('Y-m-d'), 0, 10);
    $fim = max($inicio, substr($e['data_prevista'] ?: $inicio, 0, 10), entregas_adicionar_dias_uteis($inicio, 59));
    foreach ($itens as $i) {
        if (!empty($i['fim_planejado'])) {
            $fim = max($fim, $i['fim_planejado']);
        }
    }
    $agenda = flow_pac_agenda($conn, (int)$e['id'], $config, $pessoas, $inicio, $fim);
    $contexto = ['inicio' => $inicio,'fim' => $fim,'configuracao' => $config,'colaboradores' => $pessoas,'agenda' => $agenda];
    $contexto['fingerprint'] = hash('sha256', flow_planejamento_json($contexto));
    $contexto['dias'] = flow_pac_dias($inicio, $fim);
    return $contexto;
}

function flow_pac_pontuar(array $itens, array $config, array $anteriores = []): array
{
    $porId = array_column($anteriores, null, 'entrega_item_id');
    foreach ($itens as &$i) {
        $anterior = $porId[$i['entrega_item_id']] ?? [];
        // Replanejar mantém o peso da rodada já confirmada; mudanças de parâmetros só afetam planos novos.
        $mesmoNivel = ($anterior['nivel_complexidade_snapshot'] ?? null) === $i['nivel_complexidade_snapshot'];
        $i['pontos_snapshot'] = $mesmoNivel ? ($anterior['pontos_snapshot'] ?? ($config['niveis'][$i['nivel_complexidade_snapshot']] ?? null)) : ($config['niveis'][$i['nivel_complexidade_snapshot']] ?? null);
        $i['fixado'] = false;
    }
    unset($i);
    return $itens;
}

function flow_pac_sobrecargas(array $itens, array $agenda): array
{
    $cargas = [];
    $resultado = [];
    foreach ($itens as $i) {
        if (!empty($i['responsavel_planejado_id']) && !empty($i['fim_planejado'])) {
            $id = $i['responsavel_planejado_id'];
            $d = $i['fim_planejado'];
            $cargas[$id][$d] = ($cargas[$id][$d] ?? 0) + (float)($i['pontos_snapshot'] ?? 0);
        }
    }
    foreach ($cargas as $id => $dias) {
        foreach ($dias as $d => $pontos) {
            if (!isset($agenda[$id][$d])) {
                throw new InvalidArgumentException('Dia fora da agenda ou colaborador não elegível.');
            }
            $a = $agenda[$id][$d];
            $excesso = round($pontos + $a['comprometidos'] - $a['capacidade'], 2);
            if ($excesso > 0.001) {
                $resultado[] = ['colaborador_id' => $id,'dia' => $d,'excesso' => $excesso,'pontos' => $pontos,'capacidade' => $a['capacidade'],'comprometidos' => $a['comprometidos']];
            }
        }
    }
    return $resultado;
}

/** Sugestões podem redistribuir decisões não fixadas de responsáveis históricos inelegíveis. */
function flow_pac_decisoes_sugestao(array $decisoes, array $pessoas): array
{
    $validos = array_map('intval', array_column($pessoas, 'id'));
    foreach ($decisoes as &$d) {
        if (!is_array($d)) {
            throw new InvalidArgumentException('Item de planejamento inválido.');
        }
        if (empty($d['fixado']) && !in_array((int)($d['responsavel_planejado_id'] ?? 0), $validos, true)) {
            $d['responsavel_planejado_id'] = null;
        }
    }
    unset($d);
    return $decisoes;
}

/** Testa grupos crescentes, mantendo fixações e a ordem de complexidade escolhida. */
function flow_pac_sugerir(array $itens, array $pessoas, array $agenda, array $dias, string $prazo, string $ordemComplexidade = 'MAIOR', array $porTipo = [], array $prioridadeTipos = []): array
{
    if (!in_array($ordemComplexidade, ['MAIOR','MENOR'], true)) {
        throw new InvalidArgumentException('Ordem de complexidade inválida.');
    }
    if (!entregas_valid_date($prazo)) {
        throw new DomainException('Defina o prazo cliente antes de gerar uma sugestão.');
    }
    $fixos = array_values(array_filter($itens, fn ($i) => !empty($i['fixado'])));
    $tipos = array_unique(array_column($itens, 'tipo_imagem'));
    if (!array_is_list($prioridadeTipos) || count(array_unique($prioridadeTipos, SORT_REGULAR)) !== count($prioridadeTipos)) {
        throw new InvalidArgumentException('Ordem de tipos inválida.');
    }
    foreach ($prioridadeTipos as $tipo) {
        if (!is_string($tipo) || $tipo === '' || !in_array($tipo, $tipos, true)) {
            throw new InvalidArgumentException('A prioridade contém um tipo que não pertence à entrega.');
        }
    }
    $prioridades = array_flip($prioridadeTipos);
    $ids = array_map('intval', array_column($pessoas, 'id'));
    foreach ($porTipo as $tipo => $id) {
        if (!is_string($tipo) || !in_array($tipo, $tipos, true) || !filter_var($id, FILTER_VALIDATE_INT) || !in_array((int)$id, $ids, true)) {
            throw new InvalidArgumentException('Escolha um tipo da entrega e um colaborador presente na grade.');
        }
    }
    foreach ($fixos as $i) {
        if (isset($porTipo[$i['tipo_imagem'] ?? '']) && (int)$porTipo[$i['tipo_imagem']] !== (int)($i['responsavel_planejado_id'] ?? 0)) {
            throw new DomainException('Uma imagem fixada tem responsável diferente da regra por tipo. Revise a fixação ou a regra.');
        }
    }
    foreach ($itens as $i) {
        if (empty($i['pontos_snapshot']) || (float)$i['pontos_snapshot'] <= 0) {
            throw new DomainException('Classifique todas as imagens antes de gerar sugestão.');
        }
    }
    foreach ($fixos as $i) {
        if (empty($i['responsavel_planejado_id']) || empty($i['fim_planejado'])) {
            throw new DomainException('Uma imagem fixada precisa de responsável e dia.');
        }
    }
    foreach ($fixos as $i) {
        if ($i['fim_planejado'] > $prazo) {
            throw new DomainException('Uma decisão fixada termina após o prazo. Revise-a antes de gerar sugestão.');
        }
    }
    if (flow_pac_sobrecargas($fixos, $agenda)) {
        throw new DomainException('As decisões fixadas já excedem a capacidade. Revise-as antes de gerar sugestão.');
    }
    $pendentes = array_values(array_filter($itens, fn ($i) => empty($i['fixado'])));
    usort($pendentes, static function ($a, $b) use ($ordemComplexidade, $porTipo, $prioridades): int {
        $nivelA = (int)($a['nivel_complexidade_snapshot'] ?? 0);
        $nivelB = (int)($b['nivel_complexidade_snapshot'] ?? 0);
        $ordem = $ordemComplexidade === 'MAIOR' ? $nivelB <=> $nivelA : $nivelA <=> $nivelB;
        $tipoA = $a['tipo_imagem'] ?? '';
        $tipoB = $b['tipo_imagem'] ?? '';
        return (($prioridades[$tipoA] ?? PHP_INT_MAX) <=> ($prioridades[$tipoB] ?? PHP_INT_MAX)) ?: ((int)isset($porTipo[$tipoB]) <=> (int)isset($porTipo[$tipoA])) ?: $ordem ?: ($b['pontos_snapshot'] <=> $a['pontos_snapshot']) ?: ($a['entrega_item_id'] <=> $b['entrega_item_id']);
    });
    $obrigatorios = array_unique(array_merge(array_column($fixos, 'responsavel_planejado_id'), array_map('intval', array_values($porTipo))));
    if (array_diff($obrigatorios, array_column($pessoas, 'id'))) {
        throw new DomainException('Mantenha na grade os colaboradores das imagens fixadas.');
    }
    $continuidade = array_count_values(array_filter(array_column($itens, 'responsavel_confirmado_id')));
    usort($pessoas, fn ($a, $b) => (in_array($b['id'], $obrigatorios) ? 1 : 0) <=> (in_array($a['id'], $obrigatorios) ? 1 : 0) ?: ($continuidade[$b['id']] ?? 0) <=> ($continuidade[$a['id']] ?? 0) ?: (($a['tipo_atuacao'] ?? '') === 'PRINCIPAL' ? 0 : 1) <=> (($b['tipo_atuacao'] ?? '') === 'PRINCIPAL' ? 0 : 1) ?: $a['id'] <=> $b['id']);
    $dias = array_values(array_filter($dias, fn ($d) => $d <= $prazo));
    for ($n = max(1, count($obrigatorios));$n <= count($pessoas);$n++) {
        // Testa cada candidato sozinho antes de aumentar a equipe; depois grupos ordenados.
        $grupos = [];
        $combinar = static function (array $grupo, int $pos) use (&$combinar, &$grupos, $pessoas, $n, $obrigatorios): void {
            if (count($grupos) >= 512) {
                return;
            }
            if (count($grupo) === $n) {
                if (!array_diff($obrigatorios, array_column($grupo, 'id'))) {
                    $grupos[] = $grupo;
                }return;
            }
            for ($k = $pos;$k < count($pessoas);$k++) {
                $combinar([...$grupo,$pessoas[$k]], $k + 1);
            }
        };
        $combinar([], 0);
        foreach ($grupos as $grupo) {
            $usado = [];
            $saida = $fixos;
            foreach ($fixos as $i) {
                $usado[$i['responsavel_planejado_id']][$i['fim_planejado']] = ($usado[$i['responsavel_planejado_id']][$i['fim_planejado']] ?? 0) + $i['pontos_snapshot'];
            }
            foreach ($pendentes as $i) {
                $alocado = false;
                foreach ($dias as $d) {
                    $opcoes = [];
                    foreach ($grupo as $p) {
                        $id = $p['id'];
                        $a = $agenda[$id][$d] ?? null;
                        if (isset($porTipo[$i['tipo_imagem'] ?? '']) && (int)$porTipo[$i['tipo_imagem']] !== (int)$id) {
                            continue;
                        }
                        // Ocupação desconhecida não é tratada como disponibilidade automática.
                        if (!$a || $a['avisos']) {
                            continue;
                        }
                        $sobra = $a['capacidade'] - $a['comprometidos'] - ($usado[$id][$d] ?? 0) - $i['pontos_snapshot'];
                        if ($sobra >= -0.001) {
                            $opcoes[] = ['id' => $id,'sobra' => $sobra];
                        }
                    }
                    usort($opcoes, fn ($a, $b) => $a['sobra'] <=> $b['sobra'] ?: $a['id'] <=> $b['id']);
                    if ($opcoes) {
                        $id = $opcoes[0]['id'];
                        $i['responsavel_planejado_id'] = $id;
                        $i['fim_planejado'] = $d;
                        $i['inicio_planejado'] = null;
                        $i['excecao_capacidade'] = null;
                        $usado[$id][$d] = ($usado[$id][$d] ?? 0) + $i['pontos_snapshot'];
                        $saida[] = $i;
                        $alocado = true;
                        break;
                    }
                }
                if (!$alocado) {
                    break;
                }
            }
            if (count($saida) === count($itens)) {
                return ['itens' => $saida,'mensagem' => 'Sugestão gerada sem exceder capacidade. Revise e salve o rascunho.'];
            }
        }
    }
    throw new DomainException('Não foi encontrada uma distribuição viável no prazo. Revise equipe, decisões fixadas e disponibilidade.');
}
