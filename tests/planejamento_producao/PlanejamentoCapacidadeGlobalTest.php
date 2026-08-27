<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_capacidade_global_helper.php';

function capacidade_teste_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function capacidade_teste_plano(int $versaoId, int $obraId, array $etapas, array $ajustes = []): array
{
    return array_merge([
        'planejamento_id' => $versaoId,
        'entrega_id' => 1000 + $versaoId,
        'estado' => 'CONFIRMADO',
        'versao_atual_id' => $versaoId,
        'versao_id' => $versaoId,
        'versao_numero' => 1,
        'versao_vigente' => 1,
        'margem_dias_uteis' => 5,
        'status_plano' => 'VIAVEL',
        'status_id' => 2,
        'entrega_status' => 'Pendente',
        'arquivada' => 0,
        'obra_id' => $obraId,
        'nome_obra' => 'Obra ' . $obraId,
        'nomenclatura' => 'OBR' . $obraId,
        'status_obra' => 0,
        'etapas' => $etapas,
    ], $ajustes);
}

function capacidade_teste_etapa(string $codigo, string $inicio, string $fim, float $pessoas): array
{
    return [
        'codigo_etapa' => $codigo,
        'data_inicio' => $inicio,
        'data_limite' => $fim,
        'pessoas_alocadas' => $pessoas,
        'metadados_json' => ['nao_aplicavel' => false],
    ];
}

function capacidade_teste_resultado(array $planos, array $configuracoes, string $inicio = '2026-09-14', string $fim = '2026-09-18'): array
{
    return flow_capacidade_calcular_demanda_planejada($planos, $inicio, $fim, $configuracoes);
}

function capacidade_teste_etapa_resultado(array $resultado, string $codigo): array
{
    foreach ($resultado['etapas'] as $etapa) {
        if ($etapa['codigo_etapa'] === $codigo) {
            return $etapa;
        }
    }
    throw new RuntimeException('Etapa não encontrada no resultado: ' . $codigo);
}

function capacidade_teste_dia(array $etapa, string $data): array
{
    foreach ($etapa['dias'] as $dia) {
        if ($dia['data'] === $data) {
            return $dia;
        }
    }
    throw new RuntimeException('Dia não encontrado: ' . $data);
}

// A igualdade dos principais é saudável, mas precisa sinalizar uso integral
// da capacidade-base sem criar mais um estado operacional.
$noLimite = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 2)]),
    capacidade_teste_plano(2, 11, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 2)]),
], ['COMPOSICAO' => 4]);
$composicao = capacidade_teste_etapa_resultado($noLimite, 'COMPOSICAO');
$diaNoLimite = capacidade_teste_dia($composicao, '2026-09-14');
capacidade_teste_assert($diaNoLimite['classificacao'] === 'SAUDAVEL' && $diaNoLimite['principal_no_limite'] === true, '4/4 principais deve ser saudável com sinalização de limite.');
capacidade_teste_assert($noLimite['resumo']['conflitos'] === 0, '4/4 não pode gerar conflito.');

// Principal, apoio potencial e déficit são conceitos distintos.
$capacidadePorPapel = ['COMPOSICAO' => ['capacidade_principal' => 3, 'capacidade_secundaria' => 4]];
$apenasPrincipais = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 2)]),
], $capacidadePorPapel);
$diaApenasPrincipais = capacidade_teste_dia(capacidade_teste_etapa_resultado($apenasPrincipais, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($diaApenasPrincipais['classificacao'] === 'SAUDAVEL' && $diaApenasPrincipais['capacidade_total'] === 7.0, 'Demanda dentro dos principais deve ser saudável e manter o total potencial separado.');

$apoio = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 5)]),
], $capacidadePorPapel);
$diaApoio = capacidade_teste_dia(capacidade_teste_etapa_resultado($apoio, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($diaApoio['classificacao'] === 'NECESSITA_APOIO' && $diaApoio['necessidade_apoio'] === 2.0 && $diaApoio['deficit'] === 0.0, '5 para 3 principais e 4 secundários deve exigir dois apoios, sem déficit.');

$limiteTotal = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 7)]),
], $capacidadePorPapel);
$diaLimiteTotal = capacidade_teste_dia(capacidade_teste_etapa_resultado($limiteTotal, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($diaLimiteTotal['classificacao'] === 'NECESSITA_APOIO' && $diaLimiteTotal['necessidade_apoio'] === 4.0, 'Uso de todo o apoio potencial continua sendo NECESSITA_APOIO.');

$conflitoPapel = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 8)]),
], $capacidadePorPapel);
$diaConflitoPapel = capacidade_teste_dia(capacidade_teste_etapa_resultado($conflitoPapel, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($diaConflitoPapel['classificacao'] === 'CONFLITO' && $diaConflitoPapel['necessidade_apoio'] === 4.0 && $diaConflitoPapel['deficit'] === 1.0, 'Demanda acima do total potencial deve separar apoio máximo e déficit.');

$semPrincipais = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 2)]),
], ['COMPOSICAO' => ['capacidade_principal' => 0, 'capacidade_secundaria' => 5]]);
$diaSemPrincipais = capacidade_teste_dia(capacidade_teste_etapa_resultado($semPrincipais, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($diaSemPrincipais['classificacao'] === 'SEM_PRINCIPAIS_CONFIGURADOS' && $diaSemPrincipais['necessidade_apoio'] === 2.0, 'Secundários sem principal não podem parecer capacidade-base saudável.');

// A união Caderno/Filtro deduplica a pessoa e promove principal se ela for
// principal em qualquer uma das duas funções físicas.
$poolCombinado = flow_capacidade_configuracao_por_colaboradores([
    1 => ['id' => 1, 'nome' => 'Ana', 'tipo_atuacao' => 'SECUNDARIA'],
    2 => ['id' => 2, 'nome' => 'Bia', 'tipo_atuacao' => 'PRINCIPAL'],
    3 => ['id' => 1, 'nome' => 'Ana', 'tipo_atuacao' => 'PRINCIPAL'],
], 'TESTE', 'teste', 'CADERNO_FILTRO');
capacidade_teste_assert($poolCombinado['capacidade_principal'] === 2 && $poolCombinado['capacidade_secundaria'] === 0, 'União de funções deve contar Ana uma vez e preservar sua atuação principal.');

// Conflito simples e rastreabilidade da origem.
$conflito = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 2)]),
    capacidade_teste_plano(2, 11, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 2)]),
    capacidade_teste_plano(3, 12, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 1)]),
], ['COMPOSICAO' => 4]);
$composicao = capacidade_teste_etapa_resultado($conflito, 'COMPOSICAO');
$diaConflito = capacidade_teste_dia($composicao, '2026-09-14');
capacidade_teste_assert($diaConflito['demanda_planejada'] === 5.0 && $diaConflito['deficit'] === 1.0, '5/4 deve gerar déficit 1.');
capacidade_teste_assert(count($diaConflito['projetos']) === 3, 'A origem dos três projetos deve ser preservada.');

// Mesmo período semanal, mas dias úteis diferentes: não há falso conflito.
$diasDiferentes = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('MODELAGEM_INTERNA', '2026-09-14', '2026-09-15', 2)]),
    capacidade_teste_plano(2, 11, [capacidade_teste_etapa('MODELAGEM_INTERNA', '2026-09-17', '2026-09-18', 2)]),
], ['MODELAGEM_INTERNA' => 2]);
capacidade_teste_assert($diasDiferentes['resumo']['conflitos'] === 0, 'Dias diferentes da mesma semana não podem somar como conflito.');

// Capacidade zero e uma única obra acima da capacidade.
$zero = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 2)]),
], ['COMPOSICAO' => 0]);
$diaZero = capacidade_teste_dia(capacidade_teste_etapa_resultado($zero, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($diaZero['classificacao'] === 'CONFLITO' && $diaZero['ocupacao'] === null && $diaZero['deficit'] === 2.0, 'Capacidade zero deve ser conflito crítico sem divisão por zero.');

$umaObra = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 3)]),
], ['COMPOSICAO' => 2]);
capacidade_teste_assert(capacidade_teste_dia(capacidade_teste_etapa_resultado($umaObra, 'COMPOSICAO'), '2026-09-14')['deficit'] === 1.0, 'Uma obra pode exceder a capacidade disponível.');

// Histórico e rascunho não entram; desatualizado entra com confiabilidade reduzida.
$filtros = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 2)], ['estado' => 'RASCUNHO']),
    capacidade_teste_plano(2, 11, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 2)], ['versao_vigente' => 0]),
    capacidade_teste_plano(3, 12, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 1)], ['estado' => 'DESATUALIZADO']),
], ['COMPOSICAO' => 4]);
$diaDesatualizado = capacidade_teste_dia(capacidade_teste_etapa_resultado($filtros, 'COMPOSICAO'), '2026-09-14');
capacidade_teste_assert($filtros['resumo']['planos_considerados'] === 1 && $diaDesatualizado['projetos'][0]['confiabilidade'] === 'REDUZIDA', 'Desatualizado deve entrar; rascunho e histórico, não.');

$inativos = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 1)], ['entrega_status' => 'Entregue no prazo']),
    capacidade_teste_plano(2, 11, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 1)], ['arquivada' => 1]),
    capacidade_teste_plano(3, 12, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 1)], ['status_obra' => 2]),
], ['COMPOSICAO' => 4]);
capacidade_teste_assert($inativos['resumo']['planos_considerados'] === 0, 'Entrega concluída/arquivada e obra inativa não podem consumir capacidade.');

$foraDoPeriodo = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-11-02', '2026-11-06', 1)]),
], ['COMPOSICAO' => 2], '2026-09-14', '2026-09-18');
capacidade_teste_assert(
    $foraDoPeriodo['resumo']['planos_considerados'] === 0 && empty($foraDoPeriodo['etapas']),
    'Plano vigente fora do horizonte consultado não pode aparecer como planejamento do período.'
);

$semConfiguracao = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-14', 1)]),
], []);
capacidade_teste_assert(capacidade_teste_dia(capacidade_teste_etapa_resultado($semConfiguracao, 'COMPOSICAO'), '2026-09-14')['classificacao'] === 'SEM_CAPACIDADE_CONFIGURADA', 'Ausência de configuração não pode fingir capacidade zero.');

// Fim de semana e feriado canônico de 7 de setembro não consomem capacidade.
$calendario = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-04', '2026-09-08', 1)]),
], ['COMPOSICAO' => 2], '2026-09-04', '2026-09-08');
$diasCalendario = array_column(capacidade_teste_etapa_resultado($calendario, 'COMPOSICAO')['dias'], 'data');
capacidade_teste_assert($diasCalendario === ['2026-09-04', '2026-09-08'], 'Fim de semana e 7 de setembro devem ser excluídos pelo calendário canônico.');

// Sobreposição parcial, agrupamento contínuo e pico semanal não podem esconder o dia crítico.
$parcial = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 3)]),
    capacidade_teste_plano(2, 11, [capacidade_teste_etapa('COMPOSICAO', '2026-09-16', '2026-09-16', 2)]),
], ['COMPOSICAO' => 4]);
$etapaParcial = capacidade_teste_etapa_resultado($parcial, 'COMPOSICAO');
capacidade_teste_assert($etapaParcial['conflitos'][0]['data_inicio'] === '2026-09-16' && $etapaParcial['conflitos'][0]['data_fim'] === '2026-09-16', 'A sobreposição parcial deve conflitar somente no dia real.');
capacidade_teste_assert($etapaParcial['semanas'][0]['dias_conflito'] === 1 && $etapaParcial['semanas'][0]['pico_demanda'] === 5.0, 'Resumo semanal deve manter pico e dia crítico.');
capacidade_teste_assert(
    $etapaParcial['semanas'][0]['classificacao'] === 'CONFLITO'
    && $etapaParcial['semanas'][0]['dia_referencia'] === '2026-09-16'
    && count($etapaParcial['semanas'][0]['projetos']) === 2,
    'A célula semanal deve expor o pior dia e as obras envolvidas, sem recalcular no frontend.'
);

$continuo = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('COMPOSICAO', '2026-09-14', '2026-09-18', 5)]),
], ['COMPOSICAO' => 4]);
$periodo = capacidade_teste_etapa_resultado($continuo, 'COMPOSICAO')['conflitos'][0];
capacidade_teste_assert($periodo['data_inicio'] === '2026-09-14' && $periodo['data_fim'] === '2026-09-18' && $periodo['dias_conflito'] === 5, 'Dias úteis consecutivos devem formar um período contínuo.');

// Fachada continua visível como janela gerencial, mas não consome 1x7 sem evidência de esforço integral.
$fachada = capacidade_teste_resultado([
    capacidade_teste_plano(1, 10, [capacidade_teste_etapa('MODELAGEM_FACHADA', '2026-09-14', '2026-09-18', 1)]),
], ['MODELAGEM_FACHADA' => 1]);
capacidade_teste_assert(empty($fachada['etapas']) && count($fachada['etapas_sem_demanda_inferida']) === 1, 'Fachada deve ser marcada como demanda não inferida na V1.1.');

echo "OK: capacidade principal/secundária, apoio, déficit, calendário e rastreabilidade validados.\n";
