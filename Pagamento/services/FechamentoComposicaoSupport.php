<?php

require_once __DIR__ . '/FechamentoFinanceiroRules.php';

/** Bordas da composição. A aritmética continua sendo a de centavos da 1A. */
final class FechamentoComposicaoSupport
{
    public static function moeda($valor): int
    {
        if (is_int($valor)) $valor = (string)$valor; // entrada em reais, não centavos
        if (is_float($valor)) {
            if (!is_finite($valor)) throw new InvalidArgumentException('Moeda não finita.');
            $valor = json_encode($valor, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        }
        if (!is_string($valor)) throw new InvalidArgumentException('Moeda ausente ou inválida.');
        $s = trim(str_replace("\u{00A0}", ' ', $valor));
        $br = str_starts_with($s, 'R$');
        if ($br) $s = trim(substr($s, 2));
        $negativo = str_starts_with($s, '-');
        if ($negativo) $s = substr($s, 1);
        if (str_contains($s, ',') || $br) {
            if (!preg_match('/^(\d+|[1-9]\d{0,2}(?:\.\d{3})+)(?:,(\d{1,2}))?$/D', $s, $m)) {
                throw new InvalidArgumentException('Formato brasileiro de moeda inválido.');
            }
            $inteiro = str_replace('.', '', $m[1]); $decimal = $m[2] ?? '';
        } else {
            if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $s, $m)) {
                throw new InvalidArgumentException('Moeda deve ter no máximo duas casas decimais.');
            }
            $inteiro = $m[1]; $decimal = $m[2] ?? '';
        }
        $digits = ltrim($inteiro . str_pad($decimal, 2, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        $limite = (string)PHP_INT_MAX;
        if (strlen($digits) > strlen($limite) || (strlen($digits) === strlen($limite) && strcmp($digits, $limite) > 0)) {
            throw new OverflowException('Moeda fora do intervalo de centavos.');
        }
        $centavos = (int)$digits;
        return $negativo ? -$centavos : $centavos;
    }

    public static function valor(array $item): int
    {
        if (array_key_exists('valor_centavos', $item)) {
            if (!is_int($item['valor_centavos'])) throw new InvalidArgumentException('Centavos devem ser inteiros.');
            if (array_key_exists('valor', $item) && self::moeda($item['valor']) !== $item['valor_centavos']) {
                throw new InvalidArgumentException('Valor e centavos conflitantes.');
            }
            return $item['valor_centavos'];
        }
        return self::moeda($item['valor'] ?? null);
    }

    public static function instante(string $valor): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $valor)) {
            throw new InvalidArgumentException('Instante deve indicar data, hora e fuso.');
        }
        if (preg_match('/[+-](\d{2}):(\d{2})$/D', $valor, $offset)
            && ((int)$offset[1] > 14 || (int)$offset[2] > 59 || ((int)$offset[1] === 14 && (int)$offset[2] !== 0))) {
            throw new InvalidArgumentException('Fuso do instante inválido.');
        }
        $d = new DateTimeImmutable($valor);
        if ($d->format('Y-m-d\TH:i:s') !== substr($valor, 0, 19)) throw new InvalidArgumentException('Instante inválido.');
        return $d;
    }

    public static function registro(array $r, int $b, string $ref, DateTimeImmutable $snapshot, string $classe): void
    {
        if (filter_var($r['colaborador_id'] ?? null, FILTER_VALIDATE_INT) !== $b
            || ($r['competencia'] ?? null) !== $ref || ($r['classe'] ?? null) !== $classe) {
            throw new InvalidArgumentException('Registro não corresponde ao beneficiário, competência e direito.');
        }
        if (!filter_var($r['autor_id'] ?? null, FILTER_VALIDATE_INT) || (int)$r['autor_id'] <= 0
            || !is_string($r['referencia'] ?? null) || trim($r['referencia']) === '') {
            throw new InvalidArgumentException('Registro exige autor e referência auditável.');
        }
        $instante = self::instante($r['registrado_em'] ?? '');
        if ($instante > $snapshot) throw new InvalidArgumentException('Registro posterior ao snapshot.');
    }

    public static function pendencia(string $codigo, string $componente, int $b, string $ref, array $valores, array $evidencias, string $mensagem): array
    {
        return ['codigo' => $codigo, 'severidade' => 'BLOQUEANTE', 'bloqueante' => true,
            'componente' => $componente, 'identidade' => ['beneficiario_id' => $b, 'competencia' => $ref, 'classe' => $componente],
            'valores' => $valores, 'evidencias' => $evidencias, 'mensagem' => $mensagem];
    }
}
