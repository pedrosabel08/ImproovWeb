<?php

/**
 * Regras canônicas para qualificação de capacidade produtiva.
 *
 * A presença em funcao_colaborador continua significando aptidão para a
 * função. Estas regras só definem se a pessoa entra nos cálculos de capacidade
 * global e qual é seu papel operacional naquele vínculo.
 */

const FLOW_TIPO_ATUACAO_PRINCIPAL = 'PRINCIPAL';
const FLOW_TIPO_ATUACAO_SECUNDARIA = 'SECUNDARIA';

function flow_capacidade_normalizar_tipo_atuacao($valor): string
{
    $tipo = strtoupper(trim((string) $valor));
    return $tipo === FLOW_TIPO_ATUACAO_PRINCIPAL
        ? FLOW_TIPO_ATUACAO_PRINCIPAL
        : FLOW_TIPO_ATUACAO_SECUNDARIA;
}

/** Ausência explícita do campo deve ser tratada pelo chamador como preservação. */
function flow_capacidade_tipo_atuacao_informado(array $atuacoes, int $funcaoId): bool
{
    return array_key_exists((string) $funcaoId, $atuacoes)
        || array_key_exists($funcaoId, $atuacoes);
}

function flow_capacidade_tipo_atuacao_para_funcao(array $atuacoes, int $funcaoId, string $padrao = FLOW_TIPO_ATUACAO_SECUNDARIA): string
{
    if (!flow_capacidade_tipo_atuacao_informado($atuacoes, $funcaoId)) {
        return flow_capacidade_normalizar_tipo_atuacao($padrao);
    }
    return flow_capacidade_normalizar_tipo_atuacao($atuacoes[$funcaoId] ?? $atuacoes[(string) $funcaoId] ?? $padrao);
}

/**
 * Serviço canônico de elegibilidade. Não usa nomes ou IDs especiais: a regra
 * vem do cadastro explícito do colaborador.
 */
function flow_colaborador_elegivel_capacidade(array $colaborador): bool
{
    return (int) ($colaborador['ativo'] ?? 0) === 1
        && (int) ($colaborador['elegivel_capacidade'] ?? 1) === 1;
}

function flow_colaborador_elegivel_capacidade_sql(string $alias = 'c'): string
{
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'c';
    return "$alias.ativo = 1 AND COALESCE($alias.elegivel_capacidade, 1) = 1";
}
