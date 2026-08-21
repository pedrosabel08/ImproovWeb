<?php

/**
 * Runner somente leitura para validar o motor em uma obra ou em uma R00.
 * Exemplos:
 * php scripts/planejamento_producao_r00_runner.php --obra=116 --inicio=2026-08-20 --entrega=2026-10-15
 * php scripts/planejamento_producao_r00_runner.php --r00=123
 */

require_once dirname(__DIR__) . '/conexao.php';
require_once dirname(__DIR__) . '/helpers/planejamento_producao_helper.php';
require_once dirname(__DIR__) . '/helpers/planejamento_execucao_helper.php';

$argumentos = getopt('', ['obra::', 'r00::', 'inicio::', 'entrega::', 'hoje::', 'pretty::', 'resumo::', 'tabela::', 'execucao::']);
try {
    if (!empty($argumentos['r00'])) {
        $opcoes = [
            'data_inicio' => $argumentos['inicio'] ?? null,
            'data_entrega' => $argumentos['entrega'] ?? null,
            'data_hoje' => $argumentos['hoje'] ?? date('Y-m-d'),
        ];
        $plano = array_key_exists('execucao', $argumentos)
            ? flow_planejamento_carregar_para_interface($conn, (int) $argumentos['r00'], $opcoes)
            : flow_planejamento_planejar_entrega($conn, (int) $argumentos['r00'], $opcoes);
        if (array_key_exists('execucao', $argumentos)) {
            $plano['execucao'] = flow_planejamento_monitorar_execucao($conn, (int) $argumentos['r00'], $plano, $opcoes);
        }
    } elseif (!empty($argumentos['obra'])) {
        $plano = flow_planejamento_planejar_obra($conn, (int) $argumentos['obra'], [
            'data_inicio' => $argumentos['inicio'] ?? null,
            'data_entrega' => $argumentos['entrega'] ?? null,
        ]);
    } else {
        throw new InvalidArgumentException('Informe --obra=<id> ou --r00=<id>.');
    }
    if (array_key_exists('tabela', $argumentos)) {
        echo "Obra: " . ($plano['obra']['nomenclatura'] ?? $plano['obra_id']) . PHP_EOL;
        echo "Início: {$plano['data_inicio']} | Entrega: " . ($plano['data_entrega'] ?? 'não informada') . PHP_EOL;
        echo "Etapa | Volume | Amostra | Confiança | Mediana | Taxa/dia | Duração | Início | Limite | Dependências" . PHP_EOL;
        foreach ($plano['etapas'] as $etapa) {
            if (!empty($etapa['nao_aplicavel'])) {
                continue;
            }
            $metrica = $etapa['metrica'] ?? [];
            echo implode(' | ', [
                $etapa['codigo'],
                $etapa['volume'] ?? 0,
                $metrica['amostra_ciclos_validos'] ?? '-',
                $metrica['confianca'] ?? '-',
                $metrica['duracao_mediana_dias_uteis'] ?? '-',
                $metrica['tarefas_por_dia_util_pessoa'] ?? '-',
                $etapa['duracao_dias_uteis'] ?? '-',
                $etapa['inicio'] ?? '-',
                $etapa['limite'] ?? '-',
                implode(',', $etapa['dependencias'] ?? []),
            ]) . PHP_EOL;
        }
        echo "Fim previsto: " . ($plano['fim_previsto'] ?? 'sem previsão') . " | Margem: " . ($plano['margem_dias_uteis'] ?? 'n/a') . " | Status: {$plano['status_plano']}" . PHP_EOL;
        foreach ($plano['excecoes'] as $excecao) {
            echo 'EXCEÇÃO: ' . json_encode($excecao, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        exit(0);
    }
    if (array_key_exists('resumo', $argumentos)) {
        foreach ($plano['etapas'] as &$etapa) {
            $itens = $etapa['itens'] ?? [];
            $etapa['evidencias_classificacao'] = array_values(array_map(static fn (array $item): array => [
                'tarefa_id' => $item['tarefa_id'] ?? null,
                'imagem_id' => $item['imagem_id'] ?? null,
                'imagem_nome' => $item['imagem_nome'] ?? null,
                'tipo_imagem' => $item['tipo_imagem'] ?? null,
                'origem' => $item['origem'] ?? null,
                'regra_classificacao' => $item['regra_classificacao'] ?? null,
            ], $itens));
            unset($etapa['itens']);
        }
        unset($etapa);
    }
    echo json_encode($plano, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $erro) {
    fwrite(STDERR, 'ERRO: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}
