<?php
// php tests/tela-gerencial/AnimacoesTest.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../TelaGerencial/helpers/animacoes.php';

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
foreach (['IA', 'Logo IA', 'IA detalhe', 'logo ia', ' Detalhe IA '] as $tipo) {
    $check(tela_gerencial_animacao_categoria(10, $tipo) === 'Animação IA', 'Classificação IA: ' . $tipo);
}
foreach (['horizontal', 'vertical', 'tracking 1', 'Detalhe', '', null] as $tipo) {
    $check(tela_gerencial_animacao_categoria(10, $tipo) === 'Animação 3D', 'Classificação 3D');
}
$check(tela_gerencial_animacao_categoria(5, 'Logo IA') === 'Pós-produção de animação', 'Pós independente do tipo');

$base = [
    'id' => 1, 'animacao_id' => 1, 'funcao_id' => 10,
    'colaborador_id' => 12, 'nome_colaborador' => 'Pessoa A',
    'status' => 'Finalizado', 'prazo' => '2026-10-10', 'valor' => 100,
    'tipo_animacao' => 'Logo IA', 'imagem_id' => 1, 'imagem_nome' => 'Imagem',
    'pago_em' => '9999-12-31',
];
$tarefa = static fn(array $campos): array => array_replace($base, $campos);
$tarefas = [
    $base,
    $tarefa(['id' => 2, 'animacao_id' => 2, 'tipo_animacao' => 'IA detalhe', 'valor' => 200, 'pago_em' => '2026-11-01']),
    $tarefa(['id' => 3, 'animacao_id' => 3, 'tipo_animacao' => 'IA', 'valor' => 400, 'pago_em' => '2026-10-31']),
    $tarefa(['id' => 4, 'funcao_id' => 5, 'valor' => 60]),
    $tarefa(['id' => 5, 'tipo_animacao' => 'horizontal', 'valor' => 300]),
    $tarefa(['id' => 6, 'prazo' => '2026-09-30', 'pago_em' => '2026-10-01']),
    $tarefa(['id' => 7, 'prazo' => '2026-09-30', 'pago_em' => '2026-09-30']),
    $tarefa(['id' => 8, 'prazo' => '2026-08-01']),
    $tarefa(['id' => 9, 'prazo' => '2026-08-31']),
    $tarefa(['id' => 10, 'prazo' => '2026-08-31', 'colaborador_id' => 13, 'nome_colaborador' => 'Pessoa B']),
    $tarefa(['id' => 11, 'prazo' => '2026-10-01', 'status' => ' Em aprovação ']),
    $tarefa(['id' => 12, 'status' => 'HOLD', 'valor' => 9999]),
    $tarefa(['id' => 13, 'colaborador_id' => 21, 'valor' => 9999]),
    $tarefa(['id' => 14, 'prazo' => null]),
    $tarefa(['id' => 15, 'funcao_id' => 4]),
    $tarefa(['id' => 16, 'prazo' => '2026-11-01']),
    $tarefa(['id' => 17, 'prazo' => '2026-08-15', 'colaborador_id' => 13, 'nome_colaborador' => 'Pessoa B']),
];
$relatorio = tela_gerencial_animacoes_agrupar($tarefas, '2026-10-01', '2026-10-31', true, new DateTimeImmutable('2026-11-05'));
$funcoes = array_column($relatorio['funcoes'], null, 'nome_funcao');
$ia = $funcoes['Animação IA'];
$check($ia['quantidade'] === 4 && $ia['pagas'] === 1 && $ia['nao_pagas'] === 3, 'Contagem e pagamento na data de corte');
$check($ia['custo_total'] === 400.0 && $ia['custo_medio'] === 133.33, 'Custo salvo somente de pendentes');
$check($ia['mes_anterior'] === 1, 'Mês anterior considera pagamento posterior como pendente');
$check($ia['recorde_producao'] === 4 && $ia['recorde_data'] === '2026-08', 'Recorde agrega colaboradores por mês');
$check($funcoes['Animação 3D']['quantidade'] === 1 && $funcoes['Animação 3D']['custo_total'] === 300.0, '3D separado');
$check($funcoes['Pós-produção de animação']['quantidade'] === 1 && $funcoes['Pós-produção de animação']['custo_total'] === 60.0, 'Pós separada');
$check(count($relatorio['colaboradores']) === 3, 'Somente produção elegível do período');
$linhaIA = array_values(array_filter($relatorio['colaboradores'], static fn($r) => $r['nome_funcao'] === 'Animação IA'))[0];
$check($linhaIA['recorde_producao'] === 3 && $linhaIA['mes_anterior'] === 1, 'Histórico individual');
$check(count($linhaIA['imagens']) === 4, 'Animações da mesma imagem não são deduplicadas');
$check(count(array_unique(array_column($linhaIA['imagens'], 'tarefa_id'))) === 4, 'Identidade de cada tarefa nos detalhes');
$check($linhaIA['imagens'][1]['imagem_nome'] === 'Imagem - IA detalhe', 'Tipo visível nos detalhes');
foreach (['quantidade', 'pagas', 'nao_pagas', 'custo'] as $campo) {
    $campoFuncao = $campo === 'custo' ? 'custo_total' : $campo;
    $check(array_sum(array_column($relatorio['colaboradores'], $campo)) === array_sum(array_column($relatorio['funcoes'], $campoFuncao)), 'Consistência entre tabelas: ' . $campo);
}
$vazio = tela_gerencial_animacoes_agrupar([], '2026-11-01', '2026-11-30');
$check(count($vazio['funcoes']) === 3 && !$vazio['colaboradores'], 'Categorias sempre visíveis, mesmo sem produção');
$dia = tela_gerencial_animacoes_agrupar($tarefas, '2026-10-10', '2026-10-10', false);
$check(array_sum(array_column($dia['funcoes'], 'quantidade')) === 5, 'Filtro diário');
$check(array_sum(array_column($dia['funcoes'], 'recorde_producao')) === 0, 'Filtro sem histórico');
echo "$checks verificações passaram.\n";
