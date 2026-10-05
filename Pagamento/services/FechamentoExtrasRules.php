<?php

require_once __DIR__ . '/FechamentoComposicaoSupport.php';

final class FechamentoExtrasRules
{
    public function calcular(array $dados, int $b, string $ref, DateTimeImmutable $snapshot): array
    {
        $estado = $dados['estado'] ?? 'PENDENTE'; $pend = []; $itens = []; $invalidos = []; $subtotal = 0;
        $erro = function(string $codigo, string $mensagem, array $evidencias = []) use (&$pend, $b, $ref, &$subtotal, $estado): void {
            $pend[] = FechamentoComposicaoSupport::pendencia($codigo, 'BONUS_EXTRAS', $b, $ref,
                ['estado' => $estado, 'subtotal_rubricas_validas_centavos' => $subtotal], $evidencias, $mensagem);
        };
        $input = $dados['itens'] ?? [];
        if (!in_array($estado, ['PENDENTE', 'SEM_BONUS', 'DEFINIDO'], true)) $erro('EXTRA_INVALIDO', 'Estado de bônus inválido.', ['estado' => $estado]);
        if ($estado === 'PENDENTE') $erro('BONUS_PENDENTE', 'Bônus ainda não decidido; não equivale a zero ou SEM_BONUS.');
        else {
            try {
                FechamentoComposicaoSupport::registro($dados['decisao'] ?? [], $b, $ref, $snapshot, 'BONUS_EXTRAS');
                if (($dados['decisao']['estado'] ?? null) !== $estado) throw new InvalidArgumentException('Estado e decisão de bônus conflitantes.');
                if (!is_string($dados['decisao']['motivo'] ?? null) || trim($dados['decisao']['motivo']) === '') throw new InvalidArgumentException('Decisão de bônus exige motivo.');
            } catch (Throwable $e) { $erro('BONUS_DECISAO_INVALIDA', $e->getMessage(), ['decisao' => $dados['decisao'] ?? null]); }
        }
        if (!is_array($input) || ($estado !== 'DEFINIDO' && $input) || ($estado === 'DEFINIDO' && !$input)) {
            $erro('EXTRA_INVALIDO', 'Rubricas devem corresponder ao estado: DEFINIDO não vazio; SEM_BONUS/PENDENTE sem rubricas.', ['itens' => $input]);
        } elseif ($estado === 'DEFINIDO') {
            $ids = [];
            foreach ($input as $r) if (is_array($r) && is_string($r['referencia'] ?? null)) {
                $ids[$r['referencia']] = ($ids[$r['referencia']] ?? 0) + 1;
            }
            foreach ($input as $extra) {
                try {
                    if (!is_array($extra)) throw new InvalidArgumentException('Rubrica inválida.');
                    FechamentoComposicaoSupport::registro($extra, $b, $ref, $snapshot, 'BONUS_EXTRAS');
                    if (!is_string($extra['categoria'] ?? null) || trim($extra['categoria']) === '') throw new InvalidArgumentException('Extra exige categoria.');
                    if (($ids[$extra['referencia']] ?? 0) > 1) throw new InvalidArgumentException('Rubrica duplicada.');
                    $valor = FechamentoComposicaoSupport::valor($extra);
                    if ($valor <= 0) throw new InvalidArgumentException('Extra deve ser positivo; zero é SEM_BONUS e negativo não é bônus.');
                    $subtotal = FechamentoFinanceiroRules::somar($subtotal, $valor);
                    $itens[] = array_merge($extra, ['categoria' => trim($extra['categoria']), 'valor_centavos' => $valor]);
                } catch (Throwable $e) {
                    $invalidos[] = ['rubrica' => $extra, 'erro' => $e->getMessage()];
                    $erro('EXTRA_INVALIDO', $e->getMessage(), ['rubrica' => $extra]);
                }
            }
        }
        // Sem decisão válida, mesmo rubricas válidas não foram autorizadas como devidas.
        $decisaoValida = !array_filter($pend, fn($p) => in_array($p['codigo'], ['BONUS_PENDENTE', 'BONUS_DECISAO_INVALIDA'], true));
        $conhecido = $estado === 'DEFINIDO' && $decisaoValida ? $subtotal : 0;
        return ['estado' => $estado, 'decisao' => $dados['decisao'] ?? null, 'itens' => $itens, 'itens_invalidos' => $invalidos,
            'rubricas_recebidas' => $input, 'subtotal_conhecido_centavos' => $conhecido,
            'subtotal_centavos' => $pend ? null : $subtotal, 'determinado' => !$pend, 'pendencias' => $pend];
    }
}
