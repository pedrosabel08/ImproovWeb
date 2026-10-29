<?php

/** Tabela mensal de PH. Não altera a origem nem reconhece pagamentos. */
final class FechamentoPlantaRules
{
    public const VERSION = 'planta_humanizada_tarifas_v1';

    public static function catalogo(): array
    {
        return [
            'APTO_INDIVIDUAL' => ['nome' => 'Apartamento individual','valor_centavos' => 13000],
            'APTO_VARIACAO' => ['nome' => 'Apartamento variação / repetição','valor_centavos' => 8000],
            'PAVIMENTO' => ['nome' => 'Pavimento de apartamentos / garagem','valor_centavos' => 15000],
            'GARAGEM_REPETICAO' => ['nome' => 'Pavimento de garagem repetido','valor_centavos' => 8000],
            'LAZER_IMPLANTACAO' => ['nome' => 'Lazer / implantação grande','valor_centavos' => 20000],
        ];
    }

    public static function categoria($codigo): array
    {
        if (!is_string($codigo) || !isset(self::catalogo()[$codigo])) {
            throw new InvalidArgumentException('Selecione um tipo de planta válido.');
        }
        return self::catalogo()[$codigo];
    }

    public static function normalizarInput(array $input): array
    {
        if (($input['estado'] ?? '') === 'APLICAR_TARIFA') {
            $categoria = self::categoria($input['categoria_planta'] ?? null);
            $input['motivo'] = 'Aplicação da tarifa padrão de planta humanizada: '.$categoria['nome'].'.';
        }
        return $input;
    }

    public static function metadados(array $origem, bool $comissao): ?array
    {
        if ($comissao || ($origem['origem'] ?? '') !== 'funcao_imagem' || (int)($origem['funcao_id'] ?? 0) !== 4) {
            return null;
        }
        $tipo = mb_strtolower(trim($origem['tipo_imagem'] ?? ''));
        if ($tipo !== 'planta humanizada' && !in_array((int)($origem['colaborador_id'] ?? 0), [12,24], true)) {
            return null;
        }
        $nome = mb_strtolower($origem['imagem_nome'] ?? '');
        $repetida = str_contains($nome, 'repeti') || str_contains($nome, 'varia');
        $sugerida = null;
        if (str_contains($nome, 'lazer') || str_contains($nome, 'implanta')) {
            $sugerida = 'LAZER_IMPLANTACAO';
        } elseif (str_contains($nome, 'garagem') && $repetida) {
            $sugerida = 'GARAGEM_REPETICAO';
        } elseif (str_contains($nome, 'pavimento') || str_contains($nome, 'garagem')) {
            $sugerida = 'PAVIMENTO';
        } elseif ($repetida) {
            $sugerida = 'APTO_VARIACAO';
        } elseif (preg_match('/apto|apartamento|\btipo\b|studio|duplex/u', $nome)) {
            $sugerida = 'APTO_INDIVIDUAL';
        }
        return ['version' => self::VERSION,'categoria_sugerida' => $sugerida,'categorias' => self::catalogo()];
    }

    /** Avalia o valor efetivo da revisão, após ajustes e retiradas. */
    public static function avaliar(array $item, ?array $decisao): array
    {
        if (empty($item['planta_humanizada'])) {
            return $item;
        }
        $ph = $item['planta_humanizada'];
        $codigo = $decisao['categoria'] ?? $ph['categoria_sugerida'];
        $tarifa = $codigo === null ? null : self::categoria($codigo);
        $excecao = ($decisao['modo'] ?? '') === 'EXCECAO' && trim($decisao['motivo'] ?? '') !== ''
            && isset($item['ajuste_valor']) && $item['base_centavos'] === $item['ajuste_valor']['base_centavos']
            && $decisao['valor_centavos'] === $item['base_centavos'];
        $historico = $item['situacao'] === 'QUITADO';
        $divergente = !$tarifa || ($item['base_centavos'] !== $tarifa['valor_centavos'] && !$excecao);
        $item['planta_humanizada'] = $ph + ['categoria' => $codigo,'categoria_nome' => $tarifa['nome'] ?? null,
            'categoria_confirmada' => $decisao !== null,'esperado_centavos' => $tarifa['valor_centavos'] ?? null,
            'registrado_centavos' => $item['base_original_centavos'] ?? $item['base_centavos'],
            'divergente' => $divergente,'historico' => $historico,'excecao' => $excecao,'decisao' => $decisao];
        if ($divergente && !$historico && $item['elegibilidade']['elegivel']) {
            $codigoPendencia = $tarifa ? 'PLANTA_TARIFA_DIVERGENTE' : 'PLANTA_TIPO_PENDENTE';
            $p = ['codigo' => $codigoPendencia,'severidade' => 'BLOQUEANTE','bloqueante' => true,'identidade' => $item['identidade'],
                'mensagem' => $tarifa ? 'O valor da planta difere da tarifa do tipo selecionado. Aplique a tarifa padrão ou justifique a exceção.' : 'Selecione o tipo da planta para conferir a tarifa.',
                'valores' => ['esperado_centavos' => $tarifa['valor_centavos'] ?? null,'registrado_centavos' => $item['base_centavos']]];
            $item['divergencias'][] = $p;
            $item['situacao'] = 'PENDENCIA';
            $item['motivo_exclusao'] = $codigoPendencia;
            $item['saldo_centavos'] = null;
        }
        return $item;
    }
}
