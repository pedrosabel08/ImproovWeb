<?php

$root = dirname(__DIR__, 2);

function arquitetura_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$migration = file_get_contents($root . '/sql/2026-09-11_janela_operacional_v1.sql');
foreach ([
    "'CADERNO_FILTRO'", "'MODELAGEM_COMUM'", "'MODELAGEM_FACHADA'",
    "'MODELAGEM_COMPOSICAO'", "'COMPOSICAO'", "'FINALIZACAO_INTERNA'",
    "'FINALIZACAO_EXTERNA'", "'FINALIZACAO_PLANTA'", "'POS_PRODUCAO'",
    "'ALTERACAO'",
] as $seed) {
    arquitetura_assert(str_contains($migration, $seed), 'Seed ausente ou divergente: ' . $seed);
}
arquitetura_assert(str_contains($migration, 'uq_janela_ciclo_ativo'), 'Ciclo ativo precisa de protecao UNIQUE.');
arquitetura_assert(str_contains($migration, 'limite_data_original') && str_contains($migration, 'limite_data_atual'), 'HOLD deve preservar limite original e manter limite atual.');
arquitetura_assert(str_contains($migration, 'ciclo_anterior_id'), 'Reabertura/transferencia precisa vincular ciclos.');

$service = file_get_contents($root . '/helpers/inicio_operacional_helper.php');
arquitetura_assert(str_contains($service, 'flow_wip_assert_novo_inicio'), 'Servico atomico deve validar WIP.');
arquitetura_assert(str_contains($service, 'flow_janela_avaliar'), 'Servico atomico deve recalcular a janela no backend.');
arquitetura_assert(str_contains($service, 'flow_janela_criar_ciclo'), 'Servico atomico deve criar snapshot antes do commit externo.');
arquitetura_assert(str_contains($service, "\$reabertura ? 'REABERTURA' : 'PRIMEIRO_INICIO'"), 'Abertura e reabertura devem gerar eventos semanticamente distintos.');

$windowHelper = file_get_contents($root . '/helpers/janela_operacional_helper.php');
arquitetura_assert(str_contains($windowHelper, '$aplicaRegra') && str_contains($windowHelper, '$dias'), 'Snapshot deve persistir aplica_regra separadamente do limite, inclusive para Alteracao.');
arquitetura_assert(str_contains($windowHelper, "\$motivoOperacionalCodigo = 'OUTRO'"), 'Transferencia legada nao deve inventar uma causa operacional estruturada.');
arquitetura_assert(!str_contains($windowHelper, 'fi.prazo'), 'Janela operacional nao pode ler funcao_imagem.prazo como fonte de verdade.');

$endpoint = file_get_contents($root . '/PaginaPrincipal/iniciar_operacao.php');
arquitetura_assert(str_contains($endpoint, 'begin_transaction') && str_contains($endpoint, 'rollback'), 'Endpoint de inicio deve ser transacional.');

$legacy = file_get_contents($root . '/insereFuncao.php');
arquitetura_assert(str_contains($legacy, 'flow_inicio_operacional_iniciar'), 'Writer legado deve convergir para o servico atomico.');

$flowBlock = file_get_contents($root . '/FlowBlock/api.php');
arquitetura_assert(str_contains($flowBlock, 'flow_janela_pausar') && str_contains($flowBlock, 'flow_janela_retomar'), 'Flow Block deve pausar e retomar a janela.');

$frontend = file_get_contents($root . '/PaginaPrincipal/scriptIndex.js');
arquitetura_assert(str_contains($frontend, 'iniciar_operacao.php'), 'Modal deve usar o endpoint atomico no primeiro inicio.');
arquitetura_assert(str_contains($frontend, 'atualizar_previsao_operacional.php'), 'Alteracao de previsao deve ter fluxo separado.');

$migrationCiclos = file_get_contents($root . '/sql/2026-09-21_ciclos_execucao_oficial.sql');
foreach (['numero_ciclo', 'origem_abertura', 'qualidade_dados', 'status_saida', 'AGUARDANDO_INICIO', 'funcao_imagem_angulo_ciencia'] as $campo) {
    arquitetura_assert(str_contains($migrationCiclos, $campo), 'Migration V2 deve conter: ' . $campo);
}
arquitetura_assert(str_contains($service, 'flow_inicio_operacional_enviar_aprovacao'), 'Envio à aprovação deve usar o serviço de ciclo.');
arquitetura_assert(str_contains($windowHelper, 'flow_janela_criar_ciclo_aguardando_inicio'), 'Ajuste precisa criar ciclo aguardando início.');

echo "JanelaOperacionalArchitectureTest: OK\n";
