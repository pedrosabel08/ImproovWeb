<?php

/** Único ponto da rubrica contratual R09. Sem alteração de cadastro/storage. */
final class FechamentoRubricasEspeciais
{
    public const VERSION = 'rubricas_especiais_r09_v1';
    private const RUBRICAS = [1 => ['tipo' => 'ACOMPANHAMENTO_ESPECIAL', 'valor_centavos' => 400000,
        'regra' => 'R09_NICOLLE_ID1_4000', 'origem' => 'CONTRATO_PAGAMENTO_ADENDOS_R09']];

    public function obter(int $beneficiario, string $competencia): array
    {
        $r = self::RUBRICAS[$beneficiario] ?? null;
        return ['aplicavel' => $r !== null, 'beneficiario_id' => $beneficiario, 'competencia' => $competencia,
            'tipo' => 'ACOMPANHAMENTO_ESPECIAL', 'valor_centavos' => $r['valor_centavos'] ?? 0,
            'regra' => $r['regra'] ?? 'R09_NAO_APLICAVEL', 'origem' => $r['origem'] ?? 'CONTRATO_PAGAMENTO_ADENDOS_R09',
            'config_version' => self::VERSION];
    }
}
