<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_producao_helper.php';

function teste_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function teste_item(int $imagem, int $funcao, string $tipo, string $status = 'Não iniciado'): array
{
    return ['imagem_id' => $imagem, 'funcao_id' => $funcao, 'tipo_imagem' => $tipo, 'status' => $status];
}

function teste_etapa(array $plano, string $codigo): array
{
    foreach ($plano['etapas'] as $etapa) {
        if ($etapa['codigo'] === $codigo) {
            return $etapa;
        }
    }
    throw new RuntimeException('Etapa não encontrada: ' . $codigo);
}

function teste_plano(array $itens, array $pessoas = []): array
{
    return flow_planejamento_calcular($itens, [
        'obra_id' => 116,
        'data_inicio' => '2026-08-12',
        'data_hoje' => '2026-08-20',
        'data_entrega' => '2026-10-15',
        'pessoas_alocadas' => $pessoas,
    ], static function (string $codigo): array {
        $taxas = ['CADERNO_FILTRO' => 1, 'MODELAGEM_INTERNA' => 1, 'COMPOSICAO' => 1];
        $taxa = $taxas[$codigo] ?? null;
        return [
            'confianca' => $taxa === null ? 'INSUFICIENTE' : 'ALTA',
            'tarefas_por_dia_util_pessoa' => $taxa,
            'duracao_mediana_dias_uteis' => $taxa ? 1 / $taxa : null,
            'amostra_ciclos_validos' => 30,
        ];
    });
}

// 14 imagens produtivas: Caderno/Filtro deve contar somente uma vez por imagem.
$itens = [];
for ($imagem = 1; $imagem <= 12; $imagem++) {
    $tipo = $imagem <= 10 ? 'Imagem Interna' : 'Unidade';
    foreach ([1, 8, 2, 3, 4, 5] as $funcao) {
        $itens[] = teste_item($imagem, $funcao, $tipo);
    }
}
for ($imagem = 13; $imagem <= 14; $imagem++) {
    foreach ([1, 8, 2, 3, 4, 5] as $funcao) {
        $itens[] = teste_item($imagem, $funcao, 'Imagem Externa');
    }
}
for ($imagem = 15; $imagem <= 18; $imagem++) {
    $itens[] = teste_item($imagem, 4, 'Planta Humanizada');
    $itens[] = teste_item($imagem, 7, 'Planta Humanizada');
}
$itens[] = teste_item(19, 2, 'Fachada');

$inicial = teste_plano($itens);
teste_assert(teste_etapa($inicial, 'CADERNO_FILTRO')['volume'] === 14, 'Caderno/Filtro não pode dobrar volume.');
teste_assert(teste_etapa($inicial, 'CADERNO_FILTRO')['duracao_dias_uteis'] === 14, 'Caderno/Filtro deve usar uma pessoa por padrão.');
teste_assert(teste_etapa($inicial, 'MODELAGEM_INTERNA')['dependencias'] === ['CADERNO_FILTRO'], 'Modelagem interna deve depender do marco combinado.');
teste_assert(teste_etapa($inicial, 'COMPOSICAO')['dependencias'] === ['MODELAGEM_INTERNA'], 'Composição não pode aguardar Fachada.');
teste_assert(teste_etapa($inicial, 'MODELAGEM_FACHADA')['duracao_dias_uteis'] === 7, 'Modelagem da Fachada deve manter janela fixa de sete dias.');
teste_assert(teste_etapa($inicial, 'MODELAGEM_FACHADA')['inicio'] === '2026-08-12', 'Fachada deve iniciar junto da produção.');
teste_assert(teste_etapa($inicial, 'FINALIZACAO_PLANTA')['volume'] === 4, 'Plantas devem ser Finalização Planta; função 7 é ignorada.');
teste_assert(teste_etapa($inicial, 'FINALIZACAO_PLANTA')['duracao_dias_uteis'] === 4, 'Finalização Planta deve ser uma tarefa/dia.');
teste_assert(teste_etapa($inicial, 'FINALIZACAO_GLOBAL')['duracao_dias_uteis'] === 12, 'Global deve ser máximo dos pools, nunca a soma.');
teste_assert(teste_etapa($inicial, 'POS_PRODUCAO')['duracao_dias_uteis'] === 3, 'Pós deve usar cinco tarefas/dia: ceil(14/5)=3.');

// Simulação requerida: 1 -> 2 apenas nas três etapas editáveis.
$simulado = teste_plano($itens, ['CADERNO_FILTRO' => 2, 'MODELAGEM_INTERNA' => 2, 'COMPOSICAO' => 2]);
teste_assert(teste_etapa($simulado, 'CADERNO_FILTRO')['duracao_dias_uteis'] === 7, '2 pessoas devem reduzir Caderno/Filtro de 14 para 7 dias.');
teste_assert(teste_etapa($simulado, 'MODELAGEM_INTERNA')['duracao_dias_uteis'] === 6, '2 pessoas devem reduzir Modelagem Interna de 12 para 6 dias.');
teste_assert(teste_etapa($simulado, 'COMPOSICAO')['duracao_dias_uteis'] === 7, '2 pessoas devem reduzir Composição de 14 para 7 dias.');
teste_assert(teste_etapa($simulado, 'MODELAGEM_FACHADA')['duracao_dias_uteis'] === 7, 'Capacidade não altera a janela fixa de Fachada.');
teste_assert(teste_etapa($simulado, 'COMPOSICAO')['inicio'] === teste_etapa($simulado, 'MODELAGEM_INTERNA')['limite'], 'Recalcular capacidade deve propagar pela cadeia principal.');
teste_assert($simulado['fim_previsto'] < $inicial['fim_previsto'], 'A simulação deve antecipar fim previsto.');
teste_assert($simulado['margem_dias_uteis'] > $inicial['margem_dias_uteis'], 'A simulação deve melhorar margem.');

// Histórico insuficiente, HOLD e dias úteis mantêm as proteções da V1.
$semHistorico = flow_planejamento_calcular([teste_item(1, 1, 'Imagem Interna')], ['data_inicio' => '2026-08-12'], static fn (): array => ['confianca' => 'INSUFICIENTE']);
teste_assert($semHistorico['status_plano'] === 'SEM_PREVISAO_CONFIAVEL', 'Histórico insuficiente não pode gerar prazo inventado.');
$ciclos = flow_planejamento_ciclos_validos([
    ['data' => '2026-08-03 08:00:00', 'status_novo' => 'Em andamento'],
    ['data' => '2026-08-04 08:00:00', 'status_novo' => 'HOLD'],
    ['data' => '2026-08-08 08:00:00', 'status_novo' => 'Finalizado'],
    ['data' => '2026-08-10 08:00:00', 'status_novo' => 'Em andamento'],
    ['data' => '2026-08-11 08:00:00', 'status_novo' => 'Finalizado'],
]);
teste_assert($ciclos['descartados_hold'] === 1 && count($ciclos['duracoes']) === 1, 'HOLD deve contaminar somente o ciclo afetado.');
teste_assert(flow_planejamento_adicionar_dias_uteis('2026-09-04', 1) === '2026-09-08', 'Calendário deve pular fim de semana e 7 de setembro.');

echo "OK: regras V1, capacidade e dependências do planejamento validadas.\n";
