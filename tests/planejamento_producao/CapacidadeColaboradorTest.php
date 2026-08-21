<?php

require_once dirname(__DIR__, 2) . '/helpers/capacidade_colaborador_helper.php';

function capacidade_colaborador_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

capacidade_colaborador_assert(
    flow_capacidade_normalizar_tipo_atuacao('principal') === FLOW_TIPO_ATUACAO_PRINCIPAL,
    'Principal precisa ser normalizado.'
);
capacidade_colaborador_assert(
    flow_capacidade_normalizar_tipo_atuacao('invalido') === FLOW_TIPO_ATUACAO_SECUNDARIA,
    'Valores inválidos não podem inflar capacidade-base.'
);
capacidade_colaborador_assert(
    flow_colaborador_elegivel_capacidade(['ativo' => 1, 'elegivel_capacidade' => 1]),
    'Colaborador ativo e elegível deve contar para capacidade.'
);
capacidade_colaborador_assert(
    !flow_colaborador_elegivel_capacidade(['ativo' => 1, 'elegivel_capacidade' => 0]),
    'Registro administrativo não pode contar para capacidade.'
);
capacidade_colaborador_assert(
    !flow_colaborador_elegivel_capacidade(['ativo' => 0, 'elegivel_capacidade' => 1]),
    'Colaborador inativo não pode contar para capacidade.'
);
capacidade_colaborador_assert(
    flow_colaborador_elegivel_capacidade(['ativo' => 1]),
    'Ausência do novo campo deve manter compatibilidade com cadastro antigo.'
);

$atuacoes = ['2' => 'PRINCIPAL'];
capacidade_colaborador_assert(
    flow_capacidade_tipo_atuacao_informado($atuacoes, 2)
        && flow_capacidade_tipo_atuacao_para_funcao($atuacoes, 2) === FLOW_TIPO_ATUACAO_PRINCIPAL,
    'Atuação informada precisa ser mantida por função.'
);
capacidade_colaborador_assert(
    !flow_capacidade_tipo_atuacao_informado($atuacoes, 3),
    'Ausência precisa poder ser distinguida de Secundária explícita para preservar legado.'
);

echo "OK: elegibilidade e papéis de capacidade do colaborador validados.\n";
