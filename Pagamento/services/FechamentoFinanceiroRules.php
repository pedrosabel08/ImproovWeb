<?php

require_once __DIR__ . '/../../helpers/custos_helper.php';
require_once __DIR__ . '/../PagamentoService.php';

/** FASE 1A: domínio puro. Recebe dados do repositório, nunca payload financeiro da UI. */
final class FechamentoFinanceiroRules
{
    public const VERSION = 'pagamento_adendo_financeiro_v1';
    public const TAREFA = 'REMUNERACAO_TAREFA';
    public const COMISSAO = 'COMISSAO_GESTOR';
    public const ANIMACAO = 'FUNCAO_ANIMACAO';
    public const ACOMPANHAMENTO = 'ACOMPANHAMENTO';
    private const STATUS = ['finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado'];

    public static function periodo(string $competencia): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/D', $competencia, $parts)
            || PagamentoService::competencia((int)$parts[2], (int)$parts[1]) !== $competencia) {
            throw new InvalidArgumentException('Competência inválida.');
        }
        $inicio = new DateTimeImmutable($competencia . '-01', new DateTimeZone('America/Sao_Paulo'));
        return [$inicio->format('Y-m-d'), $inicio->modify('first day of next month')->format('Y-m-d')];
    }

    public static function identidade(int $beneficiario, string $origem, int $id, string $classe): array
    {
        if ($beneficiario <= 0 || $id <= 0) throw new InvalidArgumentException('Identidade financeira inválida.');
        return ['beneficiario_id' => $beneficiario, 'origem' => $origem, 'origem_id' => $id, 'classe' => $classe];
    }

    public static function chave(array $id): string
    {
        return implode(':', [$id['beneficiario_id'], $id['origem'], $id['origem_id'], $id['classe']]);
    }

    public static function centavos($valor): int
    {
        // Arredondamento de borda consolidado no V2; todas as operações seguintes são inteiras.
        if (is_numeric($valor) && abs((float)$valor) >= PHP_INT_MAX / 100) {
            throw new InvalidArgumentException('Valor fora do intervalo de centavos.');
        }
        return custos_centavos($valor);
    }

    public static function somar(int $a, int $b): int
    {
        if (($b > 0 && $a > PHP_INT_MAX - $b) || ($b < 0 && $a < PHP_INT_MIN - $b)) {
            throw new OverflowException('Soma financeira fora do intervalo.');
        }
        return $a + $b;
    }

    public static function saldo(int $base, int $pago): array
    {
        if ($pago === PHP_INT_MIN) throw new OverflowException('Saldo fora do intervalo.');
        $bruto = self::somar($base, -$pago);
        if ($bruto === PHP_INT_MIN) throw new OverflowException('Excesso fora do intervalo.');
        return ['saldo_bruto_centavos' => $bruto, 'saldo_centavos' => max($bruto, 0),
            'excesso_centavos' => $bruto < 0 ? -$bruto : 0];
    }

    public static function comissao(array $origem): int
    {
        return ($origem['tipo_imagem'] ?? '') === 'Fachada'
            && mb_stripos((string)($origem['imagem_nome'] ?? ''), 'embasamento') === false ? 10000 : 8000;
    }

    public static function classeLedger(array $linha): ?string
    {
        $tipo = custos_tipo($linha);
        return match ($linha['origem'] ?? '') {
            'funcao_imagem' => $tipo === 'COMISSAO' ? self::COMISSAO
                : (in_array($tipo, ['TAREFA', 'FINALIZACAO_PARCIAL', 'FINALIZACAO_COMPLEMENTO'], true) ? self::TAREFA : null),
            'funcao_animacao' => $tipo === 'ANIMACAO' ? self::ANIMACAO : null,
            'acompanhamento' => $tipo === 'ACOMPANHAMENTO' ? self::ACOMPANHAMENTO : null,
            default => null,
        };
    }

    private static function noMes($data, string $inicio, string $fim): bool
    {
        return is_string($data) && $data >= $inicio && $data < $fim;
    }

    private static function statusElegivel($status): bool
    {
        return in_array(mb_strtolower(trim((string)$status)), self::STATUS, true);
    }

    public static function elegibilidade(array $origem, array $logs, string $competencia): array
    {
        [$inicio, $fim] = self::periodo($competencia);
        $tipo = $origem['origem'];
        $evidencias = [];
        if ($tipo === 'funcao_imagem') {
            if (self::statusElegivel($origem['status'] ?? '') && self::noMes($origem['prazo'] ?? null, $inicio, $fim)) {
                $evidencias[] = ['tipo' => 'STATUS_ATUAL_E_PRAZO', 'status' => $origem['status'], 'prazo' => $origem['prazo']];
            }
            foreach ($logs as $log) {
                if ((int)($log['funcao_imagem_id'] ?? 0) === (int)$origem['origem_id']
                    && self::statusElegivel($log['status_novo'] ?? '') && self::noMes($log['data'] ?? null, $inicio, $fim)) {
                    $evidencias[] = ['tipo' => 'LOG_ELEGIVEL', 'idlog' => (int)$log['idlog'],
                        'data' => $log['data'], 'status' => $log['status_novo']];
                }
            }
            $motivo = $evidencias ? 'R01_PRAZO_OU_MOVIMENTACAO_ELEGIVEL' : 'STATUS_PRAZO_E_LOG_NAO_ELEGIVEIS';
        } elseif ($tipo === 'funcao_animacao') {
            if (self::statusElegivel($origem['status'] ?? '') && self::noMes($origem['prazo'] ?? null, $inicio, $fim)) {
                $evidencias[] = ['tipo' => 'STATUS_E_PRAZO_DA_FUNCAO', 'status' => $origem['status'],
                    'prazo' => $origem['prazo'], 'data_anima_operacional' => $origem['data_anima'] ?? null];
            }
            $motivo = $evidencias ? 'R08_PRAZO_DA_FUNCAO' : 'STATUS_OU_PRAZO_DA_FUNCAO_FORA_DA_COMPETENCIA';
        } elseif ($tipo === 'acompanhamento') {
            if (self::noMes($origem['data'] ?? null, $inicio, $fim)) {
                $evidencias[] = ['tipo' => 'DATA_ACOMPANHAMENTO', 'data' => $origem['data']];
            }
            $motivo = $evidencias ? 'R09_DATA_ACOMPANHAMENTO' : 'DATA_ACOMPANHAMENTO_FORA_DA_COMPETENCIA';
        } else {
            throw new InvalidArgumentException('Origem não canônica na FASE 1A.');
        }
        return ['elegivel' => count($evidencias) > 0, 'motivo' => $motivo, 'evidencias' => $evidencias];
    }

    private static function pendencia(string $codigo, array $id, array $valores, array $evidencia, string $mensagem): array
    {
        return ['codigo' => $codigo, 'severidade' => 'BLOQUEANTE', 'bloqueante' => true,
            'identidade' => $id, 'valores' => $valores, 'evidencias' => $evidencia, 'mensagem' => $mensagem];
    }

    public function calcular(array $dados, int $beneficiario, string $competencia, DateTimeImmutable $snapshot): array
    {
        self::periodo($competencia);
        if ($beneficiario <= 0) throw new InvalidArgumentException('Colaborador inválido.');
        $ledgerPorOrigem = [];
        foreach ($dados['ledger'] ?? [] as $linha) {
            $ledgerPorOrigem[$linha['origem'] . ':' . (int)$linha['origem_id']][] = $linha;
        }
        $logsPorTarefa = [];
        foreach ($dados['logs'] ?? [] as $log) $logsPorTarefa[(int)$log['funcao_imagem_id']][] = $log;
        $legados = [];
        foreach ($dados['animacoes_legadas'] ?? [] as $legado) $legados[(int)$legado['idanimacao']] = $legado;
        $pendencias = [];
        $legadosAmbiguos = [];
        foreach ($legados as $id => $legado) {
            $pagos = array_values(array_filter($ledgerPorOrigem['animacao:' . $id] ?? [],
                fn($p) => (int)($p['beneficiario_id'] ?? $p['colaborador_id'] ?? 0) === $beneficiario));
            if (!$pagos) continue;
            $ref = self::identidade($beneficiario, 'animacao', $id, 'ANIMACAO_LEGADA');
            $pendencias[] = self::pendencia('ANIMACAO_LEGADA_AMBIGUA', $ref, ['base_legada' => $legado['valor'] ?? null],
                ['origem_legada' => $legado, 'pagamentos_legados' => $pagos],
                'Pagamento de animação legada exige reconciliação; não foi convertido em pagamento de função.');
            $legadosAmbiguos[$id] = true;
        }
        $itens = [];
        foreach ($dados['origens'] ?? [] as $origem) {
            $tipo = $origem['origem'];
            $dono = (int)$origem['colaborador_id'];
            $comissao = $tipo === 'funcao_imagem' && $beneficiario === 8
                && in_array($dono, [23, 40], true) && (int)$origem['funcao_id'] === 4;
            if ($dono !== $beneficiario && !$comissao) continue;
            $classe = $comissao ? self::COMISSAO : match ($tipo) {
                'funcao_imagem' => self::TAREFA, 'funcao_animacao' => self::ANIMACAO,
                'acompanhamento' => self::ACOMPANHAMENTO,
                default => throw new InvalidArgumentException('Origem não canônica.'),
            };
            $id = self::identidade($beneficiario, $tipo, (int)$origem['origem_id'], $classe);
            $key = self::chave($id);
            if (isset($itens[$key])) throw new RuntimeException('Direito financeiro duplicado: ' . $key);
            $elegivel = self::elegibilidade($origem, $logsPorTarefa[(int)$origem['origem_id']] ?? [], $competencia);
            $base = $comissao ? self::comissao($origem) : self::centavos($origem['valor'] ?? null);
            $pagamentos = [];
            $ignorados = [];
            $pago = 0;
            $problemas = [];
            foreach ($ledgerPorOrigem[$tipo . ':' . (int)$origem['origem_id']] ?? [] as $linha) {
                $benefPagamento = (int)($linha['beneficiario_id'] ?? $linha['colaborador_id'] ?? 0);
                $classePagamento = self::classeLedger($linha);
                if ($benefPagamento !== $beneficiario || $classePagamento !== $classe) {
                    $ignorados[] = ['pagamento' => $linha, 'motivo' => $benefPagamento !== $beneficiario
                        ? 'OUTRO_BENEFICIARIO' : 'OUTRA_CLASSE_FINANCEIRA', 'classe_classificada' => $classePagamento];
                    if ($benefPagamento === $beneficiario && $classePagamento === null && $elegivel['elegivel']) {
                        $problemas[] = self::pendencia('CLASSE_LEDGER_NAO_RECONHECIDA', $id, [], ['pagamento' => $linha],
                            'Classificação do lançamento precisa de reconciliação.');
                    }
                    continue;
                }
                $valor = self::centavos($linha['valor']);
                $pago = self::somar($pago, $valor);
                $pagamentos[] = ['idpagamento_item' => (int)$linha['idpagamento_item'], 'pagamento_id' => (int)$linha['pagamento_id'],
                    'beneficiario_id' => $benefPagamento, 'origem' => $tipo, 'origem_id' => (int)$linha['origem_id'],
                    'classe' => $classePagamento, 'tipo_classificado' => custos_tipo($linha), 'valor_centavos' => $valor,
                    'competencia_pagamento' => $linha['mes_ref'] ?? null, 'criado_em' => $linha['criado_em'] ?? null,
                    'status_pagamento' => $linha['pagamento_status'] ?? null, 'observacao' => $linha['observacao'] ?? null,
                    'aplicacao' => 'MESMO_BENEFICIARIO_ORIGEM_ID_E_CLASSE'];
            }
            $valores = self::saldo($base, $pago);
            $flag = (int)($origem['pagamento'] ?? 0);
            if ($elegivel['elegivel']) {
                if (!$comissao && $flag === 1 && !$pagamentos) {
                    $problemas[] = self::pendencia('PAGAMENTO_SEM_LEDGER', $id, ['base_centavos' => $base, 'flag_pagamento' => $flag],
                        ['origem' => $origem, 'ledger_compativel' => [], 'ledger_nao_aplicavel' => $ignorados],
                        'Origem marcada paga sem ledger compatível; valor pago e saldo não foram determinados.');
                }
                if ($valores['excesso_centavos'] > 0) {
                    $problemas[] = self::pendencia('PAGO_ACIMA_DO_DEVIDO', $id,
                        ['base_centavos' => $base, 'pago_centavos' => $pago, 'excesso_centavos' => $valores['excesso_centavos']],
                        ['pagamentos' => $pagamentos], 'Pagamentos excedem a base financeira; exige decisão explícita.');
                }
                if ($tipo === 'funcao_animacao' && isset($legadosAmbiguos[(int)($origem['animacao_id'] ?? 0)])) {
                    $problemas[] = self::pendencia('ANIMACAO_LEGADA_AMBIGUA', $id, ['base_centavos' => $base],
                        ['animacao_id' => (int)$origem['animacao_id']], 'Função vinculada a animação com pagamento legado não reconciliado.');
                }
            }
            $indeterminado = count(array_filter($problemas, fn($p) => $p['codigo'] !== 'PAGO_ACIMA_DO_DEVIDO')) > 0;
            $situacao = !$elegivel['elegivel'] ? 'NAO_ELEGIVEL' : ($problemas ? 'PENDENCIA'
                : ($valores['saldo_centavos'] > 0 ? 'DEVIDO' : ($base > 0 ? 'QUITADO' : 'SEM_VALOR_DEVIDO')));
            $exclusao = $situacao === 'DEVIDO' ? null : match ($situacao) {
                'NAO_ELEGIVEL' => $elegivel['motivo'], 'PENDENCIA' => 'PENDENCIA_FINANCEIRA_BLOQUEANTE',
                'QUITADO' => 'R06_SALDO_ZERO', default => 'SEM_VALOR_POSITIVO',
            };
            $item = ['identidade' => $id, 'descricao' => ['imagem_id' => $origem['imagem_id'] ?? null,
                'imagem' => $origem['imagem_nome'] ?? null, 'funcao_id' => $origem['funcao_id'] ?? null,
                'funcao' => $origem['nome_funcao'] ?? ($tipo === 'acompanhamento' ? 'Acompanhamento' : null)],
                'elegibilidade' => $elegivel, 'valor_original' => $origem['valor'] ?? null, 'base_centavos' => $base,
                'pagamentos' => $pagamentos, 'pagamentos_nao_aplicaveis' => $ignorados,
                'pago_ledger_centavos' => $pago, 'pago_centavos' => $indeterminado ? null : $pago,
                'saldo_bruto_centavos' => $indeterminado ? null : $valores['saldo_bruto_centavos'],
                'saldo_centavos' => $indeterminado ? null : $valores['saldo_centavos'],
                'excesso_centavos' => $indeterminado ? null : $valores['excesso_centavos'],
                'flag_legada_pagamento' => $flag, 'flag_refere_a_classe' => !$comissao,
                'regra_base' => $comissao ? 'R07_COMISSAO_100_80' : 'R05_VALOR_PERSISTIDO_DA_ORIGEM',
                'situacao' => $situacao, 'motivo_exclusao' => $exclusao, 'divergencias' => $problemas];
            $itens[$key] = $item;
            foreach ($problemas as $problema) $pendencias[] = $problema;
        }
        ksort($itens, SORT_STRING);
        $devidos = array_values(array_filter($itens, fn($i) => $i['situacao'] === 'DEVIDO'));
        $subtotal = 0;
        foreach ($devidos as $item) $subtotal = self::somar($subtotal, $item['saldo_centavos']);
        $completo = !$legadosAmbiguos && !array_filter($itens, fn($i) => $i['elegibilidade']['elegivel'] && $i['saldo_centavos'] === null);
        return ['colaborador_id' => $beneficiario, 'competencia' => $competencia,
            'snapshot_em' => $snapshot->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d\TH:i:s.uP'),
            'timezone' => 'America/Sao_Paulo', 'rule_version' => self::VERSION,
            'itens_analisados' => array_values($itens), 'servicos_devidos' => $devidos, 'pendencias' => $pendencias,
            'subtotal_servicos_centavos' => $subtotal, 'subtotal_servicos_completo' => (bool)$completo,
            'bloqueado' => count($pendencias) > 0,
            'componentes_fora_do_escopo' => ['VALOR_FIXO', 'BONUS_EXTRAS', 'ACOMPANHAMENTO_ESPECIAL_4000']];
    }
}
