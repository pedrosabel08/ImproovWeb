<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/config/auth_navigation.php';
$_SERVER['REQUEST_URI'] = '/ImproovWeb/FlowReview/';
$sessionPath = dirname(__DIR__) . '/output/auth-navigation-unit-' . bin2hex(random_bytes(6));
if (!mkdir($sessionPath, 0700, true)) {
    throw new RuntimeException('Cannot create isolated test sessions');
}
session_save_path($sessionPath);
session_id('flow-return-test-' . bin2hex(random_bytes(12)));
if (!session_start()) {
    throw new RuntimeException('Cannot start isolated test session');
}
$_SESSION = [];
$checks = 0;
$failures = [];
$check = static function ($ok, $label) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $label;
    }
};
$valid = [
    '/ImproovWeb/FlowReview/', '/ImproovWeb/FlowReview/index.php',
    '/ImproovWeb/FlowReview/?imagem_id=583&modo=revisao',
    '/ImproovWeb/FlowReview/?q=nome%20com%20espacos&url=https%3A%2F%2Fexample.com',
    '/ImproovWeb/Dashboard/obra?obra_id=123&tab=review',
    '/ImproovWeb/FlowBlock/issue.php?id=123', '/ImproovWeb/inicio.php',
];
foreach ($valid as $url) {
    $check(flow_auth_validate_return($url) === $url, 'Valid return rejected: ' . $url);
}
$_SERVER['REQUEST_URI'] = '/ImproovWeb/FlowReview/?imagem_id=583&modo=revisao&_flow_auth_resume=temporary';
$check(flow_auth_request_return_url() === '/ImproovWeb/FlowReview/?imagem_id=583&modo=revisao', 'Reserved resume marker leaked into destination');
$_SERVER['REQUEST_URI'] = '/ImproovWeb/FlowReview/?q=nome%20com%20espacos&_flow_auth_resume=temporary&tab=review';
$check(flow_auth_request_return_url() === '/ImproovWeb/FlowReview/?q=nome%20com%20espacos&tab=review', 'Removing marker rewrote query bytes/order');
$invalid = [
    null, [], '', 'https://example.com', 'http://example.com', '//example.com',
    'javascript:alert(1)', 'data:text/html,test', '/\\example.com',
    '/ImproovWeb.evil/FlowReview/', '/OtherApp/FlowReview/',
    '/ImproovWeb//FlowReview/', '/ImproovWeb/../FlowReview/',
    '/ImproovWeb/%2e%2e/FlowReview/', '/ImproovWeb/%252e%252e/FlowReview/',
    '/ImproovWeb/%2f%2fexample.com', '/ImproovWeb/FlowReview%2findex.php',
    '/ImproovWeb/FlowReview/\\example.com',
    "/ImproovWeb/FlowReview/?x=ok\r\nLocation:evil",
    '/ImproovWeb/FlowReview/?x=%0d%0aLocation%3Aevil',
    '/ImproovWeb/FlowReview/?x=%25250d%25250a',
    '/ImproovWeb/FlowReview/#fragment', '/ImproovWeb/FlowReview/tarefa.php?imagem_id=583',
    '/ImproovWeb/inexistente.php', '/ImproovWeb/index.html', '/ImproovWeb/login.php',
    '/ImproovWeb/login-preview.php', '/ImproovWeb/logout.php', '/ImproovWeb/acesso_negado.php',
    '/ImproovWeb/script/scriptLogin.js', '/ImproovWeb/assets/og/flow-default.png',
    '/ImproovWeb/FlowReview/atualizar.php', '/ImproovWeb/FlowReview/revisarTarefa.php',
    '/ImproovWeb/Custos/getCustosObra.php', '/ImproovWeb/FlowReviewExt/', '/ImproovWeb/Quadro/colaboradores.php',
    '/ImproovWeb/FlowReferencias/view.php?id=1', '/ImproovWeb/review/obra.php',
];
foreach ($invalid as $i => $url) {
    $check(flow_auth_validate_return($url) === null, 'Unsafe return accepted: ' . $i);
}
$first = flow_auth_store_return($valid[2]);
$second = flow_auth_store_return($valid[4]);
$check($first !== $second && preg_match('/^[a-f0-9]{32}$/D', $first), 'Context not opaque/unique');
$check(flow_auth_consume_return(null) === null, 'Voluntary login picked a pending return');
$check(flow_auth_consume_return('https://example.com') === null, 'URL accepted as context');
$check(flow_auth_consume_return($first) === $valid[2], 'First tab lost its query');
$check(flow_auth_consume_return($first) === null, 'Consumed return was reused');
$check(flow_auth_consume_return($second) === $valid[4], 'Second tab overwritten');
$id = flow_auth_store_return($valid[0]);
$_SESSION['_flow_login_returns'][$id]['url'] = '//example.com';
$check(flow_auth_consume_return($id) === null, 'Stored return not revalidated');
$id = flow_auth_store_return($valid[0]);
$_SESSION['_flow_login_returns'][$id]['expires'] = time() - 1;
$check(flow_auth_consume_return($id) === null, 'Expired context accepted');
for ($i = 0; $i < 12; $i++) {
    flow_auth_store_return($valid[0]);
}
$check(count(flow_auth_pending_returns()) === 8, 'Pending contexts unbounded');
$check(empty($_SESSION['logado']) && empty($_SESSION['idusuario']), 'Navigation granted authentication');
$_SESSION = [];
$check(flow_auth_consume_return($first) === null, 'Cleared session reused a return');
$_SERVER['REQUEST_URI'] = '/flow/ImproovWeb/FlowReview/';
$check(flow_auth_validate_return('/flow/ImproovWeb/FlowReview/?imagem_id=583') !== null, 'Production base rejected');
$check(flow_auth_validate_return('/ImproovWeb/FlowReview/') === null, 'Cross-deployment return accepted');
foreach (flow_auth_page_files() as $file) {
    $check(is_file(dirname(__DIR__) . '/' . $file), 'Registered page missing: ' . $file);
    $source = file_get_contents(dirname(__DIR__) . '/' . $file);
    $check(str_contains($source, 'flow_auth_redirect_to_login') || $file === 'notificacoes/index.php', 'Page has no central authentication denial: ' . $file);
}
session_destroy();
rmdir($sessionPath);
echo json_encode(compact('checks', 'failures'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failures ? 1 : 0);
