<?php

/**
 * Consulta somente leitura do mapa global de capacidade.
 *
 * Exemplos:
 * php scripts/planejamento_capacidade_global_runner.php --inicio=2026-08-24 --fim=2026-10-31
 * php scripts/planejamento_capacidade_global_runner.php --inicio=2026-08-24 --fim=2026-10-31 --obra=116 --fixture
 */

require_once dirname(__DIR__) . '/conexao.php';
require_once dirname(__DIR__) . '/helpers/planejamento_capacidade_global_helper.php';

function flow_capacidade_fixture_validacao(): array
{
    $valores = [
        'CADERNO_FILTRO' => 2, 'MODELAGEM_INTERNA' => 4, 'COMPOSICAO' => 4,
        'FINALIZACAO_EXTERNA' => 1, 'FINALIZACAO_INTERNA' => 2,
        'FINALIZACAO_PLANTA' => 1, 'POS_PRODUCAO' => 2,
    ];
    return array_map(static fn (float $capacidade): array => [
        'capacidade_padrao' => $capacidade,
        'origem' => 'FIXTURE_TESTE',
    ], $valores);
}

$argumentos = getopt('', ['inicio:', 'fim:', 'obra::', 'cliente::', 'etapa::', 'fixture::', 'resumo::']);
try {
    $inicio = (string) ($argumentos['inicio'] ?? '');
    $fim = (string) ($argumentos['fim'] ?? '');
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        throw new InvalidArgumentException('Informe --inicio=Y-m-d e --fim=Y-m-d válidos.');
    }
    $opcoes = [];
    if (!empty($argumentos['obra'])) {
        $opcoes['obra_id'] = (int) $argumentos['obra'];
    }
    if (!empty($argumentos['cliente'])) {
        $opcoes['cliente_id'] = (int) $argumentos['cliente'];
    }
    if (!empty($argumentos['etapa'])) {
        $opcoes['etapa'] = strtoupper(trim((string) $argumentos['etapa']));
    }
    if (array_key_exists('fixture', $argumentos)) {
        $opcoes['capacidades_fixture'] = flow_capacidade_fixture_validacao();
    }
    $resultado = flow_capacidade_consultar($conn, $inicio, $fim, $opcoes);
    if (array_key_exists('resumo', $argumentos)) {
        echo 'Período: ' . $inicio . ' → ' . $fim . PHP_EOL;
        echo 'Capacidade: ' . $resultado['origem_capacidade'] . PHP_EOL;
        echo 'Planos: ' . $resultado['resumo']['planos_considerados'] . ' | Obras: ' . $resultado['resumo']['obras_consideradas'] . ' | Dias úteis: ' . $resultado['resumo']['dias_uteis_analisados'] . ' | Conflitos: ' . $resultado['resumo']['conflitos'] . PHP_EOL;
        foreach (($resultado['capacidades'] ?? []) as $codigo => $capacidade) {
            $principais = array_values(array_unique(array_map(static fn (array $colaborador): string => (string) ($colaborador['nome'] ?? ''), $capacidade['colaboradores_principais'] ?? [])));
            $secundarios = array_values(array_unique(array_map(static fn (array $colaborador): string => (string) ($colaborador['nome'] ?? ''), $capacidade['colaboradores_secundarios'] ?? [])));
            echo 'CAPACIDADE: ' . implode(' | ', [
                $codigo,
                'principal ' . $capacidade['capacidade_principal'],
                'secundária potencial ' . $capacidade['capacidade_secundaria'],
                'total potencial ' . $capacidade['capacidade_total'],
                'P: ' . ($principais ? implode(', ', $principais) : 'nenhum'),
                'S: ' . ($secundarios ? implode(', ', $secundarios) : 'nenhum'),
            ]) . PHP_EOL;
        }
        foreach ($resultado['conflitos'] as $conflito) {
            echo implode(' | ', [
                $conflito['codigo_etapa'],
                $conflito['data_inicio'] . ' → ' . $conflito['data_fim'],
                'pico ' . $conflito['pico_demanda'],
                'déficit ' . $conflito['deficit_maximo'],
            ]) . PHP_EOL;
        }
        foreach ($resultado['etapas'] as $etapa) {
            $ocupados = array_values(array_filter($etapa['dias'], static fn (array $dia): bool => $dia['demanda_planejada'] > 0));
            if (!$ocupados) {
                continue;
            }
            $primeiro = $ocupados[0];
            $ultimo = $ocupados[count($ocupados) - 1];
            $pico = max(array_column($ocupados, 'demanda_planejada'));
            $principal = $primeiro['capacidade_principal'] === null ? 'não configurada' : $primeiro['capacidade_principal'];
            $secundaria = $primeiro['capacidade_secundaria'] === null ? 'não configurada' : $primeiro['capacidade_secundaria'];
            echo 'DEMANDA: ' . implode(' | ', [
                $etapa['codigo_etapa'],
                $primeiro['data'] . ' → ' . $ultimo['data'],
                'pico ' . $pico,
                'principal ' . $principal,
                'secundária potencial ' . $secundaria,
                'status ' . $primeiro['classificacao'],
            ]) . PHP_EOL;
        }
        foreach ($resultado['etapas_sem_demanda_inferida'] as $exclusao) {
            echo 'NÃO INFERIDA: ' . $exclusao['codigo_etapa'] . ' | ' . $exclusao['obra'] . ' | ' . $exclusao['motivo'] . PHP_EOL;
        }
        exit(0);
    }
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $erro) {
    fwrite(STDERR, 'ERRO: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}
