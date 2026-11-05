<?php
// Somente leitura: php tests/tela-gerencial/ProducaoIntegrationTest.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../conexao.php';
// Autenticação apenas em memória na execução CLI, sem sessão web persistente.
session_id('tg-test-' . bin2hex(random_bytes(12)));
require_once __DIR__ . '/../../config/session_bootstrap.php';
session_destroy();
$_SESSION = ['logado' => true];

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$consultar = static function (string $arquivo, array $params) use ($conn): array {
    $_GET = $params;
    ob_start();
    try {
        include __DIR__ . '/../../TelaGerencial/' . $arquivo;
        $json = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
};
$categorias = ['Animação 3D', 'Animação IA', 'Pós-produção de animação'];
$permitidos = array_column($conn->query('SELECT DISTINCT colaborador_id FROM funcao_colaborador WHERE funcao_id = 6')->fetch_all(MYSQLI_ASSOC), 'colaborador_id');
foreach (['2026-09', '2026-10', '2026-11', '2026-01'] as $periodo) {
    [$ano, $mes] = explode('-', $periodo);
    $colaboradores = $consultar('buscar_producao.php', ['ano' => $ano, 'mes' => $mes]);
    $funcoes = array_column($consultar('buscar_producao_funcao.php', ['ano' => $ano, 'mes' => $mes]), null, 'nome_funcao');
    $inicio = $periodo . '-01';
    $fim = date('Y-m-t', strtotime($inicio));
    $fimExclusivo = date('Y-m-d', strtotime($fim . ' +1 day'));
    // Prova independente por SQL, agregando cada id de função uma única vez.
    $stmt = $conn->prepare("SELECT categoria, COUNT(*) quantidade,
            SUM(pago_em <= ?) pagas, SUM(pago_em > ?) nao_pagas,
            SUM(CASE WHEN pago_em > ? THEN valor ELSE 0 END) custo
        FROM (
            SELECT fa.id, fa.valor,
                CASE WHEN fa.funcao_id = 5 THEN 'Pós-produção de animação'
                    WHEN UPPER(COALESCE(a.tipo_animacao, '')) LIKE '%IA%' THEN 'Animação IA'
                    ELSE 'Animação 3D' END categoria,
                LEAST(COALESCE(NULLIF(CAST(fa.data_pagamento AS CHAR), '0000-00-00'), '9999-12-31'),
                    COALESCE((SELECT MIN(DATE(pi.criado_em)) FROM pagamento_itens pi
                        WHERE pi.origem = 'funcao_animacao' AND pi.origem_id = fa.id), '9999-12-31')) pago_em
            FROM funcao_animacao fa JOIN animacao a ON a.idanimacao = fa.animacao_id
            JOIN colaborador c ON c.idcolaborador = fa.colaborador_id
            WHERE fa.funcao_id IN (5, 10) AND fa.colaborador_id NOT IN (21, 15)
                AND fa.prazo >= ? AND fa.prazo < ?
                AND LOWER(TRIM(fa.status)) IN ('finalizado', 'em aprovação', 'ajuste', 'aprovado com ajustes', 'aprovado')
        ) tarefas GROUP BY categoria");
    $stmt->bind_param('sssss', $fim, $fim, $fim, $inicio, $fimExclusivo);
    $stmt->execute();
    $esperado = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), null, 'categoria');
    $stmt->close();
    foreach ($categorias as $categoria) {
        $check(isset($funcoes[$categoria]), "$periodo: categoria presente");
        $linhas = array_values(array_filter($colaboradores, static fn($r) => $r['nome_funcao'] === $categoria));
        foreach (['quantidade', 'pagas', 'nao_pagas', 'custo'] as $campo) {
            $campoFuncao = $campo === 'custo' ? 'custo_total' : $campo;
            $valor = (float) $funcoes[$categoria][$campoFuncao];
            $check(abs($valor - (float) ($esperado[$categoria][$campo] ?? 0)) < 0.01, "$periodo: SQL $categoria/$campo");
            $check(abs($valor - array_sum(array_column($linhas, $campo))) < 0.01, "$periodo: consistência $categoria/$campo");
        }
        foreach ($linhas as $linha) {
            $check(count($linha['imagens']) === (int) $linha['quantidade'], "$periodo: detalhes completos");
        }
    }
    $alteracoes = array_values(array_filter($colaboradores, static fn($r) => (int) ($r['funcao_id'] ?? 0) === 6));
    foreach ($alteracoes as $linha) {
        $check(in_array($linha['colaborador_id'], $permitidos), "$periodo: Alteração exige função atribuída");
    }
    $check(array_sum(array_column($alteracoes, 'quantidade')) === (int) $funcoes['Alteração']['quantidade'], "$periodo: total de Alteração consistente");
}
foreach ([['data' => '2026-10-30'], ['inicio' => '2026-10-01', 'fim' => '2026-10-31']] as $params) {
    $funcoes = array_column($consultar('buscar_producao_funcao.php', $params), null, 'nome_funcao');
    foreach ($categorias as $categoria) {
        $check(isset($funcoes[$categoria]), 'Animações nos filtros diário e por intervalo');
    }
}
echo "$checks verificações de integração passaram.\n";
