<?php

/** Canonização v1: objetos por chave, coleções sem ordem financeira por JSON canônico. */
final class FechamentoSnapshot
{
    public const VERSION = 'financial_snapshot_canonical_v1';

    public static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function ordenar($value)
    {
        // Somente legado/entrada informativa podem conter números JSON decimais.
        // JSON serializa a representação, sem aritmética; valores financeiros canônicos são int.
        if (is_float($value) && !is_finite($value)) throw new InvalidArgumentException('Número não finito no snapshot.');
        if (is_float($value) && $value==0.0) return 0; // JSON/DB podem normalizar -0 para 0.
        if (!is_array($value)) return $value;
        $list = array_is_list($value);
        foreach ($value as &$item) $item = self::ordenar($item);
        unset($item);
        if ($list) usort($value, fn($a, $b) => strcmp(json_encode($a, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($b, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        else ksort($value, SORT_STRING);
        return $value;
    }

    public static function hash(array $snapshot): string
    {
        return hash('sha256', self::VERSION . "\n" . self::json(self::ordenar($snapshot)));
    }
}
