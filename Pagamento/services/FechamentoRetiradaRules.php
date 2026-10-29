<?php

require_once __DIR__.'/FechamentoFinanceiroRules.php';
require_once __DIR__.'/FechamentoComposicaoSupport.php';

/** Decisões da competência; não modifica atribuições, produção ou pagamentos. */
final class FechamentoRetiradaRules
{
    public static function chave(array $id): string
    {
        return FechamentoFinanceiroRules::chave($id);
    }

    private static function semPagamentoMonetario(array $item): bool
    {
        $pagamentos = $item['pagamentos'] ?? [];
        if (!$pagamentos && (int)($item['flag_legada_pagamento'] ?? 0) === 1) {
            return false;
        }
        foreach ($pagamentos as $pagamento) {
            if (!array_key_exists('valor_centavos', $pagamento) || !is_int($pagamento['valor_centavos']) || $pagamento['valor_centavos'] !== 0) {
                return false;
            }
        }
        return true;
    }

    public static function decidir(array $antes, array $input, array $servicos, int $usuario, string $iso): array
    {
        $input = FechamentoPlantaRules::normalizarInput($input);
        $acao = $input['estado'] ?? null;
        $alvo = $input['alvo'] ?? null;
        $motivo = trim($input['motivo'] ?? '');
        if (!in_array($acao, ['RETIRAR','RESTAURAR','ALTERAR_VALOR','RESTAURAR_VALOR','APLICAR_TARIFA'], true) || !in_array($alvo, ['ITEM','FUNCAO'], true) || $motivo === '' || strlen($motivo) > 1000) {
            throw new InvalidArgumentException('Retirada/restauração exige alvo e motivo válidos.');
        }
        $out = ['itens' => $antes['itens'] ?? [], 'funcoes' => $antes['funcoes'] ?? [], 'valores' => $antes['valores'] ?? [], 'plantas' => $antes['plantas'] ?? []];
        $registro = ['retirado' => $acao === 'RETIRAR','motivo' => $motivo,'autor_id' => $usuario,'registrado_em' => $iso];
        $matches = [];
        foreach ($servicos['itens_analisados'] as $s) {
            if (!$s['elegibilidade']['elegivel']) {
                continue;
            }
            if ($alvo === 'ITEM' && isset($input['identidade']) && self::chave($s['identidade']) === self::chave($input['identidade'])) {
                $matches[] = $s;
            }
            if ($alvo === 'FUNCAO' && (int)($s['descricao']['funcao_id'] ?? 0) === ($input['funcao_id'] ?? null)) {
                $matches[] = $s;
            }
        }
        if (!$matches) {
            throw new DomainException('Tarefa/função não pertence ao fechamento deste colaborador e competência.');
        }
        if (in_array($acao, ['ALTERAR_VALOR','RESTAURAR_VALOR','APLICAR_TARIFA'], true)) {
            if ($alvo !== 'ITEM' || count($matches) !== 1) {
                throw new InvalidArgumentException('A alteração de valor exige uma tarefa.');
            }
            $s = $matches[0];
            $key = self::chave($s['identidade']);
            if ($acao === 'RESTAURAR_VALOR') {
                unset($out['valores'][$key]);
                unset($out['plantas'][$key]);
                return $out;
            }
            $atual = self::aplicar($servicos, $antes);
            $item = array_values(array_filter($atual['itens_analisados'], fn ($i) => self::chave($i['identidade']) === $key))[0];
            if ($s['saldo_centavos'] === null || $s['divergencias'] || $s['situacao'] === 'QUITADO' || $item['situacao'] === 'RETIRADO') {
                throw new DomainException('Somente tarefas pendentes, sem divergências e presentes no fechamento podem ter o valor alterado.');
            }
            $padrao = $acao === 'APLICAR_TARIFA';
            if ($padrao && empty($s['planta_humanizada'])) {
                throw new InvalidArgumentException('Tarifa de planta não se aplica a esta tarefa.');
            }
            $categoria = !empty($s['planta_humanizada']) ? FechamentoPlantaRules::categoria($input['categoria_planta'] ?? null) : null;
            $valor = $padrao ? $categoria['valor_centavos'] : FechamentoComposicaoSupport::valor($input);
            if ($valor < 0 || $valor < $s['pago_ledger_centavos']) {
                throw new InvalidArgumentException('O valor total não pode ser negativo nem inferior ao já pago.');
            }
            $out['valores'][$key] = ['identidade' => $s['identidade'],'base_original_centavos' => $s['base_centavos'],
                'base_centavos' => $valor,'pago_observado_centavos' => $s['pago_ledger_centavos'],'motivo' => $motivo,'autor_id' => $usuario,'registrado_em' => $iso];
            if ($categoria) {
                $out['plantas'][$key] = ['categoria' => $input['categoria_planta'],'modo' => $padrao ? 'PADRAO' : 'EXCECAO',
                    'valor_centavos' => $valor,'motivo' => $motivo,'autor_id' => $usuario,'registrado_em' => $iso,'tabela_version' => FechamentoPlantaRules::VERSION];
            }
            return $out;
        }
        if ($alvo === 'ITEM') {
            $s = $matches[0];
            if ($acao === 'RETIRAR' && (!self::semPagamentoMonetario($s) || $s['situacao'] === 'QUITADO')) {
                throw new DomainException('Uma tarefa com pagamento registrado não pode ser retirada.');
            }
            $out['itens'][self::chave($s['identidade'])] = $registro + ['identidade' => $s['identidade'],'funcao_id' => isset($s['descricao']['funcao_id']) ? (int)$s['descricao']['funcao_id'] : null];
        } else {
            $id = $input['funcao_id'];
            if (!is_int($id) || $id <= 0) {
                throw new InvalidArgumentException('Função inválida.');
            }
            if ($acao === 'RETIRAR' && !array_filter($matches, fn ($s) => self::semPagamentoMonetario($s) && $s['situacao'] !== 'QUITADO')) {
                throw new DomainException('Não existem tarefas sem pagamento para retirar nesta função.');
            }
            $out['funcoes'][(string)$id] = $registro + ['funcao_id' => $id];
            // Uma decisão em lote substitui exceções individuais anteriores dessa função.
            foreach ($out['itens'] as $key => $item) {
                if ((int)($item['funcao_id'] ?? 0) === $id) {
                    unset($out['itens'][$key]);
                }
            }
        }
        return $out;
    }

    public static function aplicar(array $servicos, array $decisao): array
    {
        $retirados = [];
        foreach ($servicos['itens_analisados'] as &$s) {
            $key = self::chave($s['identidade']);
            $ajuste = $decisao['valores'][$key] ?? null;
            if ($ajuste && $s['elegibilidade']['elegivel']) {
                $s['ajuste_valor'] = $ajuste;
                $s['base_original_centavos'] = $s['base_centavos'];
                // Uma nova origem ou pagamento exige rever o ajuste, sem reaplicar um valor obsoleto.
                if ($ajuste['base_original_centavos'] !== $s['base_centavos'] || $ajuste['pago_observado_centavos'] !== $s['pago_ledger_centavos']
                    || $s['saldo_centavos'] === null || $s['divergencias'] || $s['situacao'] === 'QUITADO') {
                    $p = ['codigo' => 'AJUSTE_TAREFA_DESATUALIZADO','mensagem' => 'O valor ou pagamento da tarefa mudou. Restaure o valor e confira novamente.',
                        'severidade' => 'BLOQUEANTE','bloqueante' => true,'identidade' => $s['identidade']];
                    $servicos['pendencias'][] = $p;
                    $s['divergencias'][] = $p;
                    $s['saldo_centavos'] = null;
                    $s['situacao'] = 'PENDENCIA';
                } else {
                    $s['base_centavos'] = $ajuste['base_centavos'];
                    $saldo = FechamentoFinanceiroRules::saldo($s['base_centavos'], $s['pago_ledger_centavos']);
                    $s = array_replace($s, $saldo);
                    $s['situacao'] = $s['saldo_centavos'] > 0 ? 'DEVIDO' : ($s['pago_ledger_centavos'] > 0 ? 'QUITADO' : 'SEM_VALOR_DEVIDO');
                    $s['motivo_exclusao'] = $s['situacao'] === 'DEVIDO' ? null : ($s['situacao'] === 'QUITADO' ? 'R06_SALDO_ZERO' : 'SEM_VALOR_POSITIVO');
                }
            }
            $ato = $decisao['itens'][$key] ?? ($decisao['funcoes'][(string)($s['descricao']['funcao_id'] ?? 0)] ?? null);
            // Pagamentos históricos nunca são apagados por uma retirada em lote.
            if ($ato && $ato['retirado'] && $s['elegibilidade']['elegivel'] && self::semPagamentoMonetario($s) && $s['situacao'] !== 'QUITADO') {
                $s['retirada'] = $ato;
                $s['situacao_original'] = $s['situacao'];
                $s['elegibilidade_original'] = $s['elegibilidade'];
                $s['elegibilidade']['elegivel'] = false;
                $s['situacao'] = 'RETIRADO';
                $s['motivo_exclusao'] = 'RETIRADA_MANUAL_COMPETENCIA';
                $retirados[$key] = true;
            }
            $s = FechamentoPlantaRules::avaliar($s, $decisao['plantas'][$key] ?? null);
            foreach ($s['divergencias'] as $p) {
                if (in_array($p['codigo'], ['PLANTA_TARIFA_DIVERGENTE','PLANTA_TIPO_PENDENTE'], true)) {
                    $servicos['pendencias'][] = $p;
                }
            }
        }
        unset($s);
        $servicos['servicos_devidos'] = array_values(array_filter($servicos['itens_analisados'], fn ($s) => $s['situacao'] === 'DEVIDO'));
        $servicos['subtotal_servicos_centavos'] = 0;
        foreach ($servicos['servicos_devidos'] as $s) {
            $servicos['subtotal_servicos_centavos'] = FechamentoFinanceiroRules::somar($servicos['subtotal_servicos_centavos'], $s['saldo_centavos']);
        }
        $servicos['pendencias'] = array_values(array_filter($servicos['pendencias'], fn ($p) => empty($p['identidade']) || !isset($retirados[self::chave($p['identidade'])])));
        $servicos['bloqueado'] = (bool)$servicos['pendencias'];
        $servicos['subtotal_servicos_completo'] = !array_filter($servicos['itens_analisados'], fn ($s) => $s['elegibilidade']['elegivel'] && $s['saldo_centavos'] === null) && !array_filter($servicos['pendencias'], fn ($p) => $p['codigo'] === 'ANIMACAO_LEGADA_AMBIGUA');
        return $servicos;
    }
}
