<?php
require_once __DIR__ . '/../helpers/custos_helper.php';
require_once __DIR__ . '/../Custos/comercial_helper.php';
$checks = 0;
function eq($actual, $expected, $name)
{
    global $checks;
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($name . ': ' . json_encode($actual) . ' != ' . json_encode($expected));
}
function fixture(): array
{
    return ['imagens' => [['id' => 1, 'nome' => 'Fachada', 'tipo' => 'Fachada'], ['id' => 2, 'nome' => 'Sem comercial'], ['id' => 3, 'nome' => 'Sem função']], 'comercial' => [['id' => 1, 'imagem_id' => 1, 'valor' => 1000, 'valor_imposto' => 100, 'valor_comissao_comercial' => 50], ['id' => 2, 'imagem_id' => 3, 'valor' => 500], ['id' => 3, 'imagem_id' => null, 'valor' => 200]], 'tarefas' => [['origem' => 'funcao_imagem', 'origem_id' => 10, 'imagem_id' => 1, 'funcao_id' => 4, 'nome_funcao' => 'Finalização', 'valor' => 300], ['origem' => 'funcao_imagem', 'origem_id' => 11, 'imagem_id' => 1, 'funcao_id' => 6, 'nome_funcao' => 'Alteração', 'valor' => 0], ['origem' => 'acompanhamento', 'origem_id' => 1, 'imagem_id' => null, 'nome_funcao' => 'Acompanhamento', 'valor' => 80], ['origem' => 'funcao_animacao', 'origem_id' => 1, 'imagem_id' => 1, 'nome_funcao' => 'Animação', 'valor' => 120]], 'itens' => []];
}
function item($id, $origem, $origemId, $valor, $obs = null): array
{
    return ['idpagamento_item' => $id, 'pagamento_id' => 1, 'origem' => $origem, 'origem_id' => $origemId, 'valor' => $valor, 'observacao' => $obs, 'criado_em' => '2026-09-10 12:00:00'];
}
$d = fixture();
$r = custos_calcular($d);
eq($r['resumo']['vendido'], 170000, 'Receita bruta com foto');
eq($r['resumo']['liquido'], 155000, 'Deduções');
eq($r['resumo']['previsto'], 50000, 'Snapshots mais gerais');
eq($r['resumo']['realizado'], 0, 'Sem pagamento');
eq($r['resumo']['a_pagar'], 50000, 'Não pago');
eq($r['imagens'][1]['saude']['codigo'], 'atencao', 'Sem comercial');
eq($r['imagens'][2]['totais']['projetado'], 0, 'Sem função');
$d['itens'][] = item(1, 'funcao_imagem', 10, 150, 'Finalização Parcial');
$r = custos_calcular($d);
eq($r['imagens'][0]['totais']['realizado'], 15000, 'Parcial realizado');
eq($r['imagens'][0]['producao'][0]['a_pagar'], 15000, 'Parcial restante');
$d['itens'][] = item(2, 'funcao_imagem', 10, 150, 'Pago Completa');
$r = custos_calcular($d);
eq($r['imagens'][0]['producao'][0]['a_pagar'], 0, 'Complemento');
eq($r['imagens'][0]['producao'][0]['alertas'], [], 'Parcelas legítimas');
$d['itens'][] = item(3, 'funcao_imagem', 10, 100, 'Comissão Gestor');
$r = custos_calcular($d);
eq($r['imagens'][0]['totais']['realizado'], 40000, 'Comissão extra');
eq($r['imagens'][0]['totais']['previsto'], 52000, 'Comissão não abate tarefa');
$d['itens'][] = item(4, 'acompanhamento', 1, 80);
$d['itens'][] = item(5, 'funcao_animacao', 1, 120);
$r = custos_calcular($d);
eq($r['resumo']['a_pagar'], 0, 'Todas pagas');
eq($r['resumo']['realizado'], 60000, 'Total sem duplicar joins');
eq($r['custos_gerais']['totais']['realizado'], 8000, 'Custo sem imagem');
eq($r['resumo']['margem'], 95000, 'Margem');
$d['itens'][] = item(6, 'funcao_imagem', 11, 20);
$d['itens'][] = item(7, 'funcao_imagem', 11, 30);
$r = custos_calcular($d);
eq($r['resumo']['realizado'], 65000, 'Alteração múltiplos valores');
eq($r['resumo']['a_pagar'], 0, 'Excesso não torna restante negativo');
eq(count($r['imagens'][0]['producao'][1]['alertas']) >= 2, true, 'Duplicidade e excesso sinalizados');
eq(custos_saude(10000, 5000, false)['codigo'], 'neutro', 'Sem threshold inventado');
eq(custos_saude(10000, 5000, false, 40)['codigo'], 'saudavel', 'Meta configurada');
eq(custos_saude(0, -100, false)['codigo'], 'critico', 'Margem negativa');
eq(custos_tipo(['observacao' => 'Finalização parcial']), 'FINALIZACAO_PARCIAL', 'Legacy case adapter');
foreach (['-1', 'NaN', 'INF', '1.001', '1,50'] as $bad) {
    try {
        custos_decimal($bad);
        throw new RuntimeException('Aceitou ' . $bad);
    } catch (InvalidArgumentException $e) {
        $checks++;
    }
}
mt_srand(42);
for ($n = 0; $n < 100; $n++) {
    $d = fixture();
    $d['tarefas'][0]['valor'] = mt_rand(0, 100000) / 100;
    $d['itens'] = [item(1, 'funcao_imagem', 10, mt_rand(0, 150000) / 100)];
    $r = custos_calcular($d);
    eq($r['resumo']['realizado'] + $r['resumo']['a_pagar'], $r['resumo']['projetado'], 'Invariante projetado');
    eq($r['resumo']['liquido'] - $r['resumo']['projetado'], $r['resumo']['margem'], 'Invariante margem');
    eq($r['resumo']['a_pagar'] >= 0, true, 'Restante >=0');
    json_encode($r, JSON_THROW_ON_ERROR);
}
if (isset($argv[1])) {
    $d = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $r = custos_calcular($d);
    echo 'Reconciliação ' . $d['obra']['nomenclatura'] . ': ' . json_encode($r['resumo'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo 'Alertas: ' . count(array_merge(...array_column($r['imagens'], 'alertas'))) . PHP_EOL;
}
echo "$checks verificações OK\n";
