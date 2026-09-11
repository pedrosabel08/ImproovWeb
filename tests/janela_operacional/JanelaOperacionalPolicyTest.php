<?php

require_once dirname(__DIR__, 2) . '/helpers/janela_operacional_helper.php';

function janela_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

janela_assert(flow_janela_classificar('2026-09-15', '2026-09-15', '2026-09-23') === FLOW_JANELA_ESTADO_NORMAL, 'Previsao no limite deve ser NORMAL.');
janela_assert(flow_janela_classificar('2026-09-16', '2026-09-15', '2026-09-23') === FLOW_JANELA_ESTADO_EXCECAO, 'Previsao depois da janela e antes do planejamento deve ser excecao.');
janela_assert(flow_janela_classificar('2026-09-24', '2026-09-15', '2026-09-23') === FLOW_JANELA_ESTADO_CONFLITO, 'Conflito de planejamento deve ter precedencia.');
janela_assert(flow_janela_classificar('2026-09-24', null, '2026-09-23') === FLOW_JANELA_ESTADO_CONFLITO, 'Classificador deve reconhecer conflito sem limite operacional.');

janela_assert(flow_planejamento_adicionar_dias_uteis('2026-09-11', 2) === '2026-09-15', 'Inicio na sexta deve vencer na terca.');
janela_assert(flow_planejamento_adicionar_dias_uteis('2026-09-12', 2) === '2026-09-15', 'Inicio no sabado deve contar os dois proximos dias uteis.');
janela_assert(flow_planejamento_adicionar_dias_uteis('2026-09-13', 2) === '2026-09-15', 'Inicio no domingo deve contar os dois proximos dias uteis.');
janela_assert(flow_planejamento_adicionar_dias_uteis('2026-09-04', 2) === '2026-09-09', 'Feriado fixo deve ser ignorado pelo calendario central.');
janela_assert(flow_janela_dias_hold_integrais('2026-09-11', '2026-09-17') === 3, 'HOLD deve contar somente os tres dias uteis integralmente indisponiveis.');
janela_assert(flow_janela_dias_hold_integrais('2026-09-11', '2026-09-11') === 0, 'HOLD e retomada no mesmo dia nao devem ampliar a janela.');

$base = ['funcao_id' => 2, 'tipo_imagem' => 'Imagem Interna'];
janela_assert(flow_janela_codigo_perfil($base) === 'MODELAGEM_COMUM', 'Modelagem interna deve ser comum.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 2, 'tipo_imagem' => 'Fachada']) === 'MODELAGEM_FACHADA', 'Fachada deve possuir perfil proprio.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 3, 'tipo_imagem' => 'Imagem Externa']) === 'COMPOSICAO', 'Composicao deve ser identificada.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 4, 'tipo_imagem' => 'Imagem Interna']) === 'FINALIZACAO_INTERNA', 'Finalizacao interna deve ser identificada.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 4, 'tipo_imagem' => 'Imagem Externa']) === 'FINALIZACAO_EXTERNA', 'Finalizacao externa deve ser identificada.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 4, 'tipo_imagem' => 'Planta Humanizada']) === 'FINALIZACAO_PLANTA', 'Finalizacao de planta deve ser identificada.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 5, 'tipo_imagem' => 'Imagem Interna']) === 'POS_PRODUCAO', 'Pos-producao deve ser identificada.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 6, 'tipo_imagem' => 'Imagem Interna']) === 'ALTERACAO', 'Alteracao deve ser explicita.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 999, 'tipo_imagem' => 'Imagem Interna']) === null, 'Funcao desconhecida deve ficar sem regra.');
janela_assert(flow_janela_codigo_perfil($base, FLOW_UNIDADE_TIPO_MODELAGEM_COMPOSICAO) === 'MODELAGEM_COMPOSICAO', 'Unidade Modelagem + Composicao deve ter um unico perfil.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 1, 'tipo_imagem' => 'Imagem Interna'], 'CADERNO_FILTRO_LEGADO') === 'CADERNO_FILTRO', 'Par legado deve ter uma unica janela.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 1, 'tipo_imagem' => 'Imagem Interna'], null, true) === 'CADERNO', 'Caderno separado deve ter ciclo independente.');
janela_assert(flow_janela_codigo_perfil(['funcao_id' => 8, 'tipo_imagem' => 'Imagem Interna'], null, true) === 'FILTRO_ASSETS', 'Filtro separado deve ter ciclo independente.');

echo "JanelaOperacionalPolicyTest: OK\n";
