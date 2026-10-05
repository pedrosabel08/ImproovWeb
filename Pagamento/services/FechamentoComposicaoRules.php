<?php

require_once __DIR__ . '/FechamentoFixoRules.php';
require_once __DIR__ . '/FechamentoExtrasRules.php';
require_once __DIR__ . '/FechamentoRubricasEspeciais.php';

/** FASE 1B: consome o resultado 1A integralmente, sem recalcular seus serviços. */
final class FechamentoComposicaoRules
{
    public const VERSION = 'pagamento_adendo_composicao_v1';

    public function compor(array $servicos, array $contexto): array
    {
        $b = $servicos['colaborador_id'] ?? 0; $ref = $servicos['competencia'] ?? '';
        if (!is_int($b) || $b <= 0 || ($servicos['rule_version'] ?? null) !== FechamentoFinanceiroRules::VERSION) {
            throw new InvalidArgumentException('Composição exige resultado canônico 1A identificado.');
        }
        FechamentoFinanceiroRules::periodo($ref);
        $snapshot = FechamentoComposicaoSupport::instante($servicos['snapshot_em'] ?? '');
        if (($contexto['colaborador_id'] ?? null) !== $b || ($contexto['competencia'] ?? null) !== $ref) {
            throw new InvalidArgumentException('Contexto de composição pertence a outro colaborador/competência.');
        }
        if (isset($contexto['snapshot_em']) && $contexto['snapshot_em'] !== $servicos['snapshot_em']) {
            throw new InvalidArgumentException('Complementos e serviços devem pertencer ao mesmo snapshot.');
        }
        if (!is_int($servicos['subtotal_servicos_centavos'] ?? null) || $servicos['subtotal_servicos_centavos'] < 0
            || !is_bool($servicos['subtotal_servicos_completo'] ?? null) || !is_bool($servicos['bloqueado'] ?? null)
            || !is_array($servicos['pendencias'] ?? null) || ($servicos['timezone'] ?? null) !== 'America/Sao_Paulo') {
            throw new InvalidArgumentException('Resultado 1A incompleto ou inválido.');
        }
        $fixo = (new FechamentoFixoRules())->calcular($contexto['fixo'] ?? [], $b, $ref, $snapshot);
        $extras = (new FechamentoExtrasRules())->calcular($contexto['extras'] ?? [], $b, $ref, $snapshot);
        $especial = (new FechamentoRubricasEspeciais())->obter($b, $ref);
        $pend = array_merge($servicos['pendencias'], $fixo['pendencias'], $extras['pendencias']);
        if (($servicos['bloqueado'] || !$servicos['subtotal_servicos_completo']) && !$servicos['pendencias']) {
            $pend[] = FechamentoComposicaoSupport::pendencia('SERVICOS_1A_PENDENTES', 'SERVICOS', $b, $ref,
                ['subtotal_conhecido_centavos' => $servicos['subtotal_servicos_centavos']],
                ['bloqueado' => $servicos['bloqueado'], 'subtotal_completo' => $servicos['subtotal_servicos_completo']],
                'Resultado 1A bloqueado/incompleto; conservar esse estado na composição.');
        }
        $componentes = ['SERVICOS' => $servicos['subtotal_servicos_centavos'], 'VALOR_FIXO' => $fixo['saldo_centavos'],
            'ACOMPANHAMENTO_ESPECIAL' => $especial['valor_centavos'], 'BONUS_EXTRAS' => $extras['subtotal_centavos']];
        $conhecido = FechamentoFinanceiroRules::somar($servicos['subtotal_servicos_centavos'], $fixo['saldo_centavos'] ?? 0);
        $conhecido = FechamentoFinanceiroRules::somar($conhecido, $especial['valor_centavos']);
        $conhecido = FechamentoFinanceiroRules::somar($conhecido, $extras['subtotal_conhecido_centavos']);
        $indeterminados = [];
        if (!$servicos['subtotal_servicos_completo']) $indeterminados[] = 'SERVICOS';
        if ($fixo['saldo_centavos'] === null) $indeterminados[] = 'VALOR_FIXO';
        if (!$extras['determinado']) $indeterminados[] = 'BONUS_EXTRAS';
        $bloqueado = $servicos['bloqueado'] || !$servicos['subtotal_servicos_completo'] || count($pend) > 0;
        $determinado = !$bloqueado && !$indeterminados;
        $situacao = $determinado ? 'PRONTO' : (($servicos['bloqueado'] || !$servicos['subtotal_servicos_completo'])
            ? 'DIVERGENCIA_FINANCEIRA' : ($fixo['pendencias'] ? 'PENDENTE_FIXO' : ($extras['estado'] === 'PENDENTE' ? 'PENDENTE_BONUS' : 'PENDENTE_EXTRAS')));
        return ['colaborador_id' => $b, 'competencia' => $ref, 'snapshot_em' => $servicos['snapshot_em'],
            'timezone' => $servicos['timezone'], 'rule_version' => self::VERSION, 'servicos_rule_version' => $servicos['rule_version'],
            'financeiro_servicos' => $servicos, 'fixo' => $fixo, 'acompanhamento_especial' => $especial, 'extras' => $extras,
            'componentes' => $componentes, 'componentes_indeterminados' => $indeterminados,
            'componentes_conhecidos_centavos' => $conhecido, 'total_final_centavos' => $determinado ? $conhecido : null,
            'total_final_determinado' => $determinado, 'pendencias' => $pend, 'bloqueado' => $bloqueado, 'situacao' => $situacao];
    }
}
