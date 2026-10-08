<?php

require_once __DIR__.'/FechamentoFinanceiroRules.php';

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
        $acao = $input['estado'] ?? null;
        $alvo = $input['alvo'] ?? null;
        $motivo = trim($input['motivo'] ?? '');
        if (!in_array($acao, ['RETIRAR','RESTAURAR'], true) || !in_array($alvo, ['ITEM','FUNCAO'], true) || $motivo === '' || strlen($motivo) > 1000) {
            throw new InvalidArgumentException('Retirada/restauração exige alvo e motivo válidos.');
        }
        $out = ['itens' => $antes['itens'] ?? [], 'funcoes' => $antes['funcoes'] ?? []];
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
            $ato = $decisao['itens'][$key] ?? ($decisao['funcoes'][(string)($s['descricao']['funcao_id'] ?? 0)] ?? null);
            // Pagamentos históricos nunca são apagados por uma retirada em lote.
            if (!$ato || !$ato['retirado'] || !$s['elegibilidade']['elegivel'] || !self::semPagamentoMonetario($s) || $s['situacao'] === 'QUITADO') {
                continue;
            }
            $s['retirada'] = $ato;
            $s['situacao_original'] = $s['situacao'];
            $s['elegibilidade_original'] = $s['elegibilidade'];
            $s['elegibilidade']['elegivel'] = false;
            $s['situacao'] = 'RETIRADO';
            $s['motivo_exclusao'] = 'RETIRADA_MANUAL_COMPETENCIA';
            $retirados[$key] = true;
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
