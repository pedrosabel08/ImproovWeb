<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Lê a golden como texto; nunca executa seu envelope nem modifica expectativas históricas. */
function financeiro_test_golden(): array
{
    $text = file_get_contents(__DIR__ . '/../characterization/pagamento_adendos_golden.php');
    $marker = '__halt_compiler();';
    $offset = strpos($text, $marker);
    if ($offset === false) throw new RuntimeException('Golden inválida.');
    return json_decode(substr($text, $offset + strlen($marker)), true, 512, JSON_THROW_ON_ERROR);
}

/** Apenas fatos deste caso disponíveis na captura, não reconstrução de todo o mês. */
function financeiro_test_caso(array $golden, string $caseId): array
{
    $case = $golden['reference_cases'][$caseId];
    if (!isset($case['raw_origin'])) throw new InvalidArgumentException('Caso sem origem financeira congelada.');
    [$beneficiario, $competencia] = explode('/', $case['pair']);
    $raw = $case['raw_origin'];
    $raw['origem'] = $case['origin'];
    $raw['origem_id'] = $case['origin_id'];
    $logs = $case['logs'];
    foreach ($logs as &$log) $log['funcao_imagem_id'] = $case['origin_id'];
    unset($log);
    return ['beneficiario' => (int)$beneficiario, 'competencia' => $competencia,
        'snapshot' => new DateTimeImmutable($golden['captured_at']),
        'dados' => ['origens' => [$raw], 'logs' => $logs, 'ledger' => $case['all_origin_ledger'], 'animacoes_legadas' => []],
        'legacy_screen' => ['funcoes' => $case['screen_match'] ? [$case['screen_match']['endpoint_item']] : []],
        'legacy_eligible' => $case['eligible_v2_match'] ? [$case['eligible_v2_match']] : [],
        'observacao' => 'Recorte do caso real; não contém todos os candidatos/logs/legados do mês.'];
}
