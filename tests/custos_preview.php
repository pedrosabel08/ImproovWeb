<?php
// Isolated visual fixture. Never connects to any database; never serves real financial data.
if (PHP_SAPI !== 'cli-server' || getenv('FLOW_COSTS_PREVIEW') !== '1') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$assets = ['/Custos/style.css' => 'text/css', '/Custos/script.js' => 'text/javascript', '/Custos/theme.js' => 'text/javascript', '/css/styleSidebar.css' => 'text/css', '/script/sidebar.js' => 'text/javascript'];
if ($path === '/ImproovWeb/assets/css/upload-badge.css') {
    header('Content-Type: text/css');
    readfile($root . '/assets/css/upload-badge.css');
    exit;
}
if ($path === '/ImproovWeb/assets/js/upload-badge.js') {
    header('Content-Type: text/javascript');
    readfile($root . '/assets/js/upload-badge.js');
    exit;
}
if (isset($assets[$path])) {
    header('Content-Type: ' . $assets[$path]);
    readfile($root . $path);
    exit;
}
require_once $root . '/helpers/custos_helper.php';
$d = ['imagens' => [], 'comercial' => [], 'tarefas' => [], 'itens' => []];
$names = ['Fachada diurna', 'Fachada noturna', 'Fotomontagem aérea', 'Salão de festas', 'Academia', 'Vinoteca', 'Piscina', 'Living'];
$functions = [1 => 'Modelagem', 2 => 'Composição', 4 => 'Finalização', 5 => 'Pós-produção', 6 => 'Alteração'];
foreach ($names as $n => $name) {
    $id = $n + 1;
    $d['imagens'][] = ['id' => $id, 'nome' => "$id. CAP_TIV $name", 'tipo' => $n < 3 ? 'Fachada' : 'Interna'];
    $d['comercial'][] = ['id' => $id, 'categoria' => 'imagem', 'imagem_id' => $id, 'valor' => 1800, 'valor_imposto' => 100, 'valor_comissao_comercial' => 80, 'imposto' => 5.56, 'comissao_comercial' => 4.44];
    foreach ($functions as $f => $label) {
        $task = $id * 10 + $f;
        $v = ($f === 4 ? 300 : 140) + ($n === 5 ? 180 : 0);
        $d['tarefas'][] = ['origem' => 'funcao_imagem', 'origem_id' => $task, 'imagem_id' => $id, 'funcao_id' => $f, 'nome_funcao' => $label, 'valor' => $v];
        $d['itens'][] = ['idpagamento_item' => $task, 'pagamento_id' => 1, 'origem' => 'funcao_imagem', 'origem_id' => $task, 'valor' => $f === 4 ? $v / 2 : $v, 'observacao' => $f === 4 ? 'Finalização Parcial' : null, 'criado_em' => '2026-09-05 12:00:00', 'mes_ref' => '2026-09'];
    }
}
$d['tarefas'][] = ['origem' => 'acompanhamento', 'origem_id' => 1, 'imagem_id' => null, 'nome_funcao' => 'Acompanhamento', 'valor' => 180];
$d['comercial'][] = ['categoria' => 'foto', 'id' => 1, 'imagem_id' => null, 'valor' => 1000];
$r = custos_calcular($d);
$r['obra'] = ['idobra' => 1, 'nomenclatura' => 'CAP_TIV', 'status_obra' => 0];
if ($path === '/Custos/getCustosObra.php') {
    header('Content-Type: application/json');
    echo json_encode($r);
    exit;
}
if ($path === '/Custos/getCustosImagem.php') {
    header('Content-Type: application/json');
    foreach ($r['imagens'] as $i) if ($i['id'] === (int)($_GET['imagem_id'] ?? 0)) {
        echo json_encode($i);
        exit;
    }
    http_response_code(404);
    exit;
}
if ($path !== '/Custos/' && $path !== '/Custos/index.php') {
    http_response_code(404);
    exit;
}
$csrf = 'visual-fixture';
$todas = [['idobra' => 1, 'nomenclatura' => 'CAP_TIV', 'status_obra' => 0]];
$obras = $todas;
$obras_inativas = [];
$_SESSION = ['logado' => true, 'nivel_acesso' => 1, 'nome_usuario' => 'Visual QA'];
function asset_url(string $path): string
{
    return $path;
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Custos V2 · Validação visual (dados fictícios)</title>
    <script src="theme.js"></script>
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="style.css">
</head>

<body class="custos-page">
    <?php
    // Render the real sidebar markup without the version helper's external DB query.
    $sidebar = file_get_contents($root . '/sidebar.php');
    $sidebar = str_replace("require_once __DIR__ . '/config/version.php';", '', $sidebar);
    eval('?>' . $sidebar);
    include $root . '/Custos/view.php';
    ?><script src="script.js" defer></script>
    <script src="../script/sidebar.js" defer></script>
</body>

</html>