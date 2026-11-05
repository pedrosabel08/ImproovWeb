<?php
require_once __DIR__ . '/FechamentoFinanceiroRules.php';

/** Eventos canônicos já enriquecidos com o ciclo vigente no instante da conclusão. */
final class FechamentoProdutividadeRules
{
    /** Conta as finalizações próprias elegíveis que compõem o adendo da competência. */
    public function calcularAdendo(array $itens, string $ref, bool $aplicavel): array
    {
        $imagens = [];
        $tarifas = [];
        foreach ($itens as $item) {
            $identidade = $item['identidade'] ?? [];
            $descricao = $item['descricao'] ?? [];
            if (($identidade['origem'] ?? '') !== 'funcao_imagem'
                || ($identidade['classe'] ?? '') !== FechamentoFinanceiroRules::TAREFA
                || (int)($descricao['funcao_id'] ?? 0) !== 4
                || empty($item['elegibilidade']['elegivel'])
            ) continue;
            $situacao = $item['situacao'] ?? '';
            $pagoNaCompetencia = false;
            foreach ($item['pagamentos'] ?? [] as $pagamento) {
                if (($pagamento['competencia_pagamento'] ?? null) === $ref && ($pagamento['valor_centavos'] ?? 0) > 0) $pagoNaCompetencia = true;
            }
            if (!in_array($situacao, ['DEVIDO', 'SEM_VALOR_DEVIDO'], true) && !($situacao === 'QUITADO' && $pagoNaCompetencia)) continue;
            $id = (int)($descricao['imagem_id'] ?? 0);
            if ($id <= 0 || isset($imagens[$id])) continue;
            $imagens[$id] = ['imagem_id' => $id, 'origem_id' => $identidade['origem_id'] ?? null];
            $valor = $item['valor_original'] ?? null;
            if ($valor === null || !is_numeric($valor) || (float)$valor < 0) $tarifas['ausente'] = null;
            else {
                $v = FechamentoFinanceiroRules::centavos($valor);
                $tarifas[(string)$v] = $v;
            }
        }
        $n = count($imagens);
        $unidades = $n >= 32 ? 2 : ($n >= 21 ? 1 : 0);
        $tarifa = count($tarifas) === 1 ? reset($tarifas) : null;
        $pend = $aplicavel && $unidades && $tarifa === null ? ['Tarifa de Finalização ausente ou diferente entre imagens. Confira os valores na origem.'] : [];
        return [
            'quantidade_finalizacao_r0' => $n,
            'quantidade_finalizacoes_adendo' => $n,
            'meta' => 20,
            'quantidade_bonus' => $aplicavel ? $unidades : 0,
            'tarifa_centavos' => $tarifa,
            'valor_centavos' => !$aplicavel || !$unidades ? 0 : ($tarifa === null ? null : FechamentoFinanceiroRules::somar($tarifa, $unidades === 2 ? $tarifa : 0)),
            'aplicavel' => $aplicavel,
            'faixa' => $n >= 32 ? '32 ou mais Finalizações' : ($n >= 21 ? '21–31 Finalizações' : 'Até 20 Finalizações'),
            'criterio' => 'FINALIZACOES_ADENDO',
            'imagens' => array_values($imagens),
            'pendencias' => $pend
        ];
    }
    public function calcular(array $eventos, string $ref, bool $aplicavel): array
    {
        [$inicio, $fim] = FechamentoFinanceiroRules::periodo($ref);
        $imagens = [];
        $tarifas = [];
        $pend = [];
        // O repositório entrega a primeira conclusão R00 de cada imagem, inclusive fora do mês.
        foreach ($eventos as $e) {
            if (
                $aplicavel && (int)$e['funcao_id'] === 4 && $e['ciclo'] === null
                && mb_strtolower(trim($e['status_novo'])) === 'finalizado' && $e['data'] >= $inicio && $e['data'] < $fim
            ) {
                $pend[] = 'O ciclo de uma imagem finalizada não está identificado no histórico. Confira a origem.';
            }
            if (
                (int)$e['funcao_id'] !== 4 || (int)$e['ciclo'] !== 2
                || mb_strtolower(trim($e['status_novo'])) !== 'finalizado'
                || $e['data'] < $inicio || $e['data'] >= $fim
            ) continue;
            $id = (int)$e['imagem_id'];
            if (isset($imagens[$id])) continue;
            $imagens[$id] = $e;
            if ($e['valor'] === null || !is_numeric($e['valor']) || (float)$e['valor'] < 0) $tarifas['ausente'] = null;
            else {
                $v = FechamentoFinanceiroRules::centavos($e['valor']);
                $tarifas[(string)$v] = $v;
            }
        }
        $n = count($imagens);
        $unidades = $n >= 32 ? 2 : ($n >= 21 ? 1 : 0);
        $tarifa = count($tarifas) === 1 ? reset($tarifas) : null;
        if ($aplicavel && $unidades && $tarifa === null) $pend[] = 'Tarifa de Finalização R0 ausente ou diferente entre imagens. Confira os valores na origem.';
        $valor = !$aplicavel || !$unidades ? 0 : ($tarifa === null ? null : FechamentoFinanceiroRules::somar($tarifa, $unidades === 2 ? $tarifa : 0));
        return [
            'quantidade_finalizacao_r0' => $n,
            'meta' => 20,
            'quantidade_bonus' => $aplicavel ? $unidades : 0,
            'tarifa_centavos' => $tarifa,
            'valor_centavos' => $valor,
            'aplicavel' => $aplicavel,
            'faixa' => $n >= 32 ? '32 ou mais Finalizações' : ($n >= 21 ? '21–31 Finalizações' : 'Até 20 Finalizações'),
            'imagens' => array_values($imagens),
            'pendencias' => array_values(array_unique($pend))
        ];
    }
}
