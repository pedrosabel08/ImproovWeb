<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/helpers/finalizacao_completa.php';

header('Content-Type: application/json; charset=utf-8');

$inicio = isset($_GET['inicio']) ? (string) $_GET['inicio'] : '2017-01';
$fim = isset($_GET['fim']) ? (string) $_GET['fim'] : date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $inicio) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $fim) || $inicio > $fim) {
    http_response_code(422);
    echo json_encode(['error' => 'Informe inicio e fim no formato YYYY-MM.']);
    exit;
}

$cursor = new DateTimeImmutable($inicio . '-01');
$limite = new DateTimeImmutable($fim . '-01');
$linhas = [];

while ($cursor <= $limite) {
    $mes = (int) $cursor->format('n');
    $ano = (int) $cursor->format('Y');
    $tarefas = tela_gerencial_finalizacao_completa_nao_pagas($conn, $mes, $ano);
    $porColaborador = tela_gerencial_agrupar_finalizacao_completa_por_colaborador($tarefas);
    $somaColaboradores = array_sum(array_map(static fn(array $linha): int => (int) $linha['nao_pagas'], $porColaborador));

    $linhas[] = [
        'mes' => $cursor->format('Y-m'),
        'por_colaborador' => $somaColaboradores,
        'por_funcao' => count($tarefas),
        'diferenca' => $somaColaboradores - count($tarefas),
        'situacao' => $somaColaboradores === count($tarefas) ? 'OK' : 'DIVERGENTE',
    ];
    $cursor = $cursor->modify('+1 month');
}

echo json_encode(['dados' => $linhas], JSON_UNESCAPED_UNICODE);
