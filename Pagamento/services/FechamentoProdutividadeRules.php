<?php
require_once __DIR__.'/FechamentoFinanceiroRules.php';

/** Eventos canônicos já enriquecidos com o ciclo vigente no instante da conclusão. */
final class FechamentoProdutividadeRules
{
    public function calcular(array $eventos, string $ref, bool $aplicavel): array
    {
        [$inicio,$fim] = FechamentoFinanceiroRules::periodo($ref);
        $imagens = []; $tarifas = []; $pend = [];
        // O repositório entrega a primeira conclusão R00 de cada imagem, inclusive fora do mês.
        foreach ($eventos as $e) {
            if ($aplicavel && (int)$e['funcao_id']===4 && $e['ciclo']===null
                && mb_strtolower(trim($e['status_novo']))==='finalizado' && $e['data']>=$inicio && $e['data']<$fim) {
                $pend[]='O ciclo de uma imagem finalizada não está identificado no histórico. Confira a origem.';
            }
            if ((int)$e['funcao_id'] !== 4 || (int)$e['ciclo'] !== 2
                || mb_strtolower(trim($e['status_novo'])) !== 'finalizado'
                || $e['data'] < $inicio || $e['data'] >= $fim) continue;
            $id = (int)$e['imagem_id'];
            if (isset($imagens[$id])) continue;
            $imagens[$id] = $e;
            if ($e['valor'] === null || !is_numeric($e['valor']) || (float)$e['valor'] < 0) $tarifas['ausente'] = null;
            else { $v = FechamentoFinanceiroRules::centavos($e['valor']); $tarifas[(string)$v] = $v; }
        }
        $n = count($imagens); $unidades = $n >= 32 ? 2 : ($n >= 21 ? 1 : 0);
        $tarifa = count($tarifas) === 1 ? reset($tarifas) : null;
        if ($aplicavel && $unidades && $tarifa === null) $pend[] = 'Tarifa de Finalização R0 ausente ou diferente entre imagens. Confira os valores na origem.';
        $valor = !$aplicavel || !$unidades ? 0 : ($tarifa === null ? null : FechamentoFinanceiroRules::somar($tarifa,$unidades === 2 ? $tarifa : 0));
        return ['quantidade_finalizacao_r0'=>$n,'meta'=>20,'quantidade_bonus'=>$aplicavel ? $unidades : 0,
            'tarifa_centavos'=>$tarifa,'valor_centavos'=>$valor,'aplicavel'=>$aplicavel,
            'faixa'=>$n >= 32 ? '32 ou mais Finalizações' : ($n >= 21 ? '21–31 Finalizações' : 'Até 20 Finalizações'),
            'imagens'=>array_values($imagens),'pendencias'=>array_values(array_unique($pend))];
    }
}
