<?php

function pos_mediana(array $valores): ?float
{
    if (!$valores) {
        return null;
    }
    sort($valores, SORT_NUMERIC);
    $meio = intdiv(count($valores), 2);
    return count($valores) % 2 ? (float) $valores[$meio] : ($valores[$meio - 1] + $valores[$meio]) / 2;
}

function pos_resumir_ciclos(array $ciclos, string $inicioAnterior, string $inicioAtual, string $fim): array
{
    $atual = [];
    $anterior = [];
    $revisoes = [];
    foreach ($ciclos as $ciclo) {
        $envio = $ciclo['envio'];
        $segundos = (float) $ciclo['segundos'];
        if ($envio >= $inicioAtual && $envio <= $fim) {
            $atual[] = $segundos;
            $nome = $ciclo['revisao'] ?? 'Não identificada';
            if (!isset($revisoes[$nome])) {
                $revisoes[$nome] = ['revisao' => $nome, 'amostra' => 0, 'soma' => 0.0];
            }
            $revisoes[$nome]['amostra']++;
            $revisoes[$nome]['soma'] += $segundos;
        } elseif ($envio >= $inicioAnterior && $envio < $inicioAtual) {
            $anterior[] = $segundos;
        }
    }
    $medianaAtual = pos_mediana($atual);
    $medianaAnterior = pos_mediana($anterior);
    $detalhe = array_values($revisoes);
    usort($detalhe, static function ($a, $b) {
        $rank = static function ($nome) {
            if ($nome === 'Não identificada') {
                return 3;
            }
            if (strtoupper($nome) === 'EF') {
                return 2;
            }
            return preg_match('/^R\d+$/i', $nome) ? 0 : 1;
        };
        return ($rank($a['revisao']) <=> $rank($b['revisao'])) ?: strnatcasecmp($a['revisao'], $b['revisao']);
    });
    foreach ($detalhe as &$item) {
        $item['segundos'] = $item['soma'] / $item['amostra'];
        unset($item['soma']);
    }
    unset($item);
    return [
        'atual' => ['inicio' => $inicioAtual, 'fim' => $fim, 'amostra' => count($atual), 'mediana_segundos' => $medianaAtual],
        'anterior' => ['inicio' => $inicioAnterior, 'fim' => $inicioAtual, 'amostra' => count($anterior), 'mediana_segundos' => $medianaAnterior],
        'variacao_percentual' => $medianaAtual !== null && $medianaAnterior !== null && $medianaAnterior != 0.0
            ? (($medianaAtual - $medianaAnterior) / $medianaAnterior) * 100 : null,
        'revisoes' => $detalhe,
    ];
}
