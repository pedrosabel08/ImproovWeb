<?php

require_once __DIR__ . '/FechamentoComposicaoSupport.php';

final class FechamentoFixoRules
{
    public function calcular(array $dados, int $b, string $ref, DateTimeImmutable $snapshot): array
    {
        $pend = []; $configurado = null; $utilizado = null; $estado = 'NAO_DEFINIDO';
        $origem = 'COLABORADOR_VALOR_FIXO'; $override = $dados['override'] ?? null;
        $decisao = $dados['decisao'] ?? null;
        $erro = function(string $codigo, string $mensagem, array $evidencia = []) use (&$pend, $b, $ref, &$configurado): void {
            $pend[] = FechamentoComposicaoSupport::pendencia($codigo, 'VALOR_FIXO', $b, $ref,
                ['configurado_centavos' => $configurado], $evidencia, $mensagem);
        };
        try {
            if (array_key_exists('configurado', $dados) && $dados['configurado'] !== null) {
                $configurado = FechamentoComposicaoSupport::moeda($dados['configurado']);
                if ($configurado < 0) throw new InvalidArgumentException('Fixo configurado negativo.');
                $utilizado = $configurado; $estado = 'DEFINIDO';
            }
        } catch (Throwable $e) { $configurado = $utilizado = null; $erro('FIXO_INVALIDO', $e->getMessage(), ['valor_original' => $dados['configurado']]); }
        if ($override !== null || $decisao !== null) {
            try {
                if ($override !== null && $decisao !== null) throw new InvalidArgumentException('Override e decisão SEM_VALOR_FIXO conflitantes.');
                $r = $override ?? $decisao;
                FechamentoComposicaoSupport::registro($r, $b, $ref, $snapshot, 'VALOR_FIXO');
                if (!is_string($r['motivo'] ?? null) || trim($r['motivo']) === '') throw new InvalidArgumentException('Decisão exige motivo.');
                if (!array_key_exists('original_centavos', $r) || $r['original_centavos'] !== $configurado) {
                    throw new InvalidArgumentException('Valor original da decisão não corresponde à configuração.');
                }
                if ($override !== null) {
                    if (!is_int($r['substituto_centavos'] ?? null) || $r['substituto_centavos'] < 0) throw new InvalidArgumentException('Substituto deve ser centavos não negativos.');
                    $utilizado = $r['substituto_centavos']; $estado = 'DEFINIDO'; $origem = 'OVERRIDE_DE_FECHAMENTO';
                } else {
                    if (($r['estado'] ?? null) !== 'SEM_VALOR_FIXO') throw new InvalidArgumentException('Decisão de fixo inválida.');
                    $utilizado = 0; $estado = 'SEM_VALOR_FIXO'; $origem = 'DECISAO_SEM_VALOR_FIXO';
                }
            } catch (Throwable $e) {
                $utilizado = null; $estado = 'NAO_DEFINIDO';
                $erro('FIXO_OVERRIDE_INVALIDO', $e->getMessage(), ['override' => $override, 'decisao' => $decisao]);
            }
        }
        if ($utilizado === null) $erro('FIXO_NAO_DEFINIDO', 'Valor fixo não definido; exige SEM_VALOR_FIXO ou valor explícito.');
        $evidencias = $dados['evidencias_liquidacao'] ?? [];
        $pago = null; $liquidacao = 'LIQUIDACAO_INDETERMINADA'; $observado = 0; $ids = []; $semPagamento = 0;
        $validas = []; $invalidas = [];
        if (!is_array($evidencias)) $invalidas[] = ['erro' => 'Coleção de evidências inválida.', 'evidencia' => $evidencias];
        else foreach ($evidencias as $r) {
            try {
                if (!is_array($r)) throw new InvalidArgumentException('Evidência inválida.');
                FechamentoComposicaoSupport::registro($r, $b, $ref, $snapshot, 'VALOR_FIXO');
                if (isset($ids[$r['referencia']])) throw new InvalidArgumentException('Evidência duplicada.');
                $valor = FechamentoComposicaoSupport::valor($r);
                if (($r['tipo'] ?? null) === 'PAGAMENTO_FIXO' && $valor > 0) {
                    $observado = FechamentoFinanceiroRules::somar($observado, $valor);
                } elseif (($r['tipo'] ?? null) === 'APURACAO_FIXO_SEM_PAGAMENTO' && $valor === 0
                    && is_string($r['motivo'] ?? null) && trim($r['motivo']) !== '') {
                    $semPagamento++;
                } else throw new InvalidArgumentException('Evidência deve discriminar pagamento positivo ou apuração explícita sem pagamento.');
                $ids[$r['referencia']] = true; $validas[] = $r + ['valor_centavos' => $valor];
            } catch (Throwable $e) { $invalidas[] = ['erro' => $e->getMessage(), 'evidencia' => $r]; }
        }
        if ($semPagamento > 1 || ($semPagamento && $observado > 0)) $invalidas[] = ['erro' => 'Apuração sem pagamento conflita com outras evidências.'];
        if ($invalidas) $erro('FIXO_EVIDENCIA_INVALIDA', 'Evidência de liquidação incompatível ou conflitante.', ['invalidas' => $invalidas]);
        if (!$invalidas && $validas) { $pago = $observado; $liquidacao = 'DETERMINADA_POR_EVIDENCIA'; }
        elseif (!$invalidas && !$evidencias && $utilizado === 0) { $pago = 0; $liquidacao = 'NAO_APLICAVEL_VALOR_ZERO'; }
        elseif ($utilizado !== null || $invalidas) {
            $erro('FIXO_LIQUIDACAO_INDETERMINADA', 'Liquidação do fixo exige evidência discriminada; não inferir pago ou saldo.',
                ['evidencias_liquidacao' => $evidencias, 'legado' => $dados['evidencias_legadas'] ?? []]);
        }
        $saldo = $pago !== null && $utilizado !== null ? FechamentoFinanceiroRules::saldo($utilizado, $pago) : null;
        if ($saldo && $saldo['excesso_centavos'] > 0) {
            $pend[] = FechamentoComposicaoSupport::pendencia('FIXO_PAGO_ACIMA_DO_DEVIDO', 'VALOR_FIXO', $b, $ref,
                ['utilizado_centavos' => $utilizado, 'pago_centavos' => $pago, 'excesso_centavos' => $saldo['excesso_centavos']],
                ['evidencias' => $validas], 'Pagamento discriminado excede o valor fixo utilizado.');
        }
        return ['identidade' => ['beneficiario_id' => $b, 'competencia' => $ref, 'classe' => 'VALOR_FIXO'],
            'estado' => $estado, 'valor_original' => $dados['configurado'] ?? null, 'configurado_centavos' => $configurado,
            'utilizado_centavos' => $utilizado, 'pago_centavos' => $pago, 'saldo_centavos' => $saldo['saldo_centavos'] ?? null,
            'saldo_bruto_centavos' => $saldo['saldo_bruto_centavos'] ?? null, 'excesso_centavos' => $saldo['excesso_centavos'] ?? null,
            'estado_liquidacao' => $liquidacao, 'origem' => $origem, 'override' => $override, 'decisao' => $decisao,
            'pago_evidenciado_centavos' => $observado, 'evidencias_liquidacao' => $validas,
            'evidencias_invalidas' => $invalidas, 'evidencias_legadas' => $dados['evidencias_legadas'] ?? [], 'pendencias' => $pend];
    }
}
