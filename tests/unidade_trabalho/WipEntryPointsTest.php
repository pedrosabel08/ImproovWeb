<?php

$root = dirname(__DIR__, 2);
$entryPoints = [
    'insereFuncao.php',
    'insereFuncao2.php',
    'atualizarFuncoesEmAndamento.php',
    'Alteracao/updateStatusLote.php',
    'Arquitetura/update_funcao_caderno.php',
    'atribuir_flow_radar.php',
    'PaginaPrincipal/atualizaFuncaoAnimacao.php',
    'PaginaPrincipal/atualizaTarefa.php',
];

foreach ($entryPoints as $relative) {
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false || !str_contains($source, 'flow_wip_assert_novo_inicio')) {
        throw new RuntimeException($relative . ' não passa pelo motor central de WIP.');
    }
}

$flowBlock = file_get_contents($root . '/FlowBlock/api.php');
if ($flowBlock === false || !str_contains($flowBlock, "status = 'Em andamento' WHERE idfuncao_imagem = ? AND status = 'HOLD'")) {
    throw new RuntimeException('A retomada explícita de HOLD não foi preservada.');
}

$review = file_get_contents($root . '/FlowReview/revisarTarefa.php');
if ($review === false || str_contains($review, 'flow_wip_assert_novo_inicio')) {
    throw new RuntimeException('Decisões de Review não podem ser bloqueadas pelo limite de puxada.');
}

echo "WipEntryPointsTest: OK\n";

